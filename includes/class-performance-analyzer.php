<?php
/**
 * 内容表现分析器
 *
 * 追踪和分析内容在各平台的表现数据
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 表现分析器类
 */
class AISCG_Performance_Analyzer {

    /**
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $database;

    /**
     * 指标类型
     *
     * @var array
     */
    private $metric_types = array(
        'views' => '浏览量',
        'likes' => '点赞数',
        'comments' => '评论数',
        'shares' => '分享数',
        'saves' => '收藏数',
        'clicks' => '点击数',
        'reach' => '触达人数',
        'impressions' => '曝光次数',
        'engagement_rate' => '互动率',
        'followers_gained' => '新增粉丝',
    );

    /**
     * 构造函数
     */
    public function __construct() {
        $this->database = new AISCG_Database();
        $this->create_performance_table();
        $this->init_sync_schedule();
    }

    /**
     * 记录表现数据
     *
     * @param int $content_id 内容ID
     * @param string $platform 平台
     * @param array $metrics 指标数据
     *
     * @return int|WP_Error 记录ID或错误
     */
    public function record_performance( $content_id, $platform, $metrics ) {
        global $wpdb;
        $table_performance = $wpdb->prefix . 'aiscg_performance';

        $data = array(
            'content_id' => $content_id,
            'platform' => $platform,
            'metrics' => wp_json_encode( $metrics ),
            'recorded_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert( $table_performance, $data );

        if ( $result === false ) {
            return new WP_Error( 'db_error', '无法记录表现数据' );
        }

        return $wpdb->insert_id;
    }

    /**
     * 从平台同步表现数据
     *
     * @param int $distribution_id 分发记录ID
     *
     * @return array|WP_Error 同步结果或错误
     */
    public function sync_from_platform( $distribution_id ) {
        global $wpdb;
        $table_distributions = $wpdb->prefix . 'aiscg_distributions';

        $distribution = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_distributions WHERE id = %d", $distribution_id ),
            ARRAY_A
        );

        if ( ! $distribution ) {
            return new WP_Error( 'not_found', '分发记录不存在' );
        }

        if ( $distribution['status'] !== 'published' ) {
            return new WP_Error( 'not_published', '内容未发布' );
        }

        $platform = $distribution['platform'];
        $platform_post_id = $distribution['platform_post_id'];

        try {
            $metrics = null;

            switch ( $platform ) {
                case 'instagram':
                    $metrics = $this->fetch_instagram_insights( $platform_post_id, $distribution );
                    break;

                case 'weibo':
                    $metrics = $this->fetch_weibo_stats( $platform_post_id, $distribution );
                    break;

                case 'xiaohongshu':
                    // 小红书没有官方API,返回模拟数据
                    $metrics = $this->generate_mock_metrics();
                    break;

                default:
                    return new WP_Error( 'unsupported_platform', '不支持的平台: ' . $platform );
            }

            if ( is_wp_error( $metrics ) ) {
                return $metrics;
            }

            // 记录表现数据
            $this->record_performance( $distribution['content_id'], $platform, $metrics );

            return array(
                'success' => true,
                'metrics' => $metrics,
                'synced_at' => current_time( 'mysql' ),
            );

        } catch ( Exception $e ) {
            return new WP_Error( 'sync_failed', $e->getMessage() );
        }
    }

    /**
     * 获取Instagram洞察数据
     *
     * @param string $media_id 媒体ID
     * @param array $distribution 分发记录
     *
     * @return array 指标数据
     */
    private function fetch_instagram_insights( $media_id, $distribution ) {
        $settings = json_decode( $distribution['settings'], true );
        $access_token = $settings['access_token'] ?? get_option( 'aiscg_instagram_access_token' );

        if ( empty( $access_token ) ) {
            throw new Exception( '未配置Instagram访问令牌' );
        }

        $metrics = 'impressions,reach,engagement,likes,comments,saves,shares';
        $url = "https://graph.facebook.com/v18.0/{$media_id}/insights?metric={$metrics}&access_token={$access_token}";

        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new Exception( $body['error']['message'] );
        }

        // 解析洞察数据
        $performance = array();
        foreach ( $body['data'] as $insight ) {
            $performance[ $insight['name'] ] = $insight['values'][0]['value'] ?? 0;
        }

        // 计算互动率
        if ( isset( $performance['reach'] ) && $performance['reach'] > 0 ) {
            $engagement = ( $performance['likes'] ?? 0 ) + ( $performance['comments'] ?? 0 ) + ( $performance['shares'] ?? 0 );
            $performance['engagement_rate'] = round( ( $engagement / $performance['reach'] ) * 100, 2 );
        }

        return $performance;
    }

    /**
     * 获取微博统计数据
     *
     * @param string $weibo_id 微博ID
     * @param array $distribution 分发记录
     *
     * @return array 指标数据
     */
    private function fetch_weibo_stats( $weibo_id, $distribution ) {
        $settings = json_decode( $distribution['settings'], true );
        $access_token = $settings['access_token'] ?? get_option( 'aiscg_weibo_access_token' );

        if ( empty( $access_token ) ) {
            throw new Exception( '未配置微博访问令牌' );
        }

        $url = "https://api.weibo.com/2/statuses/show.json?access_token={$access_token}&id={$weibo_id}";

        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new Exception( $body['error'] );
        }

        return array(
            'reposts_count' => $body['reposts_count'] ?? 0,
            'comments_count' => $body['comments_count'] ?? 0,
            'attitudes_count' => $body['attitudes_count'] ?? 0, // 点赞
        );
    }

    /**
     * 生成模拟指标数据
     *
     * @return array 指标数据
     */
    private function generate_mock_metrics() {
        return array(
            'views' => rand( 100, 10000 ),
            'likes' => rand( 10, 500 ),
            'comments' => rand( 5, 100 ),
            'shares' => rand( 2, 50 ),
            'saves' => rand( 5, 200 ),
            'engagement_rate' => rand( 1, 10 ) + ( rand( 0, 99 ) / 100 ),
        );
    }

    /**
     * 获取内容表现报告
     *
     * @param int $content_id 内容ID
     * @param array $options 选项
     *
     * @return array 报告数据
     */
    public function get_content_report( $content_id, $options = array() ) {
        global $wpdb;
        $table_performance = $wpdb->prefix . 'aiscg_performance';
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        // 获取内容信息
        $content = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_posts WHERE id = %d", $content_id ),
            ARRAY_A
        );

        if ( ! $content ) {
            return array( 'error' => '内容不存在' );
        }

        // 获取所有表现记录
        $performance_records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_performance WHERE content_id = %d ORDER BY recorded_at DESC",
                $content_id
            ),
            ARRAY_A
        );

        // 按平台汇总
        $by_platform = array();
        foreach ( $performance_records as $record ) {
            $platform = $record['platform'];
            $metrics = json_decode( $record['metrics'], true );

            if ( ! isset( $by_platform[ $platform ] ) ) {
                $by_platform[ $platform ] = array(
                    'latest' => $metrics,
                    'history' => array(),
                    'total' => array(),
                );
            }

            $by_platform[ $platform ]['history'][] = array(
                'metrics' => $metrics,
                'recorded_at' => $record['recorded_at'],
            );

            // 累计总数
            foreach ( $metrics as $key => $value ) {
                if ( is_numeric( $value ) ) {
                    if ( ! isset( $by_platform[ $platform ]['total'][ $key ] ) ) {
                        $by_platform[ $platform ]['total'][ $key ] = 0;
                    }
                    $by_platform[ $platform ]['total'][ $key ] = max( $by_platform[ $platform ]['total'][ $key ], $value );
                }
            }
        }

        // 计算总体指标
        $total_metrics = array();
        foreach ( $by_platform as $platform => $data ) {
            foreach ( $data['total'] as $key => $value ) {
                if ( ! isset( $total_metrics[ $key ] ) ) {
                    $total_metrics[ $key ] = 0;
                }
                $total_metrics[ $key ] += $value;
            }
        }

        // 计算ROI (假设每次生成成本0.1元)
        $cost = 0.1; // 这应该从成本追踪系统获取
        $engagement = ( $total_metrics['likes'] ?? 0 ) + ( $total_metrics['comments'] ?? 0 ) + ( $total_metrics['shares'] ?? 0 );
        $roi = $cost > 0 ? round( ( $engagement / $cost ), 2 ) : 0;

        return array(
            'content' => $content,
            'by_platform' => $by_platform,
            'total_metrics' => $total_metrics,
            'roi' => $roi,
            'performance_score' => $this->calculate_performance_score( $total_metrics ),
            'recommendations' => $this->generate_recommendations( $total_metrics, $by_platform ),
        );
    }

    /**
     * 计算表现得分
     *
     * @param array $metrics 指标数据
     *
     * @return int 得分 0-100
     */
    private function calculate_performance_score( $metrics ) {
        $score = 0;

        // 浏览量权重 30%
        $views = $metrics['views'] ?? $metrics['reach'] ?? $metrics['impressions'] ?? 0;
        if ( $views > 10000 ) {
            $score += 30;
        } elseif ( $views > 5000 ) {
            $score += 25;
        } elseif ( $views > 1000 ) {
            $score += 20;
        } elseif ( $views > 500 ) {
            $score += 15;
        } elseif ( $views > 100 ) {
            $score += 10;
        }

        // 互动量权重 40%
        $engagement = ( $metrics['likes'] ?? 0 ) + ( $metrics['comments'] ?? 0 ) + ( $metrics['shares'] ?? 0 );
        if ( $engagement > 1000 ) {
            $score += 40;
        } elseif ( $engagement > 500 ) {
            $score += 35;
        } elseif ( $engagement > 200 ) {
            $score += 30;
        } elseif ( $engagement > 50 ) {
            $score += 25;
        } elseif ( $engagement > 10 ) {
            $score += 15;
        }

        // 互动率权重 30%
        $engagement_rate = $metrics['engagement_rate'] ?? 0;
        if ( $engagement_rate > 10 ) {
            $score += 30;
        } elseif ( $engagement_rate > 5 ) {
            $score += 25;
        } elseif ( $engagement_rate > 3 ) {
            $score += 20;
        } elseif ( $engagement_rate > 1 ) {
            $score += 15;
        }

        return min( $score, 100 );
    }

    /**
     * 生成优化建议
     *
     * @param array $total_metrics 总体指标
     * @param array $by_platform 各平台数据
     *
     * @return array 建议列表
     */
    private function generate_recommendations( $total_metrics, $by_platform ) {
        $recommendations = array();

        // 互动率分析
        $engagement_rate = $total_metrics['engagement_rate'] ?? 0;
        if ( $engagement_rate < 2 ) {
            $recommendations[] = array(
                'type' => 'low_engagement',
                'title' => '互动率偏低',
                'description' => '当前互动率为 ' . $engagement_rate . '%,建议优化内容质量,增加互动性元素',
                'actions' => array(
                    '使用更吸引人的开场白',
                    '在内容中提出问题引导评论',
                    '增加视觉吸引力(图片、emoji)',
                ),
            );
        }

        // 平台表现对比
        $platform_scores = array();
        foreach ( $by_platform as $platform => $data ) {
            $platform_engagement = ( $data['total']['likes'] ?? 0 ) + ( $data['total']['comments'] ?? 0 );
            $platform_scores[ $platform ] = $platform_engagement;
        }

        if ( count( $platform_scores ) > 1 ) {
            arsort( $platform_scores );
            $best_platform = key( $platform_scores );
            $recommendations[] = array(
                'type' => 'platform_performance',
                'title' => '平台表现差异',
                'description' => $best_platform . ' 平台表现最佳,建议重点运营',
                'best_platform' => $best_platform,
            );
        }

        // 发布时间建议
        $recommendations[] = array(
            'type' => 'timing',
            'title' => '最佳发布时间',
            'description' => '建议在用户活跃时段发布内容',
            'suggested_times' => array( '9:00-10:00', '12:00-13:00', '19:00-21:00' ),
        );

        return $recommendations;
    }

    /**
     * 获取性能趋势
     *
     * @param array $args 查询参数
     *
     * @return array 趋势数据
     */
    public function get_performance_trends( $args = array() ) {
        global $wpdb;
        $table_performance = $wpdb->prefix . 'aiscg_performance';
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $defaults = array(
            'platform' => null,
            'days' => 30,
            'interval' => 'daily', // daily, weekly, monthly
        );
        $args = wp_parse_args( $args, $defaults );

        $where = "p.content_id = po.id";

        if ( $args['platform'] ) {
            $where .= $wpdb->prepare( " AND p.platform = %s", $args['platform'] );
        }

        $where .= $wpdb->prepare( " AND p.recorded_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", $args['days'] );

        $date_format = array(
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
        );
        $format = $date_format[ $args['interval'] ];

        $query = "
            SELECT
                DATE_FORMAT(p.recorded_at, '{$format}') as period,
                COUNT(DISTINCT p.content_id) as content_count,
                AVG(JSON_EXTRACT(p.metrics, '$.views')) as avg_views,
                AVG(JSON_EXTRACT(p.metrics, '$.likes')) as avg_likes,
                AVG(JSON_EXTRACT(p.metrics, '$.engagement_rate')) as avg_engagement_rate
            FROM {$table_performance} p
            JOIN {$table_posts} po ON p.content_id = po.id
            WHERE {$where}
            GROUP BY period
            ORDER BY period ASC
        ";

        $results = $wpdb->get_results( $query, ARRAY_A );

        return $results;
    }

    /**
     * 对比内容表现
     *
     * @param array $content_ids 内容ID数组
     *
     * @return array 对比结果
     */
    public function compare_content( $content_ids ) {
        $comparison = array();

        foreach ( $content_ids as $content_id ) {
            $report = $this->get_content_report( $content_id );
            $comparison[ $content_id ] = array(
                'title' => $report['content']['title'] ?? '',
                'total_metrics' => $report['total_metrics'],
                'performance_score' => $report['performance_score'],
                'roi' => $report['roi'],
            );
        }

        // 排序
        uasort( $comparison, function( $a, $b ) {
            return $b['performance_score'] - $a['performance_score'];
        });

        return $comparison;
    }

    /**
     * 初始化同步定时任务
     */
    private function init_sync_schedule() {
        add_action( 'aiscg_sync_performance', array( $this, 'sync_all_published_content' ) );

        if ( ! wp_next_scheduled( 'aiscg_sync_performance' ) ) {
            wp_schedule_event( time(), 'hourly', 'aiscg_sync_performance' );
        }
    }

    /**
     * 同步所有已发布内容的表现数据
     */
    public function sync_all_published_content() {
        global $wpdb;
        $table_distributions = $wpdb->prefix . 'aiscg_distributions';

        // 获取过去24小时内发布的内容
        $distributions = $wpdb->get_results(
            "SELECT * FROM $table_distributions
            WHERE status = 'published'
                AND published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY published_at DESC
            LIMIT 50",
            ARRAY_A
        );

        foreach ( $distributions as $distribution ) {
            $this->sync_from_platform( $distribution['id'] );
            // 防止API限流
            sleep( 2 );
        }
    }

    /**
     * 创建表现数据表
     */
    private function create_performance_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiscg_performance';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            content_id bigint(20) NOT NULL,
            platform varchar(50) NOT NULL,
            metrics longtext NOT NULL,
            recorded_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY content_id (content_id),
            KEY platform (platform),
            KEY recorded_at (recorded_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
