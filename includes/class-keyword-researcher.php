<?php
/**
 * 关键词研究工具
 *
 * 提供关键词发现、分析、趋势追踪等功能
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 关键词研究工具类
 */
class AISCG_Keyword_Researcher {

    /**
     * AI服务工厂
     *
     * @var AISCG_AI_Service_Factory
     */
    private $ai_factory;

    /**
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $database;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->ai_factory = new AISCG_AI_Service_Factory();
        $this->database = new AISCG_Database();
    }

    /**
     * 发现关键词机会
     *
     * @param array $options 选项参数
     *   - platform: 平台 (xiaohongshu/instagram)
     *   - category: 分类
     *   - language: 语言
     *   - min_usage: 最小使用次数
     *   - days: 分析天数
     *   - limit: 返回数量限制
     *
     * @return array 关键词机会列表
     */
    public function discover_keywords( $options = array() ) {
        $defaults = array(
            'platform' => '',
            'category' => '',
            'language' => 'zh-CN',
            'min_usage' => 3,
            'days' => 30,
            'limit' => 50,
        );
        $options = wp_parse_args( $options, $defaults );

        // 从历史内容中提取关键词
        $historical_keywords = $this->extract_keywords_from_history( $options );

        // 分析关键词趋势
        $trending_keywords = $this->analyze_keyword_trends( $historical_keywords, $options );

        // 获取关键词统计
        $keyword_opportunities = array();
        foreach ( $trending_keywords as $keyword => $trend ) {
            $stats = $this->get_keyword_stats( $keyword, $options );

            // 计算机会得分
            $opportunity_score = $this->calculate_opportunity_score( $stats, $trend );

            $keyword_opportunities[] = array(
                'keyword' => $keyword,
                'usage_count' => $stats['usage_count'],
                'trend' => $trend['trend'],
                'growth_rate' => $trend['growth_rate'],
                'difficulty' => $stats['difficulty'],
                'opportunity_score' => $opportunity_score,
                'avg_engagement' => $stats['avg_engagement'],
                'last_used' => $stats['last_used'],
                'category' => $stats['category'],
            );
        }

        // 按机会得分排序
        usort( $keyword_opportunities, function( $a, $b ) {
            return $b['opportunity_score'] - $a['opportunity_score'];
        });

        return array_slice( $keyword_opportunities, 0, $options['limit'] );
    }

    /**
     * 分析关键词
     *
     * @param string $keyword 关键词
     * @param array $options 选项参数
     *
     * @return array 分析结果
     */
    public function analyze_keyword( $keyword, $options = array() ) {
        $defaults = array(
            'platform' => '',
            'language' => 'zh-CN',
            'include_related' => true,
            'include_ai_insights' => true,
        );
        $options = wp_parse_args( $options, $defaults );

        $analysis = array(
            'keyword' => $keyword,
            'stats' => $this->get_keyword_stats( $keyword, $options ),
            'difficulty' => $this->calculate_difficulty( $keyword, $options ),
            'trend' => $this->get_keyword_trend( $keyword, $options ),
            'related_keywords' => array(),
            'long_tail_suggestions' => array(),
            'ai_insights' => '',
        );

        // 获取相关关键词
        if ( $options['include_related'] ) {
            $analysis['related_keywords'] = $this->get_related_keywords( $keyword, $options );
            $analysis['long_tail_suggestions'] = $this->suggest_long_tail( $keyword, $options );
        }

        // 使用AI分析关键词
        if ( $options['include_ai_insights'] ) {
            $analysis['ai_insights'] = $this->get_ai_insights( $keyword, $options );
        }

        return $analysis;
    }

    /**
     * 获取相关关键词
     *
     * @param string $keyword 关键词
     * @param array $options 选项参数
     *
     * @return array 相关关键词列表
     */
    public function get_related_keywords( $keyword, $options = array() ) {
        $defaults = array(
            'platform' => '',
            'language' => 'zh-CN',
            'method' => 'hybrid', // ai, statistical, hybrid
            'limit' => 20,
        );
        $options = wp_parse_args( $options, $defaults );

        $related = array();

        // 统计方法: 基于共现分析
        if ( in_array( $options['method'], array( 'statistical', 'hybrid' ) ) ) {
            $statistical_related = $this->find_cooccurring_keywords( $keyword, $options );
            $related = array_merge( $related, $statistical_related );
        }

        // AI方法: 使用大模型生成相关关键词
        if ( in_array( $options['method'], array( 'ai', 'hybrid' ) ) ) {
            $ai_related = $this->generate_related_with_ai( $keyword, $options );
            $related = array_merge( $related, $ai_related );
        }

        // 去重和排序
        $related = array_unique( $related );
        $related = array_diff( $related, array( $keyword ) ); // 移除原关键词

        // 计算相关度分数
        $scored_keywords = array();
        foreach ( $related as $rel_keyword ) {
            $stats = $this->get_keyword_stats( $rel_keyword, $options );
            $scored_keywords[] = array(
                'keyword' => $rel_keyword,
                'relevance' => $this->calculate_relevance( $keyword, $rel_keyword, $options ),
                'usage_count' => $stats['usage_count'],
                'difficulty' => $stats['difficulty'],
            );
        }

        // 按相关度排序
        usort( $scored_keywords, function( $a, $b ) {
            return $b['relevance'] - $a['relevance'];
        });

        return array_slice( $scored_keywords, 0, $options['limit'] );
    }

    /**
     * 计算关键词难度
     *
     * @param string $keyword 关键词
     * @param array $options 选项参数
     *
     * @return int 难度分数 0-100
     */
    public function calculate_difficulty( $keyword, $options = array() ) {
        $defaults = array(
            'platform' => '',
        );
        $options = wp_parse_args( $options, $defaults );

        $stats = $this->get_keyword_stats( $keyword, $options );

        // 难度因素
        $factors = array();

        // 1. 竞争度 (使用频率)
        $usage_count = $stats['usage_count'];
        if ( $usage_count > 100 ) {
            $factors['competition'] = 80;
        } elseif ( $usage_count > 50 ) {
            $factors['competition'] = 60;
        } elseif ( $usage_count > 20 ) {
            $factors['competition'] = 40;
        } elseif ( $usage_count > 5 ) {
            $factors['competition'] = 20;
        } else {
            $factors['competition'] = 10;
        }

        // 2. 关键词长度 (越短越难)
        $word_count = $this->count_words( $keyword );
        if ( $word_count == 1 ) {
            $factors['length'] = 70;
        } elseif ( $word_count == 2 ) {
            $factors['length'] = 50;
        } elseif ( $word_count == 3 ) {
            $factors['length'] = 30;
        } else {
            $factors['length'] = 10;
        }

        // 3. 通用性 (越通用越难)
        $generic_keywords = array( '生活', '时尚', '美食', '旅游', '分享', 'life', 'style', 'love', 'happy' );
        $is_generic = false;
        foreach ( $generic_keywords as $generic ) {
            if ( stripos( $keyword, $generic ) !== false ) {
                $is_generic = true;
                break;
            }
        }
        $factors['genericity'] = $is_generic ? 60 : 20;

        // 4. 趋势 (上升趋势增加难度)
        $trend = $this->get_keyword_trend( $keyword, $options );
        if ( isset( $trend['growth_rate'] ) && $trend['growth_rate'] > 50 ) {
            $factors['trend'] = 70;
        } elseif ( isset( $trend['growth_rate'] ) && $trend['growth_rate'] > 0 ) {
            $factors['trend'] = 40;
        } else {
            $factors['trend'] = 20;
        }

        // 综合计算 (加权平均)
        $weights = array(
            'competition' => 0.4,
            'length' => 0.2,
            'genericity' => 0.2,
            'trend' => 0.2,
        );

        $difficulty = 0;
        foreach ( $factors as $factor => $score ) {
            $difficulty += $score * $weights[ $factor ];
        }

        return round( $difficulty );
    }

    /**
     * 获取趋势关键词
     *
     * @param array $options 选项参数
     *
     * @return array 趋势关键词列表
     */
    public function get_trending_keywords( $options = array() ) {
        $defaults = array(
            'platform' => '',
            'category' => '',
            'language' => 'zh-CN',
            'days' => 30,
            'min_growth_rate' => 20, // 最小增长率
            'limit' => 30,
        );
        $options = wp_parse_args( $options, $defaults );

        // 提取关键词
        $keywords = $this->extract_keywords_from_history( $options );

        // 分析趋势
        $trends = $this->analyze_keyword_trends( $keywords, $options );

        // 筛选增长关键词
        $trending = array();
        foreach ( $trends as $keyword => $trend ) {
            if ( $trend['growth_rate'] >= $options['min_growth_rate'] ) {
                $stats = $this->get_keyword_stats( $keyword, $options );
                $trending[] = array(
                    'keyword' => $keyword,
                    'growth_rate' => $trend['growth_rate'],
                    'trend' => $trend['trend'],
                    'usage_count' => $stats['usage_count'],
                    'recent_count' => $trend['recent_count'],
                    'older_count' => $trend['older_count'],
                );
            }
        }

        // 按增长率排序
        usort( $trending, function( $a, $b ) {
            return $b['growth_rate'] - $a['growth_rate'];
        });

        return array_slice( $trending, 0, $options['limit'] );
    }

    /**
     * 跟踪关键词表现
     *
     * @param string $keyword 关键词
     * @param array $options 选项参数
     *
     * @return array 表现数据
     */
    public function track_performance( $keyword, $options = array() ) {
        $defaults = array(
            'platform' => '',
            'days' => 90,
            'interval' => 'daily', // daily, weekly, monthly
        );
        $options = wp_parse_args( $options, $defaults );

        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        // 根据间隔构建SQL
        $interval_format = array(
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
        );
        $date_format = $interval_format[ $options['interval'] ];

        $where = "1=1";
        if ( ! empty( $options['platform'] ) ) {
            $where .= $wpdb->prepare( " AND platform = %s", $options['platform'] );
        }
        $where .= $wpdb->prepare( " AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", $options['days'] );

        $query = "
            SELECT
                DATE_FORMAT(created_at, '$date_format') as period,
                COUNT(*) as count
            FROM $table_posts
            WHERE $where
                AND (title LIKE %s OR content LIKE %s OR hashtags LIKE %s)
            GROUP BY period
            ORDER BY period ASC
        ";

        $like_keyword = '%' . $wpdb->esc_like( $keyword ) . '%';
        $results = $wpdb->get_results(
            $wpdb->prepare( $query, $like_keyword, $like_keyword, $like_keyword ),
            ARRAY_A
        );

        // 计算统计数据
        $total_usage = array_sum( wp_list_pluck( $results, 'count' ) );
        $avg_usage = $total_usage > 0 ? $total_usage / count( $results ) : 0;

        return array(
            'keyword' => $keyword,
            'timeline' => $results,
            'total_usage' => $total_usage,
            'avg_usage' => round( $avg_usage, 2 ),
            'peak_period' => $this->find_peak_period( $results ),
            'trend_direction' => $this->calculate_trend_direction( $results ),
        );
    }

    /**
     * 建议长尾关键词
     *
     * @param string $keyword 种子关键词
     * @param array $options 选项参数
     *
     * @return array 长尾关键词建议
     */
    public function suggest_long_tail( $keyword, $options = array() ) {
        $defaults = array(
            'language' => 'zh-CN',
            'limit' => 15,
            'use_ai' => true,
        );
        $options = wp_parse_args( $options, $defaults );

        $long_tail = array();

        // 基于模板的长尾关键词
        $templates = $this->get_long_tail_templates( $options['language'] );
        foreach ( $templates as $template ) {
            $suggestion = str_replace( '{keyword}', $keyword, $template );
            $long_tail[] = $suggestion;
        }

        // 使用AI生成长尾关键词
        if ( $options['use_ai'] ) {
            $ai_suggestions = $this->generate_long_tail_with_ai( $keyword, $options );
            $long_tail = array_merge( $long_tail, $ai_suggestions );
        }

        // 去重
        $long_tail = array_unique( $long_tail );

        // 评估每个长尾词
        $scored_suggestions = array();
        foreach ( $long_tail as $suggestion ) {
            $stats = $this->get_keyword_stats( $suggestion, $options );
            $scored_suggestions[] = array(
                'keyword' => $suggestion,
                'usage_count' => $stats['usage_count'],
                'difficulty' => $this->calculate_difficulty( $suggestion, $options ),
                'potential' => $this->calculate_long_tail_potential( $suggestion, $keyword ),
            );
        }

        // 按潜力排序
        usort( $scored_suggestions, function( $a, $b ) {
            return $b['potential'] - $a['potential'];
        });

        return array_slice( $scored_suggestions, 0, $options['limit'] );
    }

    /**
     * 关键词分组
     *
     * @param array $keywords 关键词列表
     * @param array $options 选项参数
     *
     * @return array 分组后的关键词
     */
    public function group_keywords( $keywords, $options = array() ) {
        $defaults = array(
            'method' => 'similarity', // similarity, topic, manual
            'max_groups' => 10,
        );
        $options = wp_parse_args( $options, $defaults );

        $groups = array();

        if ( $options['method'] === 'similarity' ) {
            // 基于相似度分组
            $groups = $this->group_by_similarity( $keywords, $options );
        } elseif ( $options['method'] === 'topic' ) {
            // 基于主题分组
            $groups = $this->group_by_topic( $keywords, $options );
        }

        return $groups;
    }

    /**
     * 获取关键词统计
     *
     * @param string $keyword 关键词
     * @param array $options 选项参数
     *
     * @return array 统计数据
     */
    public function get_keyword_stats( $keyword, $options = array() ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $where = "1=1";
        if ( ! empty( $options['platform'] ) ) {
            $where .= $wpdb->prepare( " AND platform = %s", $options['platform'] );
        }

        $like_keyword = '%' . $wpdb->esc_like( $keyword ) . '%';

        // 使用次数
        $usage_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_posts
                WHERE $where AND (title LIKE %s OR content LIKE %s OR hashtags LIKE %s)",
                $like_keyword, $like_keyword, $like_keyword
            )
        );

        // 最后使用时间
        $last_used = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(created_at) FROM $table_posts
                WHERE $where AND (title LIKE %s OR content LIKE %s OR hashtags LIKE %s)",
                $like_keyword, $like_keyword, $like_keyword
            )
        );

        // 平均互动 (这里模拟,实际应从真实数据获取)
        $avg_engagement = rand( 50, 500 );

        return array(
            'keyword' => $keyword,
            'usage_count' => intval( $usage_count ),
            'last_used' => $last_used,
            'avg_engagement' => $avg_engagement,
            'difficulty' => $this->calculate_difficulty( $keyword, $options ),
            'category' => $this->guess_category( $keyword ),
        );
    }

    /**
     * 从历史内容中提取关键词
     *
     * @param array $options 选项参数
     *
     * @return array 关键词数组
     */
    private function extract_keywords_from_history( $options ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $where = "1=1";
        if ( ! empty( $options['platform'] ) ) {
            $where .= $wpdb->prepare( " AND platform = %s", $options['platform'] );
        }
        $where .= $wpdb->prepare( " AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", $options['days'] );

        $posts = $wpdb->get_results(
            "SELECT title, content, hashtags FROM $table_posts WHERE $where",
            ARRAY_A
        );

        $keywords = array();
        foreach ( $posts as $post ) {
            // 从标题提取
            $title_keywords = $this->extract_keywords_from_text( $post['title'], $options['language'] );
            $keywords = array_merge( $keywords, $title_keywords );

            // 从内容提取
            $content_keywords = $this->extract_keywords_from_text( $post['content'], $options['language'] );
            $keywords = array_merge( $keywords, $content_keywords );

            // 从标签提取
            $hashtags = json_decode( $post['hashtags'], true );
            if ( is_array( $hashtags ) ) {
                foreach ( $hashtags as $tag ) {
                    $tag = ltrim( $tag, '#' );
                    if ( mb_strlen( $tag ) >= 2 ) {
                        $keywords[] = $tag;
                    }
                }
            }
        }

        return $keywords;
    }

    /**
     * 从文本中提取关键词
     *
     * @param string $text 文本
     * @param string $language 语言
     *
     * @return array 关键词数组
     */
    private function extract_keywords_from_text( $text, $language = 'zh-CN' ) {
        $keywords = array();

        // 移除emoji和特殊字符
        $text = preg_replace( '/[\x{1F300}-\x{1F9FF}]/u', '', $text );
        $text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

        if ( $language === 'zh-CN' || $language === 'zh-TW' ) {
            // 中文分词 (简单实现,实际应使用专业分词库)
            $words = $this->simple_chinese_segmentation( $text );
        } else {
            // 英文分词
            $words = preg_split( '/\s+/', strtolower( $text ) );
        }

        // 过滤停用词和短词
        $stopwords = $this->get_stopwords( $language );
        foreach ( $words as $word ) {
            $word = trim( $word );
            if ( mb_strlen( $word ) >= 2 && ! in_array( $word, $stopwords ) ) {
                $keywords[] = $word;
            }
        }

        return $keywords;
    }

    /**
     * 简单中文分词
     *
     * @param string $text 文本
     *
     * @return array 词语数组
     */
    private function simple_chinese_segmentation( $text ) {
        // 简单实现: 2-4字的n-gram
        $words = array();
        $len = mb_strlen( $text );

        for ( $i = 0; $i < $len; $i++ ) {
            // 2字词
            if ( $i + 2 <= $len ) {
                $words[] = mb_substr( $text, $i, 2 );
            }
            // 3字词
            if ( $i + 3 <= $len ) {
                $words[] = mb_substr( $text, $i, 3 );
            }
            // 4字词
            if ( $i + 4 <= $len ) {
                $words[] = mb_substr( $text, $i, 4 );
            }
        }

        return $words;
    }

    /**
     * 分析关键词趋势
     *
     * @param array $keywords 关键词数组
     * @param array $options 选项参数
     *
     * @return array 趋势数据
     */
    private function analyze_keyword_trends( $keywords, $options ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $keyword_freq = array_count_values( $keywords );
        $trends = array();

        $days = $options['days'];
        $half_days = floor( $days / 2 );

        foreach ( $keyword_freq as $keyword => $total_count ) {
            if ( $total_count < $options['min_usage'] ) {
                continue;
            }

            $where = "1=1";
            if ( ! empty( $options['platform'] ) ) {
                $where .= $wpdb->prepare( " AND platform = %s", $options['platform'] );
            }

            $like_keyword = '%' . $wpdb->esc_like( $keyword ) . '%';

            // 最近一半时间的使用次数
            $recent_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_posts
                    WHERE $where
                        AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                        AND (title LIKE %s OR content LIKE %s OR hashtags LIKE %s)",
                    $half_days, $like_keyword, $like_keyword, $like_keyword
                )
            );

            // 较早一半时间的使用次数
            $older_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_posts
                    WHERE $where
                        AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                        AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
                        AND (title LIKE %s OR content LIKE %s OR hashtags LIKE %s)",
                    $days, $half_days, $like_keyword, $like_keyword, $like_keyword
                )
            );

            // 计算增长率
            $growth_rate = 0;
            if ( $older_count > 0 ) {
                $growth_rate = ( ( $recent_count - $older_count ) / $older_count ) * 100;
            } elseif ( $recent_count > 0 ) {
                $growth_rate = 100;
            }

            $trend = 'stable';
            if ( $growth_rate > 20 ) {
                $trend = 'rising';
            } elseif ( $growth_rate < -20 ) {
                $trend = 'declining';
            }

            $trends[ $keyword ] = array(
                'trend' => $trend,
                'growth_rate' => round( $growth_rate, 2 ),
                'recent_count' => intval( $recent_count ),
                'older_count' => intval( $older_count ),
            );
        }

        return $trends;
    }

    /**
     * 计算机会得分
     *
     * @param array $stats 统计数据
     * @param array $trend 趋势数据
     *
     * @return float 机会得分 0-100
     */
    private function calculate_opportunity_score( $stats, $trend ) {
        $score = 0;

        // 1. 趋势权重 40%
        if ( $trend['trend'] === 'rising' ) {
            $score += 40 * ( min( $trend['growth_rate'], 100 ) / 100 );
        }

        // 2. 难度权重 30% (难度越低,得分越高)
        $score += 30 * ( 1 - $stats['difficulty'] / 100 );

        // 3. 使用频率权重 20% (适中最好)
        $usage = $stats['usage_count'];
        if ( $usage >= 5 && $usage <= 20 ) {
            $score += 20;
        } elseif ( $usage >= 3 && $usage < 5 ) {
            $score += 15;
        } elseif ( $usage > 20 && $usage <= 50 ) {
            $score += 10;
        }

        // 4. 新鲜度权重 10%
        if ( ! empty( $stats['last_used'] ) ) {
            $days_ago = ( time() - strtotime( $stats['last_used'] ) ) / DAY_IN_SECONDS;
            if ( $days_ago <= 7 ) {
                $score += 10;
            } elseif ( $days_ago <= 14 ) {
                $score += 7;
            } elseif ( $days_ago <= 30 ) {
                $score += 4;
            }
        }

        return round( $score, 2 );
    }

    /**
     * 获取关键词趋势
     *
     * @param string $keyword 关键词
     * @param array $options 选项参数
     *
     * @return array 趋势数据
     */
    private function get_keyword_trend( $keyword, $options ) {
        $keywords = array( $keyword );
        $trends = $this->analyze_keyword_trends( $keywords, array_merge( $options, array( 'min_usage' => 0 ) ) );

        return isset( $trends[ $keyword ] ) ? $trends[ $keyword ] : array(
            'trend' => 'unknown',
            'growth_rate' => 0,
            'recent_count' => 0,
            'older_count' => 0,
        );
    }

    /**
     * 查找共现关键词
     *
     * @param string $keyword 关键词
     * @param array $options 选项参数
     *
     * @return array 共现关键词
     */
    private function find_cooccurring_keywords( $keyword, $options ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $where = "1=1";
        if ( ! empty( $options['platform'] ) ) {
            $where .= $wpdb->prepare( " AND platform = %s", $options['platform'] );
        }

        $like_keyword = '%' . $wpdb->esc_like( $keyword ) . '%';

        // 找到包含该关键词的所有内容
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT title, content, hashtags FROM $table_posts
                WHERE $where AND (title LIKE %s OR content LIKE %s OR hashtags LIKE %s)",
                $like_keyword, $like_keyword, $like_keyword
            ),
            ARRAY_A
        );

        // 提取这些内容中的所有关键词
        $cooccurring = array();
        foreach ( $posts as $post ) {
            $text = $post['title'] . ' ' . $post['content'];
            $keywords = $this->extract_keywords_from_text( $text, $options['language'] );
            $cooccurring = array_merge( $cooccurring, $keywords );
        }

        // 统计频率并返回高频词
        $freq = array_count_values( $cooccurring );
        arsort( $freq );

        return array_keys( array_slice( $freq, 0, 10 ) );
    }

    /**
     * 使用AI生成相关关键词
     *
     * @param string $keyword 关键词
     * @param array $options 选项参数
     *
     * @return array 相关关键词
     */
    private function generate_related_with_ai( $keyword, $options ) {
        try {
            $ai_service = $this->ai_factory->get_service();

            $prompt = "请为关键词「{$keyword}」生成10个相关的关键词或短语。\n\n";
            $prompt .= "要求:\n";
            $prompt .= "1. 关键词应该与「{$keyword}」主题相关\n";
            $prompt .= "2. 包含不同的相关角度和方面\n";
            $prompt .= "3. 适合用于{$options['platform']}平台内容\n";
            $prompt .= "4. 每行一个关键词,不要编号\n";

            $response = $ai_service->generate_content( $prompt );

            // 解析AI返回的关键词
            $lines = explode( "\n", trim( $response ) );
            $keywords = array();

            foreach ( $lines as $line ) {
                $line = trim( $line );
                // 移除可能的编号和特殊字符
                $line = preg_replace( '/^[\d\.\-\*]+\s*/', '', $line );
                $line = trim( $line );

                if ( ! empty( $line ) && mb_strlen( $line ) >= 2 && mb_strlen( $line ) <= 20 ) {
                    $keywords[] = $line;
                }
            }

            return array_slice( $keywords, 0, 10 );

        } catch ( Exception $e ) {
            return array();
        }
    }

    /**
     * 使用AI获取关键词洞察
     *
     * @param string $keyword 关键词
     * @param array $options 选项参数
     *
     * @return string AI洞察
     */
    private function get_ai_insights( $keyword, $options ) {
        try {
            $ai_service = $this->ai_factory->get_service();

            $prompt = "作为内容营销专家,请分析关键词「{$keyword}」:\n\n";
            $prompt .= "1. 目标受众: 这个关键词主要吸引什么样的受众?\n";
            $prompt .= "2. 内容建议: 围绕这个关键词应该创作什么类型的内容?\n";
            $prompt .= "3. 最佳实践: 使用这个关键词的注意事项和技巧\n";
            $prompt .= "4. 内容角度: 推荐3-5个具体的内容创作角度\n\n";
            $prompt .= "请简洁专业地回答,每点2-3句话即可。";

            return $ai_service->generate_content( $prompt );

        } catch ( Exception $e ) {
            return '暂无AI洞察';
        }
    }

    /**
     * 使用AI生成长尾关键词
     *
     * @param string $keyword 种子关键词
     * @param array $options 选项参数
     *
     * @return array 长尾关键词
     */
    private function generate_long_tail_with_ai( $keyword, $options ) {
        try {
            $ai_service = $this->ai_factory->get_service();

            $prompt = "请为关键词「{$keyword}」生成10个长尾关键词。\n\n";
            $prompt .= "要求:\n";
            $prompt .= "1. 长尾关键词应包含3-6个词\n";
            $prompt .= "2. 更具体、更细分\n";
            $prompt .= "3. 搜索意图明确\n";
            $prompt .= "4. 适合{$options['language']}语言\n";
            $prompt .= "5. 每行一个,不要编号\n";

            $response = $ai_service->generate_content( $prompt );

            $lines = explode( "\n", trim( $response ) );
            $keywords = array();

            foreach ( $lines as $line ) {
                $line = trim( $line );
                $line = preg_replace( '/^[\d\.\-\*]+\s*/', '', $line );
                $line = trim( $line );

                if ( ! empty( $line ) && mb_strlen( $line ) >= 4 ) {
                    $keywords[] = $line;
                }
            }

            return array_slice( $keywords, 0, 10 );

        } catch ( Exception $e ) {
            return array();
        }
    }

    /**
     * 获取长尾关键词模板
     *
     * @param string $language 语言
     *
     * @return array 模板数组
     */
    private function get_long_tail_templates( $language ) {
        if ( $language === 'zh-CN' || $language === 'zh-TW' ) {
            return array(
                '如何{keyword}',
                '{keyword}教程',
                '{keyword}技巧',
                '{keyword}推荐',
                '最好的{keyword}',
                '{keyword}指南',
                '{keyword}攻略',
                '{keyword}分享',
                '{keyword}经验',
            );
        } else {
            return array(
                'how to {keyword}',
                '{keyword} tutorial',
                '{keyword} tips',
                '{keyword} guide',
                'best {keyword}',
                '{keyword} ideas',
                '{keyword} hacks',
            );
        }
    }

    /**
     * 计算长尾关键词潜力
     *
     * @param string $long_tail 长尾关键词
     * @param string $seed 种子关键词
     *
     * @return float 潜力得分 0-100
     */
    private function calculate_long_tail_potential( $long_tail, $seed ) {
        $score = 0;

        // 1. 包含种子词 +30分
        if ( stripos( $long_tail, $seed ) !== false ) {
            $score += 30;
        }

        // 2. 长度适中 (3-6个词) +20分
        $word_count = $this->count_words( $long_tail );
        if ( $word_count >= 3 && $word_count <= 6 ) {
            $score += 20;
        }

        // 3. 包含疑问词或动作词 +25分
        $intent_words = array( '如何', '怎么', '什么', '推荐', '最好', '教程', 'how', 'what', 'best', 'guide' );
        foreach ( $intent_words as $word ) {
            if ( stripos( $long_tail, $word ) !== false ) {
                $score += 25;
                break;
            }
        }

        // 4. 独特性 (使用次数少) +25分
        $stats = $this->get_keyword_stats( $long_tail, array() );
        if ( $stats['usage_count'] == 0 ) {
            $score += 25;
        } elseif ( $stats['usage_count'] <= 2 ) {
            $score += 15;
        }

        return min( $score, 100 );
    }

    /**
     * 基于相似度分组关键词
     *
     * @param array $keywords 关键词列表
     * @param array $options 选项参数
     *
     * @return array 分组结果
     */
    private function group_by_similarity( $keywords, $options ) {
        $groups = array();
        $used = array();

        foreach ( $keywords as $keyword ) {
            if ( in_array( $keyword, $used ) ) {
                continue;
            }

            $group = array( $keyword );
            $used[] = $keyword;

            // 查找相似的关键词
            foreach ( $keywords as $other ) {
                if ( $keyword === $other || in_array( $other, $used ) ) {
                    continue;
                }

                $similarity = $this->calculate_similarity( $keyword, $other );
                if ( $similarity > 0.5 ) {
                    $group[] = $other;
                    $used[] = $other;
                }
            }

            if ( count( $group ) > 0 ) {
                $groups[] = array(
                    'name' => $keyword,
                    'keywords' => $group,
                    'count' => count( $group ),
                );
            }

            if ( count( $groups ) >= $options['max_groups'] ) {
                break;
            }
        }

        return $groups;
    }

    /**
     * 基于主题分组关键词
     *
     * @param array $keywords 关键词列表
     * @param array $options 选项参数
     *
     * @return array 分组结果
     */
    private function group_by_topic( $keywords, $options ) {
        // 预设主题类别
        $topics = array(
            '生活' => array( '生活', '日常', '分享', '记录', 'life', 'daily' ),
            '美妆' => array( '美妆', '化妆', '护肤', '彩妆', 'makeup', 'beauty' ),
            '时尚' => array( '时尚', '穿搭', '搭配', '服饰', 'fashion', 'style' ),
            '美食' => array( '美食', '食谱', '烹饪', '餐厅', 'food', 'recipe' ),
            '旅游' => array( '旅游', '旅行', '景点', '攻略', 'travel', 'trip' ),
            '健身' => array( '健身', '运动', '瑜伽', '锻炼', 'fitness', 'workout' ),
        );

        $groups = array();

        foreach ( $topics as $topic => $topic_keywords ) {
            $matched = array();
            foreach ( $keywords as $keyword ) {
                foreach ( $topic_keywords as $topic_word ) {
                    if ( stripos( $keyword, $topic_word ) !== false ) {
                        $matched[] = $keyword;
                        break;
                    }
                }
            }

            if ( count( $matched ) > 0 ) {
                $groups[] = array(
                    'name' => $topic,
                    'keywords' => $matched,
                    'count' => count( $matched ),
                );
            }
        }

        return $groups;
    }

    /**
     * 计算两个关键词的相似度
     *
     * @param string $keyword1 关键词1
     * @param string $keyword2 关键词2
     *
     * @return float 相似度 0-1
     */
    private function calculate_similarity( $keyword1, $keyword2 ) {
        // 简单实现: 使用Levenshtein距离
        $len1 = mb_strlen( $keyword1 );
        $len2 = mb_strlen( $keyword2 );
        $max_len = max( $len1, $len2 );

        if ( $max_len == 0 ) {
            return 1.0;
        }

        $distance = levenshtein( $keyword1, $keyword2 );
        return 1 - ( $distance / $max_len );
    }

    /**
     * 计算关键词之间的相关度
     *
     * @param string $keyword1 关键词1
     * @param string $keyword2 关键词2
     * @param array $options 选项参数
     *
     * @return float 相关度 0-100
     */
    private function calculate_relevance( $keyword1, $keyword2, $options ) {
        $score = 0;

        // 1. 字符串相似度 40%
        $similarity = $this->calculate_similarity( $keyword1, $keyword2 );
        $score += $similarity * 40;

        // 2. 共现频率 60%
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $like1 = '%' . $wpdb->esc_like( $keyword1 ) . '%';
        $like2 = '%' . $wpdb->esc_like( $keyword2 ) . '%';

        // 包含keyword1的内容数
        $count1 = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_posts WHERE title LIKE %s OR content LIKE %s",
                $like1, $like1
            )
        );

        // 同时包含两个关键词的内容数
        $cooccur = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_posts
                WHERE (title LIKE %s OR content LIKE %s)
                    AND (title LIKE %s OR content LIKE %s)",
                $like1, $like1, $like2, $like2
            )
        );

        if ( $count1 > 0 ) {
            $cooccur_rate = $cooccur / $count1;
            $score += $cooccur_rate * 60;
        }

        return round( $score, 2 );
    }

    /**
     * 查找峰值时期
     *
     * @param array $timeline 时间线数据
     *
     * @return string 峰值时期
     */
    private function find_peak_period( $timeline ) {
        if ( empty( $timeline ) ) {
            return '';
        }

        $max_count = 0;
        $peak_period = '';

        foreach ( $timeline as $data ) {
            if ( $data['count'] > $max_count ) {
                $max_count = $data['count'];
                $peak_period = $data['period'];
            }
        }

        return $peak_period;
    }

    /**
     * 计算趋势方向
     *
     * @param array $timeline 时间线数据
     *
     * @return string 趋势方向 (rising/declining/stable)
     */
    private function calculate_trend_direction( $timeline ) {
        if ( count( $timeline ) < 2 ) {
            return 'stable';
        }

        // 简单实现: 比较前半段和后半段
        $mid = floor( count( $timeline ) / 2 );
        $first_half = array_slice( $timeline, 0, $mid );
        $second_half = array_slice( $timeline, $mid );

        $first_avg = array_sum( wp_list_pluck( $first_half, 'count' ) ) / count( $first_half );
        $second_avg = array_sum( wp_list_pluck( $second_half, 'count' ) ) / count( $second_half );

        $change_rate = ( $second_avg - $first_avg ) / max( $first_avg, 1 ) * 100;

        if ( $change_rate > 20 ) {
            return 'rising';
        } elseif ( $change_rate < -20 ) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * 统计词数
     *
     * @param string $text 文本
     *
     * @return int 词数
     */
    private function count_words( $text ) {
        // 检测中文
        if ( preg_match( '/[\x{4e00}-\x{9fa5}]/u', $text ) ) {
            // 中文按字符数
            return mb_strlen( preg_replace( '/\s+/', '', $text ) );
        } else {
            // 英文按单词数
            return count( preg_split( '/\s+/', trim( $text ) ) );
        }
    }

    /**
     * 获取停用词
     *
     * @param string $language 语言
     *
     * @return array 停用词数组
     */
    private function get_stopwords( $language ) {
        if ( $language === 'zh-CN' || $language === 'zh-TW' ) {
            return array( '的', '了', '是', '在', '我', '有', '和', '就', '不', '人', '都', '一', '一个', '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看' );
        } else {
            return array( 'the', 'is', 'at', 'which', 'on', 'a', 'an', 'as', 'are', 'was', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should' );
        }
    }

    /**
     * 猜测关键词分类
     *
     * @param string $keyword 关键词
     *
     * @return string 分类
     */
    private function guess_category( $keyword ) {
        $categories = array(
            '美妆' => array( '美妆', '化妆', '护肤', '彩妆', 'makeup', 'beauty', 'skincare' ),
            '时尚' => array( '时尚', '穿搭', '搭配', '服饰', 'fashion', 'style', 'outfit' ),
            '美食' => array( '美食', '食谱', '烹饪', '餐厅', 'food', 'recipe', 'cooking' ),
            '旅游' => array( '旅游', '旅行', '景点', '攻略', 'travel', 'trip', 'destination' ),
            '健身' => array( '健身', '运动', '瑜伽', '锻炼', 'fitness', 'workout', 'yoga' ),
            '生活' => array( '生活', '日常', '分享', '记录', 'life', 'daily', 'lifestyle' ),
        );

        foreach ( $categories as $category => $keywords_list ) {
            foreach ( $keywords_list as $cat_keyword ) {
                if ( stripos( $keyword, $cat_keyword ) !== false ) {
                    return $category;
                }
            }
        }

        return '其他';
    }
}
