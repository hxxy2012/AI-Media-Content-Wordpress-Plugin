<?php
/**
 * 智能内容日历
 *
 * 可视化内容规划和发布管理
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 内容日历类
 */
class AISCG_Content_Calendar {

    /**
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $database;

    /**
     * 日历事件类型
     *
     * @var array
     */
    private $event_types = array(
        'content_publish' => '内容发布',
        'content_draft' => '内容草稿',
        'campaign_start' => '活动开始',
        'campaign_end' => '活动结束',
        'reminder' => '提醒事项',
        'milestone' => '里程碑',
    );

    /**
     * 重复规则
     *
     * @var array
     */
    private $recurrence_rules = array(
        'none' => '不重复',
        'daily' => '每天',
        'weekly' => '每周',
        'biweekly' => '每两周',
        'monthly' => '每月',
        'custom' => '自定义',
    );

    /**
     * 构造函数
     */
    public function __construct() {
        $this->database = new AISCG_Database();
        $this->create_calendar_tables();
    }

    /**
     * 创建日历事件
     *
     * @param array $event_data 事件数据
     *   - title: 标题
     *   - type: 事件类型
     *   - date: 日期
     *   - time: 时间
     *   - content_id: 关联内容ID(可选)
     *   - platforms: 平台列表
     *   - notes: 备注
     *   - recurrence: 重复规则
     *   - color: 颜色标记
     *
     * @return int|WP_Error 事件ID或错误
     */
    public function create_event( $event_data ) {
        global $wpdb;
        $table_events = $wpdb->prefix . 'aiscg_calendar_events';

        $defaults = array(
            'title' => '',
            'type' => 'content_publish',
            'date' => current_time( 'Y-m-d' ),
            'time' => current_time( 'H:i:s' ),
            'content_id' => null,
            'platforms' => array(),
            'notes' => '',
            'recurrence' => 'none',
            'recurrence_end' => null,
            'color' => '#3498db',
            'status' => 'planned',
        );
        $event_data = wp_parse_args( $event_data, $defaults );

        // 验证
        if ( empty( $event_data['title'] ) ) {
            return new WP_Error( 'invalid_title', '事件标题不能为空' );
        }

        $data = array(
            'title' => $event_data['title'],
            'type' => $event_data['type'],
            'event_date' => $event_data['date'],
            'event_time' => $event_data['time'],
            'content_id' => $event_data['content_id'],
            'platforms' => wp_json_encode( $event_data['platforms'] ),
            'notes' => $event_data['notes'],
            'recurrence' => $event_data['recurrence'],
            'recurrence_end' => $event_data['recurrence_end'],
            'color' => $event_data['color'],
            'status' => $event_data['status'],
            'created_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert( $table_events, $data );

        if ( $result === false ) {
            return new WP_Error( 'db_error', '无法创建事件' );
        }

        $event_id = $wpdb->insert_id;

        // 如果是重复事件,生成后续事件
        if ( $event_data['recurrence'] !== 'none' ) {
            $this->generate_recurrence_events( $event_id, $event_data );
        }

        return $event_id;
    }

    /**
     * 生成重复事件
     *
     * @param int $parent_event_id 父事件ID
     * @param array $event_data 事件数据
     */
    private function generate_recurrence_events( $parent_event_id, $event_data ) {
        $start_date = new DateTime( $event_data['date'] );
        $end_date = $event_data['recurrence_end'] ? new DateTime( $event_data['recurrence_end'] ) : null;

        // 最多生成365天的重复事件
        $max_date = new DateTime();
        $max_date->modify( '+365 days' );

        if ( ! $end_date || $end_date > $max_date ) {
            $end_date = $max_date;
        }

        $current_date = clone $start_date;
        $occurrence_count = 0;

        while ( $current_date <= $end_date && $occurrence_count < 100 ) {
            // 根据重复规则推进日期
            switch ( $event_data['recurrence'] ) {
                case 'daily':
                    $current_date->modify( '+1 day' );
                    break;

                case 'weekly':
                    $current_date->modify( '+1 week' );
                    break;

                case 'biweekly':
                    $current_date->modify( '+2 weeks' );
                    break;

                case 'monthly':
                    $current_date->modify( '+1 month' );
                    break;
            }

            if ( $current_date > $end_date ) {
                break;
            }

            // 创建子事件
            global $wpdb;
            $table_events = $wpdb->prefix . 'aiscg_calendar_events';

            $wpdb->insert( $table_events, array(
                'parent_event_id' => $parent_event_id,
                'title' => $event_data['title'],
                'type' => $event_data['type'],
                'event_date' => $current_date->format( 'Y-m-d' ),
                'event_time' => $event_data['time'],
                'content_id' => $event_data['content_id'],
                'platforms' => wp_json_encode( $event_data['platforms'] ),
                'notes' => $event_data['notes'],
                'color' => $event_data['color'],
                'status' => 'planned',
                'created_at' => current_time( 'mysql' ),
            ) );

            $occurrence_count++;
        }
    }

    /**
     * 更新日历事件
     *
     * @param int $event_id 事件ID
     * @param array $event_data 更新数据
     *
     * @return bool|WP_Error 成功返回true,失败返回错误
     */
    public function update_event( $event_id, $event_data ) {
        global $wpdb;
        $table_events = $wpdb->prefix . 'aiscg_calendar_events';

        $update_data = array(
            'updated_at' => current_time( 'mysql' ),
        );

        $allowed_fields = array( 'title', 'type', 'event_date', 'event_time', 'content_id', 'notes', 'color', 'status' );

        foreach ( $allowed_fields as $field ) {
            if ( isset( $event_data[ $field ] ) ) {
                $update_data[ $field ] = $event_data[ $field ];
            }
        }

        if ( isset( $event_data['platforms'] ) ) {
            $update_data['platforms'] = wp_json_encode( $event_data['platforms'] );
        }

        $result = $wpdb->update( $table_events, $update_data, array( 'id' => $event_id ) );

        if ( $result === false ) {
            return new WP_Error( 'db_error', '无法更新事件' );
        }

        return true;
    }

    /**
     * 删除日历事件
     *
     * @param int $event_id 事件ID
     * @param bool $delete_series 是否删除整个系列
     *
     * @return bool|WP_Error 成功返回true,失败返回错误
     */
    public function delete_event( $event_id, $delete_series = false ) {
        global $wpdb;
        $table_events = $wpdb->prefix . 'aiscg_calendar_events';

        if ( $delete_series ) {
            // 获取父事件ID
            $event = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM $table_events WHERE id = %d", $event_id ),
                ARRAY_A
            );

            if ( $event ) {
                $parent_id = $event['parent_event_id'] ? $event['parent_event_id'] : $event_id;

                // 删除所有相关事件
                $wpdb->delete( $table_events, array( 'parent_event_id' => $parent_id ) );
                $wpdb->delete( $table_events, array( 'id' => $parent_id ) );
            }
        } else {
            // 仅删除单个事件
            $result = $wpdb->delete( $table_events, array( 'id' => $event_id ) );

            if ( $result === false ) {
                return new WP_Error( 'db_error', '无法删除事件' );
            }
        }

        return true;
    }

    /**
     * 获取日历视图数据
     *
     * @param array $args 查询参数
     *   - start_date: 开始日期
     *   - end_date: 结束日期
     *   - type: 事件类型
     *   - status: 状态
     *   - platform: 平台
     *
     * @return array 日历数据
     */
    public function get_calendar_view( $args = array() ) {
        global $wpdb;
        $table_events = $wpdb->prefix . 'aiscg_calendar_events';

        $defaults = array(
            'start_date' => date( 'Y-m-01' ), // 当月第一天
            'end_date' => date( 'Y-m-t' ), // 当月最后一天
            'type' => null,
            'status' => null,
            'platform' => null,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );

        $where[] = $wpdb->prepare( 'event_date >= %s', $args['start_date'] );
        $where[] = $wpdb->prepare( 'event_date <= %s', $args['end_date'] );

        if ( $args['type'] ) {
            $where[] = $wpdb->prepare( 'type = %s', $args['type'] );
        }

        if ( $args['status'] ) {
            $where[] = $wpdb->prepare( 'status = %s', $args['status'] );
        }

        if ( $args['platform'] ) {
            $where[] = $wpdb->prepare( 'platforms LIKE %s', '%"' . $wpdb->esc_like( $args['platform'] ) . '"%' );
        }

        $where_clause = implode( ' AND ', $where );

        $events = $wpdb->get_results(
            "SELECT * FROM $table_events WHERE $where_clause ORDER BY event_date ASC, event_time ASC",
            ARRAY_A
        );

        // 解析JSON字段
        foreach ( $events as &$event ) {
            $event['platforms'] = json_decode( $event['platforms'], true );
        }

        // 按日期分组
        $by_date = array();
        foreach ( $events as $event ) {
            $date = $event['event_date'];
            if ( ! isset( $by_date[ $date ] ) ) {
                $by_date[ $date ] = array();
            }
            $by_date[ $date ][] = $event;
        }

        return array(
            'events' => $events,
            'by_date' => $by_date,
            'start_date' => $args['start_date'],
            'end_date' => $args['end_date'],
            'statistics' => $this->calculate_calendar_statistics( $events ),
        );
    }

    /**
     * 计算日历统计数据
     *
     * @param array $events 事件列表
     *
     * @return array 统计数据
     */
    private function calculate_calendar_statistics( $events ) {
        $stats = array(
            'total_events' => count( $events ),
            'by_type' => array(),
            'by_status' => array(),
            'by_platform' => array(),
            'busiest_day' => null,
        );

        $events_per_day = array();

        foreach ( $events as $event ) {
            // 按类型统计
            $type = $event['type'];
            if ( ! isset( $stats['by_type'][ $type ] ) ) {
                $stats['by_type'][ $type ] = 0;
            }
            $stats['by_type'][ $type ]++;

            // 按状态统计
            $status = $event['status'];
            if ( ! isset( $stats['by_status'][ $status ] ) ) {
                $stats['by_status'][ $status ] = 0;
            }
            $stats['by_status'][ $status ]++;

            // 按平台统计
            $platforms = json_decode( $event['platforms'], true );
            if ( is_array( $platforms ) ) {
                foreach ( $platforms as $platform ) {
                    if ( ! isset( $stats['by_platform'][ $platform ] ) ) {
                        $stats['by_platform'][ $platform ] = 0;
                    }
                    $stats['by_platform'][ $platform ]++;
                }
            }

            // 按日期统计
            $date = $event['event_date'];
            if ( ! isset( $events_per_day[ $date ] ) ) {
                $events_per_day[ $date ] = 0;
            }
            $events_per_day[ $date ]++;
        }

        // 找到最忙的一天
        if ( ! empty( $events_per_day ) ) {
            arsort( $events_per_day );
            $busiest_date = key( $events_per_day );
            $stats['busiest_day'] = array(
                'date' => $busiest_date,
                'count' => $events_per_day[ $busiest_date ],
            );
        }

        return $stats;
    }

    /**
     * 智能生成内容计划
     *
     * @param array $options 选项
     *   - start_date: 开始日期
     *   - end_date: 结束日期
     *   - platforms: 平台列表
     *   - frequency: 发布频率(每天、每周等)
     *   - themes: 主题列表
     *
     * @return array|WP_Error 生成结果或错误
     */
    public function generate_content_plan( $options ) {
        $defaults = array(
            'start_date' => date( 'Y-m-d' ),
            'end_date' => date( 'Y-m-d', strtotime( '+30 days' ) ),
            'platforms' => array( 'xiaohongshu', 'instagram' ),
            'frequency' => 'daily', // daily, every_other_day, twice_weekly, weekly
            'themes' => array(),
            'best_times' => array( '09:00:00', '12:00:00', '19:00:00' ),
        );
        $options = wp_parse_args( $options, $defaults );

        $start = new DateTime( $options['start_date'] );
        $end = new DateTime( $options['end_date'] );
        $current = clone $start;

        $generated_events = array();

        while ( $current <= $end ) {
            $should_post = false;

            switch ( $options['frequency'] ) {
                case 'daily':
                    $should_post = true;
                    break;

                case 'every_other_day':
                    $days_diff = $start->diff( $current )->days;
                    $should_post = ( $days_diff % 2 == 0 );
                    break;

                case 'twice_weekly':
                    $day_of_week = $current->format( 'N' ); // 1=Monday, 7=Sunday
                    $should_post = in_array( $day_of_week, array( 2, 5 ) ); // Tuesday, Friday
                    break;

                case 'weekly':
                    $day_of_week = $current->format( 'N' );
                    $should_post = ( $day_of_week == 1 ); // Monday
                    break;
            }

            if ( $should_post ) {
                // 选择发布时间
                $time = $options['best_times'][ array_rand( $options['best_times'] ) ];

                // 选择主题
                $theme = ! empty( $options['themes'] ) ? $options['themes'][ array_rand( $options['themes'] ) ] : '日常分享';

                // 创建事件
                $event_id = $this->create_event( array(
                    'title' => $theme . ' - ' . $current->format( 'Y-m-d' ),
                    'type' => 'content_publish',
                    'date' => $current->format( 'Y-m-d' ),
                    'time' => $time,
                    'platforms' => $options['platforms'],
                    'notes' => 'AI自动生成的内容计划',
                    'status' => 'planned',
                    'color' => '#9b59b6',
                ) );

                if ( ! is_wp_error( $event_id ) ) {
                    $generated_events[] = $event_id;
                }
            }

            $current->modify( '+1 day' );
        }

        return array(
            'success' => true,
            'generated_count' => count( $generated_events ),
            'events' => $generated_events,
            'start_date' => $options['start_date'],
            'end_date' => $options['end_date'],
        );
    }

    /**
     * 获取即将到来的事件
     *
     * @param int $days 未来天数
     * @param int $limit 限制数量
     *
     * @return array 事件列表
     */
    public function get_upcoming_events( $days = 7, $limit = 20 ) {
        global $wpdb;
        $table_events = $wpdb->prefix . 'aiscg_calendar_events';

        $end_date = date( 'Y-m-d', strtotime( "+{$days} days" ) );

        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_events
                WHERE event_date BETWEEN CURDATE() AND %s
                    AND status != 'completed'
                ORDER BY event_date ASC, event_time ASC
                LIMIT %d",
                $end_date, $limit
            ),
            ARRAY_A
        );

        // 解析JSON字段
        foreach ( $events as &$event ) {
            $event['platforms'] = json_decode( $event['platforms'], true );
        }

        return $events;
    }

    /**
     * 标记事件为已完成
     *
     * @param int $event_id 事件ID
     *
     * @return bool|WP_Error 成功返回true,失败返回错误
     */
    public function mark_event_completed( $event_id ) {
        return $this->update_event( $event_id, array(
            'status' => 'completed',
            'completed_at' => current_time( 'mysql' ),
        ) );
    }

    /**
     * 获取日历冲突
     *
     * @param string $date 日期
     * @param string $time 时间
     * @param array $platforms 平台列表
     *
     * @return array 冲突事件列表
     */
    public function get_conflicts( $date, $time, $platforms ) {
        global $wpdb;
        $table_events = $wpdb->prefix . 'aiscg_calendar_events';

        // 查找同一时间段的事件
        $time_start = date( 'H:i:s', strtotime( $time . ' -30 minutes' ) );
        $time_end = date( 'H:i:s', strtotime( $time . ' +30 minutes' ) );

        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_events
                WHERE event_date = %s
                    AND event_time BETWEEN %s AND %s
                    AND status != 'cancelled'",
                $date, $time_start, $time_end
            ),
            ARRAY_A
        );

        $conflicts = array();

        foreach ( $events as $event ) {
            $event_platforms = json_decode( $event['platforms'], true );

            // 检查平台冲突
            $platform_overlap = array_intersect( $platforms, $event_platforms );

            if ( ! empty( $platform_overlap ) ) {
                $event['platforms'] = $event_platforms;
                $event['conflict_platforms'] = $platform_overlap;
                $conflicts[] = $event;
            }
        }

        return $conflicts;
    }

    /**
     * 创建日历表
     */
    private function create_calendar_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiscg_calendar_events';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            parent_event_id bigint(20) DEFAULT NULL,
            title varchar(200) NOT NULL,
            type varchar(50) NOT NULL,
            event_date date NOT NULL,
            event_time time NOT NULL,
            content_id bigint(20) DEFAULT NULL,
            platforms longtext,
            notes text,
            recurrence varchar(20) DEFAULT 'none',
            recurrence_end date DEFAULT NULL,
            color varchar(20) DEFAULT '#3498db',
            status varchar(20) DEFAULT 'planned',
            completed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY parent_event_id (parent_event_id),
            KEY event_date (event_date),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
