<?php
/**
 * Logger Class
 *
 * 处理插件的日志记录、错误追踪和调试信息
 *
 * @package AI_Social_Content_Generator
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Logger Class
 */
class AISCG_Logger {

    /**
     * 日志级别常量
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';

    /**
     * 日志表名
     *
     * @var string
     */
    private $table_name;

    /**
     * 是否启用日志
     *
     * @var bool
     */
    private $enabled;

    /**
     * 日志保留天数
     *
     * @var int
     */
    private $retention_days;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aiscg_logs';

        $settings = get_option( 'aiscg_logger_settings', array() );
        $this->enabled = isset( $settings['enabled'] ) ? $settings['enabled'] : true;
        $this->retention_days = isset( $settings['retention_days'] ) ? intval( $settings['retention_days'] ) : 30;
    }

    /**
     * 记录调试信息
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return int|false 插入的行ID或false
     */
    public function debug( $message, $context = array() ) {
        return $this->log( self::LEVEL_DEBUG, $message, $context );
    }

    /**
     * 记录一般信息
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return int|false 插入的行ID或false
     */
    public function info( $message, $context = array() ) {
        return $this->log( self::LEVEL_INFO, $message, $context );
    }

    /**
     * 记录警告信息
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return int|false 插入的行ID或false
     */
    public function warning( $message, $context = array() ) {
        return $this->log( self::LEVEL_WARNING, $message, $context );
    }

    /**
     * 记录错误信息
     *
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return int|false 插入的行ID或false
     */
    public function error( $message, $context = array() ) {
        return $this->log( self::LEVEL_ERROR, $message, $context );
    }

    /**
     * 记录日志
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return int|false 插入的行ID或false
     */
    private function log( $level, $message, $context = array() ) {
        if ( ! $this->enabled ) {
            return false;
        }

        global $wpdb;

        $data = array(
            'level' => sanitize_text_field( $level ),
            'message' => sanitize_text_field( $message ),
            'context' => wp_json_encode( $context ),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'created_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array( '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * 获取日志列表
     *
     * @param array $args 查询参数
     * @return array 日志列表
     */
    public function get_logs( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'level' => '',
            'start_date' => '',
            'end_date' => '',
            'search' => '',
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $where_values = array();

        if ( ! empty( $args['level'] ) ) {
            $where[] = 'level = %s';
            $where_values[] = $args['level'];
        }

        if ( ! empty( $args['start_date'] ) ) {
            $where[] = 'created_at >= %s';
            $where_values[] = $args['start_date'];
        }

        if ( ! empty( $args['end_date'] ) ) {
            $where[] = 'created_at <= %s';
            $where_values[] = $args['end_date'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where[] = 'message LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        }

        $where_clause = implode( ' AND ', $where );

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'created_at DESC';
        }

        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
                array_merge( $where_values, array( $limit, $offset ) )
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        }

        $logs = $wpdb->get_results( $sql, ARRAY_A );

        // 解析context JSON
        foreach ( $logs as &$log ) {
            if ( ! empty( $log['context'] ) ) {
                $log['context'] = json_decode( $log['context'], true );
            }
        }

        return $logs;
    }

    /**
     * 获取日志统计
     *
     * @param array $args 查询参数
     * @return array 统计数据
     */
    public function get_stats( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'start_date' => date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) ),
            'end_date' => current_time( 'mysql' ),
        );

        $args = wp_parse_args( $args, $defaults );

        // 按级别统计
        $level_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT level, COUNT(*) as count
                FROM {$this->table_name}
                WHERE created_at BETWEEN %s AND %s
                GROUP BY level",
                $args['start_date'],
                $args['end_date']
            ),
            ARRAY_A
        );

        // 按日期统计
        $date_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                FROM {$this->table_name}
                WHERE created_at BETWEEN %s AND %s
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 30",
                $args['start_date'],
                $args['end_date']
            ),
            ARRAY_A
        );

        // 总数
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at BETWEEN %s AND %s",
                $args['start_date'],
                $args['end_date']
            )
        );

        return array(
            'total' => intval( $total ),
            'by_level' => $level_stats,
            'by_date' => $date_stats,
        );
    }

    /**
     * 清理旧日志
     *
     * @return int 删除的行数
     */
    public function cleanup_old_logs() {
        global $wpdb;

        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$this->retention_days} days" ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );

        if ( $deleted ) {
            $this->info( "Cleaned up {$deleted} old log entries", array(
                'cutoff_date' => $cutoff_date,
                'retention_days' => $this->retention_days,
            ) );
        }

        return $deleted;
    }

    /**
     * 清空所有日志
     *
     * @return bool 是否成功
     */
    public function clear_all_logs() {
        global $wpdb;

        $result = $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

        return $result !== false;
    }

    /**
     * 导出日志
     *
     * @param array $args 查询参数
     * @param string $format 导出格式 (csv, json, txt)
     * @return string|false 导出文件路径或false
     */
    public function export_logs( $args = array(), $format = 'csv' ) {
        $logs = $this->get_logs( array_merge( $args, array( 'limit' => 10000 ) ) );

        if ( empty( $logs ) ) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/aiscg-exports/logs/';

        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
        }

        $filename = 'logs_' . date( 'Y-m-d_H-i-s' ) . '.' . $format;
        $filepath = $export_dir . $filename;

        switch ( $format ) {
            case 'csv':
                $this->export_to_csv( $logs, $filepath );
                break;
            case 'json':
                $this->export_to_json( $logs, $filepath );
                break;
            case 'txt':
                $this->export_to_txt( $logs, $filepath );
                break;
            default:
                return false;
        }

        return $filepath;
    }

    /**
     * 导出为CSV
     *
     * @param array $logs 日志数据
     * @param string $filepath 文件路径
     */
    private function export_to_csv( $logs, $filepath ) {
        $fp = fopen( $filepath, 'w' );

        // 写入BOM以支持中文
        fprintf( $fp, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        // CSV标题
        fputcsv( $fp, array( 'ID', 'Level', 'Message', 'User ID', 'IP Address', 'Created At' ) );

        foreach ( $logs as $log ) {
            fputcsv( $fp, array(
                $log['id'],
                $log['level'],
                $log['message'],
                $log['user_id'],
                $log['ip_address'],
                $log['created_at'],
            ) );
        }

        fclose( $fp );
    }

    /**
     * 导出为JSON
     *
     * @param array $logs 日志数据
     * @param string $filepath 文件路径
     */
    private function export_to_json( $logs, $filepath ) {
        file_put_contents( $filepath, wp_json_encode( $logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    }

    /**
     * 导出为TXT
     *
     * @param array $logs 日志数据
     * @param string $filepath 文件路径
     */
    private function export_to_txt( $logs, $filepath ) {
        $content = '';

        foreach ( $logs as $log ) {
            $content .= sprintf(
                "[%s] [%s] %s (User: %d, IP: %s)\n",
                $log['created_at'],
                $log['level'],
                $log['message'],
                $log['user_id'],
                $log['ip_address']
            );

            if ( ! empty( $log['context'] ) ) {
                $content .= "Context: " . wp_json_encode( $log['context'], JSON_UNESCAPED_UNICODE ) . "\n";
            }

            $content .= str_repeat( '-', 80 ) . "\n";
        }

        file_put_contents( $filepath, $content );
    }

    /**
     * 获取客户端IP地址
     *
     * @return string IP地址
     */
    private function get_client_ip() {
        $ip = '';

        if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return $ip;
    }

    /**
     * 记录API调用
     *
     * @param string $service AI服务名称
     * @param string $model 模型名称
     * @param bool $success 是否成功
     * @param float $duration 耗时（秒）
     * @param array $extra 额外信息
     */
    public function log_api_call( $service, $model, $success, $duration, $extra = array() ) {
        $context = array_merge( array(
            'service' => $service,
            'model' => $model,
            'success' => $success,
            'duration' => round( $duration, 3 ),
        ), $extra );

        $message = sprintf(
            'API调用 %s - %s - %s (耗时: %.3fs)',
            $service,
            $model,
            $success ? '成功' : '失败',
            $duration
        );

        if ( $success ) {
            $this->info( $message, $context );
        } else {
            $this->error( $message, $context );
        }
    }

    /**
     * 记录内容生成
     *
     * @param int $post_id 内容ID
     * @param string $platform 平台
     * @param string $ai_model AI模型
     * @param bool $success 是否成功
     */
    public function log_content_generation( $post_id, $platform, $ai_model, $success ) {
        $message = sprintf(
            '内容生成 #%d - %s - %s - %s',
            $post_id,
            $platform,
            $ai_model,
            $success ? '成功' : '失败'
        );

        $context = array(
            'post_id' => $post_id,
            'platform' => $platform,
            'ai_model' => $ai_model,
            'success' => $success,
        );

        if ( $success ) {
            $this->info( $message, $context );
        } else {
            $this->error( $message, $context );
        }
    }
}
