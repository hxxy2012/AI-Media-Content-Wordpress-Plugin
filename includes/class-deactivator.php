<?php
/**
 * 插件停用时触发
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 插件停用类
 */
class AISCG_Deactivator {

    /**
     * 插件停用时执行
     *
     * 清理计划任务和临时数据
     */
    public static function deactivate() {
        // 清除所有计划任务
        self::clear_scheduled_events();

        // 刷新重写规则
        flush_rewrite_rules();

        // 记录日志
        error_log( 'AISCG: Plugin deactivated' );
    }

    /**
     * 清除所有计划任务
     */
    private static function clear_scheduled_events() {
        // 清除自动生成内容的计划任务
        $timestamp = wp_next_scheduled( 'aiscg_auto_generate_content' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'aiscg_auto_generate_content' );
        }

        // 清除清理临时文件的计划任务
        $timestamp = wp_next_scheduled( 'aiscg_cleanup_temp_files' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'aiscg_cleanup_temp_files' );
        }

        error_log( 'AISCG: Scheduled events cleared' );
    }

    /**
     * 卸载插件时调用(可选)
     * 如果需要在卸载时删除所有数据,可以创建一个uninstall.php文件
     */
    public static function uninstall() {
        // 注意:这个方法应该在uninstall.php中调用,而不是在停用时调用
        global $wpdb;

        // 删除所有选项
        delete_option( 'aiscg_version' );
        delete_option( 'aiscg_activated_time' );
        delete_option( 'aiscg_ai_config' );
        delete_option( 'aiscg_image_config' );
        delete_option( 'aiscg_content_templates' );

        // 删除数据库表
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aiscg_posts" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aiscg_images" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aiscg_settings" );

        // 删除上传的文件
        $upload_dir = wp_upload_dir();
        $aiscg_dir = $upload_dir['basedir'] . '/aiscg-content';
        if ( is_dir( $aiscg_dir ) ) {
            self::delete_directory( $aiscg_dir );
        }

        error_log( 'AISCG: Plugin uninstalled and all data removed' );
    }

    /**
     * 递归删除目录
     *
     * @param string $dir 目录路径
     * @return bool
     */
    private static function delete_directory( $dir ) {
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

            if ( ! self::delete_directory( $dir . DIRECTORY_SEPARATOR . $item ) ) {
                return false;
            }
        }

        return rmdir( $dir );
    }
}
