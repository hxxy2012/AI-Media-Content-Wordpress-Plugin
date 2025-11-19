<?php
/**
 * 内容备份与恢复管理器
 *
 * 提供完整的内容备份、恢复、导入导出功能
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 备份管理器类
 */
class AISCG_Backup_Manager {

    /**
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $database;

    /**
     * 备份目录
     *
     * @var string
     */
    private $backup_dir;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->database = new AISCG_Database();

        // 设置备份目录
        $upload_dir = wp_upload_dir();
        $this->backup_dir = $upload_dir['basedir'] . '/aiscg-backups';

        // 确保备份目录存在
        if ( ! file_exists( $this->backup_dir ) ) {
            wp_mkdir_p( $this->backup_dir );
            // 添加.htaccess保护
            $this->protect_backup_dir();
        }
    }

    /**
     * 创建完整备份
     *
     * @param array $options 备份选项
     *   - include_content: 是否包含内容 (默认true)
     *   - include_images: 是否包含图片 (默认true)
     *   - include_settings: 是否包含设置 (默认true)
     *   - include_templates: 是否包含模板 (默认true)
     *   - compress: 是否压缩 (默认true)
     *   - description: 备份描述
     *
     * @return array|WP_Error 备份结果或错误
     */
    public function create_backup( $options = array() ) {
        $defaults = array(
            'include_content' => true,
            'include_images' => true,
            'include_settings' => true,
            'include_templates' => true,
            'compress' => true,
            'description' => '',
        );
        $options = wp_parse_args( $options, $defaults );

        $backup_id = uniqid( 'backup_' );
        $timestamp = current_time( 'mysql' );
        $backup_data = array(
            'id' => $backup_id,
            'timestamp' => $timestamp,
            'version' => AISCG_VERSION,
            'description' => $options['description'],
            'options' => $options,
            'data' => array(),
        );

        try {
            // 备份内容
            if ( $options['include_content'] ) {
                $backup_data['data']['content'] = $this->backup_content();
            }

            // 备份图片
            if ( $options['include_images'] ) {
                $backup_data['data']['images'] = $this->backup_images();
            }

            // 备份设置
            if ( $options['include_settings'] ) {
                $backup_data['data']['settings'] = $this->backup_settings();
            }

            // 备份模板
            if ( $options['include_templates'] ) {
                $backup_data['data']['templates'] = $this->backup_templates();
            }

            // 保存备份
            $backup_file = $this->save_backup( $backup_id, $backup_data, $options['compress'] );

            // 记录备份信息
            $this->record_backup( array(
                'backup_id' => $backup_id,
                'file_path' => $backup_file,
                'file_size' => filesize( $backup_file ),
                'description' => $options['description'],
                'options' => wp_json_encode( $options ),
                'created_at' => $timestamp,
            ) );

            return array(
                'success' => true,
                'backup_id' => $backup_id,
                'file' => basename( $backup_file ),
                'size' => filesize( $backup_file ),
                'timestamp' => $timestamp,
            );

        } catch ( Exception $e ) {
            return new WP_Error( 'backup_failed', $e->getMessage() );
        }
    }

    /**
     * 创建增量备份
     *
     * @param string $base_backup_id 基础备份ID
     * @param array $options 备份选项
     *
     * @return array|WP_Error 备份结果或错误
     */
    public function create_incremental_backup( $base_backup_id, $options = array() ) {
        global $wpdb;
        $table_backups = $wpdb->prefix . 'aiscg_backups';

        // 获取基础备份信息
        $base_backup = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_backups WHERE backup_id = %s", $base_backup_id ),
            ARRAY_A
        );

        if ( ! $base_backup ) {
            return new WP_Error( 'base_backup_not_found', '基础备份不存在' );
        }

        $base_timestamp = $base_backup['created_at'];
        $backup_id = uniqid( 'inc_backup_' );
        $timestamp = current_time( 'mysql' );

        $backup_data = array(
            'id' => $backup_id,
            'type' => 'incremental',
            'base_backup_id' => $base_backup_id,
            'timestamp' => $timestamp,
            'base_timestamp' => $base_timestamp,
            'version' => AISCG_VERSION,
            'description' => $options['description'] ?? '增量备份',
            'data' => array(),
        );

        try {
            // 仅备份自基础备份以来的变更
            $backup_data['data']['content'] = $this->backup_content_since( $base_timestamp );
            $backup_data['data']['images'] = $this->backup_images_since( $base_timestamp );

            // 保存增量备份
            $backup_file = $this->save_backup( $backup_id, $backup_data, true );

            // 记录备份信息
            $this->record_backup( array(
                'backup_id' => $backup_id,
                'backup_type' => 'incremental',
                'base_backup_id' => $base_backup_id,
                'file_path' => $backup_file,
                'file_size' => filesize( $backup_file ),
                'description' => $backup_data['description'],
                'created_at' => $timestamp,
            ) );

            return array(
                'success' => true,
                'backup_id' => $backup_id,
                'type' => 'incremental',
                'file' => basename( $backup_file ),
                'size' => filesize( $backup_file ),
            );

        } catch ( Exception $e ) {
            return new WP_Error( 'incremental_backup_failed', $e->getMessage() );
        }
    }

    /**
     * 恢复备份
     *
     * @param string $backup_id 备份ID
     * @param array $options 恢复选项
     *   - restore_content: 是否恢复内容
     *   - restore_images: 是否恢复图片
     *   - restore_settings: 是否恢复设置
     *   - restore_templates: 是否恢复模板
     *   - overwrite: 是否覆盖现有数据
     *
     * @return array|WP_Error 恢复结果或错误
     */
    public function restore_backup( $backup_id, $options = array() ) {
        $defaults = array(
            'restore_content' => true,
            'restore_images' => true,
            'restore_settings' => true,
            'restore_templates' => true,
            'overwrite' => false,
        );
        $options = wp_parse_args( $options, $defaults );

        try {
            // 加载备份数据
            $backup_data = $this->load_backup( $backup_id );

            if ( ! $backup_data ) {
                return new WP_Error( 'backup_not_found', '备份文件不存在' );
            }

            $results = array(
                'backup_id' => $backup_id,
                'restored' => array(),
                'skipped' => array(),
                'errors' => array(),
            );

            // 恢复内容
            if ( $options['restore_content'] && isset( $backup_data['data']['content'] ) ) {
                $result = $this->restore_content( $backup_data['data']['content'], $options['overwrite'] );
                $results['restored']['content'] = $result;
            }

            // 恢复图片
            if ( $options['restore_images'] && isset( $backup_data['data']['images'] ) ) {
                $result = $this->restore_images( $backup_data['data']['images'], $options['overwrite'] );
                $results['restored']['images'] = $result;
            }

            // 恢复设置
            if ( $options['restore_settings'] && isset( $backup_data['data']['settings'] ) ) {
                $result = $this->restore_settings( $backup_data['data']['settings'], $options['overwrite'] );
                $results['restored']['settings'] = $result;
            }

            // 恢复模板
            if ( $options['restore_templates'] && isset( $backup_data['data']['templates'] ) ) {
                $result = $this->restore_templates( $backup_data['data']['templates'], $options['overwrite'] );
                $results['restored']['templates'] = $result;
            }

            return $results;

        } catch ( Exception $e ) {
            return new WP_Error( 'restore_failed', $e->getMessage() );
        }
    }

    /**
     * 获取备份列表
     *
     * @param array $args 查询参数
     *
     * @return array 备份列表
     */
    public function get_backups( $args = array() ) {
        global $wpdb;
        $table_backups = $wpdb->prefix . 'aiscg_backups';

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $query = "SELECT * FROM $table_backups ORDER BY {$args['order_by']} {$args['order']} LIMIT %d OFFSET %d";
        $backups = $wpdb->get_results(
            $wpdb->prepare( $query, $args['limit'], $args['offset'] ),
            ARRAY_A
        );

        // 添加额外信息
        foreach ( $backups as &$backup ) {
            $backup['file_exists'] = file_exists( $backup['file_path'] );
            $backup['file_size_formatted'] = size_format( $backup['file_size'] );
            $backup['options'] = json_decode( $backup['options'], true );
        }

        return $backups;
    }

    /**
     * 删除备份
     *
     * @param string $backup_id 备份ID
     *
     * @return bool|WP_Error 成功返回true,失败返回错误
     */
    public function delete_backup( $backup_id ) {
        global $wpdb;
        $table_backups = $wpdb->prefix . 'aiscg_backups';

        // 获取备份信息
        $backup = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_backups WHERE backup_id = %s", $backup_id ),
            ARRAY_A
        );

        if ( ! $backup ) {
            return new WP_Error( 'backup_not_found', '备份不存在' );
        }

        // 删除备份文件
        if ( file_exists( $backup['file_path'] ) ) {
            if ( ! @unlink( $backup['file_path'] ) ) {
                return new WP_Error( 'file_delete_failed', '无法删除备份文件' );
            }
        }

        // 删除数据库记录
        $deleted = $wpdb->delete( $table_backups, array( 'backup_id' => $backup_id ) );

        if ( $deleted === false ) {
            return new WP_Error( 'db_delete_failed', '无法删除备份记录' );
        }

        return true;
    }

    /**
     * 清理旧备份
     *
     * @param int $days 保留天数
     *
     * @return int 删除的备份数量
     */
    public function cleanup_old_backups( $days = 30 ) {
        global $wpdb;
        $table_backups = $wpdb->prefix . 'aiscg_backups';

        $old_backups = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_backups WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ),
            ARRAY_A
        );

        $deleted_count = 0;

        foreach ( $old_backups as $backup ) {
            $result = $this->delete_backup( $backup['backup_id'] );
            if ( $result === true ) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * 导出备份
     *
     * @param string $backup_id 备份ID
     *
     * @return string|WP_Error 文件路径或错误
     */
    public function export_backup( $backup_id ) {
        global $wpdb;
        $table_backups = $wpdb->prefix . 'aiscg_backups';

        $backup = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_backups WHERE backup_id = %s", $backup_id ),
            ARRAY_A
        );

        if ( ! $backup || ! file_exists( $backup['file_path'] ) ) {
            return new WP_Error( 'backup_not_found', '备份文件不存在' );
        }

        return $backup['file_path'];
    }

    /**
     * 导入备份
     *
     * @param string $file_path 备份文件路径
     *
     * @return array|WP_Error 导入结果或错误
     */
    public function import_backup( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'file_not_found', '备份文件不存在' );
        }

        try {
            // 读取备份文件
            $content = file_get_contents( $file_path );

            // 检查是否压缩
            if ( substr( $content, 0, 2 ) === "\x1f\x8b" ) { // gzip magic number
                $content = gzdecode( $content );
            }

            $backup_data = json_decode( $content, true );

            if ( ! $backup_data ) {
                return new WP_Error( 'invalid_backup', '无效的备份文件格式' );
            }

            $new_backup_id = uniqid( 'imported_' );
            $new_file = $this->backup_dir . '/' . $new_backup_id . '.json';

            // 保存到备份目录
            copy( $file_path, $new_file );

            // 记录备份
            $this->record_backup( array(
                'backup_id' => $new_backup_id,
                'file_path' => $new_file,
                'file_size' => filesize( $new_file ),
                'description' => '导入的备份 - ' . ( $backup_data['description'] ?? '' ),
                'created_at' => current_time( 'mysql' ),
            ) );

            return array(
                'success' => true,
                'backup_id' => $new_backup_id,
                'original_timestamp' => $backup_data['timestamp'] ?? '',
            );

        } catch ( Exception $e ) {
            return new WP_Error( 'import_failed', $e->getMessage() );
        }
    }

    /**
     * 备份内容
     *
     * @return array 内容数据
     */
    private function backup_content() {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $posts = $wpdb->get_results( "SELECT * FROM $table_posts", ARRAY_A );

        return array(
            'count' => count( $posts ),
            'posts' => $posts,
        );
    }

    /**
     * 备份指定时间后的内容
     *
     * @param string $since_timestamp 起始时间
     *
     * @return array 内容数据
     */
    private function backup_content_since( $since_timestamp ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_posts WHERE created_at > %s OR updated_at > %s",
                $since_timestamp, $since_timestamp
            ),
            ARRAY_A
        );

        return array(
            'count' => count( $posts ),
            'posts' => $posts,
            'since' => $since_timestamp,
        );
    }

    /**
     * 备份图片
     *
     * @return array 图片数据
     */
    private function backup_images() {
        global $wpdb;
        $table_images = $wpdb->prefix . 'aiscg_images';

        $images = $wpdb->get_results( "SELECT * FROM $table_images", ARRAY_A );

        // 包含图片文件的base64编码
        foreach ( $images as &$image ) {
            if ( file_exists( $image['image_path'] ) ) {
                $image['image_data'] = base64_encode( file_get_contents( $image['image_path'] ) );
            }
        }

        return array(
            'count' => count( $images ),
            'images' => $images,
        );
    }

    /**
     * 备份指定时间后的图片
     *
     * @param string $since_timestamp 起始时间
     *
     * @return array 图片数据
     */
    private function backup_images_since( $since_timestamp ) {
        global $wpdb;
        $table_images = $wpdb->prefix . 'aiscg_images';

        $images = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_images WHERE created_at > %s",
                $since_timestamp
            ),
            ARRAY_A
        );

        foreach ( $images as &$image ) {
            if ( file_exists( $image['image_path'] ) ) {
                $image['image_data'] = base64_encode( file_get_contents( $image['image_path'] ) );
            }
        }

        return array(
            'count' => count( $images ),
            'images' => $images,
            'since' => $since_timestamp,
        );
    }

    /**
     * 备份设置
     *
     * @return array 设置数据
     */
    private function backup_settings() {
        global $wpdb;
        $table_settings = $wpdb->prefix . 'aiscg_settings';

        $settings = $wpdb->get_results( "SELECT * FROM $table_settings", ARRAY_A );

        return array(
            'count' => count( $settings ),
            'settings' => $settings,
        );
    }

    /**
     * 备份模板
     *
     * @return array 模板数据
     */
    private function backup_templates() {
        global $wpdb;
        $table_templates = $wpdb->prefix . 'aiscg_templates';

        $templates = $wpdb->get_results( "SELECT * FROM $table_templates", ARRAY_A );

        return array(
            'count' => count( $templates ),
            'templates' => $templates,
        );
    }

    /**
     * 保存备份文件
     *
     * @param string $backup_id 备份ID
     * @param array $backup_data 备份数据
     * @param bool $compress 是否压缩
     *
     * @return string 文件路径
     */
    private function save_backup( $backup_id, $backup_data, $compress = true ) {
        $filename = $backup_id . '.json';
        if ( $compress ) {
            $filename .= '.gz';
        }

        $file_path = $this->backup_dir . '/' . $filename;
        $json_content = wp_json_encode( $backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

        if ( $compress ) {
            $content = gzencode( $json_content, 9 );
        } else {
            $content = $json_content;
        }

        if ( file_put_contents( $file_path, $content ) === false ) {
            throw new Exception( '无法保存备份文件' );
        }

        return $file_path;
    }

    /**
     * 加载备份文件
     *
     * @param string $backup_id 备份ID
     *
     * @return array|null 备份数据
     */
    private function load_backup( $backup_id ) {
        global $wpdb;
        $table_backups = $wpdb->prefix . 'aiscg_backups';

        $backup = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_backups WHERE backup_id = %s", $backup_id ),
            ARRAY_A
        );

        if ( ! $backup || ! file_exists( $backup['file_path'] ) ) {
            return null;
        }

        $content = file_get_contents( $backup['file_path'] );

        // 检查是否压缩
        if ( substr( $content, 0, 2 ) === "\x1f\x8b" ) {
            $content = gzdecode( $content );
        }

        return json_decode( $content, true );
    }

    /**
     * 恢复内容
     *
     * @param array $content_data 内容数据
     * @param bool $overwrite 是否覆盖
     *
     * @return array 恢复结果
     */
    private function restore_content( $content_data, $overwrite = false ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $restored = 0;
        $skipped = 0;
        $errors = 0;

        foreach ( $content_data['posts'] as $post ) {
            $post_id = $post['id'];
            unset( $post['id'] );

            // 检查是否已存在
            $exists = $wpdb->get_var(
                $wpdb->prepare( "SELECT id FROM $table_posts WHERE id = %d", $post_id )
            );

            if ( $exists && ! $overwrite ) {
                $skipped++;
                continue;
            }

            if ( $exists && $overwrite ) {
                // 更新
                $result = $wpdb->update( $table_posts, $post, array( 'id' => $post_id ) );
            } else {
                // 插入
                $post['id'] = $post_id;
                $result = $wpdb->insert( $table_posts, $post );
            }

            if ( $result !== false ) {
                $restored++;
            } else {
                $errors++;
            }
        }

        return compact( 'restored', 'skipped', 'errors' );
    }

    /**
     * 恢复图片
     *
     * @param array $images_data 图片数据
     * @param bool $overwrite 是否覆盖
     *
     * @return array 恢复结果
     */
    private function restore_images( $images_data, $overwrite = false ) {
        global $wpdb;
        $table_images = $wpdb->prefix . 'aiscg_images';

        $restored = 0;
        $skipped = 0;
        $errors = 0;

        foreach ( $images_data['images'] as $image ) {
            $image_id = $image['id'];
            $image_data = $image['image_data'] ?? '';
            unset( $image['id'], $image['image_data'] );

            // 检查是否已存在
            $exists = $wpdb->get_var(
                $wpdb->prepare( "SELECT id FROM $table_images WHERE id = %d", $image_id )
            );

            if ( $exists && ! $overwrite ) {
                $skipped++;
                continue;
            }

            // 恢复图片文件
            if ( ! empty( $image_data ) && ! empty( $image['image_path'] ) ) {
                $dir = dirname( $image['image_path'] );
                if ( ! file_exists( $dir ) ) {
                    wp_mkdir_p( $dir );
                }
                file_put_contents( $image['image_path'], base64_decode( $image_data ) );
            }

            if ( $exists && $overwrite ) {
                $result = $wpdb->update( $table_images, $image, array( 'id' => $image_id ) );
            } else {
                $image['id'] = $image_id;
                $result = $wpdb->insert( $table_images, $image );
            }

            if ( $result !== false ) {
                $restored++;
            } else {
                $errors++;
            }
        }

        return compact( 'restored', 'skipped', 'errors' );
    }

    /**
     * 恢复设置
     *
     * @param array $settings_data 设置数据
     * @param bool $overwrite 是否覆盖
     *
     * @return array 恢复结果
     */
    private function restore_settings( $settings_data, $overwrite = false ) {
        global $wpdb;
        $table_settings = $wpdb->prefix . 'aiscg_settings';

        $restored = 0;
        $skipped = 0;

        foreach ( $settings_data['settings'] as $setting ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_settings WHERE setting_key = %s",
                    $setting['setting_key']
                )
            );

            if ( $exists && ! $overwrite ) {
                $skipped++;
                continue;
            }

            if ( $exists && $overwrite ) {
                $wpdb->update(
                    $table_settings,
                    array( 'setting_value' => $setting['setting_value'] ),
                    array( 'setting_key' => $setting['setting_key'] )
                );
            } else {
                $wpdb->insert( $table_settings, $setting );
            }

            $restored++;
        }

        return compact( 'restored', 'skipped' );
    }

    /**
     * 恢复模板
     *
     * @param array $templates_data 模板数据
     * @param bool $overwrite 是否覆盖
     *
     * @return array 恢复结果
     */
    private function restore_templates( $templates_data, $overwrite = false ) {
        global $wpdb;
        $table_templates = $wpdb->prefix . 'aiscg_templates';

        $restored = 0;
        $skipped = 0;

        foreach ( $templates_data['templates'] as $template ) {
            $template_id = $template['id'];
            unset( $template['id'] );

            $exists = $wpdb->get_var(
                $wpdb->prepare( "SELECT id FROM $table_templates WHERE id = %d", $template_id )
            );

            if ( $exists && ! $overwrite ) {
                $skipped++;
                continue;
            }

            if ( $exists && $overwrite ) {
                $wpdb->update( $table_templates, $template, array( 'id' => $template_id ) );
            } else {
                $template['id'] = $template_id;
                $wpdb->insert( $table_templates, $template );
            }

            $restored++;
        }

        return compact( 'restored', 'skipped' );
    }

    /**
     * 记录备份信息
     *
     * @param array $backup_info 备份信息
     *
     * @return int|false 插入的行ID或false
     */
    private function record_backup( $backup_info ) {
        global $wpdb;
        $table_backups = $wpdb->prefix . 'aiscg_backups';

        // 确保表存在
        $this->create_backups_table();

        return $wpdb->insert( $table_backups, $backup_info );
    }

    /**
     * 创建备份表
     */
    private function create_backups_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiscg_backups';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            backup_id varchar(100) NOT NULL,
            backup_type varchar(20) DEFAULT 'full',
            base_backup_id varchar(100) DEFAULT NULL,
            file_path text NOT NULL,
            file_size bigint(20) DEFAULT 0,
            description text,
            options longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY backup_id (backup_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * 保护备份目录
     */
    private function protect_backup_dir() {
        $htaccess_file = $this->backup_dir . '/.htaccess';
        $htaccess_content = "Order deny,allow\nDeny from all";
        file_put_contents( $htaccess_file, $htaccess_content );

        $index_file = $this->backup_dir . '/index.php';
        file_put_contents( $index_file, '<?php // Silence is golden' );
    }
}
