<?php
/**
 * 智能问答机器人
 *
 * 提供AI驱动的客户服务和内容咨询
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 聊天机器人类
 */
class AISCG_Chatbot {

    /**
     * AI工厂
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
     * 机器人类型
     *
     * @var array
     */
    private $bot_types = array(
        'content_assistant' => '内容创作助手',
        'customer_service' => '客户服务',
        'platform_guide' => '平台操作指南',
        'seo_advisor' => 'SEO顾问',
        'general' => '通用问答',
    );

    /**
     * 对话上下文长度
     *
     * @var int
     */
    private $context_length = 5;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->ai_factory = new AISCG_AI_Service_Factory();
        $this->database = new AISCG_Database();
    }

    /**
     * 发送消息并获取回复
     *
     * @param string $message 用户消息
     * @param array $options 选项
     *   - session_id: 会话ID
     *   - bot_type: 机器人类型
     *   - context: 额外上下文
     *   - user_id: 用户ID
     *
     * @return array|WP_Error 回复或错误
     */
    public function send_message( $message, $options = array() ) {
        $defaults = array(
            'session_id' => null,
            'bot_type' => 'content_assistant',
            'context' => array(),
            'user_id' => get_current_user_id(),
        );
        $options = wp_parse_args( $options, $defaults );

        // 创建或获取会话
        if ( ! $options['session_id'] ) {
            $session_id = $this->create_session( $options['user_id'], $options['bot_type'] );
        } else {
            $session_id = $options['session_id'];
        }

        // 保存用户消息
        $this->save_message( $session_id, 'user', $message );

        // 获取对话历史
        $conversation_history = $this->get_conversation_history( $session_id, $this->context_length );

        // 检测用户意图
        $intent = $this->detect_intent( $message, $options['bot_type'] );

        // 生成回复
        $reply = $this->generate_reply( $message, $options['bot_type'], $conversation_history, $intent, $options['context'] );

        if ( is_wp_error( $reply ) ) {
            return $reply;
        }

        // 保存机器人回复
        $this->save_message( $session_id, 'bot', $reply, array( 'intent' => $intent ) );

        // 更新会话
        $this->update_session( $session_id );

        return array(
            'session_id' => $session_id,
            'message' => $message,
            'reply' => $reply,
            'intent' => $intent,
            'timestamp' => current_time( 'mysql' ),
        );
    }

    /**
     * 生成回复
     *
     * @param string $message 用户消息
     * @param string $bot_type 机器人类型
     * @param array $history 对话历史
     * @param string $intent 用户意图
     * @param array $context 额外上下文
     *
     * @return string|WP_Error 回复或错误
     */
    private function generate_reply( $message, $bot_type, $history, $intent, $context = array() ) {
        try {
            $ai_service = $this->ai_factory->get_service();

            // 构建系统提示
            $system_prompt = $this->get_system_prompt( $bot_type );

            // 构建对话提示
            $prompt = $system_prompt . "\n\n";

            // 添加对话历史
            if ( ! empty( $history ) ) {
                $prompt .= "对话历史:\n";
                foreach ( $history as $msg ) {
                    $role = $msg['role'] === 'user' ? '用户' : '助手';
                    $prompt .= "{$role}: {$msg['message']}\n";
                }
                $prompt .= "\n";
            }

            // 添加额外上下文
            if ( ! empty( $context ) ) {
                $prompt .= "相关信息:\n";
                foreach ( $context as $key => $value ) {
                    $prompt .= "- {$key}: {$value}\n";
                }
                $prompt .= "\n";
            }

            // 当前问题
            $prompt .= "用户: {$message}\n";
            $prompt .= "助手: ";

            // 根据意图添加特殊处理
            if ( $intent === 'how_to' ) {
                $prompt .= "\n[请提供详细的步骤说明]";
            } elseif ( $intent === 'recommendation' ) {
                $prompt .= "\n[请给出具体建议并说明理由]";
            }

            $reply = $ai_service->generate_content( $prompt );

            return $reply;

        } catch ( Exception $e ) {
            return new WP_Error( 'generation_failed', $e->getMessage() );
        }
    }

    /**
     * 获取系统提示
     *
     * @param string $bot_type 机器人类型
     *
     * @return string 系统提示
     */
    private function get_system_prompt( $bot_type ) {
        $prompts = array(
            'content_assistant' => "你是一个专业的内容创作助手，精通小红书和Instagram等社交媒体平台的内容创作。你的职责是帮助用户:\n- 生成吸引人的内容创意\n- 优化文案和标题\n- 选择合适的标签\n- 提供内容策略建议\n\n请始终保持友好、专业,提供实用的建议。",

            'customer_service' => "你是一个专业的客户服务机器人，帮助用户解决使用AI内容生成插件的问题。你应该:\n- 耐心解答用户疑问\n- 提供清晰的操作指导\n- 协助排查问题\n- 推荐合适的功能\n\n请始终保持礼貌、耐心,确保用户满意。",

            'platform_guide' => "你是一个熟悉各大社交媒体平台的专家，包括小红书、Instagram、微博等。你可以:\n- 解释平台规则和最佳实践\n- 推荐发布时间和频率\n- 分析平台算法偏好\n- 提供平台运营建议\n\n请提供准确、实用的平台知识。",

            'seo_advisor' => "你是一个SEO专家，专注于社交媒体内容的搜索优化。你可以:\n- 分析关键词策略\n- 优化内容SEO\n- 提供标签建议\n- 建议内容结构\n\n请提供专业的SEO指导。",

            'general' => "你是一个友好的AI助手，可以回答各类问题并提供帮助。请保持友好、专业,提供有价值的信息。",
        );

        return $prompts[ $bot_type ] ?? $prompts['general'];
    }

    /**
     * 检测用户意图
     *
     * @param string $message 用户消息
     * @param string $bot_type 机器人类型
     *
     * @return string 意图
     */
    private function detect_intent( $message, $bot_type ) {
        $message_lower = mb_strtolower( $message );

        // 常见意图匹配
        $intents = array(
            'greeting' => array( '你好', 'hello', 'hi', '您好', '嗨' ),
            'how_to' => array( '如何', '怎么', '怎样', 'how to', 'how do' ),
            'problem' => array( '问题', '错误', '失败', '不行', 'error', 'failed', '无法' ),
            'recommendation' => array( '推荐', '建议', '什么好', 'recommend', 'suggest' ),
            'comparison' => array( '对比', '比较', '区别', 'compare', 'difference' ),
            'explanation' => array( '是什么', '什么是', '解释', 'what is', 'explain' ),
        );

        foreach ( $intents as $intent => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( stripos( $message_lower, $keyword ) !== false ) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    /**
     * 创建会话
     *
     * @param int $user_id 用户ID
     * @param string $bot_type 机器人类型
     *
     * @return string 会话ID
     */
    private function create_session( $user_id, $bot_type ) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'aiscg_chatbot_sessions';

        $session_id = uniqid( 'chat_session_' );

        $wpdb->insert(
            $table_sessions,
            array(
                'session_id' => $session_id,
                'user_id' => $user_id,
                'bot_type' => $bot_type,
                'status' => 'active',
                'started_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            )
        );

        return $session_id;
    }

    /**
     * 更新会话
     *
     * @param string $session_id 会话ID
     */
    private function update_session( $session_id ) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'aiscg_chatbot_sessions';

        $wpdb->update(
            $table_sessions,
            array(
                'message_count' => new WP_Query( "message_count + 1" ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'session_id' => $session_id )
        );
    }

    /**
     * 保存消息
     *
     * @param string $session_id 会话ID
     * @param string $role 角色 (user/bot)
     * @param string $message 消息
     * @param array $metadata 元数据
     */
    private function save_message( $session_id, $role, $message, $metadata = array() ) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aiscg_chatbot_messages';

        $wpdb->insert(
            $table_messages,
            array(
                'session_id' => $session_id,
                'role' => $role,
                'message' => $message,
                'metadata' => wp_json_encode( $metadata ),
                'created_at' => current_time( 'mysql' ),
            )
        );
    }

    /**
     * 获取对话历史
     *
     * @param string $session_id 会话ID
     * @param int $limit 限制数量
     *
     * @return array 对话历史
     */
    private function get_conversation_history( $session_id, $limit = 5 ) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aiscg_chatbot_messages';

        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, message FROM $table_messages
                WHERE session_id = %s
                ORDER BY created_at DESC
                LIMIT %d",
                $session_id, $limit
            ),
            ARRAY_A
        );

        return array_reverse( $messages );
    }

    /**
     * 获取会话详情
     *
     * @param string $session_id 会话ID
     *
     * @return array|null 会话详情
     */
    public function get_session( $session_id ) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'aiscg_chatbot_sessions';
        $table_messages = $wpdb->prefix . 'aiscg_chatbot_messages';

        $session = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_sessions WHERE session_id = %s", $session_id ),
            ARRAY_A
        );

        if ( ! $session ) {
            return null;
        }

        // 获取所有消息
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_messages WHERE session_id = %s ORDER BY created_at ASC",
                $session_id
            ),
            ARRAY_A
        );

        foreach ( $messages as &$message ) {
            $message['metadata'] = json_decode( $message['metadata'], true );
        }

        $session['messages'] = $messages;

        return $session;
    }

    /**
     * 获取用户的所有会话
     *
     * @param int $user_id 用户ID
     * @param array $args 查询参数
     *
     * @return array 会话列表
     */
    public function get_user_sessions( $user_id, $args = array() ) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'aiscg_chatbot_sessions';

        $defaults = array(
            'status' => 'active',
            'limit' => 20,
            'offset' => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        // 确保数值类型
        $user_id = intval( $user_id );
        $limit = intval( $args['limit'] );
        $offset = intval( $args['offset'] );

        if ( $args['status'] ) {
            $sessions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_sessions
                    WHERE user_id = %d AND status = %s
                    ORDER BY updated_at DESC
                    LIMIT %d OFFSET %d",
                    $user_id,
                    $args['status'],
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
        } else {
            $sessions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_sessions
                    WHERE user_id = %d
                    ORDER BY updated_at DESC
                    LIMIT %d OFFSET %d",
                    $user_id,
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
        }

        return $sessions;
    }

    /**
     * 结束会话
     *
     * @param string $session_id 会话ID
     *
     * @return bool 是否成功
     */
    public function end_session( $session_id ) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'aiscg_chatbot_sessions';

        $result = $wpdb->update(
            $table_sessions,
            array(
                'status' => 'ended',
                'ended_at' => current_time( 'mysql' ),
            ),
            array( 'session_id' => $session_id )
        );

        return $result !== false;
    }

    /**
     * 获取快速回复建议
     *
     * @param string $bot_type 机器人类型
     *
     * @return array 快速回复列表
     */
    public function get_quick_replies( $bot_type = 'content_assistant' ) {
        $quick_replies = array(
            'content_assistant' => array(
                '如何写出吸引人的标题?',
                '推荐一些热门标签',
                '什么时候发布内容最好?',
                '如何提高内容互动率?',
            ),
            'customer_service' => array(
                '如何开始使用?',
                '如何配置AI模型?',
                '如何生成内容?',
                '遇到问题怎么办?',
            ),
            'platform_guide' => array(
                '小红书的最佳实践是什么?',
                'Instagram发布时间建议',
                '各平台的标签策略',
                '如何选择合适的平台?',
            ),
            'seo_advisor' => array(
                '如何优化关键词?',
                '怎么提高搜索排名?',
                '标签应该怎么选?',
                'SEO最佳实践',
            ),
        );

        return $quick_replies[ $bot_type ] ?? $quick_replies['content_assistant'];
    }

    /**
     * 获取使用统计
     *
     * @param int $user_id 用户ID
     *
     * @return array 统计数据
     */
    public function get_usage_stats( $user_id = null ) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'aiscg_chatbot_sessions';
        $table_messages = $wpdb->prefix . 'aiscg_chatbot_messages';

        $stats = array();

        if ( $user_id ) {
            $user_id = intval( $user_id );
            $stats['total_sessions'] = $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM $table_sessions WHERE user_id = %d", $user_id )
            );
            $stats['active_sessions'] = $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM $table_sessions WHERE user_id = %d AND status = 'active'", $user_id )
            );
            $stats['total_messages'] = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_messages
                    WHERE session_id IN (SELECT session_id FROM $table_sessions WHERE user_id = %d)",
                    $user_id
                )
            );
        } else {
            $stats['total_sessions'] = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sessions" );
            $stats['active_sessions'] = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sessions WHERE status = 'active'" );
            $stats['total_messages'] = $wpdb->get_var( "SELECT COUNT(*) FROM $table_messages" );
        }

        $stats['avg_messages_per_session'] = 0;
        if ( $stats['total_sessions'] > 0 ) {
            $stats['avg_messages_per_session'] = round( $stats['total_messages'] / $stats['total_sessions'], 2 );
        }

        return $stats;
    }

    /**
     * 评价回复
     *
     * @param int $message_id 消息ID
     * @param string $rating 评价 (helpful/not_helpful)
     * @param string $feedback 反馈
     *
     * @return bool 是否成功
     */
    public function rate_reply( $message_id, $rating, $feedback = '' ) {
        global $wpdb;
        $table_messages = $wpdb->prefix . 'aiscg_chatbot_messages';

        $metadata = $wpdb->get_var(
            $wpdb->prepare( "SELECT metadata FROM $table_messages WHERE id = %d", $message_id )
        );

        $metadata = json_decode( $metadata, true ) ?: array();
        $metadata['rating'] = $rating;
        $metadata['feedback'] = $feedback;
        $metadata['rated_at'] = current_time( 'mysql' );

        $result = $wpdb->update(
            $table_messages,
            array( 'metadata' => wp_json_encode( $metadata ) ),
            array( 'id' => $message_id )
        );

        return $result !== false;
    }

    /**
     * 创建聊天机器人表
     */
    private function create_chatbot_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 会话表
        $table_sessions = $wpdb->prefix . 'aiscg_chatbot_sessions';
        $sql1 = "CREATE TABLE IF NOT EXISTS $table_sessions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            user_id bigint(20) NOT NULL,
            bot_type varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'active',
            message_count int DEFAULT 0,
            started_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            ended_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        // 消息表
        $table_messages = $wpdb->prefix . 'aiscg_chatbot_messages';
        $sql2 = "CREATE TABLE IF NOT EXISTS $table_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            role varchar(20) NOT NULL,
            message longtext NOT NULL,
            metadata longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 );
        dbDelta( $sql2 );
    }
}
