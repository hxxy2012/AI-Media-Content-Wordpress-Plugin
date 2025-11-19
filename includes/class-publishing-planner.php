<?php
/**
 * Publishing Planner Class
 *
 * 内容发布计划管理
 *
 * @package AI_Social_Content_Generator
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Publishing_Planner Class
 */
class AISCG_Publishing_Planner {

    /**
     * 计划表名
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
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aiscg_publishing_plans';
        $this->logger = new AISCG_Logger();
        $this->db = new AISCG_Database();
    }

    /**
     * 创建发布计划
     *
     * @param array $data 计划数据
     * @return int|false 计划ID或false
     */
    public function create_plan( $data ) {
        global $wpdb;

        $plan_data = array(
            'post_id' => absint( $data['post_id'] ),
            'platform' => sanitize_text_field( $data['platform'] ),
            'scheduled_time' => $data['scheduled_time'],
            'timezone' => isset( $data['timezone'] ) ? sanitize_text_field( $data['timezone'] ) : 'UTC',
            'status' => 'pending',
            'auto_publish' => isset( $data['auto_publish'] ) ? intval( $data['auto_publish'] ) : 0,
            'notify_on_publish' => isset( $data['notify_on_publish'] ) ? intval( $data['notify_on_publish'] ) : 0,
            'metadata' => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : '{}',
            'created_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert(
            $this->table_name,
            $plan_data,
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        if ( $result ) {
            $plan_id = $wpdb->insert_id;

            // 如果启用自动发布，注册WordPress定时任务
            if ( $plan_data['auto_publish'] ) {
                $this->schedule_auto_publish( $plan_id, $plan_data['scheduled_time'] );
            }

            $this->logger->info( "创建发布计划成功 #{$plan_id}", $plan_data );
            return $plan_id;
        }

        return false;
    }

    /**
     * 获取发布计划列表
     *
     * @param array $args 查询参数
     * @return array 计划列表
     */
    public function get_plans( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'platform' => '',
            'start_date' => '',
            'end_date' => '',
            'limit' => 100,
            'offset' => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $where_values = array();

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if ( ! empty( $args['platform'] ) ) {
            $where[] = 'platform = %s';
            $where_values[] = $args['platform'];
        }

        if ( ! empty( $args['start_date'] ) ) {
            $where[] = 'scheduled_time >= %s';
            $where_values[] = $args['start_date'];
        }

        if ( ! empty( $args['end_date'] ) ) {
            $where[] = 'scheduled_time <= %s';
            $where_values[] = $args['end_date'];
        }

        $where_clause = implode( ' AND ', $where );

        if ( ! empty( $where_values ) ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY scheduled_time ASC LIMIT %d OFFSET %d",
                array_merge( $where_values, array( $args['limit'], $args['offset'] ) )
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY scheduled_time ASC LIMIT %d OFFSET %d",
                $args['limit'],
                $args['offset']
            );
        }

        $plans = $wpdb->get_results( $sql, ARRAY_A );

        // 附加内容数据
        foreach ( $plans as &$plan ) {
            $plan['post'] = $this->db->get_post( $plan['post_id'] );
            if ( ! empty( $plan['metadata'] ) ) {
                $plan['metadata'] = json_decode( $plan['metadata'], true );
            }
        }

        return $plans;
    }

    /**
     * 生成发布日历
     *
     * @param string $start_date 开始日期
     * @param string $end_date 结束日期
     * @param array $options 选项
     * @return array 日历数据
     */
    public function generate_calendar( $start_date, $end_date, $options = array() ) {
        $defaults = array(
            'posts_per_day' => 2,
            'platforms' => array( 'xiaohongshu', 'instagram' ),
            'best_times' => array(
                'xiaohongshu' => array( '09:00', '12:00', '18:00', '21:00' ),
                'instagram' => array( '10:00', '14:00', '19:00', '22:00' ),
            ),
            'avoid_weekdays' => array(),
        );

        $options = wp_parse_args( $options, $defaults );

        $calendar = array();
        $current_date = strtotime( $start_date );
        $end_timestamp = strtotime( $end_date );

        // 获取待发布的内容
        $posts = $this->db->get_posts( array(
            'status' => 'draft',
            'limit' => 1000,
        ) );

        $post_index = 0;
        $total_posts = count( $posts );

        while ( $current_date <= $end_timestamp && $post_index < $total_posts ) {
            $date = date( 'Y-m-d', $current_date );
            $weekday = date( 'N', $current_date );

            // 跳过不发布的日期
            if ( in_array( $weekday, $options['avoid_weekdays'], true ) ) {
                $current_date = strtotime( '+1 day', $current_date );
                continue;
            }

            $calendar[ $date ] = array();

            // 为每天安排内容
            for ( $i = 0; $i < $options['posts_per_day'] && $post_index < $total_posts; $i++ ) {
                $post = $posts[ $post_index ];
                $platform = $post['platform'];

                // 选择最佳发布时间
                $best_times = isset( $options['best_times'][ $platform ] ) ?
                    $options['best_times'][ $platform ] : array( '12:00' );

                $time_index = $i % count( $best_times );
                $time = $best_times[ $time_index ];

                $scheduled_time = $date . ' ' . $time . ':00';

                $calendar[ $date ][] = array(
                    'post_id' => $post['id'],
                    'platform' => $platform,
                    'scheduled_time' => $scheduled_time,
                    'title' => $post['title'],
                );

                $post_index++;
            }

            $current_date = strtotime( '+1 day', $current_date );
        }

        $this->logger->info( '生成发布日历完成', array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'total_scheduled' => $post_index,
        ) );

        return $calendar;
    }

    /**
     * 批量创建发布计划
     *
     * @param array $calendar 日历数据
     * @return array 创建结果
     */
    public function batch_create_plans( $calendar ) {
        $results = array(
            'success' => 0,
            'failed' => 0,
        );

        foreach ( $calendar as $date => $items ) {
            foreach ( $items as $item ) {
                $plan_id = $this->create_plan( array(
                    'post_id' => $item['post_id'],
                    'platform' => $item['platform'],
                    'scheduled_time' => $item['scheduled_time'],
                    'auto_publish' => true,
                    'notify_on_publish' => true,
                ) );

                if ( $plan_id ) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            }
        }

        return $results;
    }

    /**
     * 注册自动发布任务
     *
     * @param int $plan_id 计划ID
     * @param string $scheduled_time 计划时间
     */
    private function schedule_auto_publish( $plan_id, $scheduled_time ) {
        $timestamp = strtotime( $scheduled_time );

        if ( $timestamp > current_time( 'timestamp' ) ) {
            wp_schedule_single_event( $timestamp, 'aiscg_auto_publish', array( $plan_id ) );
        }
    }

    /**
     * 执行自动发布
     *
     * @param int $plan_id 计划ID
     */
    public function execute_auto_publish( $plan_id ) {
        global $wpdb;

        $plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $plan_id
            ),
            ARRAY_A
        );

        if ( ! $plan ) {
            return false;
        }

        // 更新内容状态为已发布
        $this->db->update_post( $plan['post_id'], array(
            'status' => 'published',
        ) );

        // 更新计划状态
        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'published',
                'published_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $plan_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        // 发送通知
        if ( $plan['notify_on_publish'] ) {
            $notification = new AISCG_Notification();
            $post = $this->db->get_post( $plan['post_id'] );

            $notification->send_webhook( 'content_published', array(
                'plan_id' => $plan_id,
                'post_id' => $plan['post_id'],
                'title' => $post['title'],
                'platform' => $plan['platform'],
                'scheduled_time' => $plan['scheduled_time'],
            ) );
        }

        $this->logger->info( "自动发布执行成功 #{$plan_id}" );

        return true;
    }

    /**
     * 获取发布统计
     *
     * @param array $args 查询参数
     * @return array 统计数据
     */
    public function get_statistics( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'start_date' => date( 'Y-m-d', strtotime( '-30 days' ) ),
            'end_date' => date( 'Y-m-d' ),
        );

        $args = wp_parse_args( $args, $defaults );

        // 按状态统计
        $by_status = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count
                FROM {$this->table_name}
                WHERE scheduled_time BETWEEN %s AND %s
                GROUP BY status",
                $args['start_date'],
                $args['end_date']
            ),
            ARRAY_A
        );

        // 按平台统计
        $by_platform = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT platform, COUNT(*) as count
                FROM {$this->table_name}
                WHERE scheduled_time BETWEEN %s AND %s
                GROUP BY platform",
                $args['start_date'],
                $args['end_date']
            ),
            ARRAY_A
        );

        // 按日期统计
        $by_date = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(scheduled_time) as date, COUNT(*) as count
                FROM {$this->table_name}
                WHERE scheduled_time BETWEEN %s AND %s
                GROUP BY DATE(scheduled_time)
                ORDER BY date",
                $args['start_date'],
                $args['end_date']
            ),
            ARRAY_A
        );

        return array(
            'by_status' => $by_status,
            'by_platform' => $by_platform,
            'by_date' => $by_date,
        );
    }
}
