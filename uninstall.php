<?php
/**
 * 插件卸载脚本
 *
 * 当插件被删除时执行此文件
 *
 * @package AI_Social_Content_Generator
 */

// 如果不是通过WordPress卸载调用,则退出
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * 删除数据库表
 */
function aiscg_delete_database_tables() {
    global $wpdb;

    $tables = array(
        $wpdb->prefix . 'aiscg_posts',
        $wpdb->prefix . 'aiscg_images',
        $wpdb->prefix . 'aiscg_settings',
        $wpdb->prefix . 'aiscg_logs',
        $wpdb->prefix . 'aiscg_templates',
        $wpdb->prefix . 'aiscg_versions',
        $wpdb->prefix . 'aiscg_rate_limits',
        $wpdb->prefix . 'aiscg_cache',
        $wpdb->prefix . 'aiscg_notifications',
        $wpdb->prefix . 'aiscg_media_library',
        $wpdb->prefix . 'aiscg_publishing_plans',
    );

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }
}

/**
 * 删除所有选项
 */
function aiscg_delete_options() {
    $options = array(
        'aiscg_version',
        'aiscg_activated_time',
        'aiscg_ai_config',
        'aiscg_image_config',
        'aiscg_content_templates',
        'aiscg_request_timeout',
        'aiscg_concurrent_requests',
        'aiscg_enable_logging',
        'aiscg_scheduler_config',
        'aiscg_scheduler_logs',
        'aiscg_batch_queue',
        'aiscg_content_retention_days',
        'aiscg_batch_config',
        'aiscg_export_config',
        'aiscg_analytics_config',
        'aiscg_logger_settings',
        'aiscg_cache_enabled',
        'aiscg_rate_limits',
        'aiscg_webhooks',
        'aiscg_email_notifications',
        'aiscg_keep_data_on_uninstall',
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }
}

/**
 * 删除上传的文件
 */
function aiscg_delete_uploaded_files() {
    $upload_dir = wp_upload_dir();

    // 删除内容图片目录
    $content_dir = $upload_dir['basedir'] . '/aiscg-content';
    if ( is_dir( $content_dir ) ) {
        aiscg_delete_directory( $content_dir );
    }

    // 删除图片目录
    $images_dir = $upload_dir['basedir'] . '/aiscg-images';
    if ( is_dir( $images_dir ) ) {
        aiscg_delete_directory( $images_dir );
    }

    // 删除导出目录
    $export_dir = $upload_dir['basedir'] . '/aiscg-exports';
    if ( is_dir( $export_dir ) ) {
        aiscg_delete_directory( $export_dir );
    }
}

/**
 * 递归删除目录
 *
 * @param string $dir 目录路径
 * @return bool
 */
function aiscg_delete_directory( $dir ) {
    if ( ! file_exists( $dir ) ) {
        return true;
    }

    if ( ! is_dir( $dir ) ) {
        return unlink( $dir );
    }

    foreach ( scandir( $dir ) as $item ) {
        if ( $item == '.' || $item == '..' ) {
            continue;
        }

        if ( ! aiscg_delete_directory( $dir . DIRECTORY_SEPARATOR . $item ) ) {
            return false;
        }
    }

    return rmdir( $dir );
}

/**
 * 清除所有计划任务
 */
function aiscg_clear_scheduled_tasks() {
    // 清除内容生成任务
    $timestamp = wp_next_scheduled( 'aiscg_scheduled_generation' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'aiscg_scheduled_generation' );
    }

    // 清除清理任务
    $timestamp = wp_next_scheduled( 'aiscg_cleanup_old_content' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'aiscg_cleanup_old_content' );
    }
}

// 确认是否要保留数据
$keep_data = get_option( 'aiscg_keep_data_on_uninstall', false );

if ( ! $keep_data ) {
    // 清除计划任务
    aiscg_clear_scheduled_tasks();

    // 删除数据库表
    aiscg_delete_database_tables();

    // 删除选项
    aiscg_delete_options();

    // 删除上传的文件
    aiscg_delete_uploaded_files();

    // 记录日志
    error_log( 'AISCG: Plugin uninstalled and all data removed' );
}
