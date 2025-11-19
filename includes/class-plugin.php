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

        // 批量处理器
        require_once AISCG_PLUGIN_DIR . 'includes/class-batch-processor.php';

        // 定时任务调度器
        require_once AISCG_PLUGIN_DIR . 'includes/class-scheduler.php';

        // 内容导出器
        require_once AISCG_PLUGIN_DIR . 'includes/class-content-exporter.php';

        // 统计分析
        require_once AISCG_PLUGIN_DIR . 'includes/class-analytics.php';

        // 短代码
        require_once AISCG_PLUGIN_DIR . 'includes/class-shortcodes.php';

        // Widget
        require_once AISCG_PLUGIN_DIR . 'includes/class-widget.php';

        // 日志系统
        require_once AISCG_PLUGIN_DIR . 'includes/class-logger.php';

        // 模板管理器
        require_once AISCG_PLUGIN_DIR . 'includes/class-template-manager.php';

        // 版本控制
        require_once AISCG_PLUGIN_DIR . 'includes/class-version-control.php';

        // 图片定制器
        require_once AISCG_PLUGIN_DIR . 'includes/class-image-customizer.php';

        // API管理器
        require_once AISCG_PLUGIN_DIR . 'includes/class-api-manager.php';

        // 通知系统
        require_once AISCG_PLUGIN_DIR . 'includes/class-notification.php';

        // 设置管理器
        require_once AISCG_PLUGIN_DIR . 'includes/class-settings-manager.php';

        // 内容质量评分
        require_once AISCG_PLUGIN_DIR . 'includes/class-content-scorer.php';

        // 内容优化器
        require_once AISCG_PLUGIN_DIR . 'includes/class-content-optimizer.php';

        // 多语言生成器
        require_once AISCG_PLUGIN_DIR . 'includes/class-multilingual-generator.php';

        // 素材库
        require_once AISCG_PLUGIN_DIR . 'includes/class-media-library.php';

        // 发布计划
        require_once AISCG_PLUGIN_DIR . 'includes/class-publishing-planner.php';

        // 趋势分析器
        require_once AISCG_PLUGIN_DIR . 'includes/class-trend-analyzer.php';

        // SEO优化器
        require_once AISCG_PLUGIN_DIR . 'includes/class-seo-optimizer.php';

        // 协作系统
        require_once AISCG_PLUGIN_DIR . 'includes/class-collaboration-system.php';

        // 高级报表
        require_once AISCG_PLUGIN_DIR . 'includes/class-advanced-reports.php';

        // 性能监控
        require_once AISCG_PLUGIN_DIR . 'includes/class-performance-monitor.php';

        // 智能推荐引擎
        require_once AISCG_PLUGIN_DIR . 'includes/class-content-recommender.php';

        // AI标签生成器
        require_once AISCG_PLUGIN_DIR . 'includes/class-ai-tag-generator.php';

        // 成本追踪器
        require_once AISCG_PLUGIN_DIR . 'includes/class-cost-tracker.php';

        // 内容分类器
        require_once AISCG_PLUGIN_DIR . 'includes/class-content-classifier.php';

        // A/B测试管理器
        require_once AISCG_PLUGIN_DIR . 'includes/class-ab-test-manager.php';

        // 质量检测器
        require_once AISCG_PLUGIN_DIR . 'includes/class-quality-detector.php';

        // 智能写作助手
        require_once AISCG_PLUGIN_DIR . 'includes/class-writing-assistant.php';

        // 关键词研究工具
        require_once AISCG_PLUGIN_DIR . 'includes/class-keyword-researcher.php';

        // 备份管理器
        require_once AISCG_PLUGIN_DIR . 'includes/class-backup-manager.php';

        // 工作流自动化引擎
        require_once AISCG_PLUGIN_DIR . 'includes/class-workflow-engine.php';

        // 社交媒体分发系统
        require_once AISCG_PLUGIN_DIR . 'includes/class-social-distributor.php';

        // 内容表现分析器
        require_once AISCG_PLUGIN_DIR . 'includes/class-performance-analyzer.php';

        // 智能内容日历
        require_once AISCG_PLUGIN_DIR . 'includes/class-content-calendar.php';

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

        // 初始化定时调度器
        $scheduler = new AISCG_Scheduler();
    }

    /**
     * 定义公共端的钩子
     */
    private function define_public_hooks() {
        // 初始化短代码
        $shortcodes = new AISCG_Shortcodes();

        // Widget已在class-widget.php中通过hooks自动注册
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
