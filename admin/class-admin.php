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
     * 添加插件操作链接
     */
    public function add_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=aiscg-settings' ) . '">' . __( 'Settings', 'ai-social-content-generator' ) . '</a>';
        array_unshift( $links, $settings_link );

        return $links;
    }
}
