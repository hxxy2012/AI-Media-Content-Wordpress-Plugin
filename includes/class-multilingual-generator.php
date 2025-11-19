<?php
/**
 * Multilingual Generator Class
 *
 * 多语言内容生成和翻译
 *
 * @package AI_Social_Content_Generator
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Multilingual_Generator Class
 */
class AISCG_Multilingual_Generator {

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
     * 支持的语言列表
     *
     * @var array
     */
    private $supported_languages = array(
        'zh-CN' => '简体中文',
        'zh-TW' => '繁体中文',
        'en' => 'English',
        'ja' => '日本語',
        'ko' => '한국어',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'pt' => 'Português',
        'ru' => 'Русский',
        'ar' => 'العربية',
        'th' => 'ไทย',
        'vi' => 'Tiếng Việt',
    );

    /**
     * 构造函数
     */
    public function __construct() {
        $this->logger = new AISCG_Logger();
        $this->db = new AISCG_Database();
    }

    /**
     * 生成多语言内容
     *
     * @param string $topic 主题
     * @param array $languages 语言列表
     * @param array $options 选项
     * @return array 生成结果
     */
    public function generate_multilingual( $topic, $languages, $options = array() ) {
        $defaults = array(
            'platform' => 'xiaohongshu',
            'ai_model' => 'openai',
            'base_language' => 'zh-CN',
        );

        $options = wp_parse_args( $options, $defaults );

        $results = array(
            'success' => 0,
            'failed' => 0,
            'contents' => array(),
        );

        foreach ( $languages as $language ) {
            try {
                $content = $this->generate_content_in_language( $topic, $language, $options );

                if ( $content ) {
                    $results['contents'][ $language ] = $content;
                    $results['success']++;

                    $this->logger->info( "生成{$language}内容成功", array(
                        'topic' => $topic,
                        'language' => $language,
                    ) );
                } else {
                    $results['failed']++;
                }

            } catch ( Exception $e ) {
                $results['failed']++;
                $results['contents'][ $language ] = array(
                    'error' => $e->getMessage(),
                );

                $this->logger->error( "生成{$language}内容失败", array(
                    'topic' => $topic,
                    'language' => $language,
                    'error' => $e->getMessage(),
                ) );
            }

            // 避免请求过快
            sleep( 1 );
        }

        return $results;
    }

    /**
     * 生成指定语言的内容
     *
     * @param string $topic 主题
     * @param string $language 语言代码
     * @param array $options 选项
     * @return array|false 生成的内容
     */
    private function generate_content_in_language( $topic, $language, $options ) {
        $platform = $options['platform'];
        $ai_model = $options['ai_model'];

        $prompt = $this->build_multilingual_prompt( $topic, $language, $platform );

        $ai_service = AISCG_AI_Service_Factory::create( $ai_model );
        if ( ! $ai_service ) {
            throw new Exception( 'AI服务创建失败' );
        }

        $raw_content = $ai_service->generate( $prompt );

        // 解析JSON
        $content = $this->parse_content( $raw_content );

        if ( ! $content ) {
            throw new Exception( 'AI返回内容解析失败' );
        }

        // 添加语言标记
        $content['language'] = $language;
        $content['topic'] = $topic;
        $content['platform'] = $platform;

        // 保存到数据库
        $post_id = $this->db->create_post( array(
            'platform' => $platform,
            'title' => $content['title'],
            'content' => $content['content'],
            'hashtags' => wp_json_encode( $content['hashtags'] ),
            'ai_model' => $ai_model,
            'prompt_used' => $prompt,
        ) );

        $content['post_id'] = $post_id;

        return $content;
    }

    /**
     * 构建多语言prompt
     *
     * @param string $topic 主题
     * @param string $language 语言代码
     * @param string $platform 平台
     * @return string Prompt
     */
    private function build_multilingual_prompt( $topic, $language, $platform ) {
        $language_name = isset( $this->supported_languages[ $language ] ) ?
            $this->supported_languages[ $language ] : $language;

        $prompt = "Please generate {$platform} content in {$language_name} about: {$topic}\n\n";

        $prompt .= "Requirements:\n";

        if ( $platform === 'xiaohongshu' ) {
            $prompt .= "1. Catchy title (within 20 characters)\n";
            $prompt .= "2. Engaging content with appropriate emojis, well-structured paragraphs\n";
            $prompt .= "3. Content length: 300-500 characters\n";
            $prompt .= "4. Include 5-10 relevant hashtags\n";
            $prompt .= "5. Tone: casual, friendly, suitable for young audience\n";
        } else {
            $prompt .= "1. Concise and powerful title\n";
            $prompt .= "2. Brief content (100-200 characters)\n";
            $prompt .= "3. Include 10-20 hashtags in the target language\n";
            $prompt .= "4. Tone: international, suitable for global audience\n";
        }

        $prompt .= "\nIMPORTANT: Write everything in {$language_name}. ";
        $prompt .= "Hashtags should also be in {$language_name} or use popular terms in that language.\n\n";

        $prompt .= "Please output in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"title\": \"title in {$language_name}\",\n";
        $prompt .= "  \"content\": \"content in {$language_name}\",\n";
        $prompt .= "  \"hashtags\": [\"hashtag1\", \"hashtag2\"]\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * 翻译内容
     *
     * @param int|array $post 内容ID或内容数组
     * @param string $target_language 目标语言
     * @param array $options 选项
     * @return array|false 翻译结果
     */
    public function translate_content( $post, $target_language, $options = array() ) {
        if ( is_numeric( $post ) ) {
            $post = $this->db->get_post( $post );
        }

        if ( ! $post ) {
            return false;
        }

        $defaults = array(
            'ai_model' => 'openai',
            'preserve_style' => true,
            'localize_hashtags' => true,
        );

        $options = wp_parse_args( $options, $defaults );

        try {
            $prompt = $this->build_translation_prompt( $post, $target_language, $options );

            $ai_service = AISCG_AI_Service_Factory::create( $options['ai_model'] );
            if ( ! $ai_service ) {
                throw new Exception( 'AI服务创建失败' );
            }

            $translated_content = $ai_service->generate( $prompt );

            // 解析翻译后的内容
            $parsed = $this->parse_content( $translated_content );

            if ( ! $parsed ) {
                throw new Exception( '翻译内容解析失败' );
            }

            // 保存翻译版本
            $new_post_id = $this->db->create_post( array(
                'platform' => $post['platform'],
                'title' => $parsed['title'],
                'content' => $parsed['content'],
                'hashtags' => wp_json_encode( $parsed['hashtags'] ),
                'ai_model' => $options['ai_model'],
                'prompt_used' => $prompt,
            ) );

            $this->logger->info( "内容翻译成功 #{$post['id']} -> {$target_language}", array(
                'new_post_id' => $new_post_id,
                'target_language' => $target_language,
            ) );

            return array(
                'success' => true,
                'original_post_id' => $post['id'],
                'translated_post_id' => $new_post_id,
                'target_language' => $target_language,
                'content' => $parsed,
            );

        } catch ( Exception $e ) {
            $this->logger->error( "内容翻译失败 #{$post['id']}", array(
                'error' => $e->getMessage(),
                'target_language' => $target_language,
            ) );

            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * 构建翻译prompt
     *
     * @param array $post 内容数据
     * @param string $target_language 目标语言
     * @param array $options 选项
     * @return string Prompt
     */
    private function build_translation_prompt( $post, $target_language, $options ) {
        $language_name = isset( $this->supported_languages[ $target_language ] ) ?
            $this->supported_languages[ $target_language ] : $target_language;

        $prompt = "Please translate the following {$post['platform']} content to {$language_name}:\n\n";
        $prompt .= "Title: {$post['title']}\n\n";
        $prompt .= "Content:\n{$post['content']}\n\n";

        $hashtags = is_string( $post['hashtags'] ) ? json_decode( $post['hashtags'], true ) : $post['hashtags'];
        if ( ! empty( $hashtags ) ) {
            $prompt .= "Hashtags: " . implode( ' ', $hashtags ) . "\n\n";
        }

        $prompt .= "Requirements:\n";
        $prompt .= "1. Translate all content to {$language_name}\n";
        $prompt .= "2. Maintain the original tone and style\n";

        if ( $options['preserve_style'] ) {
            $prompt .= "3. Keep the emotional expressions and personality\n";
            $prompt .= "4. Preserve formatting (line breaks, emojis, etc.)\n";
        }

        if ( $options['localize_hashtags'] ) {
            $prompt .= "5. Localize hashtags to popular terms in {$language_name}\n";
        } else {
            $prompt .= "5. Keep hashtags in the original language if they are commonly used internationally\n";
        }

        $prompt .= "\nPlease output in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"title\": \"translated title\",\n";
        $prompt .= "  \"content\": \"translated content\",\n";
        $prompt .= "  \"hashtags\": [\"hashtag1\", \"hashtag2\"]\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * 解析生成的内容
     *
     * @param string $raw_content AI响应
     * @return array|false 解析结果
     */
    private function parse_content( $raw_content ) {
        // 尝试提取JSON
        if ( preg_match( '/\{[\s\S]*"title"[\s\S]*"content"[\s\S]*"hashtags"[\s\S]*\}/', $raw_content, $matches ) ) {
            $json = $matches[0];
            $data = json_decode( $json, true );

            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $data;
            }
        }

        // 尝试直接解析
        $data = json_decode( $raw_content, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            return $data;
        }

        return false;
    }

    /**
     * 批量翻译
     *
     * @param array $post_ids 内容ID数组
     * @param array $target_languages 目标语言数组
     * @param array $options 选项
     * @return array 翻译结果
     */
    public function batch_translate( $post_ids, $target_languages, $options = array() ) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'translations' => array(),
        );

        foreach ( $post_ids as $post_id ) {
            foreach ( $target_languages as $language ) {
                $result = $this->translate_content( $post_id, $language, $options );

                if ( $result && $result['success'] ) {
                    $results['success']++;
                    $results['translations'][] = $result;
                } else {
                    $results['failed']++;
                }

                // 避免请求过快
                sleep( 1 );
            }
        }

        $this->logger->info( '批量翻译完成', array(
            'success' => $results['success'],
            'failed' => $results['failed'],
        ) );

        return $results;
    }

    /**
     * 获取支持的语言列表
     *
     * @return array 语言列表
     */
    public function get_supported_languages() {
        return $this->supported_languages;
    }

    /**
     * 检测内容语言
     *
     * @param string $text 文本
     * @return string 语言代码
     */
    public function detect_language( $text ) {
        // 简单的语言检测逻辑
        // 实际应用中可以使用更复杂的检测算法或API

        // 检查中文字符
        if ( preg_match( '/[\x{4e00}-\x{9fa5}]/u', $text ) ) {
            // 检查是否有繁体字特征
            $traditional_chars = array( '臺', '裡', '讓', '導', '場', '關', '開', '門', '為', '與' );
            foreach ( $traditional_chars as $char ) {
                if ( strpos( $text, $char ) !== false ) {
                    return 'zh-TW';
                }
            }
            return 'zh-CN';
        }

        // 检查日文字符
        if ( preg_match( '/[\x{3040}-\x{309f}\x{30a0}-\x{30ff}]/u', $text ) ) {
            return 'ja';
        }

        // 检查韩文字符
        if ( preg_match( '/[\x{ac00}-\x{d7af}]/u', $text ) ) {
            return 'ko';
        }

        // 检查阿拉伯文字符
        if ( preg_match( '/[\x{0600}-\x{06ff}]/u', $text ) ) {
            return 'ar';
        }

        // 检查泰文字符
        if ( preg_match( '/[\x{0e00}-\x{0e7f}]/u', $text ) ) {
            return 'th';
        }

        // 默认英文
        return 'en';
    }

    /**
     * 创建多语言变体
     *
     * @param int $post_id 原始内容ID
     * @param array $languages 目标语言列表
     * @param array $options 选项
     * @return array 创建结果
     */
    public function create_variants( $post_id, $languages, $options = array() ) {
        $post = $this->db->get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $defaults = array(
            'ai_model' => 'openai',
            'link_variants' => true, // 是否链接各语言版本
        );

        $options = wp_parse_args( $options, $defaults );

        $variants = array(
            'original' => array(
                'post_id' => $post_id,
                'language' => $this->detect_language( $post['content'] ),
            ),
            'translations' => array(),
        );

        foreach ( $languages as $language ) {
            $result = $this->translate_content( $post_id, $language, $options );

            if ( $result && $result['success'] ) {
                $variants['translations'][ $language ] = array(
                    'post_id' => $result['translated_post_id'],
                    'language' => $language,
                );
            }
        }

        // 如果启用链接，可以在这里添加元数据关联
        if ( $options['link_variants'] ) {
            // 可以扩展实现：在数据库中保存各语言版本的关联关系
        }

        $this->logger->info( "创建多语言变体完成 #{$post_id}", array(
            'languages' => $languages,
            'success_count' => count( $variants['translations'] ),
        ) );

        return $variants;
    }
}
