<?php
/**
 * 自动化内容优化引擎
 *
 * 自动检测和修复内容问题，优化内容质量
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 自动优化器类
 */
class AISCG_Auto_Optimizer {

    /**
     * AI服务工厂
     *
     * @var AISCG_AI_Service_Factory
     */
    private $ai_factory;

    /**
     * 合规检查器
     *
     * @var AISCG_Compliance_Checker
     */
    private $compliance_checker;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->ai_factory = new AISCG_AI_Service_Factory();
        $this->compliance_checker = new AISCG_Compliance_Checker();
    }

    /**
     * 自动优化内容
     *
     * @param int|array $content 内容ID或内容数据
     * @param array $options 优化选项
     *   - fix_compliance: 修复合规问题
     *   - enhance_quality: 提升内容质量
     *   - optimize_seo: SEO优化
     *   - improve_readability: 改善可读性
     *
     * @return array 优化结果
     */
    public function auto_optimize( $content, $options = array() ) {
        $defaults = array(
            'fix_compliance' => true,
            'enhance_quality' => true,
            'optimize_seo' => true,
            'improve_readability' => true,
        );
        $options = wp_parse_args( $options, $defaults );

        // 获取内容数据
        if ( is_numeric( $content ) ) {
            global $wpdb;
            $table_posts = $wpdb->prefix . 'aiscg_posts';
            $content = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM $table_posts WHERE id = %d", $content ),
                ARRAY_A
            );

            if ( ! $content ) {
                return array( 'error' => '内容不存在' );
            }
        }

        $original_content = $content;
        $optimizations = array();
        $changes_made = array();

        // 1. 合规性修复
        if ( $options['fix_compliance'] ) {
            $compliance_result = $this->fix_compliance_issues( $content );
            if ( $compliance_result['fixed'] ) {
                $content = $compliance_result['content'];
                $changes_made = array_merge( $changes_made, $compliance_result['changes'] );
                $optimizations['compliance'] = $compliance_result['summary'];
            }
        }

        // 2. 质量提升
        if ( $options['enhance_quality'] ) {
            $quality_result = $this->enhance_content_quality( $content );
            if ( $quality_result['improved'] ) {
                $content = $quality_result['content'];
                $changes_made = array_merge( $changes_made, $quality_result['changes'] );
                $optimizations['quality'] = $quality_result['summary'];
            }
        }

        // 3. SEO优化
        if ( $options['optimize_seo'] ) {
            $seo_result = $this->optimize_seo( $content );
            if ( $seo_result['optimized'] ) {
                $content = $seo_result['content'];
                $changes_made = array_merge( $changes_made, $seo_result['changes'] );
                $optimizations['seo'] = $seo_result['summary'];
            }
        }

        // 4. 可读性改善
        if ( $options['improve_readability'] ) {
            $readability_result = $this->improve_readability( $content );
            if ( $readability_result['improved'] ) {
                $content = $readability_result['content'];
                $changes_made = array_merge( $changes_made, $readability_result['changes'] );
                $optimizations['readability'] = $readability_result['summary'];
            }
        }

        return array(
            'success' => ! empty( $changes_made ),
            'original' => $original_content,
            'optimized' => $content,
            'changes_made' => $changes_made,
            'optimizations' => $optimizations,
            'improvement_score' => $this->calculate_improvement_score( $original_content, $content ),
        );
    }

    /**
     * 修复合规问题
     *
     * @param array $content 内容数据
     *
     * @return array 修复结果
     */
    private function fix_compliance_issues( $content ) {
        // 检查合规性
        $compliance_check = $this->compliance_checker->check_compliance( $content, array(
            'checks' => array( 'advertising', 'platform_policy', 'data_privacy' ),
            'use_ai' => false,
        ));

        $changes = array();
        $fixed = false;
        $text = $content['content'];
        $title = $content['title'];

        // 修复广告法违规词
        if ( ! empty( $compliance_check['violations'] ) ) {
            foreach ( $compliance_check['violations'] as $violation ) {
                if ( $violation['type'] === 'advertising_violation' ) {
                    $banned_word = $violation['keyword'];
                    $replacement = $this->get_safe_alternative( $banned_word );

                    // 替换标题中的违规词
                    if ( stripos( $title, $banned_word ) !== false ) {
                        $title = str_ireplace( $banned_word, $replacement, $title );
                        $changes[] = "标题: 将「{$banned_word}」替换为「{$replacement}」";
                        $fixed = true;
                    }

                    // 替换正文中的违规词
                    if ( stripos( $text, $banned_word ) !== false ) {
                        $text = str_ireplace( $banned_word, $replacement, $text );
                        $changes[] = "正文: 将「{$banned_word}」替换为「{$replacement}」";
                        $fixed = true;
                    }
                }

                // 移除隐私信息
                if ( $violation['type'] === 'privacy_leak' ) {
                    $pattern = $violation['pattern'] ?? '';
                    if ( $pattern ) {
                        $text = preg_replace( $pattern, '[已隐藏]', $text );
                        $changes[] = "移除隐私信息: {$violation['info_type']}";
                        $fixed = true;
                    }
                }

                // 移除平台违规内容
                if ( $violation['type'] === 'platform_violation' ) {
                    $keyword = $violation['keyword'];
                    $text = str_ireplace( $keyword, '', $text );
                    $changes[] = "移除平台违规词: {$keyword}";
                    $fixed = true;
                }
            }
        }

        $content['title'] = $title;
        $content['content'] = $text;

        return array(
            'fixed' => $fixed,
            'content' => $content,
            'changes' => $changes,
            'summary' => sprintf( '修复了 %d 个合规问题', count( $changes ) ),
        );
    }

    /**
     * 获取安全替代词
     *
     * @param string $banned_word 违禁词
     *
     * @return string 替代词
     */
    private function get_safe_alternative( $banned_word ) {
        $alternatives = array(
            '最' => '非常',
            '第一' => '领先',
            '最佳' => '优秀',
            '最优' => '优质',
            '顶级' => '高端',
            '极致' => '出色',
            '终极' => '卓越',
            '国家级' => '专业',
            '世界级' => '国际水准',
            '全球领先' => '国际领先',
            '100%' => '高度',
            '完全' => '充分',
            '绝对' => '显著',
            '永久' => '长期',
            '包治' => '有助于',
            '保证' => '力求',
        );

        return $alternatives[ $banned_word ] ?? '优质';
    }

    /**
     * 提升内容质量
     *
     * @param array $content 内容数据
     *
     * @return array 提升结果
     */
    private function enhance_content_quality( $content ) {
        $changes = array();
        $improved = false;
        $text = $content['content'];

        // 1. 检查内容长度
        $length = mb_strlen( $text );
        if ( $length < 200 ) {
            // 内容太短，使用AI扩充
            $expanded = $this->expand_content_with_ai( $text );
            if ( $expanded && mb_strlen( $expanded ) > $length ) {
                $text = $expanded;
                $changes[] = sprintf( '扩充内容从 %d 字到 %d 字', $length, mb_strlen( $expanded ) );
                $improved = true;
            }
        }

        // 2. 添加emoji表情
        if ( $content['platform'] === 'xiaohongshu' ) {
            $emoji_count = preg_match_all( '/[\x{1F300}-\x{1F9FF}]/u', $text );
            if ( $emoji_count < 3 ) {
                $text = $this->add_emojis( $text );
                $changes[] = '添加表情增强视觉吸引力';
                $improved = true;
            }
        }

        // 3. 优化段落结构
        if ( ! $this->has_good_paragraph_structure( $text ) ) {
            $text = $this->improve_paragraph_structure( $text );
            $changes[] = '优化段落结构，提升可读性';
            $improved = true;
        }

        // 4. 添加互动引导
        if ( ! $this->has_call_to_action( $text ) ) {
            $text = $this->add_call_to_action( $text, $content['platform'] );
            $changes[] = '添加互动引导，提升用户参与度';
            $improved = true;
        }

        $content['content'] = $text;

        return array(
            'improved' => $improved,
            'content' => $content,
            'changes' => $changes,
            'summary' => sprintf( '进行了 %d 项质量提升', count( $changes ) ),
        );
    }

    /**
     * 使用AI扩充内容
     *
     * @param string $text 原文本
     *
     * @return string|false 扩充后的文本
     */
    private function expand_content_with_ai( $text ) {
        try {
            $ai_service = $this->ai_factory->get_service();

            $prompt = "请扩充以下内容，保持原意的同时增加细节和描述，使其更加丰富：\n\n{$text}\n\n要求：\n1. 保持原有风格\n2. 增加实用信息\n3. 字数增加到300-400字\n4. 不要改变核心观点";

            $result = $ai_service->generate_text( $prompt, array( 'max_tokens' => 800 ) );

            return $result['text'] ?? false;
        } catch ( Exception $e ) {
            return false;
        }
    }

    /**
     * 添加表情
     *
     * @param string $text 文本
     *
     * @return string 添加表情后的文本
     */
    private function add_emojis( $text ) {
        $emojis = array( '✨', '💡', '🎯', '💪', '👍', '🌟', '📌', '⭐' );

        // 在段落开头添加表情
        $paragraphs = explode( "\n", $text );
        foreach ( $paragraphs as $index => $paragraph ) {
            if ( trim( $paragraph ) && $index < 3 ) {
                $emoji = $emojis[ array_rand( $emojis ) ];
                $paragraphs[ $index ] = $emoji . ' ' . $paragraph;
            }
        }

        return implode( "\n", $paragraphs );
    }

    /**
     * 检查段落结构
     *
     * @param string $text 文本
     *
     * @return bool 是否有好的结构
     */
    private function has_good_paragraph_structure( $text ) {
        $paragraphs = explode( "\n", trim( $text ) );
        $paragraphs = array_filter( $paragraphs, function( $p ) {
            return trim( $p ) !== '';
        });

        // 至少有2个段落
        if ( count( $paragraphs ) < 2 ) {
            return false;
        }

        // 段落长度适中(50-200字)
        foreach ( $paragraphs as $paragraph ) {
            $length = mb_strlen( $paragraph );
            if ( $length > 300 ) {
                return false;
            }
        }

        return true;
    }

    /**
     * 改善段落结构
     *
     * @param string $text 文本
     *
     * @return string 改善后的文本
     */
    private function improve_paragraph_structure( $text ) {
        // 按标点符号分段
        $text = preg_replace( '/([。！？])\s*/', "$1\n\n", $text );

        // 移除多余的空行
        $text = preg_replace( "/\n{3,}/", "\n\n", $text );

        return trim( $text );
    }

    /**
     * 检查是否有行动号召
     *
     * @param string $text 文本
     *
     * @return bool 是否有CTA
     */
    private function has_call_to_action( $text ) {
        $cta_keywords = array( '评论', '点赞', '收藏', '分享', '关注', '留言', '互动' );

        foreach ( $cta_keywords as $keyword ) {
            if ( stripos( $text, $keyword ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 添加行动号召
     *
     * @param string $text 文本
     * @param string $platform 平台
     *
     * @return string 添加CTA后的文本
     */
    private function add_call_to_action( $text, $platform ) {
        $ctas = array(
            'xiaohongshu' => array(
                "\n\n💬 你有什么想法？欢迎评论区留言交流！",
                "\n\n👍 觉得有用的话，点个赞收藏起来吧～",
                "\n\n✨ 有问题欢迎评论，我会一一回复的！",
            ),
            'instagram' => array(
                "\n\n💭 What do you think? Let me know in the comments!",
                "\n\n❤️ Double tap if you agree!",
                "\n\n📢 Share this with someone who needs to see it!",
            ),
        );

        $platform_ctas = $ctas[ $platform ] ?? $ctas['xiaohongshu'];
        $cta = $platform_ctas[ array_rand( $platform_ctas ) ];

        return $text . $cta;
    }

    /**
     * SEO优化
     *
     * @param array $content 内容数据
     *
     * @return array 优化结果
     */
    private function optimize_seo( $content ) {
        $changes = array();
        $optimized = false;

        // 优化标题
        $title = $content['title'];
        if ( mb_strlen( $title ) < 10 ) {
            $title = $this->enhance_title( $title );
            $content['title'] = $title;
            $changes[] = '优化标题长度和吸引力';
            $optimized = true;
        }

        // 优化标签
        $hashtags = json_decode( $content['hashtags'] ?? '[]', true );
        if ( ! is_array( $hashtags ) ) {
            $hashtags = array();
        }

        $optimal_count = $content['platform'] === 'xiaohongshu' ? 8 : 15;
        if ( count( $hashtags ) < $optimal_count ) {
            $hashtags = $this->enhance_hashtags( $hashtags, $content['content'], $optimal_count );
            $content['hashtags'] = wp_json_encode( $hashtags, JSON_UNESCAPED_UNICODE );
            $changes[] = sprintf( '优化标签数量到 %d 个', count( $hashtags ) );
            $optimized = true;
        }

        return array(
            'optimized' => $optimized,
            'content' => $content,
            'changes' => $changes,
            'summary' => sprintf( '进行了 %d 项SEO优化', count( $changes ) ),
        );
    }

    /**
     * 增强标题
     *
     * @param string $title 原标题
     *
     * @return string 增强后的标题
     */
    private function enhance_title( $title ) {
        $prefixes = array( '【必看】', '【干货】', '【实用】', '【推荐】' );
        $prefix = $prefixes[ array_rand( $prefixes ) ];

        return $prefix . $title;
    }

    /**
     * 增强标签
     *
     * @param array $existing_tags 现有标签
     * @param string $content 内容
     * @param int $target_count 目标数量
     *
     * @return array 增强后的标签
     */
    private function enhance_hashtags( $existing_tags, $content, $target_count ) {
        // 从内容中提取关键词
        $keywords = $this->extract_keywords( $content );

        // 合并现有标签和新标签
        $all_tags = array_merge( $existing_tags, $keywords );
        $all_tags = array_unique( $all_tags );

        // 如果还不够，添加通用热门标签
        if ( count( $all_tags ) < $target_count ) {
            $popular_tags = array( '生活分享', '日常', '好物推荐', '种草', '干货分享' );
            $all_tags = array_merge( $all_tags, $popular_tags );
            $all_tags = array_unique( $all_tags );
        }

        return array_slice( $all_tags, 0, $target_count );
    }

    /**
     * 提取关键词
     *
     * @param string $text 文本
     *
     * @return array 关键词
     */
    private function extract_keywords( $text ) {
        // 简单的关键词提取（实际应使用更复杂的算法）
        $words = preg_split( '/[\s,，。！？、]+/u', $text );
        $words = array_filter( $words, function( $word ) {
            return mb_strlen( $word ) >= 2 && mb_strlen( $word ) <= 6;
        });

        // 统计词频
        $word_freq = array_count_values( $words );
        arsort( $word_freq );

        return array_slice( array_keys( $word_freq ), 0, 5 );
    }

    /**
     * 改善可读性
     *
     * @param array $content 内容数据
     *
     * @return array 改善结果
     */
    private function improve_readability( $content ) {
        $changes = array();
        $improved = false;
        $text = $content['content'];

        // 移除多余空格
        $original_length = strlen( $text );
        $text = preg_replace( '/\s+/', ' ', $text );
        if ( strlen( $text ) < $original_length ) {
            $changes[] = '清理多余空格';
            $improved = true;
        }

        // 统一标点符号
        $text = str_replace( array( '（', '）' ), array( '(', ')' ), $text );
        $text = str_replace( '，，', '，', $text );

        $content['content'] = $text;

        return array(
            'improved' => $improved,
            'content' => $content,
            'changes' => $changes,
            'summary' => sprintf( '进行了 %d 项可读性改善', count( $changes ) ),
        );
    }

    /**
     * 计算改善分数
     *
     * @param array $original 原始内容
     * @param array $optimized 优化后内容
     *
     * @return int 改善分数 (0-100)
     */
    private function calculate_improvement_score( $original, $optimized ) {
        $score = 0;

        // 内容长度改善
        $original_length = mb_strlen( $original['content'] );
        $optimized_length = mb_strlen( $optimized['content'] );
        if ( $optimized_length > $original_length && $optimized_length >= 200 ) {
            $score += 20;
        }

        // 标题改善
        if ( mb_strlen( $optimized['title'] ) > mb_strlen( $original['title'] ) ) {
            $score += 15;
        }

        // 标签数量改善
        $original_tags = json_decode( $original['hashtags'] ?? '[]', true );
        $optimized_tags = json_decode( $optimized['hashtags'] ?? '[]', true );
        if ( is_array( $optimized_tags ) && count( $optimized_tags ) > count( $original_tags ?? array() ) ) {
            $score += 25;
        }

        // 段落结构
        $optimized_paragraphs = explode( "\n", trim( $optimized['content'] ) );
        if ( count( $optimized_paragraphs ) >= 3 ) {
            $score += 20;
        }

        // 互动引导
        if ( $this->has_call_to_action( $optimized['content'] ) ) {
            $score += 20;
        }

        return min( $score, 100 );
    }

    /**
     * 批量优化内容
     *
     * @param array $content_ids 内容ID数组
     * @param array $options 优化选项
     *
     * @return array 批量优化结果
     */
    public function batch_optimize( $content_ids, $options = array() ) {
        $results = array();
        $success_count = 0;
        $fail_count = 0;

        foreach ( $content_ids as $content_id ) {
            $result = $this->auto_optimize( $content_id, $options );

            if ( $result['success'] ) {
                $success_count++;

                // 保存优化后的内容
                $this->save_optimized_content( $content_id, $result['optimized'] );
            } else {
                $fail_count++;
            }

            $results[] = array(
                'content_id' => $content_id,
                'success' => $result['success'],
                'changes_count' => count( $result['changes_made'] ?? array() ),
                'improvement_score' => $result['improvement_score'] ?? 0,
            );
        }

        return array(
            'total' => count( $content_ids ),
            'success' => $success_count,
            'failed' => $fail_count,
            'results' => $results,
        );
    }

    /**
     * 保存优化后的内容
     *
     * @param int $content_id 内容ID
     * @param array $content 内容数据
     *
     * @return bool 是否成功
     */
    private function save_optimized_content( $content_id, $content ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $result = $wpdb->update(
            $table_posts,
            array(
                'title' => $content['title'],
                'content' => $content['content'],
                'hashtags' => $content['hashtags'],
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $content_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        return $result !== false;
    }
}
