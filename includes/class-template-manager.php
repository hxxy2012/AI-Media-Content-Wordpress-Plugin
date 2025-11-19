<?php
/**
 * Template Manager Class
 *
 * 管理用户自定义内容模板
 *
 * @package AI_Social_Content_Generator
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Template_Manager Class
 */
class AISCG_Template_Manager {

    /**
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 日志对象
     *
     * @var AISCG_Logger
     */
    private $logger;

    /**
     * 模板表名
     *
     * @var string
     */
    private $table_name;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aiscg_templates';
        $this->db = new AISCG_Database();
        $this->logger = new AISCG_Logger();
    }

    /**
     * 创建模板
     *
     * @param array $data 模板数据
     * @return int|false 模板ID或false
     */
    public function create_template( $data ) {
        global $wpdb;

        // 验证必需字段
        if ( empty( $data['name'] ) || empty( $data['content'] ) ) {
            $this->logger->error( '创建模板失败：缺少必需字段', $data );
            return false;
        }

        $template_data = array(
            'name' => sanitize_text_field( $data['name'] ),
            'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
            'platform' => isset( $data['platform'] ) ? sanitize_text_field( $data['platform'] ) : 'xiaohongshu',
            'content' => wp_kses_post( $data['content'] ),
            'variables' => isset( $data['variables'] ) ? wp_json_encode( $data['variables'] ) : wp_json_encode( array() ),
            'category' => isset( $data['category'] ) ? sanitize_text_field( $data['category'] ) : 'general',
            'is_active' => isset( $data['is_active'] ) ? intval( $data['is_active'] ) : 1,
            'user_id' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert(
            $this->table_name,
            $template_data,
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( $result ) {
            $template_id = $wpdb->insert_id;
            $this->logger->info( "创建模板成功 #{$template_id}", $template_data );
            return $template_id;
        }

        $this->logger->error( '创建模板失败', array( 'error' => $wpdb->last_error ) );
        return false;
    }

    /**
     * 更新模板
     *
     * @param int $template_id 模板ID
     * @param array $data 更新数据
     * @return bool 是否成功
     */
    public function update_template( $template_id, $data ) {
        global $wpdb;

        $template_id = absint( $template_id );
        if ( ! $template_id ) {
            return false;
        }

        $update_data = array(
            'updated_at' => current_time( 'mysql' ),
        );

        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
        }

        if ( isset( $data['description'] ) ) {
            $update_data['description'] = sanitize_textarea_field( $data['description'] );
        }

        if ( isset( $data['platform'] ) ) {
            $update_data['platform'] = sanitize_text_field( $data['platform'] );
        }

        if ( isset( $data['content'] ) ) {
            $update_data['content'] = wp_kses_post( $data['content'] );
        }

        if ( isset( $data['variables'] ) ) {
            $update_data['variables'] = wp_json_encode( $data['variables'] );
        }

        if ( isset( $data['category'] ) ) {
            $update_data['category'] = sanitize_text_field( $data['category'] );
        }

        if ( isset( $data['is_active'] ) ) {
            $update_data['is_active'] = intval( $data['is_active'] );
        }

        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => $template_id ),
            null,
            array( '%d' )
        );

        if ( $result !== false ) {
            $this->logger->info( "更新模板成功 #{$template_id}", $update_data );
            return true;
        }

        return false;
    }

    /**
     * 删除模板
     *
     * @param int $template_id 模板ID
     * @return bool 是否成功
     */
    public function delete_template( $template_id ) {
        global $wpdb;

        $template_id = absint( $template_id );
        if ( ! $template_id ) {
            return false;
        }

        $result = $wpdb->delete(
            $this->table_name,
            array( 'id' => $template_id ),
            array( '%d' )
        );

        if ( $result ) {
            $this->logger->info( "删除模板成功 #{$template_id}" );
            return true;
        }

        return false;
    }

    /**
     * 获取模板
     *
     * @param int $template_id 模板ID
     * @return array|null 模板数据
     */
    public function get_template( $template_id ) {
        global $wpdb;

        $template_id = absint( $template_id );
        if ( ! $template_id ) {
            return null;
        }

        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $template_id
            ),
            ARRAY_A
        );

        if ( $template && ! empty( $template['variables'] ) ) {
            $template['variables'] = json_decode( $template['variables'], true );
        }

        return $template;
    }

    /**
     * 获取模板列表
     *
     * @param array $args 查询参数
     * @return array 模板列表
     */
    public function get_templates( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'platform' => '',
            'category' => '',
            'is_active' => 1,
            'user_id' => 0,
            'search' => '',
            'limit' => 100,
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

        if ( ! empty( $args['category'] ) ) {
            $where[] = 'category = %s';
            $where_values[] = $args['category'];
        }

        if ( $args['is_active'] !== '' ) {
            $where[] = 'is_active = %d';
            $where_values[] = intval( $args['is_active'] );
        }

        if ( ! empty( $args['user_id'] ) ) {
            $where[] = 'user_id = %d';
            $where_values[] = intval( $args['user_id'] );
        }

        if ( ! empty( $args['search'] ) ) {
            $where[] = '(name LIKE %s OR description LIKE %s)';
            $search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode( ' AND ', $where );

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'created_at DESC';
        }

        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
                array_merge( $where_values, array( $limit, $offset ) )
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        }

        $templates = $wpdb->get_results( $sql, ARRAY_A );

        // 解析variables JSON
        foreach ( $templates as &$template ) {
            if ( ! empty( $template['variables'] ) ) {
                $template['variables'] = json_decode( $template['variables'], true );
            }
        }

        return $templates;
    }

    /**
     * 渲染模板
     *
     * @param int $template_id 模板ID
     * @param array $variables 变量值
     * @return string|false 渲染后的内容或false
     */
    public function render_template( $template_id, $variables = array() ) {
        $template = $this->get_template( $template_id );

        if ( ! $template ) {
            $this->logger->error( "渲染模板失败：模板不存在 #{$template_id}" );
            return false;
        }

        $content = $template['content'];

        // 替换变量
        foreach ( $variables as $key => $value ) {
            $content = str_replace( '{{' . $key . '}}', $value, $content );
        }

        // 检查是否还有未替换的变量
        if ( preg_match( '/\{\{(.+?)\}\}/', $content ) ) {
            $this->logger->warning( "模板渲染警告：存在未替换的变量", array(
                'template_id' => $template_id,
                'content' => $content,
            ) );
        }

        $this->logger->info( "渲染模板成功 #{$template_id}", array(
            'variables' => $variables,
        ) );

        return $content;
    }

    /**
     * 复制模板
     *
     * @param int $template_id 模板ID
     * @param string $new_name 新模板名称
     * @return int|false 新模板ID或false
     */
    public function duplicate_template( $template_id, $new_name = '' ) {
        $template = $this->get_template( $template_id );

        if ( ! $template ) {
            return false;
        }

        if ( empty( $new_name ) ) {
            $new_name = $template['name'] . ' (副本)';
        }

        $new_template = array(
            'name' => $new_name,
            'description' => $template['description'],
            'platform' => $template['platform'],
            'content' => $template['content'],
            'variables' => $template['variables'],
            'category' => $template['category'],
            'is_active' => $template['is_active'],
        );

        return $this->create_template( $new_template );
    }

    /**
     * 获取模板分类列表
     *
     * @return array 分类列表
     */
    public function get_categories() {
        global $wpdb;

        $categories = $wpdb->get_col(
            "SELECT DISTINCT category FROM {$this->table_name} WHERE category != '' ORDER BY category"
        );

        return $categories;
    }

    /**
     * 导入模板
     *
     * @param array $templates 模板数据数组
     * @return array 导入结果
     */
    public function import_templates( $templates ) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array(),
        );

        foreach ( $templates as $template ) {
            $template_id = $this->create_template( $template );

            if ( $template_id ) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = '导入模板失败: ' . ( $template['name'] ?? '未命名' );
            }
        }

        $this->logger->info( '批量导入模板完成', $results );

        return $results;
    }

    /**
     * 导出模板
     *
     * @param array $template_ids 模板ID数组
     * @return array 模板数据数组
     */
    public function export_templates( $template_ids = array() ) {
        if ( empty( $template_ids ) ) {
            $templates = $this->get_templates( array( 'limit' => 1000 ) );
        } else {
            $templates = array();
            foreach ( $template_ids as $id ) {
                $template = $this->get_template( $id );
                if ( $template ) {
                    $templates[] = $template;
                }
            }
        }

        // 移除不需要导出的字段
        foreach ( $templates as &$template ) {
            unset( $template['id'] );
            unset( $template['user_id'] );
            unset( $template['created_at'] );
            unset( $template['updated_at'] );
        }

        return $templates;
    }

    /**
     * 获取内置模板
     *
     * @return array 内置模板列表
     */
    public function get_builtin_templates() {
        return array(
            array(
                'name' => '小红书种草模板',
                'description' => '适用于产品种草、推荐类内容',
                'platform' => 'xiaohongshu',
                'category' => 'product',
                'content' => '🌟 {{product_name}} 真的太好用了！

{{intro}}

💡 使用体验：
{{experience}}

✨ 推荐理由：
{{reasons}}

📝 小贴士：
{{tips}}

#{{hashtag1}} #{{hashtag2}} #{{hashtag3}}',
                'variables' => array( 'product_name', 'intro', 'experience', 'reasons', 'tips', 'hashtag1', 'hashtag2', 'hashtag3' ),
            ),
            array(
                'name' => '小红书攻略模板',
                'description' => '适用于教程、攻略类内容',
                'platform' => 'xiaohongshu',
                'category' => 'tutorial',
                'content' => '📚 {{title}}

{{intro}}

📍 步骤详解：

1️⃣ {{step1}}

2️⃣ {{step2}}

3️⃣ {{step3}}

⚠️ 注意事项：
{{notes}}

💬 有问题欢迎评论区交流！

#{{hashtag1}} #{{hashtag2}} #{{hashtag3}}',
                'variables' => array( 'title', 'intro', 'step1', 'step2', 'step3', 'notes', 'hashtag1', 'hashtag2', 'hashtag3' ),
            ),
            array(
                'name' => 'Instagram故事模板',
                'description' => '适用于Instagram个人分享',
                'platform' => 'instagram',
                'category' => 'story',
                'content' => '✨ {{title}}

{{content}}

📸 {{description}}

{{call_to_action}}

#{{hashtag1}} #{{hashtag2}} #{{hashtag3}}',
                'variables' => array( 'title', 'content', 'description', 'call_to_action', 'hashtag1', 'hashtag2', 'hashtag3' ),
            ),
            array(
                'name' => 'Instagram商业模板',
                'description' => '适用于Instagram商业推广',
                'platform' => 'instagram',
                'category' => 'business',
                'content' => '🎯 {{headline}}

{{intro}}

✅ Key Features:
• {{feature1}}
• {{feature2}}
• {{feature3}}

💡 {{cta}}

{{contact_info}}

#{{hashtag1}} #{{hashtag2}} #{{hashtag3}}',
                'variables' => array( 'headline', 'intro', 'feature1', 'feature2', 'feature3', 'cta', 'contact_info', 'hashtag1', 'hashtag2', 'hashtag3' ),
            ),
        );
    }

    /**
     * 安装内置模板
     *
     * @return array 安装结果
     */
    public function install_builtin_templates() {
        $builtin = $this->get_builtin_templates();
        return $this->import_templates( $builtin );
    }
}
