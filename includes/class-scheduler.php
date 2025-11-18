<?php
/**
 * 定时任务调度器类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 定时任务调度器类
 */
class AISCG_Scheduler {

    /**
     * 定时任务钩子名称
     *
     * @var string
     */
    const CRON_HOOK = 'aiscg_scheduled_generation';

    /**
     * 清理任务钩子名称
     *
     * @var string
     */
    const CLEANUP_HOOK = 'aiscg_cleanup_old_content';

    /**
     * 数据库实例
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 批量处理器
     *
     * @var AISCG_Batch_Processor
     */
    private $batch_processor;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = new AISCG_Database();
        $this->batch_processor = new AISCG_Batch_Processor();

        // 注册钩子
        add_action( self::CRON_HOOK, array( $this, 'run_scheduled_generation' ) );
        add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_old_content' ) );

        // 注册自定义定时计划
        add_filter( 'cron_schedules', array( $this, 'add_custom_schedules' ) );
    }

    /**
     * 添加自定义定时计划
     *
     * @param array $schedules 现有计划
     * @return array 更新后的计划
     */
    public function add_custom_schedules( $schedules ) {
        // 每2小时
        $schedules['aiscg_two_hours'] = array(
            'interval' => 2 * HOUR_IN_SECONDS,
            'display' => __( 'Every 2 Hours', 'ai-social-content-generator' ),
        );

        // 每6小时
        $schedules['aiscg_six_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __( 'Every 6 Hours', 'ai-social-content-generator' ),
        );

        // 每12小时
        $schedules['aiscg_twelve_hours'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __( 'Every 12 Hours', 'ai-social-content-generator' ),
        );

        return $schedules;
    }

    /**
     * 启用定时生成
     *
     * @param array $config 配置
     * @return bool
     */
    public function enable_scheduled_generation( $config ) {
        // 验证配置
        $defaults = array(
            'enabled' => true,
            'frequency' => 'daily',
            'topic_pool' => array(),
            'platform' => 'xiaohongshu',
            'ai_model' => '',
            'image_count' => 1,
            'per_run' => 5, // 每次运行生成数量
        );

        $config = wp_parse_args( $config, $defaults );

        // 保存配置
        update_option( 'aiscg_scheduler_config', $config );

        // 清除现有计划
        $this->disable_scheduled_generation();

        // 如果启用,添加新计划
        if ( $config['enabled'] ) {
            $timestamp = wp_next_scheduled( self::CRON_HOOK );

            if ( ! $timestamp ) {
                // 计划从下一个整点开始
                $start_time = strtotime( '+1 hour', current_time( 'timestamp' ) );
                $start_time = strtotime( date( 'Y-m-d H:00:00', $start_time ) );

                wp_schedule_event( $start_time, $config['frequency'], self::CRON_HOOK );

                // 记录日志
                if ( get_option( 'aiscg_enable_logging', false ) ) {
                    error_log( 'AISCG Scheduler: Enabled scheduled generation with frequency: ' . $config['frequency'] );
                }
            }

            return true;
        }

        return false;
    }

    /**
     * 禁用定时生成
     */
    public function disable_scheduled_generation() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );

            // 记录日志
            if ( get_option( 'aiscg_enable_logging', false ) ) {
                error_log( 'AISCG Scheduler: Disabled scheduled generation' );
            }
        }

        // 更新配置
        $config = get_option( 'aiscg_scheduler_config', array() );
        $config['enabled'] = false;
        update_option( 'aiscg_scheduler_config', $config );
    }

    /**
     * 运行定时生成任务
     */
    public function run_scheduled_generation() {
        $config = get_option( 'aiscg_scheduler_config', array() );

        if ( empty( $config['enabled'] ) || empty( $config['topic_pool'] ) ) {
            return;
        }

        // 记录开始
        if ( get_option( 'aiscg_enable_logging', false ) ) {
            error_log( 'AISCG Scheduler: Starting scheduled generation run' );
        }

        // 随机选择主题
        $topics = $this->select_topics( $config['topic_pool'], $config['per_run'] );

        if ( empty( $topics ) ) {
            error_log( 'AISCG Scheduler: No topics selected' );
            return;
        }

        // 批量生成
        try {
            $options = array(
                'platform' => $config['platform'],
                'ai_model' => $config['ai_model'],
                'image_count' => $config['image_count'],
                'delay' => 2, // 延迟2秒避免API限流
            );

            $results = $this->batch_processor->start_batch( $topics, $options );

            // 记录结果
            $this->log_scheduled_run( $results );

            // 记录日志
            if ( get_option( 'aiscg_enable_logging', false ) ) {
                error_log( sprintf(
                    'AISCG Scheduler: Completed run - Success: %d, Failed: %d',
                    $results['success'],
                    $results['failed']
                ) );
            }

        } catch ( Exception $e ) {
            error_log( 'AISCG Scheduler Error: ' . $e->getMessage() );
        }
    }

    /**
     * 从主题池选择主题
     *
     * @param array $topic_pool 主题池
     * @param int   $count      选择数量
     * @return array 选中的主题
     */
    private function select_topics( $topic_pool, $count ) {
        if ( count( $topic_pool ) <= $count ) {
            return $topic_pool;
        }

        // 随机选择
        $keys = array_rand( $topic_pool, $count );

        if ( ! is_array( $keys ) ) {
            $keys = array( $keys );
        }

        $selected = array();
        foreach ( $keys as $key ) {
            $selected[] = $topic_pool[ $key ];
        }

        return $selected;
    }

    /**
     * 记录定时运行结果
     *
     * @param array $results 运行结果
     */
    private function log_scheduled_run( $results ) {
        $logs = get_option( 'aiscg_scheduler_logs', array() );

        $log_entry = array(
            'timestamp' => current_time( 'mysql' ),
            'batch_id' => $results['batch_id'],
            'total' => $results['total'],
            'success' => $results['success'],
            'failed' => $results['failed'],
        );

        array_unshift( $logs, $log_entry );

        // 只保留最近30条日志
        $logs = array_slice( $logs, 0, 30 );

        update_option( 'aiscg_scheduler_logs', $logs );
    }

    /**
     * 获取定时任务日志
     *
     * @param int $limit 限制数量
     * @return array 日志列表
     */
    public function get_scheduler_logs( $limit = 20 ) {
        $logs = get_option( 'aiscg_scheduler_logs', array() );

        if ( $limit > 0 ) {
            $logs = array_slice( $logs, 0, $limit );
        }

        return $logs;
    }

    /**
     * 获取下次运行时间
     *
     * @return int|false 时间戳或false
     */
    public function get_next_run_time() {
        return wp_next_scheduled( self::CRON_HOOK );
    }

    /**
     * 获取定时任务状态
     *
     * @return array 状态信息
     */
    public function get_status() {
        $config = get_option( 'aiscg_scheduler_config', array() );
        $next_run = $this->get_next_run_time();

        return array(
            'enabled' => ! empty( $config['enabled'] ),
            'frequency' => isset( $config['frequency'] ) ? $config['frequency'] : 'daily',
            'next_run' => $next_run,
            'next_run_formatted' => $next_run ? date( 'Y-m-d H:i:s', $next_run ) : 'Not scheduled',
            'topic_count' => isset( $config['topic_pool'] ) ? count( $config['topic_pool'] ) : 0,
            'per_run' => isset( $config['per_run'] ) ? $config['per_run'] : 5,
        );
    }

    /**
     * 启用清理任务
     */
    public function enable_cleanup_task() {
        $timestamp = wp_next_scheduled( self::CLEANUP_HOOK );

        if ( ! $timestamp ) {
            // 每天凌晨3点运行
            $start_time = strtotime( 'tomorrow 03:00:00', current_time( 'timestamp' ) );
            wp_schedule_event( $start_time, 'daily', self::CLEANUP_HOOK );

            // 记录日志
            if ( get_option( 'aiscg_enable_logging', false ) ) {
                error_log( 'AISCG Scheduler: Enabled cleanup task' );
            }
        }
    }

    /**
     * 禁用清理任务
     */
    public function disable_cleanup_task() {
        $timestamp = wp_next_scheduled( self::CLEANUP_HOOK );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
        }
    }

    /**
     * 清理旧内容
     */
    public function cleanup_old_content() {
        $retention_days = get_option( 'aiscg_content_retention_days', 30 );

        if ( $retention_days <= 0 ) {
            return; // 永久保留
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aiscg_posts';

        // 删除旧内容
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s AND status = 'archived'",
                $cutoff_date
            )
        );

        // 清理孤立的图片
        $this->cleanup_orphan_images();

        // 清理批处理记录
        $this->batch_processor->cleanup_old_batches( $retention_days );

        // 记录日志
        if ( get_option( 'aiscg_enable_logging', false ) ) {
            error_log( sprintf( 'AISCG Cleanup: Deleted %d old posts', $deleted ) );
        }
    }

    /**
     * 清理孤立的图片
     */
    private function cleanup_orphan_images() {
        global $wpdb;

        $images_table = $wpdb->prefix . 'aiscg_images';
        $posts_table = $wpdb->prefix . 'aiscg_posts';

        // 查找孤立的图片
        $orphan_images = $wpdb->get_results(
            "SELECT i.* FROM {$images_table} i
            LEFT JOIN {$posts_table} p ON i.post_id = p.id
            WHERE p.id IS NULL"
        );

        foreach ( $orphan_images as $image ) {
            // 删除文件
            if ( file_exists( $image->image_path ) ) {
                unlink( $image->image_path );
            }

            // 删除数据库记录
            $wpdb->delete( $images_table, array( 'id' => $image->id ) );
        }

        // 记录日志
        if ( get_option( 'aiscg_enable_logging', false ) && count( $orphan_images ) > 0 ) {
            error_log( sprintf( 'AISCG Cleanup: Deleted %d orphan images', count( $orphan_images ) ) );
        }
    }

    /**
     * 手动触发定时任务(用于测试)
     */
    public function trigger_manual_run() {
        do_action( self::CRON_HOOK );
    }
}
