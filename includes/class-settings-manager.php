<?php
/**
 * Settings Manager Class
 *
 * 管理插件设置的导入和导出
 *
 * @package AI_Social_Content_Generator
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Settings_Manager Class
 */
class AISCG_Settings_Manager {

    /**
     * 日志对象
     *
     * @var AISCG_Logger
     */
    private $logger;

    /**
     * 所有设置选项键名
     *
     * @var array
     */
    private $setting_keys = array(
        'aiscg_ai_config',
        'aiscg_image_config',
        'aiscg_content_templates',
        'aiscg_batch_config',
        'aiscg_scheduler_config',
        'aiscg_export_config',
        'aiscg_analytics_config',
        'aiscg_logger_settings',
        'aiscg_cache_enabled',
        'aiscg_rate_limits',
        'aiscg_webhooks',
        'aiscg_email_notifications',
    );

    /**
     * 构造函数
     */
    public function __construct() {
        $this->logger = new AISCG_Logger();
    }

    /**
     * 导出所有设置
     *
     * @param array $options 导出选项
     * @return array|false 设置数据或false
     */
    public function export_settings( $options = array() ) {
        $defaults = array(
            'include_api_keys' => false,  // 是否包含API密钥（安全考虑）
            'include_templates' => true,   // 是否包含自定义模板
            'include_webhooks' => true,    // 是否包含Webhook配置
        );

        $options = wp_parse_args( $options, $defaults );

        $settings = array(
            'version' => AISCG_VERSION,
            'export_date' => current_time( 'mysql' ),
            'site_url' => get_site_url(),
            'settings' => array(),
        );

        foreach ( $this->setting_keys as $key ) {
            $value = get_option( $key );

            if ( $value !== false ) {
                // 处理敏感信息
                if ( ! $options['include_api_keys'] && $key === 'aiscg_ai_config' ) {
                    $value = $this->remove_api_keys( $value );
                }

                $settings['settings'][ $key ] = $value;
            }
        }

        // 导出自定义模板
        if ( $options['include_templates'] ) {
            $template_manager = new AISCG_Template_Manager();
            $settings['custom_templates'] = $template_manager->export_templates();
        }

        $this->logger->info( '导出设置成功', array(
            'options' => $options,
            'settings_count' => count( $settings['settings'] ),
        ) );

        return $settings;
    }

    /**
     * 导入设置
     *
     * @param array $settings 设置数据
     * @param array $options 导入选项
     * @return array 导入结果
     */
    public function import_settings( $settings, $options = array() ) {
        $defaults = array(
            'overwrite' => false,          // 是否覆盖现有设置
            'import_templates' => true,    // 是否导入自定义模板
            'validate' => true,            // 是否验证设置
        );

        $options = wp_parse_args( $options, $defaults );

        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        // 验证设置格式
        if ( $options['validate'] && ! $this->validate_settings( $settings ) ) {
            $results['errors'][] = '设置格式无效';
            $this->logger->error( '导入设置失败：格式无效' );
            return $results;
        }

        // 导入设置选项
        if ( isset( $settings['settings'] ) && is_array( $settings['settings'] ) ) {
            foreach ( $settings['settings'] as $key => $value ) {
                // 检查是否允许覆盖
                if ( ! $options['overwrite'] && get_option( $key ) !== false ) {
                    $results['skipped']++;
                    continue;
                }

                // 更新设置
                if ( update_option( $key, $value ) ) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "设置 {$key} 更新失败";
                }
            }
        }

        // 导入自定义模板
        if ( $options['import_templates'] && isset( $settings['custom_templates'] ) ) {
            $template_manager = new AISCG_Template_Manager();
            $template_results = $template_manager->import_templates( $settings['custom_templates'] );

            $results['templates_imported'] = $template_results['success'];
            $results['templates_failed'] = $template_results['failed'];
        }

        $this->logger->info( '导入设置完成', $results );

        return $results;
    }

    /**
     * 导出设置到文件
     *
     * @param array $options 导出选项
     * @return string|false 文件路径或false
     */
    public function export_to_file( $options = array() ) {
        $settings = $this->export_settings( $options );

        if ( ! $settings ) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/aiscg-exports/settings/';

        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
        }

        $filename = 'settings_' . date( 'Y-m-d_H-i-s' ) . '.json';
        $filepath = $export_dir . $filename;

        $json = wp_json_encode( $settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

        if ( file_put_contents( $filepath, $json ) ) {
            $this->logger->info( '导出设置到文件成功', array(
                'filepath' => $filepath,
            ) );
            return $filepath;
        }

        return false;
    }

    /**
     * 从文件导入设置
     *
     * @param string $filepath 文件路径
     * @param array $options 导入选项
     * @return array|false 导入结果或false
     */
    public function import_from_file( $filepath, $options = array() ) {
        if ( ! file_exists( $filepath ) ) {
            $this->logger->error( '导入设置失败：文件不存在', array(
                'filepath' => $filepath,
            ) );
            return false;
        }

        $json = file_get_contents( $filepath );
        $settings = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->logger->error( '导入设置失败：JSON解析错误', array(
                'error' => json_last_error_msg(),
            ) );
            return false;
        }

        return $this->import_settings( $settings, $options );
    }

    /**
     * 重置所有设置
     *
     * @return bool 是否成功
     */
    public function reset_all_settings() {
        $count = 0;

        foreach ( $this->setting_keys as $key ) {
            if ( delete_option( $key ) ) {
                $count++;
            }
        }

        if ( $count > 0 ) {
            $this->logger->info( "重置所有设置成功: {$count} 个设置项已删除" );
            return true;
        }

        return false;
    }

    /**
     * 重置特定设置
     *
     * @param string $key 设置键名
     * @return bool 是否成功
     */
    public function reset_setting( $key ) {
        if ( in_array( $key, $this->setting_keys, true ) ) {
            $result = delete_option( $key );

            if ( $result ) {
                $this->logger->info( "重置设置成功: {$key}" );
            }

            return $result;
        }

        return false;
    }

    /**
     * 验证设置数据
     *
     * @param array $settings 设置数据
     * @return bool 是否有效
     */
    private function validate_settings( $settings ) {
        // 检查必需字段
        if ( ! isset( $settings['version'] ) || ! isset( $settings['settings'] ) ) {
            return false;
        }

        // 检查版本兼容性
        if ( version_compare( $settings['version'], AISCG_VERSION, '>' ) ) {
            $this->logger->warning( '设置文件版本较新，可能存在兼容性问题', array(
                'file_version' => $settings['version'],
                'plugin_version' => AISCG_VERSION,
            ) );
        }

        // 验证设置格式
        if ( ! is_array( $settings['settings'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * 移除API密钥（安全处理）
     *
     * @param array $config AI配置
     * @return array 处理后的配置
     */
    private function remove_api_keys( $config ) {
        if ( ! is_array( $config ) ) {
            return $config;
        }

        $services = array( 'openai', 'gemini', 'deepseek', 'claude', 'qwen' );

        foreach ( $services as $service ) {
            if ( isset( $config[ $service ]['api_key'] ) ) {
                $config[ $service ]['api_key'] = '***REMOVED***';
            }
        }

        return $config;
    }

    /**
     * 备份当前设置
     *
     * @return string|false 备份文件路径或false
     */
    public function create_backup() {
        $backup_options = array(
            'include_api_keys' => true,
            'include_templates' => true,
            'include_webhooks' => true,
        );

        return $this->export_to_file( $backup_options );
    }

    /**
     * 获取所有备份文件
     *
     * @return array 备份文件列表
     */
    public function get_backups() {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/aiscg-exports/settings/';

        if ( ! file_exists( $export_dir ) ) {
            return array();
        }

        $files = glob( $export_dir . 'settings_*.json' );
        $backups = array();

        foreach ( $files as $file ) {
            $backups[] = array(
                'filename' => basename( $file ),
                'filepath' => $file,
                'size' => filesize( $file ),
                'created' => filemtime( $file ),
                'created_formatted' => date( 'Y-m-d H:i:s', filemtime( $file ) ),
            );
        }

        // 按创建时间倒序排列
        usort( $backups, function( $a, $b ) {
            return $b['created'] - $a['created'];
        } );

        return $backups;
    }

    /**
     * 删除备份文件
     *
     * @param string $filename 文件名
     * @return bool 是否成功
     */
    public function delete_backup( $filename ) {
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/aiscg-exports/settings/' . basename( $filename );

        if ( file_exists( $filepath ) && unlink( $filepath ) ) {
            $this->logger->info( "删除备份文件成功: {$filename}" );
            return true;
        }

        return false;
    }

    /**
     * 获取设置摘要
     *
     * @return array 设置摘要
     */
    public function get_settings_summary() {
        $summary = array(
            'total_settings' => 0,
            'configured_settings' => 0,
            'missing_settings' => array(),
            'details' => array(),
        );

        $summary['total_settings'] = count( $this->setting_keys );

        foreach ( $this->setting_keys as $key ) {
            $value = get_option( $key );

            if ( $value !== false ) {
                $summary['configured_settings']++;
                $summary['details'][ $key ] = array(
                    'configured' => true,
                    'type' => gettype( $value ),
                );
            } else {
                $summary['missing_settings'][] = $key;
                $summary['details'][ $key ] = array(
                    'configured' => false,
                );
            }
        }

        return $summary;
    }

    /**
     * 验证设置完整性
     *
     * @return array 验证结果
     */
    public function validate_configuration() {
        $results = array(
            'valid' => true,
            'errors' => array(),
            'warnings' => array(),
        );

        // 检查AI配置
        $ai_config = get_option( 'aiscg_ai_config' );
        if ( empty( $ai_config ) ) {
            $results['valid'] = false;
            $results['errors'][] = 'AI配置未设置';
        } else {
            $has_api_key = false;
            $services = array( 'openai', 'gemini', 'deepseek', 'claude', 'qwen' );

            foreach ( $services as $service ) {
                if ( ! empty( $ai_config[ $service ]['api_key'] ) && $ai_config[ $service ]['api_key'] !== '***REMOVED***' ) {
                    $has_api_key = true;
                    break;
                }
            }

            if ( ! $has_api_key ) {
                $results['warnings'][] = '未配置任何AI服务的API密钥';
            }
        }

        // 检查图片配置
        $image_config = get_option( 'aiscg_image_config' );
        if ( empty( $image_config ) ) {
            $results['warnings'][] = '图片配置未设置，将使用默认值';
        }

        // 检查上传目录权限
        $upload_dir = wp_upload_dir();
        if ( ! wp_is_writable( $upload_dir['basedir'] ) ) {
            $results['valid'] = false;
            $results['errors'][] = '上传目录不可写';
        }

        return $results;
    }

    /**
     * 获取默认设置
     *
     * @return array 默认设置
     */
    public function get_default_settings() {
        return array(
            'aiscg_ai_config' => array(
                'default_model' => 'openai',
                'openai' => array(
                    'api_key' => '',
                    'model' => 'gpt-3.5-turbo',
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ),
                'gemini' => array(
                    'api_key' => '',
                    'model' => 'gemini-pro',
                    'temperature' => 0.7,
                ),
                'deepseek' => array(
                    'api_key' => '',
                    'model' => 'deepseek-chat',
                    'temperature' => 0.7,
                ),
                'claude' => array(
                    'api_key' => '',
                    'model' => 'claude-3-sonnet-20240229',
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ),
                'qwen' => array(
                    'api_key' => '',
                    'model' => 'qwen-turbo',
                    'temperature' => 0.7,
                ),
            ),
            'aiscg_image_config' => array(
                'width' => 1080,
                'height' => 1080,
                'default_template' => 'gradient',
                'watermark' => array(
                    'enabled' => false,
                    'text' => get_bloginfo( 'name' ),
                    'position' => 'bottom-right',
                ),
            ),
            'aiscg_logger_settings' => array(
                'enabled' => true,
                'retention_days' => 30,
            ),
            'aiscg_cache_enabled' => true,
            'aiscg_rate_limits' => array(
                'openai' => array( 'limit' => 60, 'window' => 60 ),
                'gemini' => array( 'limit' => 60, 'window' => 60 ),
                'deepseek' => array( 'limit' => 60, 'window' => 60 ),
                'claude' => array( 'limit' => 60, 'window' => 60 ),
                'qwen' => array( 'limit' => 60, 'window' => 60 ),
            ),
        );
    }

    /**
     * 应用默认设置
     *
     * @param bool $overwrite 是否覆盖现有设置
     * @return int 应用的设置数量
     */
    public function apply_default_settings( $overwrite = false ) {
        $defaults = $this->get_default_settings();
        $count = 0;

        foreach ( $defaults as $key => $value ) {
            if ( $overwrite || get_option( $key ) === false ) {
                if ( update_option( $key, $value ) ) {
                    $count++;
                }
            }
        }

        if ( $count > 0 ) {
            $this->logger->info( "应用默认设置成功: {$count} 个设置项" );
        }

        return $count;
    }
}
