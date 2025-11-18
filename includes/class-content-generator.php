<?php
/**
 * 内容生成器类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 内容生成器类
 */
class AISCG_Content_Generator {

    /**
     * 数据库实例
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = new AISCG_Database();
    }

    /**
     * 生成内容
     *
     * @param string $topic        主题
     * @param string $platform     平台(xiaohongshu/instagram)
     * @param string $ai_model     AI模型
     * @param string $custom_prompt 自定义提示词
     * @return array 生成结果
     * @throws Exception 如果生成失败
     */
    public function generate( $topic, $platform = 'xiaohongshu', $ai_model = '', $custom_prompt = '' ) {
        // 验证输入
        if ( empty( $topic ) ) {
            throw new Exception( __( 'Topic is required', 'ai-social-content-generator' ) );
        }

        if ( ! in_array( $platform, array( 'xiaohongshu', 'instagram' ) ) ) {
            throw new Exception( __( 'Invalid platform', 'ai-social-content-generator' ) );
        }

        // 获取提示词模板
        $prompt = $this->build_prompt( $topic, $platform, $custom_prompt );

        // 创建AI服务实例
        $service = AISCG_AI_Service_Factory::create( $ai_model );

        // 生成内容
        $raw_content = $service->generate( $prompt );

        // 解析生成的内容
        $parsed_content = $this->parse_content( $raw_content, $platform );

        // 保存到数据库
        $post_id = $this->save_content( $parsed_content, $platform, $service->get_model_name(), $prompt );

        // 返回结果
        return array(
            'success' => true,
            'post_id' => $post_id,
            'title' => $parsed_content['title'],
            'content' => $parsed_content['content'],
            'hashtags' => $parsed_content['hashtags'],
            'platform' => $platform,
            'ai_model' => $service->get_model_name(),
        );
    }

    /**
     * 构建提示词
     *
     * @param string $topic         主题
     * @param string $platform      平台
     * @param string $custom_prompt 自定义提示词
     * @return string 提示词
     */
    private function build_prompt( $topic, $platform, $custom_prompt = '' ) {
        // 如果提供了自定义提示词,使用自定义提示词
        if ( ! empty( $custom_prompt ) ) {
            return str_replace( '{topic}', $topic, $custom_prompt );
        }

        // 否则使用默认模板
        $templates = get_option( 'aiscg_content_templates', array() );

        if ( isset( $templates[ $platform ] ) && ! empty( $templates[ $platform ] ) ) {
            $template = $templates[ $platform ];
        } else {
            // 使用内置默认模板
            $template = $this->get_default_template( $platform );
        }

        return str_replace( '{topic}', $topic, $template );
    }

    /**
     * 获取默认模板
     *
     * @param string $platform 平台
     * @return string 默认模板
     */
    private function get_default_template( $platform ) {
        $templates = array(
            'xiaohongshu' => "请根据主题「{topic}」生成一篇小红书风格的文案。\n\n要求:\n1. 标题吸引眼球,20字以内\n2. 正文包含emoji表情,分段清晰\n3. 文案长度300-500字\n4. 包含5-10个相关话题标签\n5. 语气轻松活泼,适合年轻用户\n\n请按以下JSON格式输出:\n{\n  \"title\": \"标题\",\n  \"content\": \"正文内容\",\n  \"hashtags\": [\"标签1\", \"标签2\"]\n}",
            'instagram' => "请根据主题「{topic}」生成一篇Instagram风格的文案。\n\n要求:\n1. 标题简洁有力\n2. 正文简洁,100-200字\n3. 包含10-20个英文hashtags\n4. 语气国际化,适合海外受众\n\n请按以下JSON格式输出:\n{\n  \"title\": \"标题\",\n  \"content\": \"正文内容\",\n  \"hashtags\": [\"#hashtag1\", \"#hashtag2\"]\n}",
        );

        return isset( $templates[ $platform ] ) ? $templates[ $platform ] : $templates['xiaohongshu'];
    }

    /**
     * 解析生成的内容
     *
     * @param string $raw_content 原始内容
     * @param string $platform    平台
     * @return array 解析后的内容
     */
    private function parse_content( $raw_content, $platform ) {
        // 尝试解析JSON格式
        $json_content = $this->extract_json( $raw_content );

        if ( $json_content !== null ) {
            return array(
                'title' => isset( $json_content['title'] ) ? $json_content['title'] : '',
                'content' => isset( $json_content['content'] ) ? $json_content['content'] : '',
                'hashtags' => isset( $json_content['hashtags'] ) ? $json_content['hashtags'] : array(),
            );
        }

        // 如果无法解析JSON,尝试智能提取
        return $this->smart_parse( $raw_content, $platform );
    }

    /**
     * 从文本中提取JSON
     *
     * @param string $text 文本
     * @return array|null 解析后的数组或null
     */
    private function extract_json( $text ) {
        // 尝试直接解析
        $data = json_decode( $text, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            return $data;
        }

        // 尝试提取JSON代码块
        if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i', $text, $matches ) ) {
            $data = json_decode( $matches[1], true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $data;
            }
        }

        // 尝试查找第一个完整的JSON对象
        if ( preg_match( '/\{[\s\S]*\}/', $text, $matches ) ) {
            $data = json_decode( $matches[0], true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $data;
            }
        }

        return null;
    }

    /**
     * 智能解析内容
     *
     * @param string $raw_content 原始内容
     * @param string $platform    平台
     * @return array 解析后的内容
     */
    private function smart_parse( $raw_content, $platform ) {
        $lines = explode( "\n", $raw_content );
        $title = '';
        $content = '';
        $hashtags = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );

            if ( empty( $line ) ) {
                continue;
            }

            // 提取标题
            if ( empty( $title ) && ! preg_match( '/^#/', $line ) ) {
                $title = $line;
                continue;
            }

            // 提取hashtags
            if ( preg_match_all( '/#[\w\u4e00-\u9fa5]+/', $line, $matches ) ) {
                $hashtags = array_merge( $hashtags, $matches[0] );
                // 移除hashtags后添加到内容
                $line_without_tags = preg_replace( '/#[\w\u4e00-\u9fa5]+/', '', $line );
                if ( ! empty( trim( $line_without_tags ) ) ) {
                    $content .= $line_without_tags . "\n";
                }
            } else {
                $content .= $line . "\n";
            }
        }

        return array(
            'title' => $title,
            'content' => trim( $content ),
            'hashtags' => array_unique( $hashtags ),
        );
    }

    /**
     * 保存内容到数据库
     *
     * @param array  $content  内容数据
     * @param string $platform 平台
     * @param string $ai_model AI模型
     * @param string $prompt   使用的提示词
     * @return int 内容ID
     */
    private function save_content( $content, $platform, $ai_model, $prompt ) {
        $data = array(
            'platform' => $platform,
            'title' => $content['title'],
            'content' => $content['content'],
            'hashtags' => $content['hashtags'],
            'ai_model' => $ai_model,
            'prompt_used' => $prompt,
            'status' => 'draft',
        );

        $post_id = $this->db->create_post( $data );

        if ( ! $post_id ) {
            throw new Exception( __( 'Failed to save content', 'ai-social-content-generator' ) );
        }

        return $post_id;
    }

    /**
     * 重新生成内容
     *
     * @param int $post_id 内容ID
     * @return array 生成结果
     */
    public function regenerate( $post_id ) {
        $post = $this->db->get_post( $post_id );

        if ( ! $post ) {
            throw new Exception( __( 'Post not found', 'ai-social-content-generator' ) );
        }

        // 从原始提示词中提取主题
        $topic = $this->extract_topic_from_prompt( $post->prompt_used );

        // 使用相同的平台和AI模型重新生成
        return $this->generate( $topic, $post->platform, $post->ai_model );
    }

    /**
     * 从提示词中提取主题
     *
     * @param string $prompt 提示词
     * @return string 主题
     */
    private function extract_topic_from_prompt( $prompt ) {
        // 尝试从提示词中提取主题
        if ( preg_match( '/主题「(.+?)」/', $prompt, $matches ) ) {
            return $matches[1];
        }

        // 如果无法提取,返回默认值
        return 'Unknown Topic';
    }
}
