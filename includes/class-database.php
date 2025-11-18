<?php
/**
 * 数据库操作类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 数据库操作类
 */
class AISCG_Database {

    /**
     * WordPress数据库对象
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * 内容表名
     *
     * @var string
     */
    private $posts_table;

    /**
     * 图片表名
     *
     * @var string
     */
    private $images_table;

    /**
     * 设置表名
     *
     * @var string
     */
    private $settings_table;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->posts_table = $wpdb->prefix . 'aiscg_posts';
        $this->images_table = $wpdb->prefix . 'aiscg_images';
        $this->settings_table = $wpdb->prefix . 'aiscg_settings';
    }

    /**
     * 创建内容记录
     *
     * @param array $data 内容数据
     * @return int|false 插入的ID或false
     */
    public function create_post( $data ) {
        $defaults = array(
            'platform' => 'xiaohongshu',
            'title' => '',
            'content' => '',
            'hashtags' => '',
            'ai_model' => '',
            'prompt_used' => '',
            'status' => 'draft',
        );

        $data = wp_parse_args( $data, $defaults );

        // 转换hashtags为JSON字符串
        if ( is_array( $data['hashtags'] ) ) {
            $data['hashtags'] = wp_json_encode( $data['hashtags'], JSON_UNESCAPED_UNICODE );
        }

        $result = $this->wpdb->insert(
            $this->posts_table,
            array(
                'platform' => sanitize_text_field( $data['platform'] ),
                'title' => sanitize_text_field( $data['title'] ),
                'content' => wp_kses_post( $data['content'] ),
                'hashtags' => $data['hashtags'],
                'ai_model' => sanitize_text_field( $data['ai_model'] ),
                'prompt_used' => wp_kses_post( $data['prompt_used'] ),
                'status' => sanitize_text_field( $data['status'] ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( $result === false ) {
            error_log( 'AISCG Database Error: ' . $this->wpdb->last_error );
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * 更新内容记录
     *
     * @param int   $id   内容ID
     * @param array $data 更新的数据
     * @return bool
     */
    public function update_post( $id, $data ) {
        // 转换hashtags为JSON字符串
        if ( isset( $data['hashtags'] ) && is_array( $data['hashtags'] ) ) {
            $data['hashtags'] = wp_json_encode( $data['hashtags'], JSON_UNESCAPED_UNICODE );
        }

        $result = $this->wpdb->update(
            $this->posts_table,
            $data,
            array( 'id' => $id ),
            null,
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * 获取内容记录
     *
     * @param int $id 内容ID
     * @return object|null
     */
    public function get_post( $id ) {
        $post = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->posts_table} WHERE id = %d",
                $id
            )
        );

        if ( $post && ! empty( $post->hashtags ) ) {
            $post->hashtags = json_decode( $post->hashtags, true );
        }

        return $post;
    }

    /**
     * 获取内容列表
     *
     * @param array $args 查询参数
     * @return array
     */
    public function get_posts( $args = array() ) {
        $defaults = array(
            'platform' => '',
            'status' => '',
            'ai_model' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $where_values = array();

        if ( ! empty( $args['platform'] ) ) {
            $where[] = 'platform = %s';
            $where_values[] = $args['platform'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if ( ! empty( $args['ai_model'] ) ) {
            $where[] = 'ai_model = %s';
            $where_values[] = $args['ai_model'];
        }

        $where_clause = implode( ' AND ', $where );

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

        $query = "SELECT * FROM {$this->posts_table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];

        $posts = $this->wpdb->get_results(
            $this->wpdb->prepare( $query, $where_values )
        );

        foreach ( $posts as $post ) {
            if ( ! empty( $post->hashtags ) ) {
                $post->hashtags = json_decode( $post->hashtags, true );
            }
        }

        return $posts;
    }

    /**
     * 获取内容总数
     *
     * @param array $args 查询参数
     * @return int
     */
    public function get_posts_count( $args = array() ) {
        $where = array( '1=1' );
        $where_values = array();

        if ( ! empty( $args['platform'] ) ) {
            $where[] = 'platform = %s';
            $where_values[] = $args['platform'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if ( ! empty( $args['ai_model'] ) ) {
            $where[] = 'ai_model = %s';
            $where_values[] = $args['ai_model'];
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT COUNT(*) FROM {$this->posts_table} WHERE {$where_clause}";

        if ( ! empty( $where_values ) ) {
            return (int) $this->wpdb->get_var( $this->wpdb->prepare( $query, $where_values ) );
        } else {
            return (int) $this->wpdb->get_var( $query );
        }
    }

    /**
     * 删除内容记录
     *
     * @param int $id 内容ID
     * @return bool
     */
    public function delete_post( $id ) {
        // 先删除关联的图片
        $this->delete_images_by_post_id( $id );

        // 删除内容记录
        $result = $this->wpdb->delete(
            $this->posts_table,
            array( 'id' => $id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * 创建图片记录
     *
     * @param array $data 图片数据
     * @return int|false
     */
    public function create_image( $data ) {
        $defaults = array(
            'post_id' => 0,
            'image_url' => '',
            'image_path' => '',
            'image_order' => 0,
            'width' => 0,
            'height' => 0,
        );

        $data = wp_parse_args( $data, $defaults );

        $result = $this->wpdb->insert(
            $this->images_table,
            array(
                'post_id' => absint( $data['post_id'] ),
                'image_url' => esc_url_raw( $data['image_url'] ),
                'image_path' => sanitize_text_field( $data['image_path'] ),
                'image_order' => absint( $data['image_order'] ),
                'width' => absint( $data['width'] ),
                'height' => absint( $data['height'] ),
            ),
            array( '%d', '%s', '%s', '%d', '%d', '%d' )
        );

        if ( $result === false ) {
            error_log( 'AISCG Database Error: ' . $this->wpdb->last_error );
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * 获取内容的所有图片
     *
     * @param int $post_id 内容ID
     * @return array
     */
    public function get_images_by_post_id( $post_id ) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->images_table} WHERE post_id = %d ORDER BY image_order ASC",
                $post_id
            )
        );
    }

    /**
     * 删除内容的所有图片
     *
     * @param int $post_id 内容ID
     * @return bool
     */
    public function delete_images_by_post_id( $post_id ) {
        $result = $this->wpdb->delete(
            $this->images_table,
            array( 'post_id' => $post_id ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * 保存设置
     *
     * @param string $key   设置键
     * @param mixed  $value 设置值
     * @return bool
     */
    public function save_setting( $key, $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            $value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
        }

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->settings_table} WHERE setting_key = %s",
                $key
            )
        );

        if ( $existing ) {
            $result = $this->wpdb->update(
                $this->settings_table,
                array( 'setting_value' => $value ),
                array( 'setting_key' => $key ),
                array( '%s' ),
                array( '%s' )
            );
        } else {
            $result = $this->wpdb->insert(
                $this->settings_table,
                array(
                    'setting_key' => $key,
                    'setting_value' => $value,
                ),
                array( '%s', '%s' )
            );
        }

        return $result !== false;
    }

    /**
     * 获取设置
     *
     * @param string $key     设置键
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get_setting( $key, $default = null ) {
        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT setting_value FROM {$this->settings_table} WHERE setting_key = %s",
                $key
            )
        );

        if ( $value === null ) {
            return $default;
        }

        // 尝试解码JSON
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            return $decoded;
        }

        return $value;
    }

    /**
     * 删除设置
     *
     * @param string $key 设置键
     * @return bool
     */
    public function delete_setting( $key ) {
        $result = $this->wpdb->delete(
            $this->settings_table,
            array( 'setting_key' => $key ),
            array( '%s' )
        );

        return $result !== false;
    }
}
