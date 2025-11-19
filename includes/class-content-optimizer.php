<?php
/**
 * Content Optimizer Class
 *
 * 使用AI优化现有内容
 *
 * @package AI_Social_Content_Generator
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Content_Optimizer Class
 */
class AISCG_Content_Optimizer {

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
     * 内容评分器
     *
     * @var AISCG_Content_Scorer
     */
    private $scorer;

    /**
     * 版本控制
     *
     * @var AISCG_Version_Control
     */
    private $version_control;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->logger = new AISCG_Logger();
        $this->db = new AISCG_Database();
        $this->scorer = new AISCG_Content_Scorer();
        $this->version_control = new AISCG_Version_Control();
    }

    /**
     * 优化内容
     *
     * @param int $post_id 内容ID
     * @param array $options 优化选项
     * @return array|false 优化结果
     */
    public function optimize_content( $post_id, $options = array() ) {
        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            return false;
        }

        $post = $this->db->get_post( $post_id );
        if ( ! $post ) {
            $this->logger->error( "优化内容失败：内容不存在 #{$post_id}" );
            return false;
        }

        $defaults = array(
            'ai_model' => 'openai',
            'optimization_type' => 'comprehensive', // comprehensive, readability, engagement, hashtags
            'preserve_style' => true,
            'save_version' => true,
        );

        $options = wp_parse_args( $options, $defaults );

        try {
            // 保存当前版本
            if ( $options['save_version'] ) {
                $this->version_control->save_version( $post_id, array(
                    'action' => 'before_optimization',
                    'optimization_type' => $options['optimization_type'],
                ) );
            }

            // 评估当前内容
            $current_score = $this->scorer->score_content( $post );

            // 根据优化类型生成prompt
            $prompt = $this->build_optimization_prompt( $post, $current_score, $options );

            // 调用AI进行优化
            $ai_service = AISCG_AI_Service_Factory::create( $options['ai_model'] );
            if ( ! $ai_service ) {
                throw new Exception( 'AI服务创建失败' );
            }

            $start_time = microtime( true );
            $optimized_content = $ai_service->generate( $prompt );
            $duration = microtime( true ) - $start_time;

            // 解析优化后的内容
            $parsed = $this->parse_optimized_content( $optimized_content );

            if ( ! $parsed ) {
                throw new Exception( 'AI返回内容解析失败' );
            }

            // 更新内容
            $update_data = array();

            if ( isset( $parsed['title'] ) ) {
                $update_data['title'] = $parsed['title'];
            }

            if ( isset( $parsed['content'] ) ) {
                $update_data['content'] = $parsed['content'];
            }

            if ( isset( $parsed['hashtags'] ) ) {
                $update_data['hashtags'] = wp_json_encode( $parsed['hashtags'] );
            }

            $updated = $this->db->update_post( $post_id, $update_data );

            if ( ! $updated ) {
                throw new Exception( '更新内容失败' );
            }

            // 评估优化后的内容
            $new_score = $this->scorer->score_content( $post_id );

            // 计算改进
            $improvements = array(
                'overall' => $new_score['overall'] - $current_score['overall'],
                'readability' => $new_score['readability'] - $current_score['readability'],
                'engagement' => $new_score['engagement'] - $current_score['engagement'],
                'hashtags' => $new_score['hashtags'] - $current_score['hashtags'],
            );

            $result = array(
                'success' => true,
                'post_id' => $post_id,
                'before' => $current_score,
                'after' => $new_score,
                'improvements' => $improvements,
                'duration' => round( $duration, 2 ),
                'optimized_fields' => array_keys( $update_data ),
            );

            $this->logger->info( "内容优化成功 #{$post_id}", array(
                'type' => $options['optimization_type'],
                'overall_improvement' => $improvements['overall'],
            ) );

            return $result;

        } catch ( Exception $e ) {
            $this->logger->error( "内容优化失败 #{$post_id}", array(
                'error' => $e->getMessage(),
            ) );

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * 构建优化prompt
     *
     * @param array $post 内容数据
     * @param array $score 质量评分
     * @param array $options 选项
     * @return string Prompt
     */
    private function build_optimization_prompt( $post, $score, $options ) {
        $platform = $post['platform'];
        $type = $options['optimization_type'];

        $base_prompt = "请优化以下{$platform}内容:\n\n";
        $base_prompt .= "当前标题: {$post['title']}\n";
        $base_prompt .= "当前内容:\n{$post['content']}\n\n";

        $hashtags = is_string( $post['hashtags'] ) ? json_decode( $post['hashtags'], true ) : $post['hashtags'];
        if ( ! empty( $hashtags ) ) {
            $base_prompt .= "当前标签: " . implode( ' ', $hashtags ) . "\n\n";
        }

        $base_prompt .= "当前质量评分:\n";
        $base_prompt .= "- 总分: {$score['overall']}/100\n";
        $base_prompt .= "- 可读性: {$score['readability']}/100\n";
        $base_prompt .= "- 互动潜力: {$score['engagement']}/100\n";
        $base_prompt .= "- 标签质量: {$score['hashtags']}/100\n\n";

        switch ( $type ) {
            case 'readability':
                $base_prompt .= "优化重点：提高可读性\n";
                $base_prompt .= "要求：\n";
                $base_prompt .= "1. 优化句子结构，使其更简洁易懂\n";
                $base_prompt .= "2. 改善段落划分\n";
                $base_prompt .= "3. 调整语言风格，更贴近目标受众\n";
                if ( $platform === 'xiaohongshu' ) {
                    $base_prompt .= "4. 适当添加emoji表情\n";
                }
                break;

            case 'engagement':
                $base_prompt .= "优化重点：提升互动潜力\n";
                $base_prompt .= "要求：\n";
                $base_prompt .= "1. 增加情感共鸣元素\n";
                $base_prompt .= "2. 添加引人互动的问题\n";
                $base_prompt .= "3. 加入适当的行动号召(CTA)\n";
                $base_prompt .= "4. 优化标题，使其更吸引眼球\n";
                break;

            case 'hashtags':
                $base_prompt .= "优化重点：改善标签质量\n";
                $base_prompt .= "要求：\n";
                if ( $platform === 'xiaohongshu' ) {
                    $base_prompt .= "1. 提供5-10个相关且热门的中文标签\n";
                } else {
                    $base_prompt .= "1. 提供10-20个相关且热门的英文标签\n";
                }
                $base_prompt .= "2. 混合使用热门标签和长尾标签\n";
                $base_prompt .= "3. 标签要准确反映内容主题\n";
                break;

            case 'comprehensive':
            default:
                $base_prompt .= "优化重点：全面提升内容质量\n";
                $base_prompt .= "要求：\n";
                $base_prompt .= "1. 优化标题，增强吸引力\n";
                $base_prompt .= "2. 改善内容结构和可读性\n";
                $base_prompt .= "3. 增加互动元素和情感共鸣\n";
                $base_prompt .= "4. 优化标签，提高曝光度\n";
                $base_prompt .= "5. 保持原内容的核心主题和风格\n";
                break;
        }

        if ( $options['preserve_style'] ) {
            $base_prompt .= "\n注意：请保持原有的语言风格和个性化表达方式。\n";
        }

        // 添加具体建议
        if ( ! empty( $score['suggestions'] ) ) {
            $base_prompt .= "\n当前内容存在以下问题:\n";
            foreach ( $score['suggestions'] as $index => $suggestion ) {
                $base_prompt .= ( $index + 1 ) . ". " . $suggestion['message'] . "\n";
            }
        }

        $base_prompt .= "\n请按以下JSON格式输出优化后的内容:\n";
        $base_prompt .= "{\n";
        $base_prompt .= "  \"title\": \"优化后的标题\",\n";
        $base_prompt .= "  \"content\": \"优化后的正文\",\n";
        $base_prompt .= "  \"hashtags\": [\"标签1\", \"标签2\", \"标签3\"]\n";
        $base_prompt .= "}";

        return $base_prompt;
    }

    /**
     * 解析优化后的内容
     *
     * @param string $ai_response AI响应
     * @return array|false 解析结果
     */
    private function parse_optimized_content( $ai_response ) {
        // 尝试提取JSON
        if ( preg_match( '/\{[\s\S]*"title"[\s\S]*"content"[\s\S]*"hashtags"[\s\S]*\}/', $ai_response, $matches ) ) {
            $json = $matches[0];
            $data = json_decode( $json, true );

            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $data;
            }
        }

        // 尝试直接解析整个响应
        $data = json_decode( $ai_response, true );
        if ( json_last_error() === JSON_ERROR_NONE && isset( $data['content'] ) ) {
            return $data;
        }

        return false;
    }

    /**
     * A/B测试优化
     *
     * @param int $post_id 内容ID
     * @param array $options 选项
     * @return array 测试结果
     */
    public function ab_test_optimization( $post_id, $options = array() ) {
        $defaults = array(
            'ai_model' => 'openai',
            'num_variants' => 2,
        );

        $options = wp_parse_args( $options, $defaults );
        $variants = array();

        $original_post = $this->db->get_post( $post_id );
        if ( ! $original_post ) {
            return false;
        }

        // 原始版本
        $original_score = $this->scorer->score_content( $original_post );
        $variants[] = array(
            'version' => 'original',
            'post' => $original_post,
            'score' => $original_score,
        );

        // 生成优化变体
        for ( $i = 1; $i <= $options['num_variants']; $i++ ) {
            // 为每个变体使用不同的优化策略
            $optimization_types = array( 'readability', 'engagement', 'comprehensive' );
            $type = $optimization_types[ ( $i - 1 ) % count( $optimization_types ) ];

            $result = $this->optimize_content( $post_id, array(
                'ai_model' => $options['ai_model'],
                'optimization_type' => $type,
                'save_version' => true,
            ) );

            if ( $result && $result['success'] ) {
                $optimized_post = $this->db->get_post( $post_id );
                $variants[] = array(
                    'version' => "variant_{$i}",
                    'optimization_type' => $type,
                    'post' => $optimized_post,
                    'score' => $result['after'],
                    'improvements' => $result['improvements'],
                );
            }

            // 恢复原内容为下一次优化做准备
            if ( $i < $options['num_variants'] ) {
                $this->db->update_post( $post_id, array(
                    'title' => $original_post['title'],
                    'content' => $original_post['content'],
                    'hashtags' => $original_post['hashtags'],
                ) );
            }
        }

        // 选择最佳版本
        usort( $variants, function( $a, $b ) {
            return $b['score']['overall'] - $a['score']['overall'];
        } );

        $this->logger->info( "A/B测试完成 #{$post_id}", array(
            'variants_count' => count( $variants ),
            'best_version' => $variants[0]['version'],
            'best_score' => $variants[0]['score']['overall'],
        ) );

        return array(
            'variants' => $variants,
            'best' => $variants[0],
        );
    }

    /**
     * 批量优化
     *
     * @param array $post_ids 内容ID数组
     * @param array $options 选项
     * @return array 优化结果
     */
    public function batch_optimize( $post_ids, $options = array() ) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'total_improvement' => 0,
            'items' => array(),
        );

        foreach ( $post_ids as $post_id ) {
            $result = $this->optimize_content( $post_id, $options );

            if ( $result && $result['success'] ) {
                $results['success']++;
                $results['total_improvement'] += $result['improvements']['overall'];
                $results['items'][ $post_id ] = $result;
            } else {
                $results['failed']++;
                $results['items'][ $post_id ] = array(
                    'success' => false,
                    'error' => isset( $result['error'] ) ? $result['error'] : 'Unknown error',
                );
            }

            // 避免请求过快
            sleep( 2 );
        }

        if ( $results['success'] > 0 ) {
            $results['average_improvement'] = round( $results['total_improvement'] / $results['success'], 1 );
        }

        $this->logger->info( '批量优化完成', $results );

        return $results;
    }

    /**
     * 智能优化建议
     *
     * @param int $post_id 内容ID
     * @return array 建议列表
     */
    public function get_smart_suggestions( $post_id ) {
        $post = $this->db->get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $score = $this->scorer->score_content( $post );

        $suggestions = array(
            'priority' => array(),
            'optional' => array(),
        );

        // 根据评分确定优先级
        if ( $score['overall'] < 60 ) {
            $suggestions['priority'][] = array(
                'action' => 'comprehensive_optimization',
                'reason' => '内容总体质量较低，建议进行全面优化',
                'expected_improvement' => '+20-30分',
            );
        }

        if ( $score['readability'] < 60 ) {
            $suggestions['priority'][] = array(
                'action' => 'readability_optimization',
                'reason' => '可读性需要改善',
                'expected_improvement' => '+15-25分',
            );
        }

        if ( $score['engagement'] < 60 ) {
            $suggestions['priority'][] = array(
                'action' => 'engagement_optimization',
                'reason' => '互动潜力较低',
                'expected_improvement' => '+15-20分',
            );
        }

        if ( $score['hashtags'] < 60 ) {
            $suggestions['priority'][] = array(
                'action' => 'hashtags_optimization',
                'reason' => '标签质量有待提升',
                'expected_improvement' => '+20-30分',
            );
        }

        // 可选建议
        if ( $score['overall'] >= 70 && $score['overall'] < 85 ) {
            $suggestions['optional'][] = array(
                'action' => 'fine_tune',
                'reason' => '内容已较好，可以进行微调以达到优秀',
                'expected_improvement' => '+5-10分',
            );
        }

        if ( $score['overall'] >= 60 ) {
            $suggestions['optional'][] = array(
                'action' => 'ab_test',
                'reason' => '可以尝试A/B测试，找到最佳版本',
                'expected_improvement' => '+10-15分',
            );
        }

        return array(
            'current_score' => $score,
            'suggestions' => $suggestions,
        );
    }
}
