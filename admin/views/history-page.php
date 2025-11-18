<?php
/**
 * 历史记录页面视图
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap aiscg-history-page">
    <h1><?php _e( 'Content History', 'ai-social-content-generator' ); ?></h1>

    <div class="aiscg-history-filters">
        <div class="aiscg-card">
            <div class="aiscg-filters-row">
                <div class="aiscg-filter-group">
                    <label for="filter-platform"><?php _e( 'Platform', 'ai-social-content-generator' ); ?></label>
                    <select id="filter-platform" class="aiscg-select">
                        <option value=""><?php _e( 'All Platforms', 'ai-social-content-generator' ); ?></option>
                        <option value="xiaohongshu"><?php _e( '小红书', 'ai-social-content-generator' ); ?></option>
                        <option value="instagram"><?php _e( 'Instagram', 'ai-social-content-generator' ); ?></option>
                    </select>
                </div>

                <div class="aiscg-filter-group">
                    <label for="filter-status"><?php _e( 'Status', 'ai-social-content-generator' ); ?></label>
                    <select id="filter-status" class="aiscg-select">
                        <option value=""><?php _e( 'All Status', 'ai-social-content-generator' ); ?></option>
                        <option value="draft"><?php _e( 'Draft', 'ai-social-content-generator' ); ?></option>
                        <option value="published"><?php _e( 'Published', 'ai-social-content-generator' ); ?></option>
                        <option value="archived"><?php _e( 'Archived', 'ai-social-content-generator' ); ?></option>
                    </select>
                </div>

                <div class="aiscg-filter-group">
                    <label for="filter-search"><?php _e( 'Search', 'ai-social-content-generator' ); ?></label>
                    <input type="text" id="filter-search" class="aiscg-input" placeholder="<?php _e( 'Search content...', 'ai-social-content-generator' ); ?>">
                </div>

                <div class="aiscg-filter-group">
                    <label>&nbsp;</label>
                    <button type="button" id="apply-filters" class="button button-primary">
                        <?php _e( 'Apply Filters', 'ai-social-content-generator' ); ?>
                    </button>
                    <button type="button" id="reset-filters" class="button">
                        <?php _e( 'Reset', 'ai-social-content-generator' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="aiscg-history-list">
        <div id="history-loading" class="aiscg-loading" style="display: none;">
            <span class="spinner is-active"></span>
            <?php _e( 'Loading...', 'ai-social-content-generator' ); ?>
        </div>

        <div id="history-empty" class="aiscg-empty-state" style="display: none;">
            <span class="dashicons dashicons-media-default"></span>
            <h3><?php _e( 'No content found', 'ai-social-content-generator' ); ?></h3>
            <p><?php _e( 'Start generating content to see it here', 'ai-social-content-generator' ); ?></p>
            <a href="<?php echo admin_url( 'admin.php?page=aiscg-generator' ); ?>" class="button button-primary">
                <?php _e( 'Generate Content', 'ai-social-content-generator' ); ?>
            </a>
        </div>

        <div id="history-items" class="aiscg-history-items"></div>

        <div id="history-pagination" class="aiscg-pagination"></div>
    </div>
</div>

<!-- 内容详情模态框 -->
<div id="content-modal" class="aiscg-modal" style="display: none;">
    <div class="aiscg-modal-overlay"></div>
    <div class="aiscg-modal-content">
        <div class="aiscg-modal-header">
            <h2><?php _e( 'Content Details', 'ai-social-content-generator' ); ?></h2>
            <button type="button" class="aiscg-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>

        <div class="aiscg-modal-body">
            <div id="modal-content-details"></div>
        </div>

        <div class="aiscg-modal-footer">
            <button type="button" class="button button-primary" id="modal-edit-btn">
                <?php _e( 'Edit', 'ai-social-content-generator' ); ?>
            </button>
            <button type="button" class="button" id="modal-regenerate-btn">
                <?php _e( 'Regenerate', 'ai-social-content-generator' ); ?>
            </button>
            <button type="button" class="button" id="modal-download-btn">
                <?php _e( 'Download Images', 'ai-social-content-generator' ); ?>
            </button>
            <button type="button" class="button button-link-delete" id="modal-delete-btn">
                <?php _e( 'Delete', 'ai-social-content-generator' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- 编辑模态框 -->
<div id="edit-modal" class="aiscg-modal" style="display: none;">
    <div class="aiscg-modal-overlay"></div>
    <div class="aiscg-modal-content">
        <div class="aiscg-modal-header">
            <h2><?php _e( 'Edit Content', 'ai-social-content-generator' ); ?></h2>
            <button type="button" class="aiscg-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>

        <div class="aiscg-modal-body">
            <div class="aiscg-form-group">
                <label for="edit-title"><?php _e( 'Title', 'ai-social-content-generator' ); ?></label>
                <input type="text" id="edit-title" class="aiscg-input">
            </div>

            <div class="aiscg-form-group">
                <label for="edit-content"><?php _e( 'Content', 'ai-social-content-generator' ); ?></label>
                <textarea id="edit-content" class="aiscg-textarea" rows="10"></textarea>
            </div>

            <div class="aiscg-form-group">
                <label for="edit-hashtags"><?php _e( 'Hashtags (one per line)', 'ai-social-content-generator' ); ?></label>
                <textarea id="edit-hashtags" class="aiscg-textarea" rows="5"></textarea>
            </div>

            <div class="aiscg-form-group">
                <label for="edit-status"><?php _e( 'Status', 'ai-social-content-generator' ); ?></label>
                <select id="edit-status" class="aiscg-select">
                    <option value="draft"><?php _e( 'Draft', 'ai-social-content-generator' ); ?></option>
                    <option value="published"><?php _e( 'Published', 'ai-social-content-generator' ); ?></option>
                    <option value="archived"><?php _e( 'Archived', 'ai-social-content-generator' ); ?></option>
                </select>
            </div>
        </div>

        <div class="aiscg-modal-footer">
            <button type="button" class="button button-primary" id="save-edit-btn">
                <?php _e( 'Save Changes', 'ai-social-content-generator' ); ?>
            </button>
            <button type="button" class="button aiscg-modal-close">
                <?php _e( 'Cancel', 'ai-social-content-generator' ); ?>
            </button>
        </div>
    </div>
</div>
