<?php
/**
 * 竞品内容分析器
 *
 * 分析竞争对手的内容策略和表现
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 竞品分析器类
 */
class AISCG_Competitor_Analyzer {

    /**
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $database;

    /**
     * AI工厂
     *
     * @var AISCG_AI_Service_Factory
     */
    private $ai_factory;

    /**
     * 分析维度
     *
     * @var array
     */
    private $analysis_dimensions = array(
        'content_strategy' => '内容策略',
        'posting_frequency' => '发布频率',
        'content_types' => '内容类型',
        'topic_distribution' => '话题分布',
        'hashtag_strategy' => '标签策略',
        'engagement_patterns' => '互动模式',
        'posting_times' => '发布时间',
        'content_quality' => '内容质量',
    );

    /**
     * 构造函数
     */
    public function __construct() {
        $this->database = new AISCG_Database();
        $this->ai_factory = new AISCG_AI_Service_Factory();
    }

    /**
     * 添加竞品
     *
     * @param array $competitor_data 竞品数据
     *   - name: 竞品名称
     *   - platform: 平台
     *   - account_id: 账号ID/用户名
     *   - description: 描述
     *   - category: 分类
     *
     * @return int|WP_Error 竞品ID或错误
     */
    public function add_competitor( $competitor_data ) {
        global $wpdb;
        $table_competitors = $wpdb->prefix . 'aiscg_competitors';

        $defaults = array(
            'name' => '',
            'platform' => '',
            'account_id' => '',
            'description' => '',
            'category' => 'general',
        );
        $competitor_data = wp_parse_args( $competitor_data, $defaults );

        if ( empty( $competitor_data['name'] ) || empty( $competitor_data['platform'] ) ) {
            return new WP_Error( 'invalid_data', '竞品名称和平台不能为空' );
        }

        $result = $wpdb->insert(
            $table_competitors,
            array(
                'name' => $competitor_data['name'],
                'platform' => $competitor_data['platform'],
                'account_id' => $competitor_data['account_id'],
                'description' => $competitor_data['description'],
                'category' => $competitor_data['category'],
                'created_at' => current_time( 'mysql' ),
            )
        );

        if ( $result === false ) {
            return new WP_Error( 'db_error', '无法添加竞品' );
        }

        return $wpdb->insert_id;
    }

    /**
     * 分析竞品内容
     *
     * @param int $competitor_id 竞品ID
     * @param array $options 分析选项
     *   - sample_size: 样本大小
     *   - use_ai: 是否使用AI分析
     *
     * @return array|WP_Error 分析结果或错误
     */
    public function analyze_competitor( $competitor_id, $options = array() ) {
        global $wpdb;
        $table_competitors = $wpdb->prefix . 'aiscg_competitors';

        $competitor = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_competitors WHERE id = %d", $competitor_id ),
            ARRAY_A
        );

        if ( ! $competitor ) {
            return new WP_Error( 'not_found', '竞品不存在' );
        }

        $defaults = array(
            'sample_size' => 50,
            'use_ai' => true,
        );
        $options = wp_parse_args( $options, $defaults );

        // 获取竞品内容样本(这里模拟,实际应从平台API获取)
        $content_samples = $this->fetch_competitor_content( $competitor, $options['sample_size'] );

        $analysis = array(
            'competitor' => $competitor,
            'sample_size' => count( $content_samples ),
            'content_strategy' => $this->analyze_content_strategy( $content_samples ),
            'posting_frequency' => $this->analyze_posting_frequency( $content_samples ),
            'content_types' => $this->analyze_content_types( $content_samples ),
            'topic_distribution' => $this->analyze_topic_distribution( $content_samples ),
            'hashtag_strategy' => $this->analyze_hashtag_strategy( $content_samples ),
            'engagement_patterns' => $this->analyze_engagement_patterns( $content_samples ),
            'posting_times' => $this->analyze_posting_times( $content_samples ),
            'content_quality' => $this->analyze_content_quality( $content_samples ),
        );

        // 使用AI生成综合洞察
        if ( $options['use_ai'] ) {
            $analysis['ai_insights'] = $this->generate_ai_insights( $competitor, $analysis );
            $analysis['recommendations'] = $this->generate_recommendations( $competitor, $analysis );
        }

        // 保存分析结果
        $this->save_analysis_report( $competitor_id, $analysis );

        return $analysis;
    }

    /**
     * 获取竞品内容样本
     *
     * @param array $competitor 竞品信息
     * @param int $limit 数量限制
     *
     * @return array 内容样本
     */
    private function fetch_competitor_content( $competitor, $limit = 50 ) {
        // 实际应该从平台API获取，这里返回模拟数据
        $samples = array();

        for ( $i = 0; $i < min( $limit, 20 ); $i++ ) {
            $samples[] = array(
                'id' => 'comp_' . $i,
                'title' => '竞品内容标题 ' . $i,
                'content' => $this->generate_mock_content(),
                'hashtags' => $this->generate_mock_hashtags(),
                'posted_at' => date( 'Y-m-d H:i:s', strtotime( "-{$i} days" ) ),
                'likes' => rand( 50, 5000 ),
                'comments' => rand( 5, 500 ),
                'shares' => rand( 2, 200 ),
                'type' => array_rand( array_flip( array( 'tutorial', 'review', 'showcase', 'story' ) ) ),
            );
        }

        return $samples;
    }

    /**
     * 分析内容策略
     *
     * @param array $samples 内容样本
     *
     * @return array 策略分析
     */
    private function analyze_content_strategy( $samples ) {
        $total_engagement = 0;
        $content_lengths = array();
        $image_counts = array();

        foreach ( $samples as $sample ) {
            $total_engagement += $sample['likes'] + $sample['comments'] + $sample['shares'];
            $content_lengths[] = mb_strlen( $sample['content'] );
            $image_counts[] = rand( 1, 9 ); // 模拟图片数量
        }

        $avg_engagement = count( $samples ) > 0 ? $total_engagement / count( $samples ) : 0;
        $avg_length = count( $content_lengths ) > 0 ? array_sum( $content_lengths ) / count( $content_lengths ) : 0;
        $avg_images = count( $image_counts ) > 0 ? array_sum( $image_counts ) / count( $image_counts ) : 0;

        return array(
            'avg_engagement' => round( $avg_engagement, 2 ),
            'avg_content_length' => round( $avg_length ),
            'avg_images_per_post' => round( $avg_images, 1 ),
            'engagement_rate' => round( ( $avg_engagement / 1000 ) * 100, 2 ), // 假设1000粉丝
            'strategy_type' => $this->determine_strategy_type( $avg_engagement, $avg_length ),
        );
    }

    /**
     * 确定策略类型
     *
     * @param float $avg_engagement 平均互动
     * @param int $avg_length 平均长度
     *
     * @return string 策略类型
     */
    private function determine_strategy_type( $avg_engagement, $avg_length ) {
        if ( $avg_engagement > 1000 && $avg_length > 500 ) {
            return 'high_quality_depth'; // 高质量深度内容
        } elseif ( $avg_engagement > 1000 && $avg_length <= 500 ) {
            return 'high_engagement_short'; // 高互动短内容
        } elseif ( $avg_length > 800 ) {
            return 'long_form_education'; // 长篇教育内容
        } else {
            return 'balanced_mixed'; // 平衡混合策略
        }
    }

    /**
     * 分析发布频率
     *
     * @param array $samples 内容样本
     *
     * @return array 频率分析
     */
    private function analyze_posting_frequency( $samples ) {
        if ( empty( $samples ) ) {
            return array( 'posts_per_day' => 0 );
        }

        // 按日期分组
        $posts_by_date = array();
        foreach ( $samples as $sample ) {
            $date = date( 'Y-m-d', strtotime( $sample['posted_at'] ) );
            if ( ! isset( $posts_by_date[ $date ] ) ) {
                $posts_by_date[ $date ] = 0;
            }
            $posts_by_date[ $date ]++;
        }

        $total_days = count( $posts_by_date );
        $total_posts = count( $samples );

        return array(
            'posts_per_day' => $total_days > 0 ? round( $total_posts / $total_days, 2 ) : 0,
            'posts_per_week' => $total_days > 0 ? round( ( $total_posts / $total_days ) * 7, 1 ) : 0,
            'most_active_day' => $this->find_most_active_day( $samples ),
            'consistency_score' => $this->calculate_consistency_score( $posts_by_date ),
        );
    }

    /**
     * 找到最活跃的星期几
     *
     * @param array $samples 内容样本
     *
     * @return string 星期几
     */
    private function find_most_active_day( $samples ) {
        $days = array( 'Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0 );

        foreach ( $samples as $sample ) {
            $day = date( 'l', strtotime( $sample['posted_at'] ) );
            $days[ $day ]++;
        }

        arsort( $days );
        return key( $days );
    }

    /**
     * 计算一致性得分
     *
     * @param array $posts_by_date 按日期分组的发布数
     *
     * @return int 一致性得分 0-100
     */
    private function calculate_consistency_score( $posts_by_date ) {
        if ( count( $posts_by_date ) < 2 ) {
            return 0;
        }

        $values = array_values( $posts_by_date );
        $mean = array_sum( $values ) / count( $values );

        // 计算标准差
        $variance = 0;
        foreach ( $values as $value ) {
            $variance += pow( $value - $mean, 2 );
        }
        $std_dev = sqrt( $variance / count( $values ) );

        // 一致性得分: 标准差越小,得分越高
        $consistency = max( 0, 100 - ( $std_dev * 20 ) );

        return round( $consistency );
    }

    /**
     * 分析内容类型
     *
     * @param array $samples 内容样本
     *
     * @return array 类型分析
     */
    private function analyze_content_types( $samples ) {
        $types = array();

        foreach ( $samples as $sample ) {
            $type = $sample['type'];
            if ( ! isset( $types[ $type ] ) ) {
                $types[ $type ] = 0;
            }
            $types[ $type ]++;
        }

        arsort( $types );

        // 计算百分比
        $total = count( $samples );
        $type_percentages = array();
        foreach ( $types as $type => $count ) {
            $type_percentages[ $type ] = round( ( $count / $total ) * 100, 1 );
        }

        return array(
            'types' => $types,
            'percentages' => $type_percentages,
            'primary_type' => key( $types ),
        );
    }

    /**
     * 分析话题分布
     *
     * @param array $samples 内容样本
     *
     * @return array 话题分析
     */
    private function analyze_topic_distribution( $samples ) {
        $topics = array();

        foreach ( $samples as $sample ) {
            // 简单的关键词提取
            $words = $this->extract_keywords( $sample['content'] );

            foreach ( $words as $word ) {
                if ( ! isset( $topics[ $word ] ) ) {
                    $topics[ $word ] = 0;
                }
                $topics[ $word ]++;
            }
        }

        arsort( $topics );

        return array(
            'top_topics' => array_slice( $topics, 0, 20 ),
            'topic_diversity' => count( $topics ),
            'focused_topics' => array_slice( array_keys( $topics ), 0, 5 ),
        );
    }

    /**
     * 提取关键词
     *
     * @param string $text 文本
     *
     * @return array 关键词数组
     */
    private function extract_keywords( $text ) {
        // 简单实现
        $text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );
        $words = preg_split( '/\s+/', $text );

        $keywords = array();
        foreach ( $words as $word ) {
            $word = trim( $word );
            if ( mb_strlen( $word ) >= 2 && mb_strlen( $word ) <= 10 ) {
                $keywords[] = $word;
            }
        }

        return array_slice( array_unique( $keywords ), 0, 10 );
    }

    /**
     * 分析标签策略
     *
     * @param array $samples 内容样本
     *
     * @return array 标签分析
     */
    private function analyze_hashtag_strategy( $samples ) {
        $all_hashtags = array();
        $hashtag_counts = array();

        foreach ( $samples as $sample ) {
            $hashtags = $sample['hashtags'];
            $hashtag_counts[] = count( $hashtags );
            $all_hashtags = array_merge( $all_hashtags, $hashtags );
        }

        $hashtag_frequency = array_count_values( $all_hashtags );
        arsort( $hashtag_frequency );

        $avg_hashtags = count( $hashtag_counts ) > 0 ? array_sum( $hashtag_counts ) / count( $hashtag_counts ) : 0;

        return array(
            'avg_hashtags_per_post' => round( $avg_hashtags, 1 ),
            'most_used_hashtags' => array_slice( $hashtag_frequency, 0, 20 ),
            'unique_hashtags_count' => count( $hashtag_frequency ),
            'hashtag_diversity' => count( $hashtag_frequency ) / max( count( $samples ), 1 ),
        );
    }

    /**
     * 分析互动模式
     *
     * @param array $samples 内容样本
     *
     * @return array 互动分析
     */
    private function analyze_engagement_patterns( $samples ) {
        $likes = array();
        $comments = array();
        $shares = array();

        foreach ( $samples as $sample ) {
            $likes[] = $sample['likes'];
            $comments[] = $sample['comments'];
            $shares[] = $sample['shares'];
        }

        return array(
            'avg_likes' => round( array_sum( $likes ) / max( count( $likes ), 1 ), 2 ),
            'avg_comments' => round( array_sum( $comments ) / max( count( $comments ), 1 ), 2 ),
            'avg_shares' => round( array_sum( $shares ) / max( count( $shares ), 1 ), 2 ),
            'peak_engagement_post' => $this->find_peak_engagement_post( $samples ),
            'engagement_consistency' => $this->calculate_engagement_consistency( $samples ),
        );
    }

    /**
     * 找到峰值互动帖子
     *
     * @param array $samples 内容样本
     *
     * @return array 峰值帖子信息
     */
    private function find_peak_engagement_post( $samples ) {
        $max_engagement = 0;
        $peak_post = null;

        foreach ( $samples as $sample ) {
            $engagement = $sample['likes'] + $sample['comments'] + $sample['shares'];
            if ( $engagement > $max_engagement ) {
                $max_engagement = $engagement;
                $peak_post = $sample;
            }
        }

        return $peak_post ? array(
            'title' => $peak_post['title'],
            'total_engagement' => $max_engagement,
            'posted_at' => $peak_post['posted_at'],
        ) : null;
    }

    /**
     * 计算互动一致性
     *
     * @param array $samples 内容样本
     *
     * @return int 一致性得分 0-100
     */
    private function calculate_engagement_consistency( $samples ) {
        $engagements = array();

        foreach ( $samples as $sample ) {
            $engagements[] = $sample['likes'] + $sample['comments'] + $sample['shares'];
        }

        if ( count( $engagements ) < 2 ) {
            return 0;
        }

        $mean = array_sum( $engagements ) / count( $engagements );
        $variance = 0;

        foreach ( $engagements as $engagement ) {
            $variance += pow( $engagement - $mean, 2 );
        }

        $std_dev = sqrt( $variance / count( $engagements ) );
        $cv = $mean > 0 ? ( $std_dev / $mean ) * 100 : 100; // 变异系数

        // 变异系数越小,一致性越高
        $consistency = max( 0, 100 - $cv );

        return round( $consistency );
    }

    /**
     * 分析发布时间
     *
     * @param array $samples 内容样本
     *
     * @return array 时间分析
     */
    private function analyze_posting_times( $samples ) {
        $hours = array();

        foreach ( $samples as $sample ) {
            $hour = date( 'H', strtotime( $sample['posted_at'] ) );
            if ( ! isset( $hours[ $hour ] ) ) {
                $hours[ $hour ] = 0;
            }
            $hours[ $hour ]++;
        }

        arsort( $hours );

        return array(
            'preferred_hours' => array_slice( array_keys( $hours ), 0, 3 ),
            'hour_distribution' => $hours,
            'best_performing_hour' => key( $hours ),
        );
    }

    /**
     * 分析内容质量
     *
     * @param array $samples 内容样本
     *
     * @return array 质量分析
     */
    private function analyze_content_quality( $samples ) {
        $quality_scores = array();

        foreach ( $samples as $sample ) {
            // 简单的质量评分
            $score = 0;

            // 内容长度 (30分)
            $length = mb_strlen( $sample['content'] );
            if ( $length > 500 ) {
                $score += 30;
            } elseif ( $length > 200 ) {
                $score += 20;
            } else {
                $score += 10;
            }

            // 标签数量 (20分)
            $hashtag_count = count( $sample['hashtags'] );
            if ( $hashtag_count >= 5 && $hashtag_count <= 15 ) {
                $score += 20;
            } elseif ( $hashtag_count > 0 ) {
                $score += 10;
            }

            // 互动表现 (50分)
            $engagement = $sample['likes'] + $sample['comments'] + $sample['shares'];
            if ( $engagement > 1000 ) {
                $score += 50;
            } elseif ( $engagement > 500 ) {
                $score += 40;
            } elseif ( $engagement > 100 ) {
                $score += 30;
            } else {
                $score += 20;
            }

            $quality_scores[] = $score;
        }

        $avg_quality = count( $quality_scores ) > 0 ? array_sum( $quality_scores ) / count( $quality_scores ) : 0;

        return array(
            'avg_quality_score' => round( $avg_quality, 2 ),
            'quality_level' => $this->determine_quality_level( $avg_quality ),
            'high_quality_posts' => count( array_filter( $quality_scores, function( $score ) { return $score >= 80; } ) ),
        );
    }

    /**
     * 确定质量级别
     *
     * @param float $score 质量得分
     *
     * @return string 质量级别
     */
    private function determine_quality_level( $score ) {
        if ( $score >= 80 ) {
            return 'excellent';
        } elseif ( $score >= 60 ) {
            return 'good';
        } elseif ( $score >= 40 ) {
            return 'average';
        } else {
            return 'needs_improvement';
        }
    }

    /**
     * 生成AI洞察
     *
     * @param array $competitor 竞品信息
     * @param array $analysis 分析结果
     *
     * @return string AI洞察
     */
    private function generate_ai_insights( $competitor, $analysis ) {
        try {
            $ai_service = $this->ai_factory->get_service();

            $prompt = "作为内容营销专家,请分析以下竞品数据:\n\n";
            $prompt .= "竞品: {$competitor['name']} ({$competitor['platform']})\n\n";
            $prompt .= "内容策略:\n";
            $prompt .= "- 平均互动: " . $analysis['content_strategy']['avg_engagement'] . "\n";
            $prompt .= "- 平均内容长度: " . $analysis['content_strategy']['avg_content_length'] . "字\n";
            $prompt .= "- 策略类型: " . $analysis['content_strategy']['strategy_type'] . "\n\n";
            $prompt .= "发布频率: 每天" . $analysis['posting_frequency']['posts_per_day'] . "篇\n";
            $prompt .= "主要内容类型: " . $analysis['content_types']['primary_type'] . "\n\n";
            $prompt .= "请提供:\n";
            $prompt .= "1. 该竞品的核心优势(2-3点)\n";
            $prompt .= "2. 可以学习的地方(2-3点)\n";
            $prompt .= "3. 他们的潜在弱点(1-2点)\n";
            $prompt .= "4. 差异化建议(2-3点)\n\n";
            $prompt .= "请简洁专业地回答,每点1-2句话。";

            return $ai_service->generate_content( $prompt );

        } catch ( Exception $e ) {
            return '暂无AI洞察';
        }
    }

    /**
     * 生成优化建议
     *
     * @param array $competitor 竞品信息
     * @param array $analysis 分析结果
     *
     * @return array 建议列表
     */
    private function generate_recommendations( $competitor, $analysis ) {
        $recommendations = array();

        // 基于发布频率的建议
        if ( $analysis['posting_frequency']['posts_per_day'] > 2 ) {
            $recommendations[] = array(
                'type' => 'frequency',
                'title' => '高频发布策略',
                'description' => '竞品保持每天 ' . $analysis['posting_frequency']['posts_per_day'] . ' 篇的高频发布,建议考虑提升发布频率',
                'priority' => 'high',
            );
        }

        // 基于内容类型的建议
        if ( isset( $analysis['content_types']['primary_type'] ) ) {
            $recommendations[] = array(
                'type' => 'content_type',
                'title' => '主打内容类型',
                'description' => '竞品主要发布 ' . $analysis['content_types']['primary_type'] . ' 类型内容,考虑增加此类型内容占比',
                'priority' => 'medium',
            );
        }

        // 基于互动的建议
        if ( $analysis['content_strategy']['avg_engagement'] > 500 ) {
            $recommendations[] = array(
                'type' => 'engagement',
                'title' => '高互动内容策略',
                'description' => '竞品平均互动达 ' . round( $analysis['content_strategy']['avg_engagement'] ) . ',分析其内容特点并学习',
                'priority' => 'high',
            );
        }

        // 基于标签的建议
        $avg_hashtags = $analysis['hashtag_strategy']['avg_hashtags_per_post'];
        if ( $avg_hashtags >= 10 ) {
            $recommendations[] = array(
                'type' => 'hashtags',
                'title' => '标签使用策略',
                'description' => '竞品平均使用 ' . $avg_hashtags . ' 个标签,建议优化标签数量和质量',
                'priority' => 'medium',
            );
        }

        return $recommendations;
    }

    /**
     * 保存分析报告
     *
     * @param int $competitor_id 竞品ID
     * @param array $analysis 分析结果
     */
    private function save_analysis_report( $competitor_id, $analysis ) {
        global $wpdb;
        $table_reports = $wpdb->prefix . 'aiscg_competitor_reports';

        $wpdb->insert(
            $table_reports,
            array(
                'competitor_id' => $competitor_id,
                'analysis_data' => wp_json_encode( $analysis ),
                'created_at' => current_time( 'mysql' ),
            )
        );
    }

    /**
     * 对比多个竞品
     *
     * @param array $competitor_ids 竞品ID数组
     *
     * @return array 对比结果
     */
    public function compare_competitors( $competitor_ids ) {
        $comparison = array();

        foreach ( $competitor_ids as $competitor_id ) {
            $analysis = $this->analyze_competitor( $competitor_id, array( 'use_ai' => false ) );

            if ( ! is_wp_error( $analysis ) ) {
                $comparison[ $competitor_id ] = array(
                    'name' => $analysis['competitor']['name'],
                    'posting_frequency' => $analysis['posting_frequency']['posts_per_day'],
                    'avg_engagement' => $analysis['content_strategy']['avg_engagement'],
                    'quality_score' => $analysis['content_quality']['avg_quality_score'],
                    'strategy_type' => $analysis['content_strategy']['strategy_type'],
                );
            }
        }

        return $comparison;
    }

    /**
     * 生成模拟内容
     *
     * @return string 模拟内容
     */
    private function generate_mock_content() {
        $contents = array(
            '今天分享一个超实用的小技巧，保证让你的生活更便捷！',
            '这款产品真的太好用了，强烈推荐给大家！',
            '教大家如何在短时间内掌握这个技能',
            '分享我的日常vlog，记录生活中的美好瞬间',
        );

        return $contents[ array_rand( $contents ) ] . str_repeat( '内容详情...', rand( 5, 20 ) );
    }

    /**
     * 生成模拟标签
     *
     * @return array 模拟标签
     */
    private function generate_mock_hashtags() {
        $all_tags = array( '#生活', '#分享', '#干货', '#教程', '#推荐', '#日常', '#vlog', '#美食', '#旅行', '#时尚' );
        shuffle( $all_tags );
        return array_slice( $all_tags, 0, rand( 5, 10 ) );
    }

    /**
     * 获取竞品列表
     *
     * @return array 竞品列表
     */
    public function get_competitors() {
        global $wpdb;
        $table_competitors = $wpdb->prefix . 'aiscg_competitors';

        return $wpdb->get_results( "SELECT * FROM $table_competitors ORDER BY created_at DESC", ARRAY_A );
    }

    /**
     * 创建竞品表
     */
    private function create_competitors_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 竞品表
        $table_competitors = $wpdb->prefix . 'aiscg_competitors';
        $sql1 = "CREATE TABLE IF NOT EXISTS $table_competitors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            platform varchar(50) NOT NULL,
            account_id varchar(200),
            description text,
            category varchar(50),
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY platform (platform)
        ) $charset_collate;";

        // 竞品分析报告表
        $table_reports = $wpdb->prefix . 'aiscg_competitor_reports';
        $sql2 = "CREATE TABLE IF NOT EXISTS $table_reports (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            competitor_id bigint(20) NOT NULL,
            analysis_data longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY competitor_id (competitor_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
    }
}
