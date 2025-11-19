<?php
/**
 * Trend Analyzer Class
 *
 * 分析内容趋势和热门话题
 *
 * @package AI_Social_Content_Generator
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Trend_Analyzer Class
 */
class AISCG_Trend_Analyzer {

    /**
     * 日志对象
     *
     * @var AISCG_Logger
     */
    private $logger;

    /**
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 趋势数据表名
     *
     * @var string
     */
    private $table_name;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aiscg_trends';
        $this->logger = new AISCG_Logger();
        $this->db = new AISCG_Database();
    }

    /**
     * 分析内容趋势
     *
     * @param array $args 分析参数
     * @return array 趋势数据
     */
    public function analyze_trends( $args = array() ) {
        $defaults = array(
            'platform' => '',
            'period' => '30days', // 7days, 30days, 90days
            'min_frequency' => 3,
        );

        $args = wp_parse_args( $args, $defaults );

        $period_days = $this->get_period_days( $args['period'] );
        $start_date = date( 'Y-m-d', strtotime( "-{$period_days} days" ) );

        // 获取时间范围内的所有内容
        $posts = $this->db->get_posts( array(
            'platform' => $args['platform'],
            'limit' => 10000,
        ) );

        // 过滤日期
        $posts = array_filter( $posts, function( $post ) use ( $start_date ) {
            return $post['created_at'] >= $start_date;
        } );

        $trends = array(
            'hot_topics' => $this->analyze_hot_topics( $posts, $args['min_frequency'] ),
            'trending_hashtags' => $this->analyze_trending_hashtags( $posts, $args['min_frequency'] ),
            'content_patterns' => $this->analyze_content_patterns( $posts ),
            'time_distribution' => $this->analyze_time_distribution( $posts ),
            'engagement_insights' => $this->analyze_engagement_insights( $posts ),
        );

        $this->logger->info( '趋势分析完成', array(
            'platform' => $args['platform'],
            'period' => $args['period'],
            'posts_analyzed' => count( $posts ),
        ) );

        return $trends;
    }

    /**
     * 分析热门话题
     *
     * @param array $posts 内容列表
     * @param int $min_frequency 最小频率
     * @return array 热门话题
     */
    private function analyze_hot_topics( $posts, $min_frequency ) {
        $topics = array();

        foreach ( $posts as $post ) {
            // 提取关键词
            $keywords = $this->extract_keywords( $post['title'] . ' ' . $post['content'] );

            foreach ( $keywords as $keyword ) {
                if ( ! isset( $topics[ $keyword ] ) ) {
                    $topics[ $keyword ] = array(
                        'keyword' => $keyword,
                        'frequency' => 0,
                        'posts' => array(),
                    );
                }

                $topics[ $keyword ]['frequency']++;
                $topics[ $keyword ]['posts'][] = $post['id'];
            }
        }

        // 过滤低频词并排序
        $topics = array_filter( $topics, function( $topic ) use ( $min_frequency ) {
            return $topic['frequency'] >= $min_frequency;
        } );

        usort( $topics, function( $a, $b ) {
            return $b['frequency'] - $a['frequency'];
        } );

        return array_slice( $topics, 0, 20 );
    }

    /**
     * 分析流行标签
     *
     * @param array $posts 内容列表
     * @param int $min_frequency 最小频率
     * @return array 流行标签
     */
    private function analyze_trending_hashtags( $posts, $min_frequency ) {
        $hashtags = array();

        foreach ( $posts as $post ) {
            $post_hashtags = is_string( $post['hashtags'] ) ?
                json_decode( $post['hashtags'], true ) : $post['hashtags'];

            if ( ! is_array( $post_hashtags ) ) {
                continue;
            }

            foreach ( $post_hashtags as $tag ) {
                $tag = trim( $tag, '# ' );

                if ( empty( $tag ) ) {
                    continue;
                }

                if ( ! isset( $hashtags[ $tag ] ) ) {
                    $hashtags[ $tag ] = array(
                        'hashtag' => $tag,
                        'frequency' => 0,
                        'growth_rate' => 0,
                        'posts' => array(),
                    );
                }

                $hashtags[ $tag ]['frequency']++;
                $hashtags[ $tag ]['posts'][] = array(
                    'post_id' => $post['id'],
                    'created_at' => $post['created_at'],
                );
            }
        }

        // 计算增长率
        foreach ( $hashtags as &$hashtag_data ) {
            $hashtag_data['growth_rate'] = $this->calculate_growth_rate( $hashtag_data['posts'] );
        }

        // 过滤并排序
        $hashtags = array_filter( $hashtags, function( $hashtag ) use ( $min_frequency ) {
            return $hashtag['frequency'] >= $min_frequency;
        } );

        usort( $hashtags, function( $a, $b ) {
            // 综合考虑频率和增长率
            $score_a = $a['frequency'] * ( 1 + $a['growth_rate'] / 100 );
            $score_b = $b['frequency'] * ( 1 + $b['growth_rate'] / 100 );
            return $score_b - $score_a;
        } );

        // 移除posts详情，只保留统计
        foreach ( $hashtags as &$hashtag ) {
            $hashtag['total_posts'] = count( $hashtag['posts'] );
            unset( $hashtag['posts'] );
        }

        return array_slice( $hashtags, 0, 30 );
    }

    /**
     * 分析内容模式
     *
     * @param array $posts 内容列表
     * @return array 内容模式
     */
    private function analyze_content_patterns( $posts ) {
        $patterns = array(
            'avg_title_length' => 0,
            'avg_content_length' => 0,
            'avg_hashtag_count' => 0,
            'most_used_emojis' => array(),
            'common_opening_phrases' => array(),
            'popular_formats' => array(),
        );

        $title_lengths = array();
        $content_lengths = array();
        $hashtag_counts = array();
        $emojis = array();
        $opening_phrases = array();

        foreach ( $posts as $post ) {
            // 长度统计
            $title_lengths[] = mb_strlen( $post['title'] ?? '' );
            $content_lengths[] = mb_strlen( $post['content'] ?? '' );

            $post_hashtags = is_string( $post['hashtags'] ) ?
                json_decode( $post['hashtags'], true ) : $post['hashtags'];
            $hashtag_counts[] = is_array( $post_hashtags ) ? count( $post_hashtags ) : 0;

            // Emoji统计
            if ( preg_match_all( '/[\x{1F300}-\x{1F9FF}]/u', $post['content'], $matches ) ) {
                foreach ( $matches[0] as $emoji ) {
                    if ( ! isset( $emojis[ $emoji ] ) ) {
                        $emojis[ $emoji ] = 0;
                    }
                    $emojis[ $emoji ]++;
                }
            }

            // 开场白分析（前20个字）
            $opening = mb_substr( $post['content'], 0, 20 );
            $opening_key = preg_replace( '/[^\p{L}\p{N}\s]/u', '', $opening );

            if ( ! empty( $opening_key ) ) {
                if ( ! isset( $opening_phrases[ $opening_key ] ) ) {
                    $opening_phrases[ $opening_key ] = array(
                        'phrase' => $opening,
                        'count' => 0,
                    );
                }
                $opening_phrases[ $opening_key ]['count']++;
            }
        }

        // 计算平均值
        $patterns['avg_title_length'] = ! empty( $title_lengths ) ? round( array_sum( $title_lengths ) / count( $title_lengths ), 1 ) : 0;
        $patterns['avg_content_length'] = ! empty( $content_lengths ) ? round( array_sum( $content_lengths ) / count( $content_lengths ), 1 ) : 0;
        $patterns['avg_hashtag_count'] = ! empty( $hashtag_counts ) ? round( array_sum( $hashtag_counts ) / count( $hashtag_counts ), 1 ) : 0;

        // 最常用emoji（前10个）
        arsort( $emojis );
        $patterns['most_used_emojis'] = array_slice( $emojis, 0, 10, true );

        // 常用开场白（前5个）
        usort( $opening_phrases, function( $a, $b ) {
            return $b['count'] - $a['count'];
        } );
        $patterns['common_opening_phrases'] = array_slice( $opening_phrases, 0, 5 );

        return $patterns;
    }

    /**
     * 分析时间分布
     *
     * @param array $posts 内容列表
     * @return array 时间分布
     */
    private function analyze_time_distribution( $posts ) {
        $by_hour = array_fill( 0, 24, 0 );
        $by_weekday = array_fill( 0, 7, 0 );
        $by_date = array();

        foreach ( $posts as $post ) {
            $timestamp = strtotime( $post['created_at'] );

            $hour = intval( date( 'G', $timestamp ) );
            $weekday = intval( date( 'N', $timestamp ) ); // 1=周一, 7=周日
            $date = date( 'Y-m-d', $timestamp );

            $by_hour[ $hour ]++;
            $by_weekday[ $weekday - 1 ]++;

            if ( ! isset( $by_date[ $date ] ) ) {
                $by_date[ $date ] = 0;
            }
            $by_date[ $date ]++;
        }

        // 找出最活跃时段
        $peak_hours = array();
        arsort( $by_hour );
        $peak_hours = array_slice( array_keys( $by_hour ), 0, 3, true );

        $peak_weekdays = array();
        arsort( $by_weekday );
        $peak_weekdays = array_slice( array_keys( $by_weekday ), 0, 3, true );

        return array(
            'by_hour' => $by_hour,
            'by_weekday' => $by_weekday,
            'by_date' => $by_date,
            'peak_hours' => $peak_hours,
            'peak_weekdays' => array_map( function( $day ) {
                return $day + 1; // 转回1-7格式
            }, $peak_weekdays ),
        );
    }

    /**
     * 分析互动洞察
     *
     * @param array $posts 内容列表
     * @return array 互动洞察
     */
    private function analyze_engagement_insights( $posts ) {
        $scorer = new AISCG_Content_Scorer();

        $scores = array();
        $high_performers = array();
        $low_performers = array();

        foreach ( $posts as $post ) {
            $score = $scorer->score_content( $post );

            if ( $score ) {
                $scores[] = $score['overall'];

                if ( $score['overall'] >= 85 ) {
                    $high_performers[] = array(
                        'post_id' => $post['id'],
                        'title' => $post['title'],
                        'score' => $score['overall'],
                        'grade' => $score['grade'],
                    );
                } elseif ( $score['overall'] < 60 ) {
                    $low_performers[] = array(
                        'post_id' => $post['id'],
                        'title' => $post['title'],
                        'score' => $score['overall'],
                        'grade' => $score['grade'],
                    );
                }
            }
        }

        $avg_score = ! empty( $scores ) ? round( array_sum( $scores ) / count( $scores ), 1 ) : 0;

        // 排序
        usort( $high_performers, function( $a, $b ) {
            return $b['score'] - $a['score'];
        } );

        usort( $low_performers, function( $a, $b ) {
            return $a['score'] - $b['score'];
        } );

        return array(
            'average_quality_score' => $avg_score,
            'high_performers' => array_slice( $high_performers, 0, 5 ),
            'low_performers' => array_slice( $low_performers, 0, 5 ),
            'total_analyzed' => count( $scores ),
        );
    }

    /**
     * 提取关键词
     *
     * @param string $text 文本
     * @return array 关键词列表
     */
    private function extract_keywords( $text ) {
        // 移除特殊字符
        $text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

        // 分词
        $words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

        // 停用词
        $stopwords = array(
            '的', '了', '是', '在', '我', '有', '和', '就', '不', '人', '都', '一', '你', '他', '这', '那',
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'it',
        );

        // 过滤停用词和短词
        $keywords = array_filter( $words, function( $word ) use ( $stopwords ) {
            $word = mb_strtolower( $word );
            return mb_strlen( $word ) >= 2 && ! in_array( $word, $stopwords, true );
        } );

        // 统计词频
        $freq = array_count_values( $keywords );
        arsort( $freq );

        // 返回高频词
        return array_keys( array_slice( $freq, 0, 10, true ) );
    }

    /**
     * 计算增长率
     *
     * @param array $posts 带时间戳的内容列表
     * @return float 增长率（百分比）
     */
    private function calculate_growth_rate( $posts ) {
        if ( count( $posts ) < 2 ) {
            return 0;
        }

        // 按时间排序
        usort( $posts, function( $a, $b ) {
            return strtotime( $a['created_at'] ) - strtotime( $b['created_at'] );
        } );

        $total_days = ( strtotime( end( $posts )['created_at'] ) - strtotime( reset( $posts )['created_at'] ) ) / 86400;

        if ( $total_days < 1 ) {
            return 0;
        }

        // 分成前后两半
        $mid_point = floor( count( $posts ) / 2 );
        $first_half = array_slice( $posts, 0, $mid_point );
        $second_half = array_slice( $posts, $mid_point );

        $first_half_days = ( strtotime( end( $first_half )['created_at'] ) - strtotime( reset( $first_half )['created_at'] ) ) / 86400 ?: 1;
        $second_half_days = ( strtotime( end( $second_half )['created_at'] ) - strtotime( reset( $second_half )['created_at'] ) ) / 86400 ?: 1;

        $first_rate = count( $first_half ) / $first_half_days;
        $second_rate = count( $second_half ) / $second_half_days;

        if ( $first_rate == 0 ) {
            return 100;
        }

        return round( ( ( $second_rate - $first_rate ) / $first_rate ) * 100, 1 );
    }

    /**
     * 获取周期天数
     *
     * @param string $period 周期
     * @return int 天数
     */
    private function get_period_days( $period ) {
        switch ( $period ) {
            case '7days':
                return 7;
            case '90days':
                return 90;
            case '30days':
            default:
                return 30;
        }
    }

    /**
     * 保存趋势数据
     *
     * @param array $trends 趋势数据
     * @param string $platform 平台
     * @return bool 是否成功
     */
    public function save_trends( $trends, $platform = '' ) {
        global $wpdb;

        $data = array(
            'platform' => sanitize_text_field( $platform ),
            'trends_data' => wp_json_encode( $trends ),
            'analyzed_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array( '%s', '%s', '%s' )
        );

        if ( $result ) {
            $this->logger->info( '保存趋势数据成功', array(
                'platform' => $platform,
            ) );
            return true;
        }

        return false;
    }

    /**
     * 获取历史趋势
     *
     * @param array $args 查询参数
     * @return array 趋势历史
     */
    public function get_trends_history( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'platform' => '',
            'limit' => 30,
        );

        $args = wp_parse_args( $args, $defaults );

        $where = '1=1';
        $where_values = array();

        if ( ! empty( $args['platform'] ) ) {
            $where .= ' AND platform = %s';
            $where_values[] = $args['platform'];
        }

        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY analyzed_at DESC LIMIT %d",
                array_merge( $where_values, array( $args['limit'] ) )
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY analyzed_at DESC LIMIT %d",
                $args['limit']
            );
        }

        $history = $wpdb->get_results( $sql, ARRAY_A );

        // 解析JSON
        foreach ( $history as &$item ) {
            if ( ! empty( $item['trends_data'] ) ) {
                $item['trends_data'] = json_decode( $item['trends_data'], true );
            }
        }

        return $history;
    }

    /**
     * 生成趋势报告
     *
     * @param string $platform 平台
     * @param string $period 周期
     * @return array 报告数据
     */
    public function generate_report( $platform = '', $period = '30days' ) {
        $trends = $this->analyze_trends( array(
            'platform' => $platform,
            'period' => $period,
        ) );

        // 保存到数据库
        $this->save_trends( $trends, $platform );

        // 生成建议
        $recommendations = $this->generate_recommendations( $trends );

        $report = array(
            'platform' => $platform,
            'period' => $period,
            'generated_at' => current_time( 'mysql' ),
            'trends' => $trends,
            'recommendations' => $recommendations,
        );

        $this->logger->info( '生成趋势报告', array(
            'platform' => $platform,
            'period' => $period,
        ) );

        return $report;
    }

    /**
     * 生成建议
     *
     * @param array $trends 趋势数据
     * @return array 建议列表
     */
    private function generate_recommendations( $trends ) {
        $recommendations = array();

        // 基于热门话题的建议
        if ( ! empty( $trends['hot_topics'] ) ) {
            $top_topic = $trends['hot_topics'][0];
            $recommendations[] = array(
                'type' => 'hot_topic',
                'priority' => 'high',
                'title' => '利用热门话题',
                'message' => "当前最热门的话题是「{$top_topic['keyword']}」，建议创建相关内容以提高曝光度",
                'data' => $top_topic,
            );
        }

        // 基于流行标签的建议
        if ( ! empty( $trends['trending_hashtags'] ) ) {
            $trending_tags = array_slice( $trends['trending_hashtags'], 0, 5 );
            $tag_names = array_column( $trending_tags, 'hashtag' );

            $recommendations[] = array(
                'type' => 'trending_hashtags',
                'priority' => 'medium',
                'title' => '使用流行标签',
                'message' => '建议在内容中使用这些流行标签: #' . implode( ' #', $tag_names ),
                'data' => $trending_tags,
            );
        }

        // 基于最佳发布时间的建议
        if ( ! empty( $trends['time_distribution']['peak_hours'] ) ) {
            $peak_hours = $trends['time_distribution']['peak_hours'];
            $recommendations[] = array(
                'type' => 'best_time',
                'priority' => 'medium',
                'title' => '最佳发布时间',
                'message' => '建议在 ' . implode( ':00, ', $peak_hours ) . ':00 时段发布内容',
                'data' => $peak_hours,
            );
        }

        // 基于内容质量的建议
        if ( isset( $trends['engagement_insights']['average_quality_score'] ) ) {
            $avg_score = $trends['engagement_insights']['average_quality_score'];

            if ( $avg_score < 70 ) {
                $recommendations[] = array(
                    'type' => 'quality_improvement',
                    'priority' => 'high',
                    'title' => '提升内容质量',
                    'message' => "当前内容平均质量分为 {$avg_score}，建议使用AI优化功能提升内容质量",
                    'data' => array( 'avg_score' => $avg_score ),
                );
            }
        }

        return $recommendations;
    }
}
