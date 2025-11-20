<?php
/**
 * 管理员界面类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 管理员界面类
 */
class AISCG_Admin {

    /**
     * 插件名称
     *
     * @var string
     */
    private $plugin_name;

    /**
     * 插件版本
     *
     * @var string
     */
    private $version;

    /**
     * 数据库实例
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 构造函数
     *
     * @param string $plugin_name 插件名称
     * @param string $version     插件版本
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->db = new AISCG_Database();
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        // 主菜单
        add_menu_page(
            __( 'AI Content Generator', 'ai-social-content-generator' ),
            __( 'AI Content', 'ai-social-content-generator' ),
            'manage_options',
            'aiscg-generator',
            array( $this, 'display_generator_page' ),
            'dashicons-admin-customizer',
            30
        );

        // 内容生成器子菜单
        add_submenu_page(
            'aiscg-generator',
            __( 'Generate Content', 'ai-social-content-generator' ),
            __( 'Generate', 'ai-social-content-generator' ),
            'manage_options',
            'aiscg-generator',
            array( $this, 'display_generator_page' )
        );

        // 历史记录子菜单
        add_submenu_page(
            'aiscg-generator',
            __( 'Content History', 'ai-social-content-generator' ),
            __( 'History', 'ai-social-content-generator' ),
            'manage_options',
            'aiscg-history',
            array( $this, 'display_history_page' )
        );

        // 设置子菜单
        add_submenu_page(
            'aiscg-generator',
            __( 'Settings', 'ai-social-content-generator' ),
            __( 'Settings', 'ai-social-content-generator' ),
            'manage_options',
            'aiscg-settings',
            array( $this, 'display_settings_page' )
        );
    }

    /**
     * 注册并加载管理端样式
     */
    public function enqueue_styles( $hook ) {
        // 只在插件页面加载样式
        if ( strpos( $hook, 'aiscg' ) === false ) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            AISCG_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * 注册并加载管理端脚本
     */
    public function enqueue_scripts( $hook ) {
        // 只在插件页面加载脚本
        if ( strpos( $hook, 'aiscg' ) === false ) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            AISCG_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery', 'wp-api-fetch' ),
            $this->version,
            true
        );

        // 传递数据到JavaScript
        wp_localize_script(
            $this->plugin_name,
            'aiscgData',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'restUrl' => rest_url( 'aiscg/v1' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'pluginUrl' => AISCG_PLUGIN_URL,
                'i18n' => array(
                    'generating' => __( 'Generating...', 'ai-social-content-generator' ),
                    'error' => __( 'Error occurred', 'ai-social-content-generator' ),
                    'success' => __( 'Success', 'ai-social-content-generator' ),
                ),
            )
        );
    }

    /**
     * 注册REST API路由
     */
    public function register_rest_routes() {
        // 生成内容
        register_rest_route( 'aiscg/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_generate_content' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 获取内容列表
        register_rest_route( 'aiscg/v1', '/posts', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_posts' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 获取单个内容
        register_rest_route( 'aiscg/v1', '/posts/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_post' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 删除内容
        register_rest_route( 'aiscg/v1', '/posts/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array( $this, 'rest_delete_post' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 保存设置
        register_rest_route( 'aiscg/v1', '/settings', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_save_settings' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 获取设置
        register_rest_route( 'aiscg/v1', '/settings', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_settings' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 测试AI连接
        register_rest_route( 'aiscg/v1', '/test-connection', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_test_connection' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 生成图片
        register_rest_route( 'aiscg/v1', '/generate-images', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_generate_images' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 更新内容
        register_rest_route( 'aiscg/v1', '/posts/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array( $this, 'rest_update_post' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 重新生成内容
        register_rest_route( 'aiscg/v1', '/regenerate/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_regenerate_content' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 下载图片
        register_rest_route( 'aiscg/v1', '/download-images/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_download_images' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 批量生成
        register_rest_route( 'aiscg/v1', '/batch-generate', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_batch_generate' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 导出内容
        register_rest_route( 'aiscg/v1', '/export', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_export_content' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 获取统计信息
        register_rest_route( 'aiscg/v1', '/statistics', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_statistics' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 获取趋势数据
        register_rest_route( 'aiscg/v1', '/statistics/trend', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_trend_data' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 定时任务管理
        register_rest_route( 'aiscg/v1', '/scheduler', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_scheduler_status' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        register_rest_route( 'aiscg/v1', '/scheduler', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_update_scheduler' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 竞品分析 - 添加竞品
        register_rest_route( 'aiscg/v1', '/competitors', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_add_competitor' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 竞品分析 - 获取竞品列表
        register_rest_route( 'aiscg/v1', '/competitors', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_competitors' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 竞品分析 - 分析竞品
        register_rest_route( 'aiscg/v1', '/competitors/(?P<id>\d+)/analyze', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_analyze_competitor' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 合规检查 - 检查内容
        register_rest_route( 'aiscg/v1', '/compliance/check', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_check_compliance' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 合规检查 - 获取检查历史
        register_rest_route( 'aiscg/v1', '/compliance/history/(?P<content_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_compliance_history' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 聊天机器人 - 发送消息
        register_rest_route( 'aiscg/v1', '/chatbot/message', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_chatbot_message' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 聊天机器人 - 获取会话列表
        register_rest_route( 'aiscg/v1', '/chatbot/sessions', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_chatbot_sessions' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 聊天机器人 - 获取会话详情
        register_rest_route( 'aiscg/v1', '/chatbot/sessions/(?P<session_id>[a-zA-Z0-9_]+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_chatbot_session' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 聊天机器人 - 结束会话
        register_rest_route( 'aiscg/v1', '/chatbot/sessions/(?P<session_id>[a-zA-Z0-9_]+)/end', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_end_chatbot_session' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 聊天机器人 - 获取统计
        register_rest_route( 'aiscg/v1', '/chatbot/stats', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_chatbot_stats' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 智能推荐 - 获取内容推荐
        register_rest_route( 'aiscg/v1', '/intelligence/recommendations', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_get_content_recommendations' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 智能推荐 - 获取个性化内容建议
        register_rest_route( 'aiscg/v1', '/intelligence/suggestions', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_get_content_suggestions' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 自动优化 - 优化单个内容
        register_rest_route( 'aiscg/v1', '/optimize/content/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_optimize_content' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );

        // 自动优化 - 批量优化
        register_rest_route( 'aiscg/v1', '/optimize/batch', array(
            'methods' => 'POST',
            'callback' => array( $this, 'rest_batch_optimize' ),
            'permission_callback' => array( $this, 'rest_permission_check' ),
        ) );
    }

    /**
     * REST API权限检查
     */
    public function rest_permission_check() {
        return current_user_can( 'manage_options' );
    }

    /**
     * REST API: 生成内容
     */
    public function rest_generate_content( $request ) {
        $params = $request->get_json_params();

        $topic = isset( $params['topic'] ) ? sanitize_text_field( $params['topic'] ) : '';
        $platform = isset( $params['platform'] ) ? sanitize_text_field( $params['platform'] ) : 'xiaohongshu';
        $ai_model = isset( $params['ai_model'] ) ? sanitize_text_field( $params['ai_model'] ) : '';
        $custom_prompt = isset( $params['custom_prompt'] ) ? wp_kses_post( $params['custom_prompt'] ) : '';

        if ( empty( $topic ) ) {
            return new WP_Error( 'missing_topic', __( 'Topic is required', 'ai-social-content-generator' ), array( 'status' => 400 ) );
        }

        try {
            $generator = new AISCG_Content_Generator();
            $result = $generator->generate( $topic, $platform, $ai_model, $custom_prompt );

            return rest_ensure_response( $result );
        } catch ( Exception $e ) {
            return new WP_Error( 'generation_failed', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * REST API: 获取内容列表
     */
    public function rest_get_posts( $request ) {
        $params = $request->get_params();

        $args = array(
            'platform' => isset( $params['platform'] ) ? sanitize_text_field( $params['platform'] ) : '',
            'status' => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '',
            'ai_model' => isset( $params['ai_model'] ) ? sanitize_text_field( $params['ai_model'] ) : '',
            'limit' => isset( $params['limit'] ) ? absint( $params['limit'] ) : 20,
            'offset' => isset( $params['offset'] ) ? absint( $params['offset'] ) : 0,
        );

        $posts = $this->db->get_posts( $args );
        $total = $this->db->get_posts_count( $args );

        return rest_ensure_response( array(
            'posts' => $posts,
            'total' => $total,
        ) );
    }

    /**
     * REST API: 获取单个内容
     */
    public function rest_get_post( $request ) {
        $id = $request->get_param( 'id' );
        $post = $this->db->get_post( $id );

        if ( ! $post ) {
            return new WP_Error( 'not_found', __( 'Post not found', 'ai-social-content-generator' ), array( 'status' => 404 ) );
        }

        // 获取关联的图片
        $post->images = $this->db->get_images_by_post_id( $id );

        return rest_ensure_response( $post );
    }

    /**
     * REST API: 删除内容
     */
    public function rest_delete_post( $request ) {
        $id = $request->get_param( 'id' );
        $result = $this->db->delete_post( $id );

        if ( ! $result ) {
            return new WP_Error( 'delete_failed', __( 'Failed to delete post', 'ai-social-content-generator' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * REST API: 保存设置
     */
    public function rest_save_settings( $request ) {
        $params = $request->get_json_params();

        if ( isset( $params['ai_config'] ) ) {
            update_option( 'aiscg_ai_config', $params['ai_config'] );
        }

        if ( isset( $params['image_config'] ) ) {
            update_option( 'aiscg_image_config', $params['image_config'] );
        }

        if ( isset( $params['content_templates'] ) ) {
            update_option( 'aiscg_content_templates', $params['content_templates'] );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * REST API: 获取设置
     */
    public function rest_get_settings( $request ) {
        return rest_ensure_response( array(
            'ai_config' => get_option( 'aiscg_ai_config', array() ),
            'image_config' => get_option( 'aiscg_image_config', array() ),
            'content_templates' => get_option( 'aiscg_content_templates', array() ),
        ) );
    }

    /**
     * REST API: 测试AI连接
     */
    public function rest_test_connection( $request ) {
        $params = $request->get_json_params();
        $ai_model = isset( $params['ai_model'] ) ? sanitize_text_field( $params['ai_model'] ) : '';

        try {
            $service = AISCG_AI_Service_Factory::create( $ai_model );
            $result = $service->test_connection();

            return rest_ensure_response( array( 'success' => true, 'message' => $result ) );
        } catch ( Exception $e ) {
            return new WP_Error( 'test_failed', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * 显示内容生成器页面
     */
    public function display_generator_page() {
        require_once AISCG_PLUGIN_DIR . 'admin/views/generator-page.php';
    }

    /**
     * 显示历史记录页面
     */
    public function display_history_page() {
        require_once AISCG_PLUGIN_DIR . 'admin/views/history-page.php';
    }

    /**
     * 显示设置页面
     */
    public function display_settings_page() {
        $settings = new AISCG_Settings();
        $settings->display();
    }

    /**
     * REST API: 生成图片
     */
    public function rest_generate_images( $request ) {
        $params = $request->get_json_params();

        $post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
        $count = isset( $params['count'] ) ? absint( $params['count'] ) : 1;

        if ( ! $post_id ) {
            return new WP_Error( 'missing_post_id', __( 'Post ID is required', 'ai-social-content-generator' ), array( 'status' => 400 ) );
        }

        try {
            $image_generator = new AISCG_Image_Generator();
            $images = $image_generator->generate_for_post( $post_id, $count );

            return rest_ensure_response( $images );
        } catch ( Exception $e ) {
            return new WP_Error( 'generation_failed', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * REST API: 更新内容
     */
    public function rest_update_post( $request ) {
        $id = $request->get_param( 'id' );
        $params = $request->get_json_params();

        $data = array();

        if ( isset( $params['title'] ) ) {
            $data['title'] = sanitize_text_field( $params['title'] );
        }

        if ( isset( $params['content'] ) ) {
            $data['content'] = wp_kses_post( $params['content'] );
        }

        if ( isset( $params['hashtags'] ) ) {
            $data['hashtags'] = $params['hashtags'];
        }

        if ( isset( $params['status'] ) ) {
            $data['status'] = sanitize_text_field( $params['status'] );
        }

        $result = $this->db->update_post( $id, $data );

        if ( ! $result ) {
            return new WP_Error( 'update_failed', __( 'Failed to update post', 'ai-social-content-generator' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * REST API: 重新生成内容
     */
    public function rest_regenerate_content( $request ) {
        $id = $request->get_param( 'id' );

        try {
            $generator = new AISCG_Content_Generator();
            $result = $generator->regenerate( $id );

            return rest_ensure_response( $result );
        } catch ( Exception $e ) {
            return new WP_Error( 'regeneration_failed', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * REST API: 下载图片
     */
    public function rest_download_images( $request ) {
        $id = $request->get_param( 'id' );

        $images = $this->db->get_images_by_post_id( $id );

        if ( empty( $images ) ) {
            return new WP_Error( 'no_images', __( 'No images found', 'ai-social-content-generator' ), array( 'status' => 404 ) );
        }

        // 如果只有一张图片,直接下载
        if ( count( $images ) === 1 ) {
            $image_path = $images[0]->image_path;
            if ( file_exists( $image_path ) ) {
                header( 'Content-Type: image/png' );
                header( 'Content-Disposition: attachment; filename="' . basename( $image_path ) . '"' );
                readfile( $image_path );
                exit;
            }
        }

        // 多张图片,打包成ZIP
        $zip = new ZipArchive();
        $zip_filename = tempnam( sys_get_temp_dir(), 'aiscg_' );

        if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== true ) {
            return new WP_Error( 'zip_failed', __( 'Failed to create ZIP file', 'ai-social-content-generator' ), array( 'status' => 500 ) );
        }

        foreach ( $images as $image ) {
            if ( file_exists( $image->image_path ) ) {
                $zip->addFile( $image->image_path, basename( $image->image_path ) );
            }
        }

        $zip->close();

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="images_' . $id . '.zip"' );
        header( 'Content-Length: ' . filesize( $zip_filename ) );
        readfile( $zip_filename );
        unlink( $zip_filename );
        exit;
    }

    /**
     * REST API: 批量生成
     */
    public function rest_batch_generate( $request ) {
        $params = $request->get_json_params();

        $topics = isset( $params['topics'] ) ? $params['topics'] : array();
        $options = isset( $params['options'] ) ? $params['options'] : array();

        if ( empty( $topics ) ) {
            return new WP_Error( 'missing_topics', __( 'Topics are required', 'ai-social-content-generator' ), array( 'status' => 400 ) );
        }

        try {
            $batch_processor = new AISCG_Batch_Processor();
            $results = $batch_processor->start_batch( $topics, $options );

            return rest_ensure_response( $results );
        } catch ( Exception $e ) {
            return new WP_Error( 'batch_failed', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * REST API: 导出内容
     */
    public function rest_export_content( $request ) {
        $params = $request->get_json_params();

        $post_ids = isset( $params['post_ids'] ) ? $params['post_ids'] : array();
        $format = isset( $params['format'] ) ? sanitize_text_field( $params['format'] ) : 'json';

        try {
            $exporter = new AISCG_Content_Exporter();

            if ( ! empty( $post_ids ) ) {
                $filepath = $exporter->export_multiple( $post_ids, $format );
            } else {
                $filters = isset( $params['filters'] ) ? $params['filters'] : array();
                $filepath = $exporter->export_all( $filters, $format );
            }

            $upload = wp_upload_dir();
            $file_url = str_replace( $upload['basedir'], $upload['baseurl'], $filepath );

            return rest_ensure_response( array(
                'success' => true,
                'file_url' => $file_url,
                'filename' => basename( $filepath ),
            ) );
        } catch ( Exception $e ) {
            return new WP_Error( 'export_failed', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * REST API: 获取统计信息
     */
    public function rest_get_statistics( $request ) {
        $analytics = new AISCG_Analytics();

        $stats = array(
            'overview' => $analytics->get_overview_stats(),
            'ai_model_usage' => $analytics->get_ai_model_usage(),
            'content_quality' => $analytics->get_content_quality_metrics(),
            'productivity' => $analytics->get_productivity_stats(),
            'storage' => $analytics->get_storage_stats(),
        );

        return rest_ensure_response( $stats );
    }

    /**
     * REST API: 获取趋势数据
     */
    public function rest_get_trend_data( $request ) {
        $days = $request->get_param( 'days' );
        $days = $days ? absint( $days ) : 30;

        $analytics = new AISCG_Analytics();
        $trend_data = $analytics->get_trend_data( $days );

        return rest_ensure_response( $trend_data );
    }

    /**
     * REST API: 获取调度器状态
     */
    public function rest_get_scheduler_status( $request ) {
        $scheduler = new AISCG_Scheduler();
        $status = $scheduler->get_status();
        $logs = $scheduler->get_scheduler_logs( 10 );

        return rest_ensure_response( array(
            'status' => $status,
            'logs' => $logs,
        ) );
    }

    /**
     * REST API: 更新调度器
     */
    public function rest_update_scheduler( $request ) {
        $params = $request->get_json_params();
        $action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : '';

        $scheduler = new AISCG_Scheduler();

        try {
            if ( $action === 'enable' ) {
                $config = isset( $params['config'] ) ? $params['config'] : array();
                $result = $scheduler->enable_scheduled_generation( $config );

                return rest_ensure_response( array(
                    'success' => $result,
                    'message' => __( 'Scheduler enabled', 'ai-social-content-generator' ),
                ) );
            } elseif ( $action === 'disable' ) {
                $scheduler->disable_scheduled_generation();

                return rest_ensure_response( array(
                    'success' => true,
                    'message' => __( 'Scheduler disabled', 'ai-social-content-generator' ),
                ) );
            } else {
                return new WP_Error( 'invalid_action', __( 'Invalid action', 'ai-social-content-generator' ), array( 'status' => 400 ) );
            }
        } catch ( Exception $e ) {
            return new WP_Error( 'scheduler_error', $e->getMessage(), array( 'status' => 500 ) );
        }
    }

    /**
     * REST API: 添加竞品
     */
    public function rest_add_competitor( $request ) {
        $analyzer = new AISCG_Competitor_Analyzer();

        $data = array(
            'name' => sanitize_text_field( $request->get_param( 'name' ) ),
            'platform' => sanitize_text_field( $request->get_param( 'platform' ) ),
            'account_id' => sanitize_text_field( $request->get_param( 'account_id' ) ),
            'description' => sanitize_textarea_field( $request->get_param( 'description' ) ),
            'category' => sanitize_text_field( $request->get_param( 'category' ) ),
        );

        $competitor_id = $analyzer->add_competitor( $data );

        if ( $competitor_id ) {
            return rest_ensure_response( array(
                'success' => true,
                'competitor_id' => $competitor_id,
            ) );
        }

        return new WP_Error( 'add_failed', '添加竞品失败', array( 'status' => 500 ) );
    }

    /**
     * REST API: 获取竞品列表
     */
    public function rest_get_competitors( $request ) {
        $analyzer = new AISCG_Competitor_Analyzer();
        $competitors = $analyzer->get_competitors();

        return rest_ensure_response( $competitors );
    }

    /**
     * REST API: 分析竞品
     */
    public function rest_analyze_competitor( $request ) {
        $id = intval( $request->get_param( 'id' ) );
        $options = array(
            'sample_size' => intval( $request->get_param( 'sample_size' ) ) ?: 50,
            'use_ai' => $request->get_param( 'use_ai' ) !== 'false',
        );

        $analyzer = new AISCG_Competitor_Analyzer();
        $analysis = $analyzer->analyze_competitor( $id, $options );

        if ( is_wp_error( $analysis ) ) {
            return new WP_Error( $analysis->get_error_code(), $analysis->get_error_message(), array( 'status' => 404 ) );
        }

        return rest_ensure_response( $analysis );
    }

    /**
     * REST API: 合规检查
     */
    public function rest_check_compliance( $request ) {
        $content_id = intval( $request->get_param( 'content_id' ) );
        $content = $request->get_param( 'content' );

        $options = array(
            'checks' => $request->get_param( 'checks' ) ?: array( 'legal', 'copyright', 'advertising', 'platform_policy' ),
            'use_ai' => $request->get_param( 'use_ai' ) !== 'false',
            'strict_mode' => $request->get_param( 'strict_mode' ) === 'true',
        );

        $checker = new AISCG_Compliance_Checker();

        // 如果传了content_id就检查ID，否则检查传入的内容
        $result = $checker->check_compliance( $content_id ?: $content, $options );

        return rest_ensure_response( $result );
    }

    /**
     * REST API: 获取合规检查历史
     */
    public function rest_get_compliance_history( $request ) {
        $content_id = intval( $request->get_param( 'content_id' ) );

        $checker = new AISCG_Compliance_Checker();
        $history = $checker->get_compliance_history( $content_id );

        return rest_ensure_response( $history );
    }

    /**
     * REST API: 聊天机器人发送消息
     */
    public function rest_chatbot_message( $request ) {
        $message = sanitize_textarea_field( $request->get_param( 'message' ) );
        $session_id = sanitize_text_field( $request->get_param( 'session_id' ) );
        $bot_type = sanitize_text_field( $request->get_param( 'bot_type' ) ) ?: 'content_assistant';

        $options = array(
            'bot_type' => $bot_type,
        );

        if ( $session_id ) {
            $options['session_id'] = $session_id;
        }

        $chatbot = new AISCG_Chatbot();
        $result = $chatbot->send_message( $message, $options );

        return rest_ensure_response( $result );
    }

    /**
     * REST API: 获取聊天机器人会话列表
     */
    public function rest_get_chatbot_sessions( $request ) {
        $user_id = get_current_user_id();
        $status = sanitize_text_field( $request->get_param( 'status' ) );
        $limit = intval( $request->get_param( 'limit' ) ) ?: 20;

        $args = array(
            'status' => $status,
            'limit' => $limit,
        );

        $chatbot = new AISCG_Chatbot();
        $sessions = $chatbot->get_user_sessions( $user_id, $args );

        return rest_ensure_response( $sessions );
    }

    /**
     * REST API: 获取聊天机器人会话详情
     */
    public function rest_get_chatbot_session( $request ) {
        $session_id = sanitize_text_field( $request->get_param( 'session_id' ) );

        $chatbot = new AISCG_Chatbot();
        $session = $chatbot->get_session_details( $session_id );

        if ( ! $session ) {
            return new WP_Error( 'session_not_found', '会话不存在', array( 'status' => 404 ) );
        }

        return rest_ensure_response( $session );
    }

    /**
     * REST API: 结束聊天机器人会话
     */
    public function rest_end_chatbot_session( $request ) {
        $session_id = sanitize_text_field( $request->get_param( 'session_id' ) );

        $chatbot = new AISCG_Chatbot();
        $result = $chatbot->end_session( $session_id );

        if ( $result ) {
            return rest_ensure_response( array( 'success' => true ) );
        }

        return new WP_Error( 'end_session_failed', '结束会话失败', array( 'status' => 500 ) );
    }

    /**
     * REST API: 获取聊天机器人统计
     */
    public function rest_get_chatbot_stats( $request ) {
        $user_id = intval( $request->get_param( 'user_id' ) ) ?: get_current_user_id();

        $chatbot = new AISCG_Chatbot();
        $stats = $chatbot->get_usage_stats( $user_id );

        return rest_ensure_response( $stats );
    }

    /**
     * REST API: 获取内容推荐
     */
    public function rest_get_content_recommendations( $request ) {
        $platform = sanitize_text_field( $request->get_param( 'platform' ) ) ?: 'xiaohongshu';
        $category = sanitize_text_field( $request->get_param( 'category' ) );
        $goal = sanitize_text_field( $request->get_param( 'goal' ) ) ?: 'engagement';
        $use_ai = $request->get_param( 'use_ai' ) !== 'false';

        $options = array(
            'platform' => $platform,
            'category' => $category,
            'goal' => $goal,
            'use_ai' => $use_ai,
        );

        $intelligence = new AISCG_Content_Intelligence();
        $recommendations = $intelligence->get_content_recommendations( $options );

        return rest_ensure_response( $recommendations );
    }

    /**
     * REST API: 获取个性化内容建议
     */
    public function rest_get_content_suggestions( $request ) {
        $topic = sanitize_text_field( $request->get_param( 'topic' ) );
        $platform = sanitize_text_field( $request->get_param( 'platform' ) ) ?: 'xiaohongshu';
        $style = sanitize_text_field( $request->get_param( 'style' ) ) ?: 'engaging';
        $count = intval( $request->get_param( 'count' ) ) ?: 5;

        if ( ! $topic ) {
            return new WP_Error( 'missing_topic', '请提供主题', array( 'status' => 400 ) );
        }

        $options = array(
            'platform' => $platform,
            'style' => $style,
            'count' => $count,
        );

        $intelligence = new AISCG_Content_Intelligence();
        $suggestions = $intelligence->get_personalized_content_suggestions( $topic, $options );

        return rest_ensure_response( $suggestions );
    }

    /**
     * REST API: 优化内容
     */
    public function rest_optimize_content( $request ) {
        $id = intval( $request->get_param( 'id' ) );
        $fix_compliance = $request->get_param( 'fix_compliance' ) !== 'false';
        $enhance_quality = $request->get_param( 'enhance_quality' ) !== 'false';
        $optimize_seo = $request->get_param( 'optimize_seo' ) !== 'false';
        $improve_readability = $request->get_param( 'improve_readability' ) !== 'false';

        $options = array(
            'fix_compliance' => $fix_compliance,
            'enhance_quality' => $enhance_quality,
            'optimize_seo' => $optimize_seo,
            'improve_readability' => $improve_readability,
        );

        $optimizer = new AISCG_Auto_Optimizer();
        $result = $optimizer->auto_optimize( $id, $options );

        return rest_ensure_response( $result );
    }

    /**
     * REST API: 批量优化
     */
    public function rest_batch_optimize( $request ) {
        $content_ids = $request->get_param( 'content_ids' );

        if ( ! is_array( $content_ids ) || empty( $content_ids ) ) {
            return new WP_Error( 'invalid_ids', '请提供有效的内容ID列表', array( 'status' => 400 ) );
        }

        $content_ids = array_map( 'intval', $content_ids );

        $options = array(
            'fix_compliance' => $request->get_param( 'fix_compliance' ) !== 'false',
            'enhance_quality' => $request->get_param( 'enhance_quality' ) !== 'false',
            'optimize_seo' => $request->get_param( 'optimize_seo' ) !== 'false',
            'improve_readability' => $request->get_param( 'improve_readability' ) !== 'false',
        );

        $optimizer = new AISCG_Auto_Optimizer();
        $result = $optimizer->batch_optimize( $content_ids, $options );

        return rest_ensure_response( $result );
    }

    /**
     * 添加插件操作链接
     */
    public function add_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=aiscg-settings' ) . '">' . __( 'Settings', 'ai-social-content-generator' ) . '</a>';
        array_unshift( $links, $settings_link );

        return $links;
    }
}
