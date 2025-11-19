<?php
/**
 * Media Library Class
 *
 * 管理素材库（文案片段、图片模板、常用标签等）
 *
 * @package AI_Social_Content_Generator
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Media_Library Class
 */
class AISCG_Media_Library {

    /**
     * 素材库表名
     *
     * @var string
     */
    private $table_name;

    /**
     * 日志对象
     *
     * @var AISCG_Logger
     */
    private $logger;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aiscg_media_library';
        $this->logger = new AISCG_Logger();
    }

    /**
     * 添加素材
     *
     * @param array $data 素材数据
     * @return int|false 素材ID或false
     */
    public function add_item( $data ) {
        global $wpdb;

        $item_data = array(
            'type' => sanitize_text_field( $data['type'] ), // snippet, image_template, hashtag_set, opening, closing
            'title' => sanitize_text_field( $data['title'] ),
            'content' => wp_kses_post( $data['content'] ),
            'category' => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : 'general',
            'platform' => isset( $data['platform'] ) ? sanitize_text_field( $data['platform'] ) : '',
            'language' => isset( $data['language'] ) ? sanitize_text_field( $data['language'] ) : 'zh-CN',
            'tags' => isset( $data['tags'] ) ? wp_json_encode( $data['tags'] ) : '[]',
            'metadata' => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : '{}',
            'usage_count' => 0,
            'user_id' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert(
            $this->table_name,
            $item_data,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( $result ) {
            $item_id = $wpdb->insert_id;
            $this->logger->info( "添加素材成功 #{$item_id}", $item_data );
            return $item_id;
        }

        return false;
    }

    /**
     * 获取素材列表
     *
     * @param array $args 查询参数
     * @return array 素材列表
     */
    public function get_items( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'type' => '',
            'category' => '',
            'platform' => '',
            'language' => '',
            'search' => '',
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'usage_count',
            'order' => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $where_values = array();

        if ( ! empty( $args['type'] ) ) {
            $where[] = 'type = %s';
            $where_values[] = $args['type'];
        }

        if ( ! empty( $args['category'] ) ) {
            $where[] = 'category = %s';
            $where_values[] = $args['category'];
        }

        if ( ! empty( $args['platform'] ) ) {
            $where[] = 'platform = %s';
            $where_values[] = $args['platform'];
        }

        if ( ! empty( $args['language'] ) ) {
            $where[] = 'language = %s';
            $where_values[] = $args['language'];
        }

        if ( ! empty( $args['search'] ) ) {
            $where[] = '(title LIKE %s OR content LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode( ' AND ', $where );

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'usage_count DESC';
        }

        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
                array_merge( $where_values, array( $args['limit'], $args['offset'] ) )
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
                $args['limit'],
                $args['offset']
            );
        }

        $items = $wpdb->get_results( $sql, ARRAY_A );

        // 解析JSON字段
        foreach ( $items as &$item ) {
            if ( ! empty( $item['tags'] ) ) {
                $item['tags'] = json_decode( $item['tags'], true );
            }
            if ( ! empty( $item['metadata'] ) ) {
                $item['metadata'] = json_decode( $item['metadata'], true );
            }
        }

        return $items;
    }

    /**
     * 使用素材（增加使用计数）
     *
     * @param int $item_id 素材ID
     */
    public function use_item( $item_id ) {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name} SET usage_count = usage_count + 1 WHERE id = %d",
                $item_id
            )
        );
    }

    /**
     * 获取内置素材
     *
     * @return array 内置素材列表
     */
    public function get_builtin_items() {
        return array(
            // 开场白
            array(
                'type' => 'opening',
                'title' => '小红书开场-惊喜发现',
                'content' => '姐妹们！我发现了一个超级{形容词}的{名词}！',
                'category' => 'opening',
                'platform' => 'xiaohongshu',
                'language' => 'zh-CN',
                'tags' => array( '开场', '惊喜' ),
            ),
            array(
                'type' => 'opening',
                'title' => '小红书开场-分享心得',
                'content' => '今天来和大家分享一下我最近在用的{产品/方法}～',
                'category' => 'opening',
                'platform' => 'xiaohongshu',
                'language' => 'zh-CN',
                'tags' => array( '开场', '分享' ),
            ),
            // 结尾
            array(
                'type' => 'closing',
                'title' => '小红书结尾-互动型',
                'content' => '你们有什么好的建议吗？评论区告诉我吧！❤️',
                'category' => 'closing',
                'platform' => 'xiaohongshu',
                'language' => 'zh-CN',
                'tags' => array( '结尾', '互动' ),
            ),
            // 标签集
            array(
                'type' => 'hashtag_set',
                'title' => '美妆类标签',
                'content' => '#美妆分享 #化妆教程 #美妆好物 #护肤心得 #美妆测评',
                'category' => 'beauty',
                'platform' => 'xiaohongshu',
                'language' => 'zh-CN',
                'tags' => array( '美妆', '标签' ),
            ),
            array(
                'type' => 'hashtag_set',
                'title' => '美食类标签',
                'content' => '#美食分享 #美食推荐 #探店 #美食测评 #美食打卡',
                'category' => 'food',
                'platform' => 'xiaohongshu',
                'language' => 'zh-CN',
                'tags' => array( '美食', '标签' ),
            ),
        );
    }

    /**
     * 安装内置素材
     *
     * @return int 安装数量
     */
    public function install_builtin_items() {
        $items = $this->get_builtin_items();
        $count = 0;

        foreach ( $items as $item ) {
            $result = $this->add_item( $item );
            if ( $result ) {
                $count++;
            }
        }

        $this->logger->info( "安装内置素材完成: {$count} 个" );

        return $count;
    }
}
