<?php
/**
 * 最新内容Widget
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 最新内容Widget类
 */
class AISCG_Latest_Posts_Widget extends WP_Widget {

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
        parent::__construct(
            'aiscg_latest_posts',
            __( 'AI Generated Content - Latest Posts', 'ai-social-content-generator' ),
            array(
                'description' => __( 'Display latest AI generated posts', 'ai-social-content-generator' ),
            )
        );

        $this->db = new AISCG_Database();
    }

    /**
     * 输出widget内容
     *
     * @param array $args     Widget参数
     * @param array $instance Widget实例
     */
    public function widget( $args, $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Latest AI Content', 'ai-social-content-generator' );
        $platform = ! empty( $instance['platform'] ) ? $instance['platform'] : '';
        $number = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : 5;
        $show_images = ! empty( $instance['show_images'] );

        echo $args['before_widget'];

        if ( ! empty( $title ) ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }

        $posts = $this->db->get_posts( array(
            'platform' => $platform,
            'status' => 'published',
            'limit' => $number,
        ) );

        if ( ! empty( $posts ) ) {
            echo '<ul class="aiscg-widget-posts">';

            foreach ( $posts as $post ) {
                echo '<li class="aiscg-widget-post-item">';

                if ( $show_images ) {
                    $images = $this->db->get_images_by_post_id( $post->id );
                    if ( ! empty( $images ) ) {
                        echo '<div class="aiscg-widget-post-thumb">';
                        echo '<img src="' . esc_url( $images[0]->image_url ) . '" alt="' . esc_attr( $post->title ) . '">';
                        echo '</div>';
                    }
                }

                echo '<div class="aiscg-widget-post-info">';
                echo '<h4 class="aiscg-widget-post-title">' . esc_html( wp_trim_words( $post->title, 8 ) ) . '</h4>';
                echo '<span class="aiscg-widget-post-date">' . esc_html( date( 'M d, Y', strtotime( $post->created_at ) ) ) . '</span>';
                echo '</div>';

                echo '</li>';
            }

            echo '</ul>';
        } else {
            echo '<p>' . __( 'No posts found.', 'ai-social-content-generator' ) . '</p>';
        }

        echo $args['after_widget'];

        $this->widget_styles();
    }

    /**
     * Widget设置表单
     *
     * @param array $instance Widget实例
     */
    public function form( $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Latest AI Content', 'ai-social-content-generator' );
        $platform = ! empty( $instance['platform'] ) ? $instance['platform'] : '';
        $number = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : 5;
        $show_images = ! empty( $instance['show_images'] );

        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php _e( 'Title:', 'ai-social-content-generator' ); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'platform' ) ); ?>">
                <?php _e( 'Platform:', 'ai-social-content-generator' ); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'platform' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'platform' ) ); ?>">
                <option value="" <?php selected( $platform, '' ); ?>><?php _e( 'All Platforms', 'ai-social-content-generator' ); ?></option>
                <option value="xiaohongshu" <?php selected( $platform, 'xiaohongshu' ); ?>><?php _e( '小红书', 'ai-social-content-generator' ); ?></option>
                <option value="instagram" <?php selected( $platform, 'instagram' ); ?>><?php _e( 'Instagram', 'ai-social-content-generator' ); ?></option>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>">
                <?php _e( 'Number of posts:', 'ai-social-content-generator' ); ?>
            </label>
            <input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1" max="20" value="<?php echo esc_attr( $number ); ?>" size="3">
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_images' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_images' ) ); ?>" value="1" <?php checked( $show_images, true ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_images' ) ); ?>">
                <?php _e( 'Show images', 'ai-social-content-generator' ); ?>
            </label>
        </p>
        <?php
    }

    /**
     * 保存widget设置
     *
     * @param array $new_instance 新实例
     * @param array $old_instance 旧实例
     * @return array 更新后的实例
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();

        $instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
        $instance['platform'] = ! empty( $new_instance['platform'] ) ? sanitize_text_field( $new_instance['platform'] ) : '';
        $instance['number'] = ! empty( $new_instance['number'] ) ? absint( $new_instance['number'] ) : 5;
        $instance['show_images'] = ! empty( $new_instance['show_images'] );

        return $instance;
    }

    /**
     * Widget样式
     */
    private function widget_styles() {
        static $loaded = false;

        if ( $loaded ) {
            return;
        }

        wp_add_inline_style( 'wp-block-library', '
            .aiscg-widget-posts {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .aiscg-widget-post-item {
                display: flex;
                gap: 10px;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }

            .aiscg-widget-post-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }

            .aiscg-widget-post-thumb {
                flex: 0 0 60px;
            }

            .aiscg-widget-post-thumb img {
                width: 100%;
                height: 60px;
                object-fit: cover;
                border-radius: 4px;
            }

            .aiscg-widget-post-info {
                flex: 1;
            }

            .aiscg-widget-post-title {
                margin: 0 0 5px 0;
                font-size: 14px;
                line-height: 1.4;
            }

            .aiscg-widget-post-date {
                font-size: 12px;
                color: #999;
            }
        ' );

        $loaded = true;
    }
}

/**
 * 统计Widget类
 */
class AISCG_Stats_Widget extends WP_Widget {

    /**
     * 构造函数
     */
    public function __construct() {
        parent::__construct(
            'aiscg_stats',
            __( 'AI Generated Content - Statistics', 'ai-social-content-generator' ),
            array(
                'description' => __( 'Display AI content statistics', 'ai-social-content-generator' ),
            )
        );
    }

    /**
     * 输出widget内容
     *
     * @param array $args     Widget参数
     * @param array $instance Widget实例
     */
    public function widget( $args, $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'AI Content Stats', 'ai-social-content-generator' );

        echo $args['before_widget'];

        if ( ! empty( $title ) ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }

        $analytics = new AISCG_Analytics();
        $stats = $analytics->get_overview_stats();

        echo '<div class="aiscg-widget-stats">';

        echo '<div class="aiscg-widget-stat">';
        echo '<span class="stat-value">' . esc_html( $stats['total_posts'] ) . '</span>';
        echo '<span class="stat-label">' . __( 'Total Posts', 'ai-social-content-generator' ) . '</span>';
        echo '</div>';

        echo '<div class="aiscg-widget-stat">';
        echo '<span class="stat-value">' . esc_html( $stats['today_posts'] ) . '</span>';
        echo '<span class="stat-label">' . __( 'Today', 'ai-social-content-generator' ) . '</span>';
        echo '</div>';

        echo '<div class="aiscg-widget-stat">';
        echo '<span class="stat-value">' . esc_html( $stats['week_posts'] ) . '</span>';
        echo '<span class="stat-label">' . __( 'This Week', 'ai-social-content-generator' ) . '</span>';
        echo '</div>';

        echo '<div class="aiscg-widget-stat">';
        echo '<span class="stat-value">' . esc_html( $stats['month_posts'] ) . '</span>';
        echo '<span class="stat-label">' . __( 'This Month', 'ai-social-content-generator' ) . '</span>';
        echo '</div>';

        echo '</div>';

        echo $args['after_widget'];

        $this->widget_styles();
    }

    /**
     * Widget设置表单
     *
     * @param array $instance Widget实例
     */
    public function form( $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'AI Content Stats', 'ai-social-content-generator' );

        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php _e( 'Title:', 'ai-social-content-generator' ); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }

    /**
     * 保存widget设置
     *
     * @param array $new_instance 新实例
     * @param array $old_instance 旧实例
     * @return array 更新后的实例
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';

        return $instance;
    }

    /**
     * Widget样式
     */
    private function widget_styles() {
        static $loaded = false;

        if ( $loaded ) {
            return;
        }

        wp_add_inline_style( 'wp-block-library', '
            .aiscg-widget-stats {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .aiscg-widget-stat {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 15px;
                border-radius: 8px;
                text-align: center;
            }

            .aiscg-widget-stat .stat-value {
                display: block;
                font-size: 24px;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .aiscg-widget-stat .stat-label {
                display: block;
                font-size: 11px;
                opacity: 0.9;
            }
        ' );

        $loaded = true;
    }
}

/**
 * 注册widgets
 */
function aiscg_register_widgets() {
    register_widget( 'AISCG_Latest_Posts_Widget' );
    register_widget( 'AISCG_Stats_Widget' );
}
add_action( 'widgets_init', 'aiscg_register_widgets' );
