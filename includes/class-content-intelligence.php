<?php
/**
 * 智能内容推荐系统
 *
 * 整合历史数据、竞品分析、合规检查，提供智能内容策略推荐
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 智能内容推荐类
 */
class AISCG_Content_Intelligence {

    /**
     * 数据库操作
     *
     * @var AISCG_Database
     */
    private $database;

    /**
     * AI服务工厂
     *
     * @var AISCG_AI_Service_Factory
     */
    private $ai_factory;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->database = new AISCG_Database();
        $this->ai_factory = new AISCG_AI_Service_Factory();
    }

    /**
     * 获取智能内容推荐
     *
     * @param array $options 选项
     *   - platform: 平台 (xiaohongshu/instagram)
     *   - category: 内容分类
     *   - goal: 目标 (engagement/reach/conversion)
     *   - use_ai: 是否使用AI增强推荐
     *
     * @return array 推荐结果
     */
    public function get_content_recommendations( $options = array() ) {
        $defaults = array(
            'platform' => 'xiaohongshu',
            'category' => '',
            'goal' => 'engagement',
            'use_ai' => true,
        );
        $options = wp_parse_args( $options, $defaults );

        // 1. 分析历史表现数据
        $historical_insights = $this->analyze_historical_performance( $options['platform'] );

        // 2. 获取竞品策略
        $competitor_insights = $this->get_competitor_strategies( $options['platform'] );

        // 3. 获取合规建议
        $compliance_guidelines = $this->get_compliance_guidelines( $options['platform'] );

        // 4. 分析当前趋势
        $trending_topics = $this->analyze_trending_topics( $options['platform'] );

        // 5. 整合生成推荐
        $recommendations = array(
            'overview' => array(
                'platform' => $options['platform'],
                'goal' => $options['goal'],
                'generated_at' => current_time( 'mysql' ),
            ),
            'content_strategy' => $this->generate_content_strategy( $historical_insights, $competitor_insights ),
            'optimal_posting' => $this->recommend_posting_schedule( $historical_insights ),
            'hashtag_strategy' => $this->recommend_hashtags( $historical_insights, $trending_topics ),
            'content_format' => $this->recommend_content_format( $historical_insights, $options['goal'] ),
            'topic_suggestions' => $trending_topics,
            'compliance_tips' => $compliance_guidelines,
            'competitor_benchmarks' => $competitor_insights,
        );

        // 6. 使用AI生成深度洞察
        if ( $options['use_ai'] ) {
            $recommendations['ai_insights'] = $this->generate_ai_insights( $recommendations );
            $recommendations['action_plan'] = $this->generate_action_plan( $recommendations );
        }

        return $recommendations;
    }

    /**
     * 分析历史表现
     *
     * @param string $platform 平台
     *
     * @return array 历史洞察
     */
    private function analyze_historical_performance( $platform ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';
        $table_performance = $wpdb->prefix . 'aiscg_performance';

        // 获取最近30天的内容表现
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, perf.views, perf.likes, perf.comments, perf.shares, perf.engagement_rate
                FROM $table_posts p
                LEFT JOIN $table_performance perf ON p.id = perf.post_id
                WHERE p.platform = %s
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY p.created_at DESC",
                $platform
            ),
            ARRAY_A
        );

        if ( empty( $posts ) ) {
            return array(
                'total_posts' => 0,
                'avg_engagement' => 0,
                'best_performing' => array(),
                'insights' => array( '暂无历史数据' ),
            );
        }

        // 计算统计数据
        $total_engagement = 0;
        $total_views = 0;
        $best_posts = array();

        foreach ( $posts as $post ) {
            $engagement = intval( $post['likes'] ?? 0 ) + intval( $post['comments'] ?? 0 ) + intval( $post['shares'] ?? 0 );
            $total_engagement += $engagement;
            $total_views += intval( $post['views'] ?? 0 );

            if ( $engagement > 0 ) {
                $best_posts[] = array(
                    'title' => $post['title'],
                    'engagement' => $engagement,
                    'created_at' => $post['created_at'],
                );
            }
        }

        // 排序获取最佳表现内容
        usort( $best_posts, function( $a, $b ) {
            return $b['engagement'] - $a['engagement'];
        });

        $best_posts = array_slice( $best_posts, 0, 5 );

        $avg_engagement = count( $posts ) > 0 ? $total_engagement / count( $posts ) : 0;

        return array(
            'total_posts' => count( $posts ),
            'avg_engagement' => round( $avg_engagement, 2 ),
            'avg_views' => count( $posts ) > 0 ? round( $total_views / count( $posts ), 2 ) : 0,
            'best_performing' => $best_posts,
            'insights' => $this->extract_performance_insights( $posts ),
        );
    }

    /**
     * 提取表现洞察
     *
     * @param array $posts 帖子数据
     *
     * @return array 洞察
     */
    private function extract_performance_insights( $posts ) {
        $insights = array();

        // 分析内容长度与表现关系
        $length_performance = array();
        foreach ( $posts as $post ) {
            $length = mb_strlen( $post['content'] ?? '' );
            $engagement = intval( $post['likes'] ?? 0 ) + intval( $post['comments'] ?? 0 );

            if ( $length < 300 ) {
                $length_performance['short'][] = $engagement;
            } elseif ( $length < 600 ) {
                $length_performance['medium'][] = $engagement;
            } else {
                $length_performance['long'][] = $engagement;
            }
        }

        // 找出最佳长度
        $best_length = 'medium';
        $best_avg = 0;
        foreach ( $length_performance as $type => $engagements ) {
            if ( ! empty( $engagements ) ) {
                $avg = array_sum( $engagements ) / count( $engagements );
                if ( $avg > $best_avg ) {
                    $best_avg = $avg;
                    $best_length = $type;
                }
            }
        }

        $length_map = array(
            'short' => '短内容(300字以内)',
            'medium' => '中等长度(300-600字)',
            'long' => '长内容(600字以上)',
        );

        $insights[] = $length_map[ $best_length ] . '表现最佳';

        // 分析标签数量
        $hashtag_counts = array();
        foreach ( $posts as $post ) {
            $hashtags = json_decode( $post['hashtags'] ?? '[]', true );
            $count = is_array( $hashtags ) ? count( $hashtags ) : 0;
            $engagement = intval( $post['likes'] ?? 0 ) + intval( $post['comments'] ?? 0 );
            $hashtag_counts[ $count ] = ( $hashtag_counts[ $count ] ?? 0 ) + $engagement;
        }

        if ( ! empty( $hashtag_counts ) ) {
            arsort( $hashtag_counts );
            $best_count = array_key_first( $hashtag_counts );
            $insights[] = "使用 {$best_count} 个标签的内容互动率更高";
        }

        return $insights;
    }

    /**
     * 获取竞品策略
     *
     * @param string $platform 平台
     *
     * @return array 竞品策略
     */
    private function get_competitor_strategies( $platform ) {
        global $wpdb;
        $table_competitors = $wpdb->prefix . 'aiscg_competitors';
        $table_reports = $wpdb->prefix . 'aiscg_competitor_reports';

        // 获取最新的竞品分析报告
        $reports = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, c.name as competitor_name
                FROM $table_reports r
                INNER JOIN $table_competitors c ON r.competitor_id = c.id
                WHERE c.platform = %s
                ORDER BY r.created_at DESC
                LIMIT 3",
                $platform
            ),
            ARRAY_A
        );

        if ( empty( $reports ) ) {
            return array(
                'available' => false,
                'message' => '暂无竞品分析数据',
            );
        }

        $strategies = array();
        foreach ( $reports as $report ) {
            $analysis = json_decode( $report['analysis_data'], true );
            if ( $analysis ) {
                $strategies[] = array(
                    'competitor' => $report['competitor_name'],
                    'posting_frequency' => $analysis['posting_frequency']['avg_posts_per_day'] ?? 0,
                    'top_hashtags' => array_slice( $analysis['hashtag_strategy']['top_hashtags'] ?? array(), 0, 5 ),
                    'content_strategy' => $analysis['content_strategy']['strategy_type'] ?? 'unknown',
                );
            }
        }

        return array(
            'available' => true,
            'strategies' => $strategies,
            'insights' => $this->extract_competitor_insights( $strategies ),
        );
    }

    /**
     * 提取竞品洞察
     *
     * @param array $strategies 竞品策略
     *
     * @return array 洞察
     */
    private function extract_competitor_insights( $strategies ) {
        $insights = array();

        if ( empty( $strategies ) ) {
            return $insights;
        }

        // 平均发布频率
        $avg_frequency = 0;
        foreach ( $strategies as $strategy ) {
            $avg_frequency += $strategy['posting_frequency'];
        }
        $avg_frequency = $avg_frequency / count( $strategies );
        $insights[] = sprintf( '竞品平均每天发布 %.1f 篇内容', $avg_frequency );

        // 汇总热门标签
        $all_hashtags = array();
        foreach ( $strategies as $strategy ) {
            foreach ( $strategy['top_hashtags'] as $tag ) {
                $all_hashtags[] = $tag;
            }
        }
        $hashtag_counts = array_count_values( $all_hashtags );
        arsort( $hashtag_counts );
        $top_tags = array_slice( array_keys( $hashtag_counts ), 0, 3 );
        if ( ! empty( $top_tags ) ) {
            $insights[] = '竞品常用标签: ' . implode( ', ', $top_tags );
        }

        return $insights;
    }

    /**
     * 获取合规指南
     *
     * @param string $platform 平台
     *
     * @return array 合规建议
     */
    private function get_compliance_guidelines( $platform ) {
        $guidelines = array(
            'xiaohongshu' => array(
                '避免使用广告法禁用词(最、第一、顶级等)',
                '不要包含外部链接或联系方式',
                '确保内容真实，不夸大宣传',
                '注意保护个人隐私信息',
                '遵守平台社区规范',
            ),
            'instagram' => array(
                'Avoid spam-like behavior',
                'Use relevant hashtags (max 30)',
                'Engage authentically with followers',
                'Follow community guidelines',
                'Respect copyright and intellectual property',
            ),
        );

        return array(
            'platform' => $platform,
            'guidelines' => $guidelines[ $platform ] ?? array(),
        );
    }

    /**
     * 分析热门话题
     *
     * @param string $platform 平台
     *
     * @return array 热门话题
     */
    private function analyze_trending_topics( $platform ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        // 从最近的内容中提取高频话题
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT title, hashtags, created_at
                FROM $table_posts
                WHERE platform = %s
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY created_at DESC
                LIMIT 50",
                $platform
            ),
            ARRAY_A
        );

        $hashtag_freq = array();
        foreach ( $posts as $post ) {
            $hashtags = json_decode( $post['hashtags'] ?? '[]', true );
            if ( is_array( $hashtags ) ) {
                foreach ( $hashtags as $tag ) {
                    $tag = trim( $tag, '# ' );
                    $hashtag_freq[ $tag ] = ( $hashtag_freq[ $tag ] ?? 0 ) + 1;
                }
            }
        }

        arsort( $hashtag_freq );
        $trending = array_slice( array_keys( $hashtag_freq ), 0, 10 );

        return array(
            'trending_hashtags' => $trending,
            'period' => 'last_7_days',
        );
    }

    /**
     * 生成内容策略
     *
     * @param array $historical 历史数据
     * @param array $competitor 竞品数据
     *
     * @return array 内容策略
     */
    private function generate_content_strategy( $historical, $competitor ) {
        $strategy = array();

        // 基于历史表现
        if ( $historical['avg_engagement'] > 50 ) {
            $strategy[] = '当前内容策略表现良好，保持现有风格';
        } elseif ( $historical['avg_engagement'] > 20 ) {
            $strategy[] = '内容有一定互动，可尝试优化标题和标签';
        } else {
            $strategy[] = '建议重新评估内容策略，参考竞品表现';
        }

        // 基于竞品对比
        if ( $competitor['available'] && ! empty( $competitor['strategies'] ) ) {
            $avg_comp_freq = 0;
            foreach ( $competitor['strategies'] as $comp ) {
                $avg_comp_freq += $comp['posting_frequency'];
            }
            $avg_comp_freq = $avg_comp_freq / count( $competitor['strategies'] );

            if ( $avg_comp_freq > 2 ) {
                $strategy[] = sprintf( '竞品平均每天发布%.1f篇，建议增加发布频率', $avg_comp_freq );
            }
        }

        return $strategy;
    }

    /**
     * 推荐发布时间
     *
     * @param array $historical 历史数据
     *
     * @return array 发布时间建议
     */
    private function recommend_posting_schedule( $historical ) {
        // 基于小红书和Instagram的最佳发布时间研究
        $optimal_times = array(
            'xiaohongshu' => array(
                'weekday' => array( '07:00-09:00', '12:00-13:00', '18:00-21:00' ),
                'weekend' => array( '10:00-12:00', '14:00-16:00', '19:00-22:00' ),
            ),
            'instagram' => array(
                'weekday' => array( '11:00-13:00', '19:00-21:00' ),
                'weekend' => array( '09:00-11:00', '17:00-19:00' ),
            ),
        );

        return array(
            'optimal_times' => $optimal_times,
            'recommendation' => '建议在用户活跃时段发布，工作日早晚和午休时间效果最佳',
            'frequency' => '建议每天发布1-2篇高质量内容',
        );
    }

    /**
     * 推荐标签策略
     *
     * @param array $historical 历史数据
     * @param array $trending 热门话题
     *
     * @return array 标签建议
     */
    private function recommend_hashtags( $historical, $trending ) {
        return array(
            'trending' => $trending['trending_hashtags'],
            'count_recommendation' => '小红书建议5-10个标签，Instagram建议10-20个标签',
            'strategy' => '混合使用热门标签和细分标签，提高曝光和精准度',
        );
    }

    /**
     * 推荐内容格式
     *
     * @param array $historical 历史数据
     * @param string $goal 目标
     *
     * @return array 格式建议
     */
    private function recommend_content_format( $historical, $goal ) {
        $formats = array(
            'engagement' => array(
                'type' => '互动型内容',
                'features' => array( '提问引导评论', '投票/选择题', 'UGC征集', '话题讨论' ),
                'length' => '300-500字',
                'images' => '3-6张',
            ),
            'reach' => array(
                'type' => '病毒传播型',
                'features' => array( '热点话题', '情感共鸣', '实用干货', '视觉冲击' ),
                'length' => '200-400字',
                'images' => '1-3张',
            ),
            'conversion' => array(
                'type' => '转化导向型',
                'features' => array( '产品展示', '使用教程', '前后对比', '限时优惠' ),
                'length' => '400-600字',
                'images' => '4-9张',
            ),
        );

        return $formats[ $goal ] ?? $formats['engagement'];
    }

    /**
     * 使用AI生成深度洞察
     *
     * @param array $recommendations 推荐数据
     *
     * @return string AI洞察
     */
    private function generate_ai_insights( $recommendations ) {
        try {
            $ai_service = $this->ai_factory->get_service();

            $prompt = "作为社交媒体内容策略专家，基于以下数据提供深度洞察和建议：\n\n";
            $prompt .= "历史表现：\n" . wp_json_encode( $recommendations['content_strategy'], JSON_UNESCAPED_UNICODE ) . "\n\n";
            $prompt .= "竞品分析：\n" . wp_json_encode( $recommendations['competitor_benchmarks'], JSON_UNESCAPED_UNICODE ) . "\n\n";
            $prompt .= "热门话题：\n" . wp_json_encode( $recommendations['topic_suggestions'], JSON_UNESCAPED_UNICODE ) . "\n\n";
            $prompt .= "请提供：\n1. 核心洞察(2-3条)\n2. 具体建议(3-5条)\n3. 需要注意的风险点";

            $result = $ai_service->generate_text( $prompt );

            return $result['text'] ?? '暂无AI洞察';
        } catch ( Exception $e ) {
            return 'AI洞察生成失败: ' . $e->getMessage();
        }
    }

    /**
     * 生成行动计划
     *
     * @param array $recommendations 推荐数据
     *
     * @return array 行动计划
     */
    private function generate_action_plan( $recommendations ) {
        $plan = array(
            array(
                'priority' => 'high',
                'action' => '优化内容标题和开场',
                'description' => '使用更吸引眼球的标题，在前3秒抓住用户注意力',
                'expected_impact' => '提升10-20%点击率',
            ),
            array(
                'priority' => 'high',
                'action' => '调整标签策略',
                'description' => '使用热门话题标签: ' . implode( ', ', array_slice( $recommendations['topic_suggestions']['trending_hashtags'], 0, 5 ) ),
                'expected_impact' => '扩大内容曝光范围',
            ),
            array(
                'priority' => 'medium',
                'action' => '优化发布时间',
                'description' => '在用户活跃高峰期发布内容',
                'expected_impact' => '提升初始互动率',
            ),
            array(
                'priority' => 'medium',
                'action' => '学习竞品优势',
                'description' => '分析竞品高表现内容，借鉴其成功要素',
                'expected_impact' => '提升内容质量',
            ),
            array(
                'priority' => 'low',
                'action' => '定期内容审核',
                'description' => '确保所有内容符合平台规范，避免违规风险',
                'expected_impact' => '降低内容下架风险',
            ),
        );

        return $plan;
    }

    /**
     * 获取个性化内容建议
     *
     * @param string $topic 主题
     * @param array $options 选项
     *
     * @return array 内容建议
     */
    public function get_personalized_content_suggestions( $topic, $options = array() ) {
        $defaults = array(
            'platform' => 'xiaohongshu',
            'style' => 'engaging',
            'count' => 5,
        );
        $options = wp_parse_args( $options, $defaults );

        try {
            $ai_service = $this->ai_factory->get_service();

            $platform_name = $options['platform'] === 'xiaohongshu' ? '小红书' : 'Instagram';
            $prompt = "为{$platform_name}平台生成{$options['count']}个关于「{$topic}」的内容创意。\n\n";
            $prompt .= "要求：\n";
            $prompt .= "1. 每个创意包括标题和简短描述\n";
            $prompt .= "2. 风格: {$options['style']}\n";
            $prompt .= "3. 适合{$platform_name}平台特点\n";
            $prompt .= "4. 容易引起用户互动\n\n";
            $prompt .= "输出JSON格式：[{\"title\": \"标题\", \"description\": \"描述\", \"hashtags\": [\"标签1\", \"标签2\"]}]";

            $result = $ai_service->generate_text( $prompt );
            $suggestions = json_decode( $result['text'], true );

            if ( ! is_array( $suggestions ) ) {
                // 如果AI没有返回正确的JSON，提供默认建议
                return $this->get_default_suggestions( $topic, $options );
            }

            return $suggestions;
        } catch ( Exception $e ) {
            return $this->get_default_suggestions( $topic, $options );
        }
    }

    /**
     * 获取默认建议
     *
     * @param string $topic 主题
     * @param array $options 选项
     *
     * @return array 默认建议
     */
    private function get_default_suggestions( $topic, $options ) {
        $templates = array(
            array(
                'title' => "关于{$topic}的5个实用技巧",
                'description' => '分享实用干货，帮助用户解决实际问题',
                'hashtags' => array( $topic, '实用技巧', '干货分享' ),
            ),
            array(
                'title' => "我的{$topic}经验分享",
                'description' => '真实经验分享，引起用户共鸣',
                'hashtags' => array( $topic, '经验分享', '真实体验' ),
            ),
            array(
                'title' => "{$topic}前后对比，效果惊人",
                'description' => '视觉冲击力强，容易引起关注',
                'hashtags' => array( $topic, '效果对比', '变化' ),
            ),
        );

        return array_slice( $templates, 0, $options['count'] );
    }
}
