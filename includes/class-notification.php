<?php
/**
 * Notification Class
 *
 * 处理Webhook通知和邮件通知
 *
 * @package AI_Social_Content_Generator
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Notification Class
 */
class AISCG_Notification {

    /**
     * 日志对象
     *
     * @var AISCG_Logger
     */
    private $logger;

    /**
     * 通知历史表名
     *
     * @var string
     */
    private $table_name;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aiscg_notifications';
        $this->logger = new AISCG_Logger();
    }

    /**
     * 发送Webhook通知
     *
     * @param string $event 事件名称
     * @param array $data 数据
     * @return bool 是否成功
     */
    public function send_webhook( $event, $data ) {
        $webhooks = get_option( 'aiscg_webhooks', array() );

        if ( empty( $webhooks ) ) {
            return false;
        }

        $sent = false;

        foreach ( $webhooks as $webhook ) {
            if ( ! $webhook['enabled'] ) {
                continue;
            }

            // 检查事件过滤
            if ( ! empty( $webhook['events'] ) && ! in_array( $event, $webhook['events'], true ) ) {
                continue;
            }

            $payload = array(
                'event' => $event,
                'data' => $data,
                'timestamp' => current_time( 'mysql' ),
                'site_url' => get_site_url(),
            );

            $result = $this->send_webhook_request( $webhook['url'], $payload, $webhook );

            if ( $result ) {
                $sent = true;
                $this->logger->info( "Webhook发送成功: {$event}", array(
                    'url' => $webhook['url'],
                    'event' => $event,
                ) );
            }
        }

        return $sent;
    }

    /**
     * 发送Webhook请求
     *
     * @param string $url Webhook URL
     * @param array $payload 数据
     * @param array $config 配置
     * @return bool 是否成功
     */
    private function send_webhook_request( $url, $payload, $config = array() ) {
        $headers = array(
            'Content-Type' => 'application/json',
        );

        // 添加自定义头部
        if ( ! empty( $config['headers'] ) ) {
            $headers = array_merge( $headers, $config['headers'] );
        }

        // 添加签名
        if ( ! empty( $config['secret'] ) ) {
            $signature = hash_hmac( 'sha256', wp_json_encode( $payload ), $config['secret'] );
            $headers['X-AISCG-Signature'] = $signature;
        }

        $args = array(
            'headers' => $headers,
            'body' => wp_json_encode( $payload ),
            'timeout' => 15,
            'blocking' => true,
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Webhook发送失败', array(
                'url' => $url,
                'error' => $response->get_error_message(),
            ) );

            $this->record_notification( 'webhook', $url, false, array(
                'error' => $response->get_error_message(),
            ) );

            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code >= 200 && $status_code < 300 ) {
            $this->record_notification( 'webhook', $url, true, array(
                'status_code' => $status_code,
            ) );
            return true;
        }

        $this->logger->error( 'Webhook返回错误状态码', array(
            'url' => $url,
            'status_code' => $status_code,
        ) );

        $this->record_notification( 'webhook', $url, false, array(
            'status_code' => $status_code,
        ) );

        return false;
    }

    /**
     * 发送邮件通知
     *
     * @param string $event 事件名称
     * @param array $data 数据
     * @return bool 是否成功
     */
    public function send_email( $event, $data ) {
        $email_settings = get_option( 'aiscg_email_notifications', array() );

        if ( empty( $email_settings['enabled'] ) ) {
            return false;
        }

        // 检查事件过滤
        if ( ! empty( $email_settings['events'] ) && ! in_array( $event, $email_settings['events'], true ) ) {
            return false;
        }

        $to = isset( $email_settings['recipients'] ) ? $email_settings['recipients'] : get_option( 'admin_email' );
        $subject = $this->get_email_subject( $event, $data );
        $message = $this->get_email_message( $event, $data );

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( $sent ) {
            $this->logger->info( "邮件通知发送成功: {$event}", array(
                'to' => $to,
                'event' => $event,
            ) );

            $this->record_notification( 'email', $to, true, array(
                'event' => $event,
            ) );
        } else {
            $this->logger->error( "邮件通知发送失败: {$event}", array(
                'to' => $to,
            ) );

            $this->record_notification( 'email', $to, false, array(
                'event' => $event,
            ) );
        }

        return $sent;
    }

    /**
     * 获取邮件主题
     *
     * @param string $event 事件名称
     * @param array $data 数据
     * @return string 邮件主题
     */
    private function get_email_subject( $event, $data ) {
        $site_name = get_bloginfo( 'name' );

        switch ( $event ) {
            case 'content_generated':
                return "[{$site_name}] 新内容已生成";

            case 'batch_completed':
                return "[{$site_name}] 批量任务已完成";

            case 'scheduled_completed':
                return "[{$site_name}] 定时任务已完成";

            case 'error_occurred':
                return "[{$site_name}] 发生错误";

            default:
                return "[{$site_name}] 插件通知";
        }
    }

    /**
     * 获取邮件内容
     *
     * @param string $event 事件名称
     * @param array $data 数据
     * @return string 邮件内容
     */
    private function get_email_message( $event, $data ) {
        $message = '<html><body>';
        $message .= '<h2>AI Social Content Generator - 通知</h2>';
        $message .= '<p><strong>事件:</strong> ' . esc_html( $event ) . '</p>';
        $message .= '<p><strong>时间:</strong> ' . current_time( 'Y-m-d H:i:s' ) . '</p>';
        $message .= '<hr>';

        switch ( $event ) {
            case 'content_generated':
                $message .= '<h3>内容生成成功</h3>';
                $message .= '<p><strong>标题:</strong> ' . esc_html( $data['title'] ?? '' ) . '</p>';
                $message .= '<p><strong>平台:</strong> ' . esc_html( $data['platform'] ?? '' ) . '</p>';
                $message .= '<p><strong>AI模型:</strong> ' . esc_html( $data['ai_model'] ?? '' ) . '</p>';
                break;

            case 'batch_completed':
                $message .= '<h3>批量任务完成</h3>';
                $message .= '<p><strong>总数:</strong> ' . intval( $data['total'] ?? 0 ) . '</p>';
                $message .= '<p><strong>成功:</strong> ' . intval( $data['success'] ?? 0 ) . '</p>';
                $message .= '<p><strong>失败:</strong> ' . intval( $data['failed'] ?? 0 ) . '</p>';
                break;

            case 'scheduled_completed':
                $message .= '<h3>定时任务完成</h3>';
                $message .= '<p><strong>生成数量:</strong> ' . intval( $data['count'] ?? 0 ) . '</p>';
                break;

            case 'error_occurred':
                $message .= '<h3>错误信息</h3>';
                $message .= '<p><strong>错误:</strong> ' . esc_html( $data['error'] ?? '' ) . '</p>';
                break;

            default:
                $message .= '<pre>' . esc_html( wp_json_encode( $data, JSON_PRETTY_PRINT ) ) . '</pre>';
        }

        $message .= '<hr>';
        $message .= '<p><a href="' . admin_url( 'admin.php?page=aiscg-generator' ) . '">前往插件管理页面</a></p>';
        $message .= '</body></html>';

        return $message;
    }

    /**
     * 记录通知历史
     *
     * @param string $type 通知类型
     * @param string $recipient 接收者
     * @param bool $success 是否成功
     * @param array $metadata 元数据
     * @return bool 是否成功
     */
    private function record_notification( $type, $recipient, $success, $metadata = array() ) {
        global $wpdb;

        $data = array(
            'type' => sanitize_text_field( $type ),
            'recipient' => sanitize_text_field( $recipient ),
            'success' => intval( $success ),
            'metadata' => wp_json_encode( $metadata ),
            'created_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array( '%s', '%s', '%d', '%s', '%s' )
        );

        return $result !== false;
    }

    /**
     * 获取通知历史
     *
     * @param array $args 查询参数
     * @return array 通知列表
     */
    public function get_notifications( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'type' => '',
            'success' => '',
            'limit' => 100,
            'offset' => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $where_values = array();

        if ( ! empty( $args['type'] ) ) {
            $where[] = 'type = %s';
            $where_values[] = $args['type'];
        }

        if ( $args['success'] !== '' ) {
            $where[] = 'success = %d';
            $where_values[] = intval( $args['success'] );
        }

        $where_clause = implode( ' AND ', $where );

        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge( $where_values, array( $args['limit'], $args['offset'] ) )
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $args['limit'],
                $args['offset']
            );
        }

        $notifications = $wpdb->get_results( $sql, ARRAY_A );

        foreach ( $notifications as &$notification ) {
            if ( ! empty( $notification['metadata'] ) ) {
                $notification['metadata'] = json_decode( $notification['metadata'], true );
            }
        }

        return $notifications;
    }

    /**
     * 测试Webhook
     *
     * @param string $url Webhook URL
     * @param array $config 配置
     * @return array 测试结果
     */
    public function test_webhook( $url, $config = array() ) {
        $payload = array(
            'event' => 'test',
            'data' => array(
                'message' => 'This is a test notification from AI Social Content Generator',
            ),
            'timestamp' => current_time( 'mysql' ),
            'site_url' => get_site_url(),
        );

        $start_time = microtime( true );
        $result = $this->send_webhook_request( $url, $payload, $config );
        $duration = microtime( true ) - $start_time;

        return array(
            'success' => $result,
            'duration' => round( $duration, 3 ),
            'url' => $url,
        );
    }

    /**
     * 清理旧通知记录
     *
     * @param int $days 保留天数
     * @return int 删除的条数
     */
    public function cleanup_old_notifications( $days = 30 ) {
        global $wpdb;

        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );

        if ( $deleted ) {
            $this->logger->info( "清理旧通知记录完成: {$deleted} 条" );
        }

        return $deleted;
    }
}
