<?php
/**
 * 核心插件类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 核心插件类
 */
class AISCG_Plugin {

    /**
     * 插件加载器
     *
     * @var AISCG_Loader
     */
    protected $loader;

    /**
     * 插件唯一标识符
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * 插件版本
     *
     * @var string
     */
    protected $version;

    /**
     * 初始化插件
     */
    public function __construct() {
        $this->version = AISCG_VERSION;
        $this->plugin_name = 'ai-social-content-generator';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * 加载依赖文件
     */
    private function load_dependencies() {
        // 数据库操作类
        require_once AISCG_PLUGIN_DIR . 'includes/class-database.php';

        // AI服务接口和实现
        require_once AISCG_PLUGIN_DIR . 'includes/ai-services/interface-ai-service.php';
        require_once AISCG_PLUGIN_DIR . 'includes/ai-services/class-ai-service-factory.php';
        require_once AISCG_PLUGIN_DIR . 'includes/ai-services/class-openai-service.php';
        require_once AISCG_PLUGIN_DIR . 'includes/ai-services/class-gemini-service.php';
        require_once AISCG_PLUGIN_DIR . 'includes/ai-services/class-deepseek-service.php';
        require_once AISCG_PLUGIN_DIR . 'includes/ai-services/class-claude-service.php';
        require_once AISCG_PLUGIN_DIR . 'includes/ai-services/class-qwen-service.php';

        // 内容生成器
        require_once AISCG_PLUGIN_DIR . 'includes/class-content-generator.php';

        // 图片生成器
        require_once AISCG_PLUGIN_DIR . 'includes/class-image-generator.php';

        // 管理员界面
        require_once AISCG_PLUGIN_DIR . 'admin/class-admin.php';
        require_once AISCG_PLUGIN_DIR . 'admin/class-settings.php';
    }

    /**
     * 设置插件的国际化
     */
    private function set_locale() {
        add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
    }

    /**
     * 加载插件文本域
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'ai-social-content-generator',
            false,
            dirname( AISCG_PLUGIN_BASENAME ) . '/languages/'
        );
    }

    /**
     * 定义管理员相关的钩子
     */
    private function define_admin_hooks() {
        $admin = new AISCG_Admin( $this->plugin_name, $this->version );

        // 添加管理菜单
        add_action( 'admin_menu', array( $admin, 'add_admin_menu' ) );

        // 注册脚本和样式
        add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_scripts' ) );

        // 注册REST API端点
        add_action( 'rest_api_init', array( $admin, 'register_rest_routes' ) );

        // 添加插件设置链接
        add_filter( 'plugin_action_links_' . AISCG_PLUGIN_BASENAME, array( $admin, 'add_action_links' ) );
    }

    /**
     * 定义公共端的钩子
     */
    private function define_public_hooks() {
        // 目前公共端没有特别的钩子
        // 可以在这里添加短代码或公共端的功能
    }

    /**
     * 运行插件
     */
    public function run() {
        // 插件已经通过WordPress的钩子系统运行
    }

    /**
     * 获取插件名称
     *
     * @return string
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * 获取插件版本
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}
