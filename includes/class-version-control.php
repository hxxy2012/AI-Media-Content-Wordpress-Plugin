<?php
/**
 * Version Control Class
 *
 * 管理内容的版本控制和历史记录
 *
 * @package AI_Social_Content_Generator
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Version_Control Class
 */
class AISCG_Version_Control {

    /**
     * 版本表名
     *
     * @var string
     */
    private $table_name;

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
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aiscg_versions';
        $this->db = new AISCG_Database();
        $this->logger = new AISCG_Logger();
    }

    /**
     * 保存版本
     *
     * @param int $post_id 内容ID
     * @param array $changes 变更说明
     * @return int|false 版本ID或false
     */
    public function save_version( $post_id, $changes = array() ) {
        global $wpdb;

        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            return false;
        }

        // 获取当前内容
        $post = $this->db->get_post( $post_id );
        if ( ! $post ) {
            $this->logger->error( "保存版本失败：内容不存在 #{$post_id}" );
            return false;
        }

        // 获取关联的图片
        $images = $this->db->get_images_by_post_id( $post_id );

        // 创建版本数据
        $version_data = array(
            'post_id' => $post_id,
            'title' => $post['title'],
            'content' => $post['content'],
            'hashtags' => $post['hashtags'],
            'platform' => $post['platform'],
            'ai_model' => $post['ai_model'],
            'images' => wp_json_encode( $images ),
            'metadata' => wp_json_encode( array(
                'status' => $post['status'],
                'prompt' => $post['prompt'] ?? '',
            ) ),
            'changes' => wp_json_encode( $changes ),
            'user_id' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert(
            $this->table_name,
            $version_data,
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
        );

        if ( $result ) {
            $version_id = $wpdb->insert_id;

            // 获取版本号
            $version_number = $this->get_version_count( $post_id );

            $this->logger->info( "保存版本成功 #{$version_id} (内容 #{$post_id}, 版本 #{$version_number})", array(
                'post_id' => $post_id,
                'version_number' => $version_number,
                'changes' => $changes,
            ) );

            return $version_id;
        }

        $this->logger->error( '保存版本失败', array( 'error' => $wpdb->last_error ) );
        return false;
    }

    /**
     * 获取版本
     *
     * @param int $version_id 版本ID
     * @return array|null 版本数据
     */
    public function get_version( $version_id ) {
        global $wpdb;

        $version_id = absint( $version_id );
        if ( ! $version_id ) {
            return null;
        }

        $version = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $version_id
            ),
            ARRAY_A
        );

        if ( $version ) {
            // 解析JSON字段
            $version['images'] = json_decode( $version['images'], true );
            $version['metadata'] = json_decode( $version['metadata'], true );
            $version['changes'] = json_decode( $version['changes'], true );
        }

        return $version;
    }

    /**
     * 获取内容的所有版本
     *
     * @param int $post_id 内容ID
     * @param array $args 查询参数
     * @return array 版本列表
     */
    public function get_versions( $post_id, $args = array() ) {
        global $wpdb;

        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            return array();
        }

        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args( $args, $defaults );

        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
        if ( ! $orderby ) {
            $orderby = 'created_at DESC';
        }

        $limit = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        $versions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE post_id = %d ORDER BY {$orderby} LIMIT %d OFFSET %d",
                $post_id,
                $limit,
                $offset
            ),
            ARRAY_A
        );

        // 解析JSON字段并添加版本号
        $total_versions = count( $versions );
        foreach ( $versions as $index => &$version ) {
            $version['images'] = json_decode( $version['images'], true );
            $version['metadata'] = json_decode( $version['metadata'], true );
            $version['changes'] = json_decode( $version['changes'], true );
            $version['version_number'] = $total_versions - $index;
        }

        return $versions;
    }

    /**
     * 获取版本数量
     *
     * @param int $post_id 内容ID
     * @return int 版本数量
     */
    public function get_version_count( $post_id ) {
        global $wpdb;

        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            return 0;
        }

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE post_id = %d",
                $post_id
            )
        );

        return intval( $count );
    }

    /**
     * 恢复版本
     *
     * @param int $version_id 版本ID
     * @return bool 是否成功
     */
    public function restore_version( $version_id ) {
        $version = $this->get_version( $version_id );

        if ( ! $version ) {
            $this->logger->error( "恢复版本失败：版本不存在 #{$version_id}" );
            return false;
        }

        $post_id = $version['post_id'];

        // 在恢复之前，先保存当前版本
        $this->save_version( $post_id, array(
            'action' => 'before_restore',
            'restored_from_version' => $version_id,
        ) );

        // 更新内容
        $update_data = array(
            'title' => $version['title'],
            'content' => $version['content'],
            'hashtags' => $version['hashtags'],
            'updated_at' => current_time( 'mysql' ),
        );

        $result = $this->db->update_post( $post_id, $update_data );

        if ( $result ) {
            // 删除现有图片
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'aiscg_images',
                array( 'post_id' => $post_id ),
                array( '%d' )
            );

            // 恢复图片
            if ( ! empty( $version['images'] ) ) {
                foreach ( $version['images'] as $image ) {
                    $this->db->create_image( array(
                        'post_id' => $post_id,
                        'image_url' => $image['image_url'],
                        'image_path' => $image['image_path'],
                        'width' => $image['width'],
                        'height' => $image['height'],
                        'template' => $image['template'] ?? 'gradient',
                    ) );
                }
            }

            $this->logger->info( "恢复版本成功 #{$version_id} -> 内容 #{$post_id}" );

            return true;
        }

        $this->logger->error( "恢复版本失败 #{$version_id}" );
        return false;
    }

    /**
     * 比较两个版本
     *
     * @param int $version_id_1 版本1 ID
     * @param int $version_id_2 版本2 ID
     * @return array|false 差异数据或false
     */
    public function compare_versions( $version_id_1, $version_id_2 ) {
        $version1 = $this->get_version( $version_id_1 );
        $version2 = $this->get_version( $version_id_2 );

        if ( ! $version1 || ! $version2 ) {
            return false;
        }

        if ( $version1['post_id'] !== $version2['post_id'] ) {
            return false;
        }

        $diff = array(
            'version1' => array(
                'id' => $version1['id'],
                'created_at' => $version1['created_at'],
            ),
            'version2' => array(
                'id' => $version2['id'],
                'created_at' => $version2['created_at'],
            ),
            'differences' => array(),
        );

        // 比较各个字段
        $fields = array( 'title', 'content', 'hashtags' );
        foreach ( $fields as $field ) {
            if ( $version1[ $field ] !== $version2[ $field ] ) {
                $diff['differences'][ $field ] = array(
                    'old' => $version1[ $field ],
                    'new' => $version2[ $field ],
                );
            }
        }

        // 比较图片数量
        $images1_count = count( $version1['images'] ?? array() );
        $images2_count = count( $version2['images'] ?? array() );

        if ( $images1_count !== $images2_count ) {
            $diff['differences']['images_count'] = array(
                'old' => $images1_count,
                'new' => $images2_count,
            );
        }

        return $diff;
    }

    /**
     * 删除版本
     *
     * @param int $version_id 版本ID
     * @return bool 是否成功
     */
    public function delete_version( $version_id ) {
        global $wpdb;

        $version_id = absint( $version_id );
        if ( ! $version_id ) {
            return false;
        }

        $result = $wpdb->delete(
            $this->table_name,
            array( 'id' => $version_id ),
            array( '%d' )
        );

        if ( $result ) {
            $this->logger->info( "删除版本成功 #{$version_id}" );
            return true;
        }

        return false;
    }

    /**
     * 清理旧版本
     *
     * @param int $post_id 内容ID
     * @param int $keep_count 保留的版本数量
     * @return int 删除的版本数
     */
    public function cleanup_old_versions( $post_id, $keep_count = 10 ) {
        global $wpdb;

        $post_id = absint( $post_id );
        if ( ! $post_id || $keep_count < 1 ) {
            return 0;
        }

        // 获取要删除的版本ID
        $versions_to_delete = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name}
                WHERE post_id = %d
                ORDER BY created_at DESC
                LIMIT 999999 OFFSET %d",
                $post_id,
                $keep_count
            )
        );

        if ( empty( $versions_to_delete ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $versions_to_delete ), '%d' ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ({$placeholders})",
                $versions_to_delete
            )
        );

        if ( $deleted ) {
            $this->logger->info( "清理旧版本成功", array(
                'post_id' => $post_id,
                'deleted_count' => $deleted,
                'kept_count' => $keep_count,
            ) );
        }

        return $deleted;
    }

    /**
     * 获取版本统计
     *
     * @return array 统计数据
     */
    public function get_statistics() {
        global $wpdb;

        // 总版本数
        $total_versions = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

        // 今日版本数
        $today_versions = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(created_at) = %s",
                current_time( 'Y-m-d' )
            )
        );

        // 按内容统计
        $by_post = $wpdb->get_results(
            "SELECT post_id, COUNT(*) as version_count
            FROM {$this->table_name}
            GROUP BY post_id
            ORDER BY version_count DESC
            LIMIT 10",
            ARRAY_A
        );

        // 按用户统计
        $by_user = $wpdb->get_results(
            "SELECT user_id, COUNT(*) as version_count
            FROM {$this->table_name}
            GROUP BY user_id
            ORDER BY version_count DESC
            LIMIT 10",
            ARRAY_A
        );

        return array(
            'total_versions' => intval( $total_versions ),
            'today_versions' => intval( $today_versions ),
            'by_post' => $by_post,
            'by_user' => $by_user,
        );
    }

    /**
     * 自动保存版本（在内容更新时调用）
     *
     * @param int $post_id 内容ID
     * @param array $old_data 旧数据
     * @param array $new_data 新数据
     */
    public function auto_save_on_update( $post_id, $old_data, $new_data ) {
        // 检查是否有实质性变更
        $has_changes = false;
        $changes = array( 'action' => 'update', 'fields' => array() );

        $fields_to_check = array( 'title', 'content', 'hashtags' );

        foreach ( $fields_to_check as $field ) {
            if ( isset( $old_data[ $field ] ) && isset( $new_data[ $field ] ) ) {
                if ( $old_data[ $field ] !== $new_data[ $field ] ) {
                    $has_changes = true;
                    $changes['fields'][] = $field;
                }
            }
        }

        if ( $has_changes ) {
            $this->save_version( $post_id, $changes );

            // 清理旧版本（保留最近20个）
            $this->cleanup_old_versions( $post_id, 20 );
        }
    }
}
