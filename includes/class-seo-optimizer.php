<?php
/**
 * SEO Optimizer Class
 *
 * SEO优化工具
 *
 * @package AI_Social_Content_Generator
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_SEO_Optimizer Class
 */
class AISCG_SEO_Optimizer {

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
     * SEO分析
     *
     * @param int|array $post 内容ID或内容数组
     * @return array SEO分析结果
     */
    public function analyze_seo( $post ) {
        if ( is_numeric( $post ) ) {
            $post = $this->db->get_post( $post );
        }

        if ( ! $post ) {
            return false;
        }

        $analysis = array(
            'overall_score' => 0,
            'title_seo' => $this->analyze_title( $post ),
            'content_seo' => $this->analyze_content( $post ),
            'keywords_seo' => $this->analyze_keywords( $post ),
            'hashtags_seo' => $this->analyze_hashtags( $post ),
            'readability' => $this->analyze_readability( $post ),
            'suggestions' => array(),
        );

        // 计算总分
        $analysis['overall_score'] = round(
            ( $analysis['title_seo']['score'] * 0.25 +
              $analysis['content_seo']['score'] * 0.30 +
              $analysis['keywords_seo']['score'] * 0.25 +
              $analysis['hashtags_seo']['score'] * 0.10 +
              $analysis['readability']['score'] * 0.10 ),
            1
        );

        // 生成建议
        $analysis['suggestions'] = $this->generate_seo_suggestions( $analysis );

        $this->logger->info( "SEO分析完成 #{$post['id']}", array(
            'overall_score' => $analysis['overall_score'],
        ) );

        return $analysis;
    }

    /**
     * 分析标题
     *
     * @param array $post 内容数据
     * @return array 标题分析
     */
    private function analyze_title( $post ) {
        $title = $post['title'] ?? '';
        $score = 100;
        $issues = array();

        $title_length = mb_strlen( $title );

        // 标题长度检查
        if ( $title_length < 10 ) {
            $score -= 30;
            $issues[] = '标题过短，建议至少10个字符';
        } elseif ( $title_length > 60 ) {
            $score -= 20;
            $issues[] = '标题过长，可能在搜索结果中被截断';
        }

        // 关键词前置检查
        $content_keywords = $this->extract_top_keywords( $post['content'], 3 );
        $title_has_keyword = false;

        foreach ( $content_keywords as $keyword ) {
            if ( stripos( $title, $keyword ) !== false ) {
                $title_has_keyword = true;
                break;
            }
        }

        if ( ! $title_has_keyword && ! empty( $content_keywords ) ) {
            $score -= 20;
            $issues[] = '标题中应包含主要关键词';
        }

        // 数字的使用（提升点击率）
        if ( ! preg_match( '/\d/', $title ) ) {
            $score -= 10;
            $issues[] = '标题中包含数字可以提升点击率';
        }

        // 特殊字符检查
        if ( preg_match( '/[！？]/', $title ) ) {
            $score += 5; // 情感化标题
        }

        return array(
            'score' => max( 0, min( 100, $score ) ),
            'length' => $title_length,
            'has_numbers' => preg_match( '/\d/', $title ) ? true : false,
            'has_keywords' => $title_has_keyword,
            'issues' => $issues,
        );
    }

    /**
     * 分析内容
     *
     * @param array $post 内容数据
     * @return array 内容分析
     */
    private function analyze_content( $post ) {
        $content = $post['content'] ?? '';
        $score = 100;
        $issues = array();

        $content_length = mb_strlen( $content );

        // 内容长度
        $platform = $post['platform'];
        $min_length = $platform === 'xiaohongshu' ? 300 : 150;
        $max_length = $platform === 'xiaohongshu' ? 1000 : 500;

        if ( $content_length < $min_length ) {
            $score -= 30;
            $issues[] = "内容过短，建议至少{$min_length}字";
        } elseif ( $content_length > $max_length ) {
            $score -= 15;
            $issues[] = "内容过长，可能影响用户阅读体验";
        }

        // 段落结构
        $paragraphs = explode( "\n", trim( $content ) );
        $paragraph_count = count( array_filter( $paragraphs ) );

        if ( $paragraph_count < 3 && $content_length > 200 ) {
            $score -= 15;
            $issues[] = '建议将内容分成3-5个段落';
        }

        // 关键词密度
        $keywords = $this->extract_top_keywords( $content, 5 );
        $keyword_density = $this->calculate_keyword_density( $content, $keywords );

        if ( $keyword_density < 1 ) {
            $score -= 10;
            $issues[] = '关键词密度较低';
        } elseif ( $keyword_density > 5 ) {
            $score -= 15;
            $issues[] = '关键词密度过高，可能被判定为关键词堆砌';
        }

        // 外部链接（如果有）
        $has_links = preg_match( '/https?:\/\//', $content );

        return array(
            'score' => max( 0, min( 100, $score ) ),
            'length' => $content_length,
            'paragraph_count' => $paragraph_count,
            'keyword_density' => round( $keyword_density, 2 ),
            'has_links' => $has_links,
            'issues' => $issues,
        );
    }

    /**
     * 分析关键词
     *
     * @param array $post 内容数据
     * @return array 关键词分析
     */
    private function analyze_keywords( $post ) {
        $content = ( $post['title'] ?? '' ) . ' ' . ( $post['content'] ?? '' );
        $score = 100;
        $issues = array();

        $keywords = $this->extract_top_keywords( $content, 10 );

        // 关键词数量
        $keyword_count = count( $keywords );

        if ( $keyword_count < 3 ) {
            $score -= 30;
            $issues[] = '关键词数量不足';
        } elseif ( $keyword_count > 15 ) {
            $score -= 20;
            $issues[] = '关键词过多，建议聚焦核心词汇';
        }

        // LSI关键词（语义相关词）
        $lsi_keywords = $this->find_lsi_keywords( $keywords );

        // 长尾关键词
        $long_tail_keywords = array_filter( $keywords, function( $keyword ) {
            return mb_strlen( $keyword ) >= 4;
        } );

        $long_tail_ratio = count( $long_tail_keywords ) / max( $keyword_count, 1 );

        if ( $long_tail_ratio < 0.3 ) {
            $score -= 10;
            $issues[] = '建议增加长尾关键词以提高精准流量';
        }

        return array(
            'score' => max( 0, min( 100, $score ) ),
            'primary_keywords' => array_slice( $keywords, 0, 5 ),
            'lsi_keywords' => $lsi_keywords,
            'long_tail_keywords' => $long_tail_keywords,
            'keyword_count' => $keyword_count,
            'issues' => $issues,
        );
    }

    /**
     * 分析标签
     *
     * @param array $post 内容数据
     * @return array 标签分析
     */
    private function analyze_hashtags( $post ) {
        $hashtags_raw = $post['hashtags'] ?? '';
        $score = 100;
        $issues = array();

        $hashtags = is_string( $hashtags_raw ) ?
            json_decode( $hashtags_raw, true ) : $hashtags_raw;

        if ( ! is_array( $hashtags ) ) {
            $hashtags = array();
        }

        $hashtag_count = count( $hashtags );
        $platform = $post['platform'];

        // 数量检查
        if ( $platform === 'xiaohongshu' ) {
            if ( $hashtag_count < 5 ) {
                $score -= 30;
                $issues[] = '小红书建议使用5-10个标签';
            } elseif ( $hashtag_count > 15 ) {
                $score -= 15;
                $issues[] = '标签过多可能分散流量';
            }
        } else {
            if ( $hashtag_count < 10 ) {
                $score -= 30;
                $issues[] = 'Instagram建议使用10-20个标签';
            } elseif ( $hashtag_count > 30 ) {
                $score -= 15;
                $issues[] = '标签过多可能被视为垃圾信息';
            }
        }

        // 标签相关性
        $content_keywords = $this->extract_top_keywords( $post['content'], 10 );
        $relevant_count = 0;

        foreach ( $hashtags as $tag ) {
            $tag_clean = trim( $tag, '# ' );
            foreach ( $content_keywords as $keyword ) {
                if ( stripos( $tag_clean, $keyword ) !== false || stripos( $keyword, $tag_clean ) !== false ) {
                    $relevant_count++;
                    break;
                }
            }
        }

        $relevance_ratio = $hashtag_count > 0 ? ( $relevant_count / $hashtag_count ) : 0;

        if ( $relevance_ratio < 0.3 ) {
            $score -= 20;
            $issues[] = '部分标签与内容相关性较低';
        }

        return array(
            'score' => max( 0, min( 100, $score ) ),
            'count' => $hashtag_count,
            'relevant_count' => $relevant_count,
            'relevance_ratio' => round( $relevance_ratio * 100, 1 ),
            'issues' => $issues,
        );
    }

    /**
     * 分析可读性
     *
     * @param array $post 内容数据
     * @return array 可读性分析
     */
    private function analyze_readability( $post ) {
        $content = $post['content'] ?? '';
        $score = 100;
        $issues = array();

        // 句子数量
        $sentences = preg_split( '/[。！？.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
        $sentence_count = count( $sentences );

        if ( $sentence_count > 0 ) {
            // 平均句子长度
            $total_chars = mb_strlen( str_replace( array( ' ', "\n" ), '', $content ) );
            $avg_sentence_length = $total_chars / $sentence_count;

            if ( $avg_sentence_length > 40 ) {
                $score -= 20;
                $issues[] = '句子平均长度过长，建议简化表达';
            } elseif ( $avg_sentence_length < 8 ) {
                $score -= 10;
                $issues[] = '句子过短，建议适当延展';
            }
        }

        // 复杂词汇检查（简化处理）
        $complex_chars = preg_match_all( '/[\x{4e00}-\x{9fa5}]{4,}/u', $content );
        $total_chars = mb_strlen( str_replace( ' ', '', $content ) );
        $complex_ratio = $total_chars > 0 ? ( $complex_chars / $total_chars * 100 ) : 0;

        if ( $complex_ratio > 30 ) {
            $score -= 10;
            $issues[] = '复杂词汇较多，可能影响阅读体验';
        }

        return array(
            'score' => max( 0, min( 100, $score ) ),
            'sentence_count' => $sentence_count,
            'avg_sentence_length' => isset( $avg_sentence_length ) ? round( $avg_sentence_length, 1 ) : 0,
            'readability_level' => $score >= 80 ? 'easy' : ( $score >= 60 ? 'moderate' : 'difficult' ),
            'issues' => $issues,
        );
    }

    /**
     * 提取高频关键词
     *
     * @param string $text 文本
     * @param int $limit 数量限制
     * @return array 关键词列表
     */
    private function extract_top_keywords( $text, $limit = 10 ) {
        // 移除特殊字符
        $text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

        // 分词
        $words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

        // 停用词
        $stopwords = array(
            '的', '了', '是', '在', '我', '有', '和', '就', '不', '人', '都', '一', '你', '他', '这', '那', '要', '会', '能',
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'it', 'be',
        );

        // 过滤
        $words = array_filter( $words, function( $word ) use ( $stopwords ) {
            $word = mb_strtolower( $word );
            return mb_strlen( $word ) >= 2 && ! in_array( $word, $stopwords, true );
        } );

        // 统计频率
        $freq = array_count_values( $words );
        arsort( $freq );

        return array_keys( array_slice( $freq, 0, $limit, true ) );
    }

    /**
     * 计算关键词密度
     *
     * @param string $content 内容
     * @param array $keywords 关键词列表
     * @return float 密度百分比
     */
    private function calculate_keyword_density( $content, $keywords ) {
        if ( empty( $keywords ) ) {
            return 0;
        }

        $total_words = str_word_count( $content );
        if ( $total_words == 0 ) {
            return 0;
        }

        $keyword_count = 0;
        foreach ( $keywords as $keyword ) {
            $keyword_count += substr_count( mb_strtolower( $content ), mb_strtolower( $keyword ) );
        }

        return ( $keyword_count / $total_words ) * 100;
    }

    /**
     * 查找LSI关键词（语义相关词）
     *
     * @param array $keywords 主关键词
     * @return array LSI关键词
     */
    private function find_lsi_keywords( $keywords ) {
        // 简化版LSI关键词查找
        // 实际应用中可以使用更复杂的语义分析或API

        $lsi_map = array(
            '美妆' => array( '护肤', '化妆', '彩妆', '美容', '保养' ),
            '美食' => array( '料理', '烹饪', '餐厅', '菜谱', '食材' ),
            '旅行' => array( '旅游', '景点', '攻略', '酒店', '行程' ),
            'fitness' => array( 'workout', 'exercise', 'gym', 'health', 'training' ),
            'fashion' => array( 'style', 'outfit', 'clothing', 'trend', 'wear' ),
        );

        $lsi_keywords = array();

        foreach ( $keywords as $keyword ) {
            $keyword_lower = mb_strtolower( $keyword );

            foreach ( $lsi_map as $main_keyword => $related ) {
                if ( stripos( $keyword_lower, $main_keyword ) !== false ) {
                    $lsi_keywords = array_merge( $lsi_keywords, $related );
                }
            }
        }

        return array_unique( $lsi_keywords );
    }

    /**
     * 生成SEO建议
     *
     * @param array $analysis SEO分析结果
     * @return array 建议列表
     */
    private function generate_seo_suggestions( $analysis ) {
        $suggestions = array();

        // 汇总所有问题
        $all_issues = array();

        if ( ! empty( $analysis['title_seo']['issues'] ) ) {
            foreach ( $analysis['title_seo']['issues'] as $issue ) {
                $all_issues[] = array(
                    'category' => '标题优化',
                    'priority' => 'high',
                    'message' => $issue,
                );
            }
        }

        if ( ! empty( $analysis['content_seo']['issues'] ) ) {
            foreach ( $analysis['content_seo']['issues'] as $issue ) {
                $all_issues[] = array(
                    'category' => '内容优化',
                    'priority' => 'medium',
                    'message' => $issue,
                );
            }
        }

        if ( ! empty( $analysis['keywords_seo']['issues'] ) ) {
            foreach ( $analysis['keywords_seo']['issues'] as $issue ) {
                $all_issues[] = array(
                    'category' => '关键词优化',
                    'priority' => 'high',
                    'message' => $issue,
                );
            }
        }

        if ( ! empty( $analysis['hashtags_seo']['issues'] ) ) {
            foreach ( $analysis['hashtags_seo']['issues'] as $issue ) {
                $all_issues[] = array(
                    'category' => '标签优化',
                    'priority' => 'medium',
                    'message' => $issue,
                );
            }
        }

        if ( ! empty( $analysis['readability']['issues'] ) ) {
            foreach ( $analysis['readability']['issues'] as $issue ) {
                $all_issues[] = array(
                    'category' => '可读性优化',
                    'priority' => 'low',
                    'message' => $issue,
                );
            }
        }

        // 添加积极建议
        if ( $analysis['overall_score'] >= 80 ) {
            $suggestions[] = array(
                'category' => '总体',
                'priority' => 'info',
                'message' => 'SEO表现优秀！继续保持这个水平',
            );
        }

        // 关键词建议
        if ( ! empty( $analysis['keywords_seo']['lsi_keywords'] ) ) {
            $lsi_keywords = array_slice( $analysis['keywords_seo']['lsi_keywords'], 0, 3 );
            $suggestions[] = array(
                'category' => '关键词优化',
                'priority' => 'medium',
                'message' => '建议添加相关词: ' . implode( '、', $lsi_keywords ),
            );
        }

        return array_merge( $suggestions, $all_issues );
    }

    /**
     * SEO优化（使用AI）
     *
     * @param int $post_id 内容ID
     * @param array $options 选项
     * @return array 优化结果
     */
    public function optimize_for_seo( $post_id, $options = array() ) {
        $post = $this->db->get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $defaults = array(
            'ai_model' => 'openai',
            'focus_keyword' => '',
        );

        $options = wp_parse_args( $options, $defaults );

        // SEO分析
        $seo_analysis = $this->analyze_seo( $post );

        // 构建优化prompt
        $prompt = $this->build_seo_optimization_prompt( $post, $seo_analysis, $options );

        try {
            $ai_service = AISCG_AI_Service_Factory::create( $options['ai_model'] );
            if ( ! $ai_service ) {
                throw new Exception( 'AI服务创建失败' );
            }

            $optimized_content = $ai_service->generate( $prompt );

            // 解析优化后的内容
            $parsed = $this->parse_optimized_content( $optimized_content );

            if ( ! $parsed ) {
                throw new Exception( '优化内容解析失败' );
            }

            // 更新内容
            $this->db->update_post( $post_id, array(
                'title' => $parsed['title'],
                'content' => $parsed['content'],
                'hashtags' => wp_json_encode( $parsed['hashtags'] ),
            ) );

            // 重新分析SEO
            $new_seo_analysis = $this->analyze_seo( $post_id );

            $this->logger->info( "SEO优化完成 #{$post_id}", array(
                'before_score' => $seo_analysis['overall_score'],
                'after_score' => $new_seo_analysis['overall_score'],
            ) );

            return array(
                'success' => true,
                'before' => $seo_analysis,
                'after' => $new_seo_analysis,
                'improvement' => $new_seo_analysis['overall_score'] - $seo_analysis['overall_score'],
            );

        } catch ( Exception $e ) {
            $this->logger->error( "SEO优化失败 #{$post_id}", array(
                'error' => $e->getMessage(),
            ) );

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * 构建SEO优化prompt
     *
     * @param array $post 内容数据
     * @param array $seo_analysis SEO分析
     * @param array $options 选项
     * @return string Prompt
     */
    private function build_seo_optimization_prompt( $post, $seo_analysis, $options ) {
        $prompt = "请对以下内容进行SEO优化:\n\n";
        $prompt .= "标题: {$post['title']}\n";
        $prompt .= "内容:\n{$post['content']}\n\n";

        $prompt .= "当前SEO得分: {$seo_analysis['overall_score']}/100\n\n";

        $prompt .= "优化要求:\n";

        if ( ! empty( $options['focus_keyword'] ) ) {
            $prompt .= "1. 重点优化关键词: {$options['focus_keyword']}\n";
            $prompt .= "2. 在标题和内容中自然地使用该关键词\n";
        }

        if ( ! empty( $seo_analysis['title_seo']['issues'] ) ) {
            $prompt .= "3. 标题问题: " . implode( '；', $seo_analysis['title_seo']['issues'] ) . "\n";
        }

        if ( ! empty( $seo_analysis['keywords_seo']['primary_keywords'] ) ) {
            $keywords = array_slice( $seo_analysis['keywords_seo']['primary_keywords'], 0, 3 );
            $prompt .= "4. 保持使用主要关键词: " . implode( '、', $keywords ) . "\n";
        }

        $prompt .= "5. 保持原有风格和主题\n";
        $prompt .= "6. 确保内容自然流畅，避免关键词堆砌\n\n";

        $prompt .= "请按以下JSON格式输出:\n";
        $prompt .= "{\n";
        $prompt .= "  \"title\": \"SEO优化后的标题\",\n";
        $prompt .= "  \"content\": \"SEO优化后的内容\",\n";
        $prompt .= "  \"hashtags\": [\"优化后的标签\"]\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * 解析优化后的内容
     *
     * @param string $ai_response AI响应
     * @return array|false 解析结果
     */
    private function parse_optimized_content( $ai_response ) {
        if ( preg_match( '/\{[\s\S]*"title"[\s\S]*"content"[\s\S]*"hashtags"[\s\S]*\}/', $ai_response, $matches ) ) {
            $json = $matches[0];
            $data = json_decode( $json, true );

            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $data;
            }
        }

        return false;
    }
}
