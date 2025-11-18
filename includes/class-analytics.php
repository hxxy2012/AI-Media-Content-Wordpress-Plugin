<?php
/**
 * 统计分析类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 统计分析类
 */
class AISCG_Analytics {

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
    }

    /**
     * 获取总体统计
     *
     * @return array 统计数据
     */
    public function get_overview_stats() {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'aiscg_posts';
        $images_table = $wpdb->prefix . 'aiscg_images';

        // 总内容数
        $total_posts = $wpdb->get_var( "SELECT COUNT(*) FROM {$posts_table}" );

        // 按平台统计
        $platform_stats = $wpdb->get_results(
            "SELECT platform, COUNT(*) as count FROM {$posts_table} GROUP BY platform"
        );

        // 按状态统计
        $status_stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$posts_table} GROUP BY status"
        );

        // 按AI模型统计
        $ai_model_stats = $wpdb->get_results(
            "SELECT ai_model, COUNT(*) as count FROM {$posts_table} GROUP BY ai_model ORDER BY count DESC LIMIT 5"
        );

        // 总图片数
        $total_images = $wpdb->get_var( "SELECT COUNT(*) FROM {$images_table}" );

        // 今日生成数
        $today_posts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$posts_table} WHERE DATE(created_at) = %s",
                current_time( 'Y-m-d' )
            )
        );

        // 本周生成数
        $week_start = date( 'Y-m-d', strtotime( 'monday this week' ) );
        $week_posts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$posts_table} WHERE created_at >= %s",
                $week_start
            )
        );

        // 本月生成数
        $month_start = date( 'Y-m-01' );
        $month_posts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$posts_table} WHERE created_at >= %s",
                $month_start
            )
        );

        return array(
            'total_posts' => (int) $total_posts,
            'total_images' => (int) $total_images,
            'today_posts' => (int) $today_posts,
            'week_posts' => (int) $week_posts,
            'month_posts' => (int) $month_posts,
            'platform_stats' => $this->format_stats( $platform_stats ),
            'status_stats' => $this->format_stats( $status_stats ),
            'ai_model_stats' => $this->format_stats( $ai_model_stats, 'ai_model' ),
        );
    }

    /**
     * 格式化统计数据
     *
     * @param array  $stats 原始统计
     * @param string $key   键名
     * @return array 格式化后的数据
     */
    private function format_stats( $stats, $key = null ) {
        $formatted = array();

        foreach ( $stats as $stat ) {
            $label = $key ? $stat->$key : ( isset( $stat->platform ) ? $stat->platform : $stat->status );
            $formatted[ $label ] = (int) $stat->count;
        }

        return $formatted;
    }

    /**
     * 获取时间趋势数据
     *
     * @param int $days 天数
     * @return array 趋势数据
     */
    public function get_trend_data( $days = 30 ) {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'aiscg_posts';
        $start_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );

        $trend_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count, platform
                FROM {$posts_table}
                WHERE created_at >= %s
                GROUP BY DATE(created_at), platform
                ORDER BY date ASC",
                $start_date
            )
        );

        // 组织数据
        $dates = array();
        $platforms = array();

        // 初始化所有日期
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $dates[] = $date;
            $platforms['xiaohongshu'][ $date ] = 0;
            $platforms['instagram'][ $date ] = 0;
        }

        // 填充实际数据
        foreach ( $trend_data as $item ) {
            if ( isset( $platforms[ $item->platform ][ $item->date ] ) ) {
                $platforms[ $item->platform ][ $item->date ] = (int) $item->count;
            }
        }

        return array(
            'dates' => $dates,
            'datasets' => array(
                array(
                    'label' => '小红书',
                    'data' => array_values( $platforms['xiaohongshu'] ),
                    'platform' => 'xiaohongshu',
                ),
                array(
                    'label' => 'Instagram',
                    'data' => array_values( $platforms['instagram'] ),
                    'platform' => 'instagram',
                ),
            ),
        );
    }

    /**
     * 获取AI模型使用统计
     *
     * @return array AI模型统计
     */
    public function get_ai_model_usage() {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'aiscg_posts';

        $stats = $wpdb->get_results(
            "SELECT ai_model, COUNT(*) as count,
            AVG(CHAR_LENGTH(content)) as avg_length
            FROM {$posts_table}
            GROUP BY ai_model
            ORDER BY count DESC"
        );

        $result = array();

        foreach ( $stats as $stat ) {
            $result[] = array(
                'model' => $stat->ai_model,
                'count' => (int) $stat->count,
                'avg_length' => (int) $stat->avg_length,
                'percentage' => 0, // 稍后计算
            );
        }

        // 计算百分比
        $total = array_sum( array_column( $result, 'count' ) );

        if ( $total > 0 ) {
            foreach ( $result as &$item ) {
                $item['percentage'] = round( ( $item['count'] / $total ) * 100, 2 );
            }
        }

        return $result;
    }

    /**
     * 获取内容质量分析
     *
     * @return array 质量指标
     */
    public function get_content_quality_metrics() {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'aiscg_posts';

        // 平均标题长度
        $avg_title_length = $wpdb->get_var(
            "SELECT AVG(CHAR_LENGTH(title)) FROM {$posts_table}"
        );

        // 平均内容长度
        $avg_content_length = $wpdb->get_var(
            "SELECT AVG(CHAR_LENGTH(content)) FROM {$posts_table}"
        );

        // 平均标签数
        $avg_hashtags = $wpdb->get_var(
            "SELECT AVG(JSON_LENGTH(hashtags)) FROM {$posts_table} WHERE hashtags IS NOT NULL"
        );

        // 按平台的内容长度
        $platform_lengths = $wpdb->get_results(
            "SELECT platform,
            AVG(CHAR_LENGTH(content)) as avg_length,
            MIN(CHAR_LENGTH(content)) as min_length,
            MAX(CHAR_LENGTH(content)) as max_length
            FROM {$posts_table}
            GROUP BY platform"
        );

        $platform_metrics = array();
        foreach ( $platform_lengths as $pl ) {
            $platform_metrics[ $pl->platform ] = array(
                'avg_length' => (int) $pl->avg_length,
                'min_length' => (int) $pl->min_length,
                'max_length' => (int) $pl->max_length,
            );
        }

        return array(
            'avg_title_length' => (int) $avg_title_length,
            'avg_content_length' => (int) $avg_content_length,
            'avg_hashtags' => round( $avg_hashtags, 1 ),
            'platform_metrics' => $platform_metrics,
        );
    }

    /**
     * 获取最受欢迎的标签
     *
     * @param int $limit 限制数量
     * @return array 标签列表
     */
    public function get_popular_hashtags( $limit = 20 ) {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'aiscg_posts';

        $posts = $wpdb->get_results(
            "SELECT hashtags FROM {$posts_table} WHERE hashtags IS NOT NULL AND hashtags != ''"
        );

        $hashtag_counts = array();

        foreach ( $posts as $post ) {
            $hashtags = json_decode( $post->hashtags, true );

            if ( is_array( $hashtags ) ) {
                foreach ( $hashtags as $tag ) {
                    $tag = trim( $tag );
                    if ( ! empty( $tag ) ) {
                        if ( ! isset( $hashtag_counts[ $tag ] ) ) {
                            $hashtag_counts[ $tag ] = 0;
                        }
                        $hashtag_counts[ $tag ]++;
                    }
                }
            }
        }

        // 排序
        arsort( $hashtag_counts );

        // 限制数量
        $hashtag_counts = array_slice( $hashtag_counts, 0, $limit, true );

        $result = array();
        foreach ( $hashtag_counts as $tag => $count ) {
            $result[] = array(
                'tag' => $tag,
                'count' => $count,
            );
        }

        return $result;
    }

    /**
     * 获取生产力统计
     *
     * @return array 生产力数据
     */
    public function get_productivity_stats() {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'aiscg_posts';

        // 每天平均生成数
        $days_with_posts = $wpdb->get_var(
            "SELECT COUNT(DISTINCT DATE(created_at)) FROM {$posts_table}"
        );

        $total_posts = $wpdb->get_var( "SELECT COUNT(*) FROM {$posts_table}" );

        $avg_per_day = $days_with_posts > 0 ? $total_posts / $days_with_posts : 0;

        // 最活跃的日期
        $most_productive_day = $wpdb->get_row(
            "SELECT DATE(created_at) as date, COUNT(*) as count
            FROM {$posts_table}
            GROUP BY DATE(created_at)
            ORDER BY count DESC
            LIMIT 1"
        );

        // 最活跃的小时
        $most_productive_hour = $wpdb->get_row(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count
            FROM {$posts_table}
            GROUP BY HOUR(created_at)
            ORDER BY count DESC
            LIMIT 1"
        );

        // 每周的趋势
        $weekly_trend = $wpdb->get_results(
            "SELECT DAYOFWEEK(created_at) as day_of_week, COUNT(*) as count
            FROM {$posts_table}
            GROUP BY DAYOFWEEK(created_at)
            ORDER BY day_of_week"
        );

        $days_of_week = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
        $weekly_data = array_fill( 0, 7, 0 );

        foreach ( $weekly_trend as $item ) {
            $weekly_data[ $item->day_of_week - 1 ] = (int) $item->count;
        }

        return array(
            'avg_per_day' => round( $avg_per_day, 2 ),
            'most_productive_day' => $most_productive_day ? array(
                'date' => $most_productive_day->date,
                'count' => (int) $most_productive_day->count,
            ) : null,
            'most_productive_hour' => $most_productive_hour ? (int) $most_productive_hour->hour : null,
            'weekly_trend' => array(
                'labels' => $days_of_week,
                'data' => $weekly_data,
            ),
        );
    }

    /**
     * 获取存储使用统计
     *
     * @return array 存储统计
     */
    public function get_storage_stats() {
        global $wpdb;

        $images_table = $wpdb->prefix . 'aiscg_images';

        // 图片总数
        $total_images = $wpdb->get_var( "SELECT COUNT(*) FROM {$images_table}" );

        // 计算总存储大小
        $images = $wpdb->get_results( "SELECT image_path FROM {$images_table}" );

        $total_size = 0;

        foreach ( $images as $image ) {
            if ( file_exists( $image->image_path ) ) {
                $total_size += filesize( $image->image_path );
            }
        }

        // 数据库大小
        $db_size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(data_length + index_length)
                FROM information_schema.TABLES
                WHERE table_schema = %s
                AND table_name IN (%s, %s, %s)",
                DB_NAME,
                $wpdb->prefix . 'aiscg_posts',
                $wpdb->prefix . 'aiscg_images',
                $wpdb->prefix . 'aiscg_settings'
            )
        );

        return array(
            'total_images' => (int) $total_images,
            'total_size' => (int) $total_size,
            'formatted_size' => size_format( $total_size ),
            'db_size' => (int) $db_size,
            'formatted_db_size' => size_format( $db_size ),
            'avg_image_size' => $total_images > 0 ? $total_size / $total_images : 0,
            'formatted_avg_size' => $total_images > 0 ? size_format( $total_size / $total_images ) : '0 B',
        );
    }

    /**
     * 导出统计报告
     *
     * @param string $format 格式(json/csv)
     * @return string 文件路径
     */
    public function export_stats_report( $format = 'json' ) {
        $stats = array(
            'generated_at' => current_time( 'mysql' ),
            'overview' => $this->get_overview_stats(),
            'ai_model_usage' => $this->get_ai_model_usage(),
            'content_quality' => $this->get_content_quality_metrics(),
            'popular_hashtags' => $this->get_popular_hashtags(),
            'productivity' => $this->get_productivity_stats(),
            'storage' => $this->get_storage_stats(),
        );

        $upload = wp_upload_dir();
        $export_dir = $upload['basedir'] . '/aiscg-exports';

        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
        }

        $filename = 'stats_report_' . time() . '.' . $format;
        $filepath = $export_dir . '/' . $filename;

        if ( $format === 'json' ) {
            file_put_contents( $filepath, wp_json_encode( $stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
        } elseif ( $format === 'csv' ) {
            // CSV格式的简化报告
            $handle = fopen( $filepath, 'w' );

            fputcsv( $handle, array( 'Metric', 'Value' ) );
            fputcsv( $handle, array( 'Total Posts', $stats['overview']['total_posts'] ) );
            fputcsv( $handle, array( 'Total Images', $stats['overview']['total_images'] ) );
            fputcsv( $handle, array( 'Today Posts', $stats['overview']['today_posts'] ) );
            fputcsv( $handle, array( 'Week Posts', $stats['overview']['week_posts'] ) );
            fputcsv( $handle, array( 'Month Posts', $stats['overview']['month_posts'] ) );

            fclose( $handle );
        }

        return $filepath;
    }
}
