<?php
/**
 * 短代码类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 短代码类
 */
class AISCG_Shortcodes {

    /**
     * 数据库实例
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = new AISCG_Database();
        $this->register_shortcodes();
    }

    /**
     * 注册短代码
     */
    private function register_shortcodes() {
        add_shortcode( 'aiscg_content', array( $this, 'render_content' ) );
        add_shortcode( 'aiscg_gallery', array( $this, 'render_gallery' ) );
        add_shortcode( 'aiscg_stats', array( $this, 'render_stats' ) );
        add_shortcode( 'aiscg_latest', array( $this, 'render_latest_posts' ) );
    }

    /**
     * 渲染单个内容
     *
     * 用法: [aiscg_content id="123" show_images="yes"]
     *
     * @param array $atts 属性
     * @return string HTML输出
     */
    public function render_content( $atts ) {
        $atts = shortcode_atts( array(
            'id' => 0,
            'show_images' => 'yes',
            'show_hashtags' => 'yes',
            'class' => '',
        ), $atts );

        $post = $this->db->get_post( $atts['id'] );

        if ( ! $post ) {
            return '<p>' . __( 'Content not found.', 'ai-social-content-generator' ) . '</p>';
        }

        $output = '<div class="aiscg-content ' . esc_attr( $atts['class'] ) . '">';

        // 标题
        $output .= '<h3 class="aiscg-content-title">' . esc_html( $post->title ) . '</h3>';

        // 内容
        $output .= '<div class="aiscg-content-text">' . nl2br( esc_html( $post->content ) ) . '</div>';

        // 标签
        if ( $atts['show_hashtags'] === 'yes' && ! empty( $post->hashtags ) ) {
            $hashtags = is_array( $post->hashtags ) ? $post->hashtags : json_decode( $post->hashtags, true );

            if ( is_array( $hashtags ) ) {
                $output .= '<div class="aiscg-content-hashtags">';
                foreach ( $hashtags as $tag ) {
                    $output .= '<span class="aiscg-hashtag">' . esc_html( $tag ) . '</span> ';
                }
                $output .= '</div>';
            }
        }

        // 图片
        if ( $atts['show_images'] === 'yes' ) {
            $images = $this->db->get_images_by_post_id( $atts['id'] );

            if ( ! empty( $images ) ) {
                $output .= '<div class="aiscg-content-images">';
                foreach ( $images as $image ) {
                    $output .= '<img src="' . esc_url( $image->image_url ) . '" alt="' . esc_attr( $post->title ) . '" class="aiscg-image">';
                }
                $output .= '</div>';
            }
        }

        $output .= '</div>';

        // 添加样式
        $this->enqueue_shortcode_styles();

        return $output;
    }

    /**
     * 渲染图片画廊
     *
     * 用法: [aiscg_gallery post_id="123" columns="3"]
     *
     * @param array $atts 属性
     * @return string HTML输出
     */
    public function render_gallery( $atts ) {
        $atts = shortcode_atts( array(
            'post_id' => 0,
            'columns' => 3,
            'size' => 'medium',
            'class' => '',
        ), $atts );

        $images = $this->db->get_images_by_post_id( $atts['post_id'] );

        if ( empty( $images ) ) {
            return '<p>' . __( 'No images found.', 'ai-social-content-generator' ) . '</p>';
        }

        $columns = absint( $atts['columns'] );
        $columns = max( 1, min( 6, $columns ) ); // 限制在1-6列

        $output = '<div class="aiscg-gallery aiscg-gallery-columns-' . $columns . ' ' . esc_attr( $atts['class'] ) . '">';

        foreach ( $images as $image ) {
            $output .= '<div class="aiscg-gallery-item">';
            $output .= '<a href="' . esc_url( $image->image_url ) . '" target="_blank">';
            $output .= '<img src="' . esc_url( $image->image_url ) . '" alt="Image">';
            $output .= '</a>';
            $output .= '</div>';
        }

        $output .= '</div>';

        $this->enqueue_shortcode_styles();

        return $output;
    }

    /**
     * 渲染统计信息
     *
     * 用法: [aiscg_stats type="overview"]
     *
     * @param array $atts 属性
     * @return string HTML输出
     */
    public function render_stats( $atts ) {
        $atts = shortcode_atts( array(
            'type' => 'overview',
            'class' => '',
        ), $atts );

        $analytics = new AISCG_Analytics();

        switch ( $atts['type'] ) {
            case 'overview':
                $stats = $analytics->get_overview_stats();
                $output = $this->render_overview_stats( $stats );
                break;

            case 'ai_models':
                $stats = $analytics->get_ai_model_usage();
                $output = $this->render_ai_model_stats( $stats );
                break;

            default:
                $output = '<p>' . __( 'Invalid stats type.', 'ai-social-content-generator' ) . '</p>';
        }

        $this->enqueue_shortcode_styles();

        return '<div class="aiscg-stats ' . esc_attr( $atts['class'] ) . '">' . $output . '</div>';
    }

    /**
     * 渲染最新内容列表
     *
     * 用法: [aiscg_latest platform="xiaohongshu" limit="5"]
     *
     * @param array $atts 属性
     * @return string HTML输出
     */
    public function render_latest_posts( $atts ) {
        $atts = shortcode_atts( array(
            'platform' => '',
            'limit' => 5,
            'show_images' => 'yes',
            'show_excerpt' => 'yes',
            'class' => '',
        ), $atts );

        $args = array(
            'platform' => $atts['platform'],
            'limit' => absint( $atts['limit'] ),
            'status' => 'published',
        );

        $posts = $this->db->get_posts( $args );

        if ( empty( $posts ) ) {
            return '<p>' . __( 'No posts found.', 'ai-social-content-generator' ) . '</p>';
        }

        $output = '<div class="aiscg-latest-posts ' . esc_attr( $atts['class'] ) . '">';

        foreach ( $posts as $post ) {
            $output .= '<div class="aiscg-latest-post">';

            // 图片
            if ( $atts['show_images'] === 'yes' ) {
                $images = $this->db->get_images_by_post_id( $post->id );
                if ( ! empty( $images ) ) {
                    $output .= '<div class="aiscg-latest-post-image">';
                    $output .= '<img src="' . esc_url( $images[0]->image_url ) . '" alt="' . esc_attr( $post->title ) . '">';
                    $output .= '</div>';
                }
            }

            $output .= '<div class="aiscg-latest-post-content">';

            // 标题
            $output .= '<h4 class="aiscg-latest-post-title">' . esc_html( $post->title ) . '</h4>';

            // 摘要
            if ( $atts['show_excerpt'] === 'yes' ) {
                $excerpt = wp_trim_words( $post->content, 20, '...' );
                $output .= '<p class="aiscg-latest-post-excerpt">' . esc_html( $excerpt ) . '</p>';
            }

            // 元信息
            $output .= '<div class="aiscg-latest-post-meta">';
            $output .= '<span class="platform">' . esc_html( $post->platform ) . '</span>';
            $output .= '<span class="date">' . esc_html( date( 'Y-m-d', strtotime( $post->created_at ) ) ) . '</span>';
            $output .= '</div>';

            $output .= '</div>'; // .aiscg-latest-post-content
            $output .= '</div>'; // .aiscg-latest-post
        }

        $output .= '</div>';

        $this->enqueue_shortcode_styles();

        return $output;
    }

    /**
     * 渲染总体统计
     *
     * @param array $stats 统计数据
     * @return string HTML输出
     */
    private function render_overview_stats( $stats ) {
        $output = '<div class="aiscg-stats-grid">';

        $output .= '<div class="aiscg-stat-box">';
        $output .= '<div class="stat-value">' . esc_html( $stats['total_posts'] ) . '</div>';
        $output .= '<div class="stat-label">' . __( 'Total Posts', 'ai-social-content-generator' ) . '</div>';
        $output .= '</div>';

        $output .= '<div class="aiscg-stat-box">';
        $output .= '<div class="stat-value">' . esc_html( $stats['today_posts'] ) . '</div>';
        $output .= '<div class="stat-label">' . __( 'Today', 'ai-social-content-generator' ) . '</div>';
        $output .= '</div>';

        $output .= '<div class="aiscg-stat-box">';
        $output .= '<div class="stat-value">' . esc_html( $stats['week_posts'] ) . '</div>';
        $output .= '<div class="stat-label">' . __( 'This Week', 'ai-social-content-generator' ) . '</div>';
        $output .= '</div>';

        $output .= '<div class="aiscg-stat-box">';
        $output .= '<div class="stat-value">' . esc_html( $stats['month_posts'] ) . '</div>';
        $output .= '<div class="stat-label">' . __( 'This Month', 'ai-social-content-generator' ) . '</div>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * 渲染AI模型统计
     *
     * @param array $stats 统计数据
     * @return string HTML输出
     */
    private function render_ai_model_stats( $stats ) {
        $output = '<div class="aiscg-ai-stats">';

        foreach ( $stats as $stat ) {
            $output .= '<div class="aiscg-ai-stat-item">';
            $output .= '<div class="ai-model-name">' . esc_html( $stat['model'] ) . '</div>';
            $output .= '<div class="ai-model-count">' . esc_html( $stat['count'] ) . ' posts (' . esc_html( $stat['percentage'] ) . '%)</div>';
            $output .= '<div class="ai-model-bar" style="width: ' . esc_attr( $stat['percentage'] ) . '%;"></div>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * 加载短代码样式
     */
    private function enqueue_shortcode_styles() {
        static $loaded = false;

        if ( $loaded ) {
            return;
        }

        wp_add_inline_style( 'wp-block-library', '
            .aiscg-content {
                margin: 20px 0;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 8px;
            }

            .aiscg-content-title {
                margin-top: 0;
                color: #333;
            }

            .aiscg-content-text {
                margin: 15px 0;
                line-height: 1.6;
            }

            .aiscg-content-hashtags {
                margin: 15px 0;
            }

            .aiscg-hashtag {
                display: inline-block;
                background: #007cba;
                color: #fff;
                padding: 3px 10px;
                border-radius: 3px;
                font-size: 12px;
                margin-right: 5px;
                margin-bottom: 5px;
            }

            .aiscg-content-images {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 10px;
                margin-top: 15px;
            }

            .aiscg-image {
                width: 100%;
                height: auto;
                border-radius: 4px;
            }

            .aiscg-gallery {
                display: grid;
                gap: 10px;
                margin: 20px 0;
            }

            .aiscg-gallery-columns-1 { grid-template-columns: repeat(1, 1fr); }
            .aiscg-gallery-columns-2 { grid-template-columns: repeat(2, 1fr); }
            .aiscg-gallery-columns-3 { grid-template-columns: repeat(3, 1fr); }
            .aiscg-gallery-columns-4 { grid-template-columns: repeat(4, 1fr); }
            .aiscg-gallery-columns-5 { grid-template-columns: repeat(5, 1fr); }
            .aiscg-gallery-columns-6 { grid-template-columns: repeat(6, 1fr); }

            .aiscg-gallery-item img {
                width: 100%;
                height: auto;
                border-radius: 4px;
                transition: transform 0.2s;
            }

            .aiscg-gallery-item img:hover {
                transform: scale(1.05);
            }

            .aiscg-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }

            .aiscg-stat-box {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            }

            .stat-value {
                font-size: 32px;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .stat-label {
                font-size: 14px;
                opacity: 0.9;
            }

            .aiscg-latest-posts {
                margin: 20px 0;
            }

            .aiscg-latest-post {
                display: flex;
                gap: 15px;
                margin-bottom: 20px;
                padding: 15px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
            }

            .aiscg-latest-post-image {
                flex: 0 0 150px;
            }

            .aiscg-latest-post-image img {
                width: 100%;
                height: auto;
                border-radius: 4px;
            }

            .aiscg-latest-post-content {
                flex: 1;
            }

            .aiscg-latest-post-title {
                margin: 0 0 10px 0;
                font-size: 18px;
            }

            .aiscg-latest-post-excerpt {
                margin: 0 0 10px 0;
                color: #666;
            }

            .aiscg-latest-post-meta {
                font-size: 12px;
                color: #999;
            }

            .aiscg-latest-post-meta span {
                margin-right: 15px;
            }
        ' );

        $loaded = true;
    }
}
