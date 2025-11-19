<?php
/**
 * 插件激活时触发
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 插件激活类
 */
class AISCG_Activator {

    /**
     * 插件激活时执行
     *
     * 创建必要的数据库表和默认选项
     */
    public static function activate() {
        // 创建数据库表
        self::create_tables();

        // 设置默认选项
        self::set_default_options();

        // 设置插件版本
        update_option( 'aiscg_version', AISCG_VERSION );

        // 添加激活时间戳
        if ( ! get_option( 'aiscg_activated_time' ) ) {
            update_option( 'aiscg_activated_time', current_time( 'timestamp' ) );
        }

        // 刷新重写规则
        flush_rewrite_rules();
    }

    /**
     * 创建数据库表
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // 内容表
        $table_posts = $wpdb->prefix . 'aiscg_posts';
        $sql_posts = "CREATE TABLE IF NOT EXISTS $table_posts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            platform varchar(20) NOT NULL,
            title text,
            content text,
            hashtags text,
            ai_model varchar(50),
            prompt_used text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'draft',
            PRIMARY KEY  (id),
            KEY platform (platform),
            KEY ai_model (ai_model),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 图片表
        $table_images = $wpdb->prefix . 'aiscg_images';
        $sql_images = "CREATE TABLE IF NOT EXISTS $table_images (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            image_url text,
            image_path text,
            image_order int DEFAULT 0,
            width int,
            height int,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY image_order (image_order)
        ) $charset_collate;";

        // 设置表
        $table_settings = $wpdb->prefix . 'aiscg_settings';
        $sql_settings = "CREATE TABLE IF NOT EXISTS $table_settings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        // 日志表
        $table_logs = $wpdb->prefix . 'aiscg_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message text,
            context longtext,
            user_id bigint(20),
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 模板表
        $table_templates = $wpdb->prefix . 'aiscg_templates';
        $sql_templates = "CREATE TABLE IF NOT EXISTS $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text,
            platform varchar(20),
            content longtext,
            variables longtext,
            category varchar(50),
            is_active tinyint(1) DEFAULT 1,
            user_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY platform (platform),
            KEY category (category),
            KEY user_id (user_id)
        ) $charset_collate;";

        // 版本控制表
        $table_versions = $wpdb->prefix . 'aiscg_versions';
        $sql_versions = "CREATE TABLE IF NOT EXISTS $table_versions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            title text,
            content text,
            hashtags text,
            platform varchar(20),
            ai_model varchar(50),
            images longtext,
            metadata longtext,
            changes longtext,
            user_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 速率限制表
        $table_rate_limits = $wpdb->prefix . 'aiscg_rate_limits';
        $sql_rate_limits = "CREATE TABLE IF NOT EXISTS $table_rate_limits (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            service varchar(50) NOT NULL,
            metadata longtext,
            user_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY service (service),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 缓存表
        $table_cache = $wpdb->prefix . 'aiscg_cache';
        $sql_cache = "CREATE TABLE IF NOT EXISTS $table_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_value longtext,
            expires_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY cache_key (cache_key),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // 通知表
        $table_notifications = $wpdb->prefix . 'aiscg_notifications';
        $sql_notifications = "CREATE TABLE IF NOT EXISTS $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(20) NOT NULL,
            recipient text,
            success tinyint(1),
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY success (success),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 素材库表
        $table_media_library = $wpdb->prefix . 'aiscg_media_library';
        $sql_media_library = "CREATE TABLE IF NOT EXISTS $table_media_library (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            title varchar(200) NOT NULL,
            content longtext,
            category varchar(50),
            platform varchar(20),
            language varchar(10),
            tags longtext,
            metadata longtext,
            usage_count int DEFAULT 0,
            user_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY category (category),
            KEY platform (platform),
            KEY usage_count (usage_count)
        ) $charset_collate;";

        // 发布计划表
        $table_publishing_plans = $wpdb->prefix . 'aiscg_publishing_plans';
        $sql_publishing_plans = "CREATE TABLE IF NOT EXISTS $table_publishing_plans (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            platform varchar(20),
            scheduled_time datetime,
            timezone varchar(50),
            status varchar(20) DEFAULT 'pending',
            auto_publish tinyint(1) DEFAULT 0,
            notify_on_publish tinyint(1) DEFAULT 0,
            published_at datetime,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY scheduled_time (scheduled_time)
        ) $charset_collate;";

        // 执行SQL
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_posts );
        dbDelta( $sql_images );
        dbDelta( $sql_settings );
        dbDelta( $sql_logs );
        dbDelta( $sql_templates );
        dbDelta( $sql_versions );
        dbDelta( $sql_rate_limits );
        dbDelta( $sql_cache );
        dbDelta( $sql_notifications );
        dbDelta( $sql_media_library );
        dbDelta( $sql_publishing_plans );

        // 记录日志
        error_log( 'AISCG: Database tables created successfully' );
    }

    /**
     * 设置默认选项
     */
    private static function set_default_options() {
        // 默认AI模型配置
        $default_ai_config = array(
            'default_model' => 'openai',
            'models' => array(
                'openai' => array(
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'gpt-4',
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ),
                'gemini' => array(
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'gemini-pro',
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ),
                'deepseek' => array(
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'deepseek-chat',
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ),
                'claude' => array(
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'claude-3-sonnet-20240229',
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ),
                'qwen' => array(
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'qwen-max',
                    'temperature' => 0.7,
                    'max_tokens' => 2000,
                ),
            ),
        );

        add_option( 'aiscg_ai_config', $default_ai_config );

        // 默认图片配置
        $default_image_config = array(
            'xiaohongshu' => array(
                'width' => 1080,
                'height' => 1440,
                'ratio' => '3:4',
            ),
            'instagram' => array(
                'width' => 1080,
                'height' => 1080,
                'ratio' => '1:1',
            ),
            'default_template' => 'gradient',
            'font_family' => 'Arial',
            'watermark_enabled' => false,
            'watermark_image' => '',
        );

        add_option( 'aiscg_image_config', $default_image_config );

        // 默认内容模板
        $default_templates = array(
            'xiaohongshu' => "请根据主题「{topic}」生成一篇小红书风格的文案。\n\n要求:\n1. 标题吸引眼球,20字以内\n2. 正文包含emoji表情,分段清晰\n3. 文案长度300-500字\n4. 包含5-10个相关话题标签\n5. 语气轻松活泼,适合年轻用户\n\n请按以下JSON格式输出:\n{\n  \"title\": \"标题\",\n  \"content\": \"正文内容\",\n  \"hashtags\": [\"标签1\", \"标签2\"]\n}",
            'instagram' => "请根据主题「{topic}」生成一篇Instagram风格的文案。\n\n要求:\n1. 标题简洁有力\n2. 正文简洁,100-200字\n3. 包含10-20个英文hashtags\n4. 语气国际化,适合海外受众\n\n请按以下JSON格式输出:\n{\n  \"title\": \"标题\",\n  \"content\": \"正文内容\",\n  \"hashtags\": [\"#hashtag1\", \"#hashtag2\"]\n}",
        );

        add_option( 'aiscg_content_templates', $default_templates );

        // 记录日志
        error_log( 'AISCG: Default options set successfully' );
    }
}
