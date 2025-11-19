<?php
/**
 * Content Scorer Class
 *
 * 评估生成内容的质量并给出评分
 *
 * @package AI_Social_Content_Generator
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Content_Scorer Class
 */
class AISCG_Content_Scorer {

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
     * 构造函数
     */
    public function __construct() {
        $this->logger = new AISCG_Logger();
        $this->db = new AISCG_Database();
    }

    /**
     * 评估内容质量
     *
     * @param int|array $post 内容ID或内容数组
     * @return array 评分结果
     */
    public function score_content( $post ) {
        if ( is_numeric( $post ) ) {
            $post = $this->db->get_post( $post );
        }

        if ( ! $post ) {
            return false;
        }

        $scores = array(
            'overall' => 0,
            'readability' => $this->score_readability( $post ),
            'length' => $this->score_length( $post ),
            'hashtags' => $this->score_hashtags( $post ),
            'engagement' => $this->score_engagement_potential( $post ),
            'keywords' => $this->score_keywords( $post ),
            'structure' => $this->score_structure( $post ),
            'details' => array(),
            'suggestions' => array(),
        );

        // 计算总分
        $scores['overall'] = round(
            ( $scores['readability'] * 0.20 +
              $scores['length'] * 0.15 +
              $scores['hashtags'] * 0.20 +
              $scores['engagement'] * 0.25 +
              $scores['keywords'] * 0.10 +
              $scores['structure'] * 0.10 ),
            1
        );

        // 生成建议
        $scores['suggestions'] = $this->generate_suggestions( $scores, $post );

        // 评级
        $scores['grade'] = $this->get_grade( $scores['overall'] );

        $this->logger->info( "内容质量评分完成 #{$post['id']}", array(
            'overall' => $scores['overall'],
            'grade' => $scores['grade'],
        ) );

        return $scores;
    }

    /**
     * 评估可读性
     *
     * @param array $post 内容数据
     * @return float 可读性评分 (0-100)
     */
    private function score_readability( $post ) {
        $content = $post['content'];
        $score = 100;

        // 计算句子数量
        $sentences = preg_split( '/[。！？.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
        $sentence_count = count( $sentences );

        if ( $sentence_count === 0 ) {
            return 0;
        }

        // 计算平均句子长度
        $total_chars = mb_strlen( str_replace( array( ' ', "\n" ), '', $content ) );
        $avg_sentence_length = $total_chars / $sentence_count;

        // 理想句子长度: 15-25字
        if ( $avg_sentence_length < 10 ) {
            $score -= 20; // 句子太短
        } elseif ( $avg_sentence_length > 40 ) {
            $score -= 30; // 句子太长
        }

        // 检查段落结构
        $paragraphs = explode( "\n", trim( $content ) );
        $paragraph_count = count( array_filter( $paragraphs ) );

        if ( $paragraph_count < 2 && mb_strlen( $content ) > 200 ) {
            $score -= 15; // 长内容缺少分段
        }

        // 检查emoji使用（小红书风格）
        if ( $post['platform'] === 'xiaohongshu' ) {
            $emoji_count = preg_match_all( '/[\x{1F300}-\x{1F9FF}]/u', $content );
            if ( $emoji_count < 3 ) {
                $score -= 10; // 小红书内容emoji太少
            } elseif ( $emoji_count > 20 ) {
                $score -= 15; // emoji过多
            }
        }

        return max( 0, min( 100, $score ) );
    }

    /**
     * 评估内容长度
     *
     * @param array $post 内容数据
     * @return float 长度评分 (0-100)
     */
    private function score_length( $post ) {
        $content = $post['content'];
        $length = mb_strlen( $content );
        $platform = $post['platform'];

        $ideal_ranges = array(
            'xiaohongshu' => array( 'min' => 200, 'ideal_min' => 300, 'ideal_max' => 600, 'max' => 1000 ),
            'instagram' => array( 'min' => 80, 'ideal_min' => 125, 'ideal_max' => 250, 'max' => 500 ),
        );

        $range = isset( $ideal_ranges[ $platform ] ) ? $ideal_ranges[ $platform ] : $ideal_ranges['xiaohongshu'];

        if ( $length < $range['min'] ) {
            return max( 0, 50 - ( $range['min'] - $length ) / 2 );
        } elseif ( $length >= $range['ideal_min'] && $length <= $range['ideal_max'] ) {
            return 100;
        } elseif ( $length < $range['ideal_min'] ) {
            return 70 + ( $length - $range['min'] ) / ( $range['ideal_min'] - $range['min'] ) * 30;
        } elseif ( $length <= $range['max'] ) {
            return 100 - ( $length - $range['ideal_max'] ) / ( $range['max'] - $range['ideal_max'] ) * 30;
        } else {
            return max( 0, 70 - ( $length - $range['max'] ) / 10 );
        }
    }

    /**
     * 评估标签质量
     *
     * @param array $post 内容数据
     * @return float 标签评分 (0-100)
     */
    private function score_hashtags( $post ) {
        $hashtags_raw = $post['hashtags'];

        if ( empty( $hashtags_raw ) ) {
            return 0;
        }

        // 解析hashtags
        if ( is_string( $hashtags_raw ) ) {
            $hashtags = json_decode( $hashtags_raw, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $hashtags = explode( ',', $hashtags_raw );
            }
        } else {
            $hashtags = $hashtags_raw;
        }

        if ( ! is_array( $hashtags ) ) {
            return 0;
        }

        $hashtags = array_filter( $hashtags );
        $count = count( $hashtags );

        $score = 100;
        $platform = $post['platform'];

        // 检查数量
        if ( $platform === 'xiaohongshu' ) {
            if ( $count < 3 ) {
                $score -= 30;
            } elseif ( $count > 15 ) {
                $score -= 20;
            } elseif ( $count >= 5 && $count <= 10 ) {
                $score += 0; // 理想范围
            }
        } elseif ( $platform === 'instagram' ) {
            if ( $count < 5 ) {
                $score -= 30;
            } elseif ( $count > 30 ) {
                $score -= 20;
            } elseif ( $count >= 10 && $count <= 20 ) {
                $score += 0; // 理想范围
            }
        }

        // 检查标签质量
        foreach ( $hashtags as $tag ) {
            $tag = trim( $tag, '# ' );
            $tag_length = mb_strlen( $tag );

            // 标签太短或太长
            if ( $tag_length < 2 ) {
                $score -= 5;
            } elseif ( $tag_length > 30 ) {
                $score -= 3;
            }

            // 检查是否包含空格（不良标签）
            if ( strpos( $tag, ' ' ) !== false ) {
                $score -= 5;
            }
        }

        return max( 0, min( 100, $score ) );
    }

    /**
     * 评估互动潜力
     *
     * @param array $post 内容数据
     * @return float 互动潜力评分 (0-100)
     */
    private function score_engagement_potential( $post ) {
        $content = $post['content'];
        $title = $post['title'] ?? '';
        $score = 50; // 基础分

        // 检查标题吸引力
        if ( ! empty( $title ) ) {
            // 数字的使用（如"5个方法"）
            if ( preg_match( '/\d+/', $title ) ) {
                $score += 10;
            }

            // 疑问句（增加互动）
            if ( preg_match( '/[？?]/', $title ) ) {
                $score += 10;
            }

            // 感叹号（增加情感）
            if ( preg_match( '/[！!]/', $title ) ) {
                $score += 5;
            }

            // 标题长度适中
            $title_length = mb_strlen( $title );
            if ( $title_length >= 10 && $title_length <= 30 ) {
                $score += 10;
            }
        }

        // 检查行动号召（CTA）
        $cta_patterns = array(
            '点赞', '收藏', '关注', '评论', '分享', '转发',
            'like', 'share', 'follow', 'comment', 'save',
        );

        foreach ( $cta_patterns as $pattern ) {
            if ( stripos( $content, $pattern ) !== false ) {
                $score += 5;
                break;
            }
        }

        // 检查疑问句（鼓励评论）
        $question_count = substr_count( $content, '?' ) + substr_count( $content, '？' );
        if ( $question_count > 0 ) {
            $score += min( 10, $question_count * 3 );
        }

        // 检查情感词汇
        $emotion_words = array(
            '太', '超', '很', '特别', '非常', '真的', '简直',
            'amazing', 'awesome', 'great', 'perfect', 'love',
        );

        $emotion_count = 0;
        foreach ( $emotion_words as $word ) {
            $emotion_count += substr_count( mb_strtolower( $content ), mb_strtolower( $word ) );
        }

        if ( $emotion_count > 0 ) {
            $score += min( 15, $emotion_count * 2 );
        }

        return min( 100, $score );
    }

    /**
     * 评估关键词使用
     *
     * @param array $post 内容数据
     * @return float 关键词评分 (0-100)
     */
    private function score_keywords( $post ) {
        $content = $post['content'];
        $score = 70; // 基础分

        // 提取常见词汇
        $words = $this->extract_words( $content );
        $word_count = count( $words );

        if ( $word_count === 0 ) {
            return 0;
        }

        // 计算词频
        $word_freq = array_count_values( $words );
        arsort( $word_freq );

        // 获取前5个高频词
        $top_words = array_slice( $word_freq, 0, 5, true );

        // 检查关键词重复度
        $max_freq = reset( $top_words );
        $repetition_rate = $max_freq / $word_count;

        if ( $repetition_rate > 0.15 ) {
            $score -= 20; // 关键词过度重复
        } elseif ( $repetition_rate > 0.10 ) {
            $score -= 10;
        } elseif ( $repetition_rate >= 0.03 && $repetition_rate <= 0.08 ) {
            $score += 15; // 理想的关键词密度
        }

        // 检查词汇多样性
        $unique_words = count( array_unique( $words ) );
        $diversity = $unique_words / $word_count;

        if ( $diversity > 0.7 ) {
            $score += 15; // 词汇丰富
        } elseif ( $diversity < 0.4 ) {
            $score -= 10; // 词汇单一
        }

        return max( 0, min( 100, $score ) );
    }

    /**
     * 评估内容结构
     *
     * @param array $post 内容数据
     * @return float 结构评分 (0-100)
     */
    private function score_structure( $post ) {
        $content = $post['content'];
        $score = 100;

        // 检查是否有标题
        if ( empty( $post['title'] ) ) {
            $score -= 20;
        }

        // 检查段落数量
        $paragraphs = explode( "\n", trim( $content ) );
        $paragraph_count = count( array_filter( $paragraphs, function( $p ) {
            return trim( $p ) !== '';
        } ) );

        if ( $paragraph_count < 2 ) {
            $score -= 15; // 缺少分段
        } elseif ( $paragraph_count >= 3 && $paragraph_count <= 6 ) {
            $score += 0; // 理想段落数
        } elseif ( $paragraph_count > 10 ) {
            $score -= 10; // 段落过多可能显得零碎
        }

        // 检查列表或要点
        $has_bullets = preg_match( '/^[•·\-\*\d+\.]/m', $content );
        if ( $has_bullets ) {
            $score += 10; // 使用列表使内容更清晰
        }

        // 检查小红书特有结构元素
        if ( $post['platform'] === 'xiaohongshu' ) {
            // 检查是否有步骤/tips等结构化元素
            $structured_patterns = array( '步骤', '方法', 'tips', '攻略', '要点', '注意' );
            foreach ( $structured_patterns as $pattern ) {
                if ( stripos( $content, $pattern ) !== false ) {
                    $score += 5;
                    break;
                }
            }
        }

        return max( 0, min( 100, $score ) );
    }

    /**
     * 生成改进建议
     *
     * @param array $scores 评分数据
     * @param array $post 内容数据
     * @return array 建议列表
     */
    private function generate_suggestions( $scores, $post ) {
        $suggestions = array();

        // 可读性建议
        if ( $scores['readability'] < 70 ) {
            if ( mb_strlen( $post['content'] ) > 500 ) {
                $suggestions[] = array(
                    'type' => 'readability',
                    'severity' => 'medium',
                    'message' => '内容较长，建议增加分段以提高可读性',
                );
            }

            if ( $post['platform'] === 'xiaohongshu' ) {
                $emoji_count = preg_match_all( '/[\x{1F300}-\x{1F9FF}]/u', $post['content'] );
                if ( $emoji_count < 3 ) {
                    $suggestions[] = array(
                        'type' => 'readability',
                        'severity' => 'low',
                        'message' => '小红书内容建议添加更多emoji表情以增加亲和力',
                    );
                }
            }
        }

        // 长度建议
        if ( $scores['length'] < 70 ) {
            $length = mb_strlen( $post['content'] );
            if ( $post['platform'] === 'xiaohongshu' && $length < 300 ) {
                $suggestions[] = array(
                    'type' => 'length',
                    'severity' => 'high',
                    'message' => sprintf( '内容过短(%d字)，建议扩展到300-600字', $length ),
                );
            } elseif ( $post['platform'] === 'instagram' && $length < 125 ) {
                $suggestions[] = array(
                    'type' => 'length',
                    'severity' => 'high',
                    'message' => sprintf( '内容过短(%d字)，建议扩展到125-250字', $length ),
                );
            }
        }

        // 标签建议
        if ( $scores['hashtags'] < 70 ) {
            $hashtags_raw = $post['hashtags'];
            $hashtags = is_string( $hashtags_raw ) ? json_decode( $hashtags_raw, true ) : $hashtags_raw;
            $count = is_array( $hashtags ) ? count( array_filter( $hashtags ) ) : 0;

            if ( $post['platform'] === 'xiaohongshu' && $count < 5 ) {
                $suggestions[] = array(
                    'type' => 'hashtags',
                    'severity' => 'medium',
                    'message' => sprintf( '标签数量偏少(%d个)，建议添加到5-10个', $count ),
                );
            } elseif ( $post['platform'] === 'instagram' && $count < 10 ) {
                $suggestions[] = array(
                    'type' => 'hashtags',
                    'severity' => 'medium',
                    'message' => sprintf( '标签数量偏少(%d个)，建议添加到10-20个', $count ),
                );
            }
        }

        // 互动建议
        if ( $scores['engagement'] < 60 ) {
            if ( ! preg_match( '/[？?]/', $post['content'] ) ) {
                $suggestions[] = array(
                    'type' => 'engagement',
                    'severity' => 'medium',
                    'message' => '建议添加疑问句以鼓励用户评论互动',
                );
            }

            $cta_patterns = array( '点赞', '收藏', '关注', 'like', 'follow' );
            $has_cta = false;
            foreach ( $cta_patterns as $pattern ) {
                if ( stripos( $post['content'], $pattern ) !== false ) {
                    $has_cta = true;
                    break;
                }
            }

            if ( ! $has_cta ) {
                $suggestions[] = array(
                    'type' => 'engagement',
                    'severity' => 'low',
                    'message' => '建议添加行动号召（CTA），如"点赞收藏"等',
                );
            }
        }

        // 结构建议
        if ( $scores['structure'] < 70 ) {
            $paragraphs = explode( "\n", trim( $post['content'] ) );
            $paragraph_count = count( array_filter( $paragraphs ) );

            if ( $paragraph_count < 3 && mb_strlen( $post['content'] ) > 200 ) {
                $suggestions[] = array(
                    'type' => 'structure',
                    'severity' => 'medium',
                    'message' => '建议将内容分成3-5个段落，使结构更清晰',
                );
            }
        }

        return $suggestions;
    }

    /**
     * 获取评级
     *
     * @param float $score 总分
     * @return string 评级
     */
    private function get_grade( $score ) {
        if ( $score >= 90 ) {
            return 'A+';
        } elseif ( $score >= 85 ) {
            return 'A';
        } elseif ( $score >= 80 ) {
            return 'A-';
        } elseif ( $score >= 75 ) {
            return 'B+';
        } elseif ( $score >= 70 ) {
            return 'B';
        } elseif ( $score >= 65 ) {
            return 'B-';
        } elseif ( $score >= 60 ) {
            return 'C+';
        } elseif ( $score >= 55 ) {
            return 'C';
        } elseif ( $score >= 50 ) {
            return 'C-';
        } else {
            return 'D';
        }
    }

    /**
     * 提取词汇
     *
     * @param string $text 文本
     * @return array 词汇数组
     */
    private function extract_words( $text ) {
        // 移除标点符号和特殊字符
        $text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

        // 分割成词
        $words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

        // 过滤短词和常见停用词
        $stopwords = array( '的', '了', '是', '在', '我', '有', '和', '就', '不', '人', '都', '一', '你', '他',
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by' );

        $words = array_filter( $words, function( $word ) use ( $stopwords ) {
            return mb_strlen( $word ) >= 2 && ! in_array( mb_strtolower( $word ), $stopwords, true );
        } );

        return array_values( $words );
    }

    /**
     * 批量评分
     *
     * @param array $post_ids 内容ID数组
     * @return array 评分结果数组
     */
    public function batch_score( $post_ids ) {
        $results = array();

        foreach ( $post_ids as $post_id ) {
            $score = $this->score_content( $post_id );
            if ( $score ) {
                $results[ $post_id ] = $score;
            }
        }

        return $results;
    }

    /**
     * 获取评分统计
     *
     * @param array $args 查询参数
     * @return array 统计数据
     */
    public function get_score_statistics( $args = array() ) {
        $defaults = array(
            'platform' => '',
            'start_date' => date( 'Y-m-d', strtotime( '-30 days' ) ),
            'end_date' => date( 'Y-m-d' ),
        );

        $args = wp_parse_args( $args, $defaults );

        // 获取内容列表
        $posts = $this->db->get_posts( array(
            'platform' => $args['platform'],
            'limit' => 1000,
        ) );

        if ( empty( $posts ) ) {
            return array(
                'average_overall' => 0,
                'average_by_category' => array(),
                'grade_distribution' => array(),
                'total_scored' => 0,
            );
        }

        $scores = array();
        $grade_count = array();

        foreach ( $posts as $post ) {
            $score = $this->score_content( $post );
            if ( $score ) {
                $scores[] = $score;

                $grade = $score['grade'];
                if ( ! isset( $grade_count[ $grade ] ) ) {
                    $grade_count[ $grade ] = 0;
                }
                $grade_count[ $grade ]++;
            }
        }

        $total = count( $scores );

        if ( $total === 0 ) {
            return array(
                'average_overall' => 0,
                'average_by_category' => array(),
                'grade_distribution' => array(),
                'total_scored' => 0,
            );
        }

        // 计算平均分
        $sum_overall = array_sum( array_column( $scores, 'overall' ) );
        $sum_readability = array_sum( array_column( $scores, 'readability' ) );
        $sum_length = array_sum( array_column( $scores, 'length' ) );
        $sum_hashtags = array_sum( array_column( $scores, 'hashtags' ) );
        $sum_engagement = array_sum( array_column( $scores, 'engagement' ) );

        return array(
            'average_overall' => round( $sum_overall / $total, 1 ),
            'average_by_category' => array(
                'readability' => round( $sum_readability / $total, 1 ),
                'length' => round( $sum_length / $total, 1 ),
                'hashtags' => round( $sum_hashtags / $total, 1 ),
                'engagement' => round( $sum_engagement / $total, 1 ),
            ),
            'grade_distribution' => $grade_count,
            'total_scored' => $total,
        );
    }
}
