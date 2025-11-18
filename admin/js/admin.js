/**
 * AI Social Content Generator - Admin JavaScript
 *
 * @package AI_Social_Content_Generator
 */

(function($) {
    'use strict';

    /**
     * Generator Page
     */
    const GeneratorPage = {
        currentPostId: null,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // 生成模式切换
            $('#generation-mode').on('change', this.handleModeChange.bind(this));

            // 单个生成
            $('#generate-btn').on('click', this.handleGenerate.bind(this));
            $('#regenerate-btn').on('click', this.handleRegenerate.bind(this));
            $('#copy-content-btn').on('click', this.handleCopyContent.bind(this));
            $('#download-images-btn').on('click', this.handleDownloadImages.bind(this));

            // 批量生成
            $('#batch-generate-btn').on('click', this.handleBatchGenerate.bind(this));
            $('#csv-upload').on('change', this.handleCSVUpload.bind(this));
        },

        handleModeChange: function(e) {
            const mode = $(e.target).val();

            if (mode === 'single') {
                $('#single-mode').show();
                $('#batch-mode').hide();
            } else {
                $('#single-mode').hide();
                $('#batch-mode').show();
            }
        },

        handleGenerate: function(e) {
            e.preventDefault();

            const topic = $('#topic').val().trim();
            const platform = $('#platform').val();
            const aiModel = $('#ai-model').val();
            const customPrompt = $('#custom-prompt').val().trim();
            const imageCount = parseInt($('#image-count').val()) || 1;

            if (!topic) {
                alert(aiscgData.i18n.error + ': Topic is required');
                return;
            }

            this.showProgress();

            // 第一步:生成内容
            this.generateContent(topic, platform, aiModel, customPrompt)
                .then(result => {
                    this.currentPostId = result.post_id;
                    this.displayResult(result);

                    // 第二步:生成图片
                    return this.generateImages(result.post_id, imageCount);
                })
                .then(images => {
                    this.displayImages(images);
                    this.hideProgress();
                })
                .catch(error => {
                    this.hideProgress();
                    alert(aiscgData.i18n.error + ': ' + error.message);
                });
        },

        generateContent: function(topic, platform, aiModel, customPrompt) {
            return fetch(aiscgData.restUrl + '/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiscgData.nonce
                },
                body: JSON.stringify({
                    topic: topic,
                    platform: platform,
                    ai_model: aiModel,
                    custom_prompt: customPrompt
                })
            }).then(response => {
                if (!response.ok) {
                    throw new Error('Failed to generate content');
                }
                return response.json();
            });
        },

        generateImages: function(postId, count) {
            return fetch(aiscgData.restUrl + '/generate-images', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiscgData.nonce
                },
                body: JSON.stringify({
                    post_id: postId,
                    count: count
                })
            }).then(response => {
                if (!response.ok) {
                    throw new Error('Failed to generate images');
                }
                return response.json();
            });
        },

        showProgress: function() {
            $('#generation-result').hide();
            $('#generation-progress').show();
            $('.aiscg-progress-fill').css('width', '50%');
        },

        hideProgress: function() {
            $('#generation-progress').hide();
        },

        displayResult: function(result) {
            $('#result-title').text(result.title);
            $('#result-content').text(result.content);
            $('#result-hashtags').html(
                result.hashtags.map(tag => `<span class="aiscg-tag">${tag}</span>`).join(' ')
            );
            $('#generation-result').show();
        },

        displayImages: function(images) {
            const container = $('#result-images-container');
            container.empty();

            images.forEach(image => {
                const html = `
                    <div class="aiscg-image-item">
                        <img src="${image.url}" alt="Generated Image ${image.order + 1}">
                        <div class="aiscg-image-actions">
                            <a href="${image.url}" download>Download</a>
                        </div>
                    </div>
                `;
                container.append(html);
            });
        },

        handleRegenerate: function(e) {
            e.preventDefault();

            if (!this.currentPostId) {
                alert('No content to regenerate');
                return;
            }

            this.showProgress();

            fetch(aiscgData.restUrl + '/regenerate/' + this.currentPostId, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aiscgData.nonce
                }
            })
            .then(response => response.json())
            .then(result => {
                this.displayResult(result);
                return this.generateImages(result.post_id, $('#image-count').val());
            })
            .then(images => {
                this.displayImages(images);
                this.hideProgress();
            })
            .catch(error => {
                this.hideProgress();
                alert(aiscgData.i18n.error + ': ' + error.message);
            });
        },

        handleCopyContent: function(e) {
            e.preventDefault();

            const title = $('#result-title').text();
            const content = $('#result-content').text();
            const hashtags = $('#result-hashtags').text();

            const fullText = `${title}\n\n${content}\n\n${hashtags}`;

            navigator.clipboard.writeText(fullText).then(() => {
                alert(aiscgData.i18n.success + ': Content copied to clipboard');
            }).catch(err => {
                alert('Failed to copy content');
            });
        },

        handleDownloadImages: function(e) {
            e.preventDefault();

            if (!this.currentPostId) {
                alert('No images to download');
                return;
            }

            window.location.href = aiscgData.restUrl + '/download-images/' + this.currentPostId;
        },

        handleBatchGenerate: function(e) {
            e.preventDefault();

            const topics = $('#batch-topics').val().trim().split('\n').filter(t => t.trim());

            if (topics.length === 0) {
                alert('Please enter at least one topic');
                return;
            }

            this.startBatchGeneration(topics);
        },

        startBatchGeneration: function(topics) {
            const platform = $('#platform').val();
            const aiModel = $('#ai-model').val();
            const imageCount = $('#image-count').val();

            $('#batch-total').text(topics.length);
            $('#batch-current').text(0);
            $('#batch-progress').show();
            $('#batch-results-list').empty();

            let completed = 0;

            const processNext = () => {
                if (completed >= topics.length) {
                    $('#batch-progress').hide();
                    $('#batch-results').show();
                    return;
                }

                const topic = topics[completed];

                this.generateContent(topic, platform, aiModel, '')
                    .then(result => {
                        completed++;
                        $('#batch-current').text(completed);
                        $('.aiscg-progress-fill').css('width', (completed / topics.length * 100) + '%');

                        this.addBatchResult(topic, result, 'success');
                        processNext();
                    })
                    .catch(error => {
                        completed++;
                        $('#batch-current').text(completed);
                        $('.aiscg-progress-fill').css('width', (completed / topics.length * 100) + '%');

                        this.addBatchResult(topic, null, 'error', error.message);
                        processNext();
                    });
            };

            processNext();
        },

        addBatchResult: function(topic, result, status, errorMsg = '') {
            const html = `
                <div class="aiscg-batch-result ${status}">
                    <h4>${topic}</h4>
                    ${status === 'success' ? `
                        <p>✓ Generated successfully</p>
                        <a href="${aiscgData.ajaxUrl}?action=aiscg_view_post&post_id=${result.post_id}" class="button">View</a>
                    ` : `
                        <p>✗ Failed: ${errorMsg}</p>
                    `}
                </div>
            `;
            $('#batch-results-list').append(html);
        },

        handleCSVUpload: function(e) {
            const file = e.target.files[0];

            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.onload = (evt) => {
                const text = evt.target.result;
                const lines = text.split('\n').map(line => line.trim()).filter(line => line);
                $('#batch-topics').val(lines.join('\n'));
            };
            reader.readAsText(file);
        }
    };

    /**
     * History Page
     */
    const HistoryPage = {
        currentPage: 1,
        perPage: 20,
        currentPostId: null,

        init: function() {
            this.bindEvents();
            this.loadHistory();
        },

        bindEvents: function() {
            $('#apply-filters').on('click', this.applyFilters.bind(this));
            $('#reset-filters').on('click', this.resetFilters.bind(this));

            // 模态框
            $('.aiscg-modal-close, .aiscg-modal-overlay').on('click', this.closeModal.bind(this));
            $('#modal-delete-btn').on('click', this.handleDelete.bind(this));
            $('#modal-edit-btn').on('click', this.openEditModal.bind(this));
            $('#save-edit-btn').on('click', this.saveEdit.bind(this));
        },

        loadHistory: function(page = 1) {
            this.currentPage = page;
            $('#history-loading').show();
            $('#history-items').hide();

            const params = new URLSearchParams({
                platform: $('#filter-platform').val(),
                status: $('#filter-status').val(),
                limit: this.perPage,
                offset: (page - 1) * this.perPage
            });

            fetch(aiscgData.restUrl + '/posts?' + params.toString(), {
                headers: {
                    'X-WP-Nonce': aiscgData.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                this.displayHistory(data.posts);
                this.displayPagination(data.total);
                $('#history-loading').hide();
                $('#history-items').show();
            })
            .catch(error => {
                $('#history-loading').hide();
                alert('Failed to load history');
            });
        },

        displayHistory: function(posts) {
            const container = $('#history-items');
            container.empty();

            if (posts.length === 0) {
                $('#history-empty').show();
                return;
            }

            $('#history-empty').hide();

            posts.forEach(post => {
                const html = `
                    <div class="aiscg-history-item" data-post-id="${post.id}">
                        <div class="aiscg-history-thumbnail">
                            ${post.images && post.images.length > 0 ?
                                `<img src="${post.images[0].url}" alt="${post.title}">` :
                                '<div style="width:100%;height:100%;background:#f0f0f1;"></div>'}
                        </div>
                        <div class="aiscg-history-info">
                            <h4>${post.title}</h4>
                            <div class="aiscg-history-meta">
                                <span class="aiscg-platform-badge ${post.platform}">${post.platform}</span>
                                <span class="aiscg-status-badge ${post.status}">${post.status}</span>
                                <span>${post.created_at}</span>
                                <span>${post.ai_model}</span>
                            </div>
                            <div class="aiscg-history-excerpt">${post.content.substring(0, 150)}...</div>
                        </div>
                        <div class="aiscg-history-actions">
                            <button class="button view-btn" data-post-id="${post.id}">View</button>
                            <button class="button edit-btn" data-post-id="${post.id}">Edit</button>
                            <button class="button delete-btn" data-post-id="${post.id}">Delete</button>
                        </div>
                    </div>
                `;
                container.append(html);
            });

            // 绑定按钮事件
            $('.view-btn').on('click', this.viewPost.bind(this));
            $('.edit-btn').on('click', this.editPost.bind(this));
            $('.delete-btn').on('click', this.deletePost.bind(this));
        },

        displayPagination: function(total) {
            const totalPages = Math.ceil(total / this.perPage);
            const container = $('#history-pagination');
            container.empty();

            if (totalPages <= 1) {
                return;
            }

            for (let i = 1; i <= totalPages; i++) {
                const btn = $('<button>')
                    .addClass('button')
                    .text(i)
                    .toggleClass('active', i === this.currentPage)
                    .on('click', () => this.loadHistory(i));
                container.append(btn);
            }
        },

        applyFilters: function() {
            this.loadHistory(1);
        },

        resetFilters: function() {
            $('#filter-platform').val('');
            $('#filter-status').val('');
            $('#filter-search').val('');
            this.loadHistory(1);
        },

        viewPost: function(e) {
            const postId = $(e.target).data('post-id');
            this.loadPost(postId);
            $('#content-modal').show();
        },

        editPost: function(e) {
            const postId = $(e.target).data('post-id');
            this.currentPostId = postId;
            this.loadPostForEdit(postId);
        },

        deletePost: function(e) {
            const postId = $(e.target).data('post-id');

            if (!confirm('Are you sure you want to delete this content?')) {
                return;
            }

            fetch(aiscgData.restUrl + '/posts/' + postId, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': aiscgData.nonce
                }
            })
            .then(response => response.json())
            .then(() => {
                this.loadHistory(this.currentPage);
            })
            .catch(error => {
                alert('Failed to delete content');
            });
        },

        loadPost: function(postId) {
            fetch(aiscgData.restUrl + '/posts/' + postId, {
                headers: {
                    'X-WP-Nonce': aiscgData.nonce
                }
            })
            .then(response => response.json())
            .then(post => {
                this.displayPostDetails(post);
            });
        },

        displayPostDetails: function(post) {
            const html = `
                <h4>${post.title}</h4>
                <div class="aiscg-history-meta">
                    <span class="aiscg-platform-badge ${post.platform}">${post.platform}</span>
                    <span class="aiscg-status-badge ${post.status}">${post.status}</span>
                    <span>${post.created_at}</span>
                </div>
                <div style="margin: 20px 0;">
                    <strong>Content:</strong>
                    <div style="white-space: pre-wrap; padding: 10px; background: #f9f9f9; border-radius: 4px; margin-top: 10px;">
                        ${post.content}
                    </div>
                </div>
                <div style="margin: 20px 0;">
                    <strong>Hashtags:</strong>
                    <div style="margin-top: 10px;">
                        ${post.hashtags.map(tag => `<span class="aiscg-tag">${tag}</span>`).join(' ')}
                    </div>
                </div>
                ${post.images && post.images.length > 0 ? `
                    <div style="margin: 20px 0;">
                        <strong>Images:</strong>
                        <div class="aiscg-images-grid" style="margin-top: 10px;">
                            ${post.images.map(img => `
                                <div class="aiscg-image-item">
                                    <img src="${img.image_url}" alt="Image">
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            `;
            $('#modal-content-details').html(html);
        },

        loadPostForEdit: function(postId) {
            fetch(aiscgData.restUrl + '/posts/' + postId, {
                headers: {
                    'X-WP-Nonce': aiscgData.nonce
                }
            })
            .then(response => response.json())
            .then(post => {
                $('#edit-title').val(post.title);
                $('#edit-content').val(post.content);
                $('#edit-hashtags').val(post.hashtags.join('\n'));
                $('#edit-status').val(post.status);
                $('#edit-modal').show();
            });
        },

        openEditModal: function() {
            $('#content-modal').hide();
            // Load for edit
        },

        saveEdit: function() {
            const data = {
                title: $('#edit-title').val(),
                content: $('#edit-content').val(),
                hashtags: $('#edit-hashtags').val().split('\n').filter(t => t.trim()),
                status: $('#edit-status').val()
            };

            fetch(aiscgData.restUrl + '/posts/' + this.currentPostId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiscgData.nonce
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(() => {
                $('#edit-modal').hide();
                this.loadHistory(this.currentPage);
                alert('Content updated successfully');
            })
            .catch(error => {
                alert('Failed to update content');
            });
        },

        handleDelete: function() {
            if (this.currentPostId) {
                this.deletePost({ target: { dataset: { postId: this.currentPostId } } });
                this.closeModal();
            }
        },

        closeModal: function() {
            $('.aiscg-modal').hide();
        }
    };

    /**
     * Settings Page
     */
    const SettingsPage = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#aiscg-ai-config-form').on('submit', this.saveAIConfig.bind(this));
            $('#aiscg-image-config-form').on('submit', this.saveImageConfig.bind(this));
            $('#aiscg-templates-form').on('submit', this.saveTemplates.bind(this));
            $('#aiscg-advanced-form').on('submit', this.saveAdvanced.bind(this));

            $('.aiscg-test-connection').on('click', this.testConnection.bind(this));
            $('.aiscg-reset-templates').on('click', this.resetTemplates.bind(this));
        },

        saveAIConfig: function(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            const data = {};

            formData.forEach((value, key) => {
                if (key.includes('[')) {
                    // Handle nested arrays
                    const matches = key.match(/^([^\[]+)\[([^\]]+)\]\[([^\]]+)\]$/);
                    if (matches) {
                        if (!data[matches[1]]) data[matches[1]] = {};
                        if (!data[matches[1]][matches[2]]) data[matches[1]][matches[2]] = {};
                        data[matches[1]][matches[2]][matches[3]] = value;
                    }
                } else {
                    data[key] = value;
                }
            });

            this.saveSettings({ ai_config: data });
        },

        saveImageConfig: function(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            this.saveSettings({ image_config: data });
        },

        saveTemplates: function(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            this.saveSettings({ content_templates: data });
        },

        saveAdvanced: function(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            this.saveSettings(data);
        },

        saveSettings: function(data) {
            fetch(aiscgData.restUrl + '/settings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiscgData.nonce
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(() => {
                alert(aiscgData.i18n.success + ': Settings saved');
            })
            .catch(error => {
                alert(aiscgData.i18n.error + ': Failed to save settings');
            });
        },

        testConnection: function(e) {
            const button = $(e.target);
            const model = button.data('model');
            const resultSpan = button.siblings('.aiscg-test-result');

            button.prop('disabled', true).text('Testing...');
            resultSpan.text('');

            fetch(aiscgData.restUrl + '/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': aiscgData.nonce
                },
                body: JSON.stringify({ ai_model: model })
            })
            .then(response => response.json())
            .then(data => {
                button.prop('disabled', false).text('Test Connection');
                resultSpan.addClass('success').text('✓ ' + data.message);
            })
            .catch(error => {
                button.prop('disabled', false).text('Test Connection');
                resultSpan.addClass('error').text('✗ Failed');
            });
        },

        resetTemplates: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to reset templates to default?')) {
                return;
            }

            // Reset logic here
            alert('Templates reset to default');
        }
    };

    /**
     * Initialize
     */
    $(document).ready(function() {
        // Detect current page and initialize
        if ($('.aiscg-generator-page').length) {
            GeneratorPage.init();
        }

        if ($('.aiscg-history-page').length) {
            HistoryPage.init();
        }

        if ($('.aiscg-settings').length) {
            SettingsPage.init();
        }
    });

})(jQuery);
