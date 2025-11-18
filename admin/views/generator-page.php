<?php
/**
 * 内容生成器页面视图
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 获取可用的AI服务
$available_services = AISCG_AI_Service_Factory::get_available_services();
?>

<div class="wrap aiscg-generator-page">
    <h1><?php _e( 'AI Content Generator', 'ai-social-content-generator' ); ?></h1>

    <?php if ( empty( $available_services ) ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    __( 'No AI models are configured. Please <a href="%s">configure at least one AI model</a> to start generating content.', 'ai-social-content-generator' ),
                    admin_url( 'admin.php?page=aiscg-settings' )
                );
                ?>
            </p>
        </div>
    <?php else : ?>

        <div class="aiscg-generator-container">
            <div class="aiscg-generator-sidebar">
                <div class="aiscg-card">
                    <h3><?php _e( 'Generation Settings', 'ai-social-content-generator' ); ?></h3>

                    <div class="aiscg-form-group">
                        <label for="generation-mode"><?php _e( 'Generation Mode', 'ai-social-content-generator' ); ?></label>
                        <select id="generation-mode" class="aiscg-select">
                            <option value="single"><?php _e( 'Single Generation', 'ai-social-content-generator' ); ?></option>
                            <option value="batch"><?php _e( 'Batch Generation', 'ai-social-content-generator' ); ?></option>
                        </select>
                    </div>

                    <div class="aiscg-form-group">
                        <label for="platform"><?php _e( 'Platform', 'ai-social-content-generator' ); ?></label>
                        <select id="platform" class="aiscg-select">
                            <option value="xiaohongshu"><?php _e( '小红书 (Xiaohongshu)', 'ai-social-content-generator' ); ?></option>
                            <option value="instagram"><?php _e( 'Instagram', 'ai-social-content-generator' ); ?></option>
                        </select>
                    </div>

                    <div class="aiscg-form-group">
                        <label for="ai-model"><?php _e( 'AI Model', 'ai-social-content-generator' ); ?></label>
                        <select id="ai-model" class="aiscg-select">
                            <option value=""><?php _e( 'Default Model', 'ai-social-content-generator' ); ?></option>
                            <?php foreach ( $available_services as $key => $name ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="aiscg-form-group">
                        <label for="image-count"><?php _e( 'Number of Images', 'ai-social-content-generator' ); ?></label>
                        <input type="number" id="image-count" class="aiscg-input" value="1" min="1" max="9">
                        <p class="description"><?php _e( 'Generate 1-9 images', 'ai-social-content-generator' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="aiscg-generator-main">
                <div id="single-mode" class="aiscg-generation-mode">
                    <div class="aiscg-card">
                        <h3><?php _e( 'Content Topic', 'ai-social-content-generator' ); ?></h3>

                        <div class="aiscg-form-group">
                            <label for="topic"><?php _e( 'Topic or Keywords', 'ai-social-content-generator' ); ?></label>
                            <input type="text" id="topic" class="aiscg-input" placeholder="<?php _e( 'Enter your topic or keywords...', 'ai-social-content-generator' ); ?>">
                        </div>

                        <div class="aiscg-form-group">
                            <label for="custom-prompt">
                                <?php _e( 'Custom Prompt (Optional)', 'ai-social-content-generator' ); ?>
                            </label>
                            <textarea id="custom-prompt" class="aiscg-textarea" rows="5" placeholder="<?php _e( 'Enter custom prompt template (use {topic} as placeholder)...', 'ai-social-content-generator' ); ?>"></textarea>
                        </div>

                        <div class="aiscg-form-actions">
                            <button type="button" id="generate-btn" class="button button-primary button-large">
                                <span class="dashicons dashicons-admin-customizer"></span>
                                <?php _e( 'Generate Content', 'ai-social-content-generator' ); ?>
                            </button>
                        </div>
                    </div>

                    <div id="generation-progress" class="aiscg-card" style="display: none;">
                        <h3><?php _e( 'Generating...', 'ai-social-content-generator' ); ?></h3>
                        <div class="aiscg-progress-bar">
                            <div class="aiscg-progress-fill"></div>
                        </div>
                        <p class="aiscg-progress-text"><?php _e( 'Please wait...', 'ai-social-content-generator' ); ?></p>
                    </div>

                    <div id="generation-result" class="aiscg-card" style="display: none;">
                        <h3><?php _e( 'Generated Content', 'ai-social-content-generator' ); ?></h3>

                        <div class="aiscg-result-content">
                            <div class="aiscg-result-text">
                                <h4><?php _e( 'Title', 'ai-social-content-generator' ); ?></h4>
                                <div id="result-title" class="aiscg-result-field"></div>

                                <h4><?php _e( 'Content', 'ai-social-content-generator' ); ?></h4>
                                <div id="result-content" class="aiscg-result-field"></div>

                                <h4><?php _e( 'Hashtags', 'ai-social-content-generator' ); ?></h4>
                                <div id="result-hashtags" class="aiscg-result-field"></div>
                            </div>

                            <div class="aiscg-result-images">
                                <h4><?php _e( 'Generated Images', 'ai-social-content-generator' ); ?></h4>
                                <div id="result-images-container" class="aiscg-images-grid"></div>
                            </div>
                        </div>

                        <div class="aiscg-result-actions">
                            <button type="button" id="regenerate-btn" class="button">
                                <?php _e( 'Regenerate', 'ai-social-content-generator' ); ?>
                            </button>
                            <button type="button" id="download-images-btn" class="button">
                                <?php _e( 'Download Images', 'ai-social-content-generator' ); ?>
                            </button>
                            <button type="button" id="copy-content-btn" class="button">
                                <?php _e( 'Copy Content', 'ai-social-content-generator' ); ?>
                            </button>
                            <a href="<?php echo admin_url( 'admin.php?page=aiscg-history' ); ?>" class="button">
                                <?php _e( 'View History', 'ai-social-content-generator' ); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div id="batch-mode" class="aiscg-generation-mode" style="display: none;">
                    <div class="aiscg-card">
                        <h3><?php _e( 'Batch Generation', 'ai-social-content-generator' ); ?></h3>

                        <div class="aiscg-form-group">
                            <label><?php _e( 'Input Topics', 'ai-social-content-generator' ); ?></label>
                            <p class="description">
                                <?php _e( 'Enter one topic per line, or upload a CSV file with topics', 'ai-social-content-generator' ); ?>
                            </p>
                            <textarea id="batch-topics" class="aiscg-textarea" rows="10" placeholder="<?php _e( 'Topic 1\nTopic 2\nTopic 3...', 'ai-social-content-generator' ); ?>"></textarea>
                        </div>

                        <div class="aiscg-form-group">
                            <label><?php _e( 'Or Upload CSV', 'ai-social-content-generator' ); ?></label>
                            <input type="file" id="csv-upload" accept=".csv" class="aiscg-file-input">
                        </div>

                        <div class="aiscg-form-actions">
                            <button type="button" id="batch-generate-btn" class="button button-primary button-large">
                                <?php _e( 'Start Batch Generation', 'ai-social-content-generator' ); ?>
                            </button>
                        </div>
                    </div>

                    <div id="batch-progress" class="aiscg-card" style="display: none;">
                        <h3><?php _e( 'Batch Generation Progress', 'ai-social-content-generator' ); ?></h3>
                        <div class="aiscg-progress-bar">
                            <div class="aiscg-progress-fill"></div>
                        </div>
                        <p class="aiscg-progress-text">
                            <span id="batch-current">0</span> / <span id="batch-total">0</span>
                        </p>
                    </div>

                    <div id="batch-results" class="aiscg-card" style="display: none;">
                        <h3><?php _e( 'Batch Results', 'ai-social-content-generator' ); ?></h3>
                        <div id="batch-results-list"></div>
                    </div>
                </div>
            </div>

            <div class="aiscg-generator-tips">
                <div class="aiscg-card">
                    <h3><?php _e( 'Tips', 'ai-social-content-generator' ); ?></h3>
                    <ul>
                        <li><?php _e( 'Be specific with your topics for better results', 'ai-social-content-generator' ); ?></li>
                        <li><?php _e( 'Different AI models may produce different styles of content', 'ai-social-content-generator' ); ?></li>
                        <li><?php _e( 'You can customize the prompt template in Settings', 'ai-social-content-generator' ); ?></li>
                        <li><?php _e( 'Generated content is saved in History for later use', 'ai-social-content-generator' ); ?></li>
                    </ul>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>
