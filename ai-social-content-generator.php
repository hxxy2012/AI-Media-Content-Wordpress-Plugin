<?php
/**
 * Plugin Name: AI Social Content Generator
 * Plugin URI: https://github.com/hxxy2012/AI-Media-Content-Wordpress-Plugin
 * Description: 通过多个AI模型(Gemini、DeepSeek等)生成小红书和Instagram的营销内容及配图,支持单个生成、批量生成和自动化生成功能。
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/hxxy2012
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-social-content-generator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 定义插件常量
define( 'AISCG_VERSION', '1.0.0' );
define( 'AISCG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AISCG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AISCG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * 插件激活钩子
 */
function activate_aiscg() {
    require_once AISCG_PLUGIN_DIR . 'includes/class-activator.php';
    AISCG_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_aiscg' );

/**
 * 插件停用钩子
 */
function deactivate_aiscg() {
    require_once AISCG_PLUGIN_DIR . 'includes/class-deactivator.php';
    AISCG_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_aiscg' );

/**
 * 核心插件类,用于定义国际化、管理员特定钩子和公共端钩子
 */
require_once AISCG_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * 开始执行插件
 */
function run_aiscg() {
    $plugin = new AISCG_Plugin();
    $plugin->run();
}
run_aiscg();
