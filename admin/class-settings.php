<?php
/**
 * 设置页面类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 设置页面类
 */
class AISCG_Settings {

    /**
     * 显示设置页面
     */
    public function display() {
        // 检查用户权限
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'ai-social-content-generator' ) );
        }

        // 获取当前激活的标签
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'ai_models';

        ?>
        <div class="wrap aiscg-settings">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=aiscg-settings&tab=ai_models" class="nav-tab <?php echo $active_tab === 'ai_models' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'AI Models', 'ai-social-content-generator' ); ?>
                </a>
                <a href="?page=aiscg-settings&tab=image_settings" class="nav-tab <?php echo $active_tab === 'image_settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Image Settings', 'ai-social-content-generator' ); ?>
                </a>
                <a href="?page=aiscg-settings&tab=templates" class="nav-tab <?php echo $active_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Content Templates', 'ai-social-content-generator' ); ?>
                </a>
                <a href="?page=aiscg-settings&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Advanced', 'ai-social-content-generator' ); ?>
                </a>
            </h2>

            <div class="aiscg-settings-content">
                <?php
                switch ( $active_tab ) {
                    case 'ai_models':
                        $this->display_ai_models_tab();
                        break;
                    case 'image_settings':
                        $this->display_image_settings_tab();
                        break;
                    case 'templates':
                        $this->display_templates_tab();
                        break;
                    case 'advanced':
                        $this->display_advanced_tab();
                        break;
                    default:
                        $this->display_ai_models_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * 显示AI模型设置标签
     */
    private function display_ai_models_tab() {
        $ai_config = get_option( 'aiscg_ai_config', array() );
        ?>
        <div class="aiscg-tab-content">
            <h2><?php _e( 'AI Model Configuration', 'ai-social-content-generator' ); ?></h2>
            <p class="description">
                <?php _e( 'Configure your AI service providers. You need to obtain API keys from respective providers.', 'ai-social-content-generator' ); ?>
            </p>

            <form id="aiscg-ai-config-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Default AI Model', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <select name="default_model" id="default_model">
                                <option value="openai" <?php selected( isset( $ai_config['default_model'] ) ? $ai_config['default_model'] : '', 'openai' ); ?>>OpenAI GPT</option>
                                <option value="gemini" <?php selected( isset( $ai_config['default_model'] ) ? $ai_config['default_model'] : '', 'gemini' ); ?>>Google Gemini</option>
                                <option value="deepseek" <?php selected( isset( $ai_config['default_model'] ) ? $ai_config['default_model'] : '', 'deepseek' ); ?>>DeepSeek</option>
                                <option value="claude" <?php selected( isset( $ai_config['default_model'] ) ? $ai_config['default_model'] : '', 'claude' ); ?>>Anthropic Claude</option>
                                <option value="qwen" <?php selected( isset( $ai_config['default_model'] ) ? $ai_config['default_model'] : '', 'qwen' ); ?>>通义千问 (Qwen)</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php
                // AI模型配置
                $ai_models = array(
                    'openai' => array(
                        'name' => 'OpenAI GPT',
                        'models' => array( 'gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo' ),
                        'docs' => 'https://platform.openai.com/docs/api-reference',
                    ),
                    'gemini' => array(
                        'name' => 'Google Gemini',
                        'models' => array( 'gemini-pro', 'gemini-pro-vision' ),
                        'docs' => 'https://ai.google.dev/docs',
                    ),
                    'deepseek' => array(
                        'name' => 'DeepSeek',
                        'models' => array( 'deepseek-chat', 'deepseek-coder' ),
                        'docs' => 'https://platform.deepseek.com/api-docs',
                    ),
                    'claude' => array(
                        'name' => 'Anthropic Claude',
                        'models' => array( 'claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307' ),
                        'docs' => 'https://docs.anthropic.com/claude/reference',
                    ),
                    'qwen' => array(
                        'name' => '通义千问 (Qwen)',
                        'models' => array( 'qwen-max', 'qwen-plus', 'qwen-turbo' ),
                        'docs' => 'https://help.aliyun.com/document_detail/2400395.html',
                    ),
                );

                foreach ( $ai_models as $key => $model_info ) :
                    $model_config = isset( $ai_config['models'][ $key ] ) ? $ai_config['models'][ $key ] : array();
                    ?>
                    <div class="aiscg-ai-model-section">
                        <h3><?php echo esc_html( $model_info['name'] ); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php _e( 'Enable', 'ai-social-content-generator' ); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="models[<?php echo $key; ?>][enabled]" value="1" <?php checked( isset( $model_config['enabled'] ) ? $model_config['enabled'] : false, true ); ?>>
                                        <?php _e( 'Enable this AI model', 'ai-social-content-generator' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e( 'API Key', 'ai-social-content-generator' ); ?></label>
                                </th>
                                <td>
                                    <input type="password" name="models[<?php echo $key; ?>][api_key]" value="<?php echo esc_attr( isset( $model_config['api_key'] ) ? $model_config['api_key'] : '' ); ?>" class="regular-text">
                                    <p class="description">
                                        <?php printf( __( 'Get your API key from %s', 'ai-social-content-generator' ), '<a href="' . esc_url( $model_info['docs'] ) . '" target="_blank">' . esc_html( $model_info['name'] ) . '</a>' ); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e( 'Model', 'ai-social-content-generator' ); ?></label>
                                </th>
                                <td>
                                    <select name="models[<?php echo $key; ?>][model]">
                                        <?php foreach ( $model_info['models'] as $model ) : ?>
                                            <option value="<?php echo esc_attr( $model ); ?>" <?php selected( isset( $model_config['model'] ) ? $model_config['model'] : '', $model ); ?>>
                                                <?php echo esc_html( $model ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e( 'Temperature', 'ai-social-content-generator' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="models[<?php echo $key; ?>][temperature]" value="<?php echo esc_attr( isset( $model_config['temperature'] ) ? $model_config['temperature'] : 0.7 ); ?>" min="0" max="2" step="0.1" class="small-text">
                                    <p class="description"><?php _e( 'Controls randomness (0-2). Lower is more focused, higher is more creative.', 'ai-social-content-generator' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e( 'Max Tokens', 'ai-social-content-generator' ); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="models[<?php echo $key; ?>][max_tokens]" value="<?php echo esc_attr( isset( $model_config['max_tokens'] ) ? $model_config['max_tokens'] : 2000 ); ?>" min="100" max="8000" step="100" class="small-text">
                                    <p class="description"><?php _e( 'Maximum length of generated content.', 'ai-social-content-generator' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <button type="button" class="button aiscg-test-connection" data-model="<?php echo esc_attr( $key ); ?>">
                                        <?php _e( 'Test Connection', 'ai-social-content-generator' ); ?>
                                    </button>
                                    <span class="aiscg-test-result"></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endforeach; ?>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e( 'Save Settings', 'ai-social-content-generator' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * 显示图片设置标签
     */
    private function display_image_settings_tab() {
        $image_config = get_option( 'aiscg_image_config', array() );
        ?>
        <div class="aiscg-tab-content">
            <h2><?php _e( 'Image Generation Settings', 'ai-social-content-generator' ); ?></h2>

            <form id="aiscg-image-config-form">
                <h3><?php _e( 'Platform Image Sizes', 'ai-social-content-generator' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e( '小红书 (Xiaohongshu)', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="xiaohongshu[width]" value="<?php echo esc_attr( isset( $image_config['xiaohongshu']['width'] ) ? $image_config['xiaohongshu']['width'] : 1080 ); ?>" class="small-text"> x
                            <input type="number" name="xiaohongshu[height]" value="<?php echo esc_attr( isset( $image_config['xiaohongshu']['height'] ) ? $image_config['xiaohongshu']['height'] : 1440 ); ?>" class="small-text"> px
                            <p class="description"><?php _e( 'Recommended: 1080x1440 (3:4 ratio)', 'ai-social-content-generator' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Instagram', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="instagram[width]" value="<?php echo esc_attr( isset( $image_config['instagram']['width'] ) ? $image_config['instagram']['width'] : 1080 ); ?>" class="small-text"> x
                            <input type="number" name="instagram[height]" value="<?php echo esc_attr( isset( $image_config['instagram']['height'] ) ? $image_config['instagram']['height'] : 1080 ); ?>" class="small-text"> px
                            <p class="description"><?php _e( 'Recommended: 1080x1080 (1:1 ratio) or 1080x1350 (4:5 ratio)', 'ai-social-content-generator' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h3><?php _e( 'Default Template', 'ai-social-content-generator' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Template Style', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <select name="default_template">
                                <option value="gradient" <?php selected( isset( $image_config['default_template'] ) ? $image_config['default_template'] : '', 'gradient' ); ?>><?php _e( 'Gradient Background', 'ai-social-content-generator' ); ?></option>
                                <option value="solid" <?php selected( isset( $image_config['default_template'] ) ? $image_config['default_template'] : '', 'solid' ); ?>><?php _e( 'Solid Color', 'ai-social-content-generator' ); ?></option>
                                <option value="minimal" <?php selected( isset( $image_config['default_template'] ) ? $image_config['default_template'] : '', 'minimal' ); ?>><?php _e( 'Minimal', 'ai-social-content-generator' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Font Family', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="font_family" value="<?php echo esc_attr( isset( $image_config['font_family'] ) ? $image_config['font_family'] : 'Arial' ); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Font family for text in images', 'ai-social-content-generator' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h3><?php _e( 'Watermark', 'ai-social-content-generator' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Enable Watermark', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="watermark_enabled" value="1" <?php checked( isset( $image_config['watermark_enabled'] ) ? $image_config['watermark_enabled'] : false, true ); ?>>
                                <?php _e( 'Add watermark to generated images', 'ai-social-content-generator' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Watermark Image', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="watermark_image" id="watermark_image" value="<?php echo esc_attr( isset( $image_config['watermark_image'] ) ? $image_config['watermark_image'] : '' ); ?>" class="regular-text">
                            <button type="button" class="button aiscg-upload-image"><?php _e( 'Upload Image', 'ai-social-content-generator' ); ?></button>
                            <p class="description"><?php _e( 'Upload a watermark/logo image', 'ai-social-content-generator' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e( 'Save Settings', 'ai-social-content-generator' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * 显示内容模板标签
     */
    private function display_templates_tab() {
        $templates = get_option( 'aiscg_content_templates', array() );
        ?>
        <div class="aiscg-tab-content">
            <h2><?php _e( 'Content Generation Templates', 'ai-social-content-generator' ); ?></h2>
            <p class="description">
                <?php _e( 'Customize the prompt templates for each platform. Use {topic} as placeholder for the topic.', 'ai-social-content-generator' ); ?>
            </p>

            <form id="aiscg-templates-form">
                <h3><?php _e( '小红书 (Xiaohongshu) Template', 'ai-social-content-generator' ); ?></h3>
                <textarea name="xiaohongshu" rows="10" class="large-text code"><?php echo esc_textarea( isset( $templates['xiaohongshu'] ) ? $templates['xiaohongshu'] : '' ); ?></textarea>

                <h3><?php _e( 'Instagram Template', 'ai-social-content-generator' ); ?></h3>
                <textarea name="instagram" rows="10" class="large-text code"><?php echo esc_textarea( isset( $templates['instagram'] ) ? $templates['instagram'] : '' ); ?></textarea>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e( 'Save Templates', 'ai-social-content-generator' ); ?></button>
                    <button type="button" class="button aiscg-reset-templates"><?php _e( 'Reset to Default', 'ai-social-content-generator' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * 显示高级设置标签
     */
    private function display_advanced_tab() {
        ?>
        <div class="aiscg-tab-content">
            <h2><?php _e( 'Advanced Settings', 'ai-social-content-generator' ); ?></h2>

            <form id="aiscg-advanced-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Request Timeout', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="request_timeout" value="<?php echo esc_attr( get_option( 'aiscg_request_timeout', 30 ) ); ?>" min="10" max="120" class="small-text"> <?php _e( 'seconds', 'ai-social-content-generator' ); ?>
                            <p class="description"><?php _e( 'Timeout for AI API requests', 'ai-social-content-generator' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Concurrent Requests', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="concurrent_requests" value="<?php echo esc_attr( get_option( 'aiscg_concurrent_requests', 3 ) ); ?>" min="1" max="10" class="small-text">
                            <p class="description"><?php _e( 'Maximum concurrent requests for batch generation', 'ai-social-content-generator' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e( 'Enable Logging', 'ai-social-content-generator' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_logging" value="1" <?php checked( get_option( 'aiscg_enable_logging', false ), true ); ?>>
                                <?php _e( 'Enable debug logging', 'ai-social-content-generator' ); ?>
                            </label>
                            <p class="description"><?php _e( 'Log AI requests and responses for debugging', 'ai-social-content-generator' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e( 'Save Settings', 'ai-social-content-generator' ); ?></button>
                </p>
            </form>

            <hr>

            <h3><?php _e( 'Database Management', 'ai-social-content-generator' ); ?></h3>
            <p class="description">
                <?php _e( 'Dangerous operations - use with caution!', 'ai-social-content-generator' ); ?>
            </p>
            <button type="button" class="button aiscg-clear-history"><?php _e( 'Clear All History', 'ai-social-content-generator' ); ?></button>
        </div>
        <?php
    }
}
