<?php
/**
 * API Manager Class
 *
 * 管理AI API调用、速率限制和缓存
 *
 * @package AI_Social_Content_Generator
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_API_Manager Class
 */
class AISCG_API_Manager {

    /**
     * 日志对象
     *
     * @var AISCG_Logger
     */
    private $logger;

    /**
     * 速率限制表名
     *
     * @var string
     */
    private $rate_limit_table;

    /**
     * 缓存表名
     *
     * @var string
     */
    private $cache_table;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->rate_limit_table = $wpdb->prefix . 'aiscg_rate_limits';
        $this->cache_table = $wpdb->prefix . 'aiscg_cache';
        $this->logger = new AISCG_Logger();
    }

    /**
     * 检查速率限制
     *
     * @param string $service AI服务名称
     * @param int $limit 限制数量
     * @param int $window 时间窗口（秒）
     * @return bool 是否在限制内
     */
    public function check_rate_limit( $service, $limit = 60, $window = 60 ) {
        global $wpdb;

        $service = sanitize_text_field( $service );
        $now = current_time( 'timestamp' );
        $window_start = date( 'Y-m-d H:i:s', $now - $window );

        // 统计时间窗口内的请求数
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->rate_limit_table}
                WHERE service = %s AND created_at > %s",
                $service,
                $window_start
            )
        );

        if ( $count >= $limit ) {
            $this->logger->warning( "API速率限制触发", array(
                'service' => $service,
                'count' => $count,
                'limit' => $limit,
                'window' => $window,
            ) );
            return false;
        }

        return true;
    }

    /**
     * 记录API调用
     *
     * @param string $service AI服务名称
     * @param array $metadata 元数据
     * @return bool 是否成功
     */
    public function record_api_call( $service, $metadata = array() ) {
        global $wpdb;

        $data = array(
            'service' => sanitize_text_field( $service ),
            'metadata' => wp_json_encode( $metadata ),
            'user_id' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        );

        $result = $wpdb->insert(
            $this->rate_limit_table,
            $data,
            array( '%s', '%s', '%d', '%s' )
        );

        return $result !== false;
    }

    /**
     * 获取缓存
     *
     * @param string $key 缓存键
     * @return mixed|false 缓存值或false
     */
    public function get_cache( $key ) {
        global $wpdb;

        $key = sanitize_text_field( $key );

        $cache = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->cache_table} WHERE cache_key = %s AND expires_at > %s",
                $key,
                current_time( 'mysql' )
            ),
            ARRAY_A
        );

        if ( $cache ) {
            $this->logger->debug( "缓存命中: {$key}" );
            return maybe_unserialize( $cache['cache_value'] );
        }

        $this->logger->debug( "缓存未命中: {$key}" );
        return false;
    }

    /**
     * 设置缓存
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒）
     * @return bool 是否成功
     */
    public function set_cache( $key, $value, $ttl = 3600 ) {
        global $wpdb;

        $key = sanitize_text_field( $key );
        $expires_at = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $ttl );

        // 检查缓存是否存在
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->cache_table} WHERE cache_key = %s",
                $key
            )
        );

        if ( $exists ) {
            // 更新缓存
            $result = $wpdb->update(
                $this->cache_table,
                array(
                    'cache_value' => maybe_serialize( $value ),
                    'expires_at' => $expires_at,
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'cache_key' => $key ),
                array( '%s', '%s', '%s' ),
                array( '%s' )
            );
        } else {
            // 插入新缓存
            $result = $wpdb->insert(
                $this->cache_table,
                array(
                    'cache_key' => $key,
                    'cache_value' => maybe_serialize( $value ),
                    'expires_at' => $expires_at,
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );
        }

        if ( $result ) {
            $this->logger->debug( "缓存设置成功: {$key}" );
            return true;
        }

        return false;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键
     * @return bool 是否成功
     */
    public function delete_cache( $key ) {
        global $wpdb;

        $key = sanitize_text_field( $key );

        $result = $wpdb->delete(
            $this->cache_table,
            array( 'cache_key' => $key ),
            array( '%s' )
        );

        if ( $result ) {
            $this->logger->debug( "缓存删除成功: {$key}" );
            return true;
        }

        return false;
    }

    /**
     * 清空所有缓存
     *
     * @return bool 是否成功
     */
    public function clear_all_cache() {
        global $wpdb;

        $result = $wpdb->query( "TRUNCATE TABLE {$this->cache_table}" );

        if ( $result !== false ) {
            $this->logger->info( '清空所有缓存成功' );
            return true;
        }

        return false;
    }

    /**
     * 清理过期缓存
     *
     * @return int 清理的条数
     */
    public function cleanup_expired_cache() {
        global $wpdb;

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->cache_table} WHERE expires_at < %s",
                current_time( 'mysql' )
            )
        );

        if ( $deleted ) {
            $this->logger->info( "清理过期缓存完成: {$deleted} 条" );
        }

        return $deleted;
    }

    /**
     * 生成内容缓存键
     *
     * @param string $prompt 提示词
     * @param string $ai_model AI模型
     * @param string $platform 平台
     * @return string 缓存键
     */
    public function generate_content_cache_key( $prompt, $ai_model, $platform ) {
        return 'aiscg_content_' . md5( $prompt . $ai_model . $platform );
    }

    /**
     * 带缓存的内容生成
     *
     * @param string $prompt 提示词
     * @param string $ai_model AI模型
     * @param string $platform 平台
     * @param callable $generator 生成函数
     * @param int $cache_ttl 缓存时间
     * @return mixed 生成结果
     */
    public function get_or_generate_content( $prompt, $ai_model, $platform, $generator, $cache_ttl = 3600 ) {
        $cache_enabled = get_option( 'aiscg_cache_enabled', true );

        if ( $cache_enabled ) {
            $cache_key = $this->generate_content_cache_key( $prompt, $ai_model, $platform );
            $cached = $this->get_cache( $cache_key );

            if ( $cached !== false ) {
                $this->logger->info( '使用缓存内容', array(
                    'cache_key' => $cache_key,
                ) );
                return $cached;
            }
        }

        // 检查速率限制
        $rate_limits = get_option( 'aiscg_rate_limits', array() );
        $service_limit = isset( $rate_limits[ $ai_model ] ) ? $rate_limits[ $ai_model ] : array(
            'limit' => 60,
            'window' => 60,
        );

        if ( ! $this->check_rate_limit( $ai_model, $service_limit['limit'], $service_limit['window'] ) ) {
            throw new Exception( 'API调用频率超限，请稍后再试' );
        }

        // 生成内容
        $start_time = microtime( true );

        try {
            $result = call_user_func( $generator );

            $duration = microtime( true ) - $start_time;

            // 记录API调用
            $this->record_api_call( $ai_model, array(
                'platform' => $platform,
                'duration' => $duration,
                'success' => true,
            ) );

            $this->logger->log_api_call( $ai_model, $ai_model, true, $duration );

            // 缓存结果
            if ( $cache_enabled && $result ) {
                $cache_key = $this->generate_content_cache_key( $prompt, $ai_model, $platform );
                $this->set_cache( $cache_key, $result, $cache_ttl );
            }

            return $result;

        } catch ( Exception $e ) {
            $duration = microtime( true ) - $start_time;

            $this->record_api_call( $ai_model, array(
                'platform' => $platform,
                'duration' => $duration,
                'success' => false,
                'error' => $e->getMessage(),
            ) );

            $this->logger->log_api_call( $ai_model, $ai_model, false, $duration, array(
                'error' => $e->getMessage(),
            ) );

            throw $e;
        }
    }

    /**
     * 获取API调用统计
     *
     * @param array $args 查询参数
     * @return array 统计数据
     */
    public function get_api_statistics( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'start_date' => date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) ),
            'end_date' => current_time( 'mysql' ),
            'service' => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );
        $where_values = array();

        $where[] = 'created_at BETWEEN %s AND %s';
        $where_values[] = $args['start_date'];
        $where_values[] = $args['end_date'];

        if ( ! empty( $args['service'] ) ) {
            $where[] = 'service = %s';
            $where_values[] = $args['service'];
        }

        $where_clause = implode( ' AND ', $where );

        // 总调用次数
        $total_calls = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->rate_limit_table} WHERE {$where_clause}",
                $where_values
            )
        );

        // 按服务统计
        $by_service = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT service, COUNT(*) as count
                FROM {$this->rate_limit_table}
                WHERE {$where_clause}
                GROUP BY service
                ORDER BY count DESC",
                $where_values
            ),
            ARRAY_A
        );

        // 按日期统计
        $by_date = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                FROM {$this->rate_limit_table}
                WHERE {$where_clause}
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 30",
                $where_values
            ),
            ARRAY_A
        );

        // 按小时统计（今日）
        $by_hour = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT HOUR(created_at) as hour, COUNT(*) as count
                FROM {$this->rate_limit_table}
                WHERE DATE(created_at) = %s
                GROUP BY HOUR(created_at)
                ORDER BY hour",
                current_time( 'Y-m-d' )
            ),
            ARRAY_A
        );

        return array(
            'total_calls' => intval( $total_calls ),
            'by_service' => $by_service,
            'by_date' => $by_date,
            'by_hour' => $by_hour,
        );
    }

    /**
     * 获取缓存统计
     *
     * @return array 统计数据
     */
    public function get_cache_statistics() {
        global $wpdb;

        // 总缓存数
        $total_cache = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->cache_table}" );

        // 过期缓存数
        $expired_cache = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->cache_table} WHERE expires_at < %s",
                current_time( 'mysql' )
            )
        );

        // 有效缓存数
        $valid_cache = $total_cache - $expired_cache;

        // 缓存大小估算
        $cache_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(cache_value)) FROM {$this->cache_table}"
        );

        return array(
            'total_cache' => intval( $total_cache ),
            'valid_cache' => intval( $valid_cache ),
            'expired_cache' => intval( $expired_cache ),
            'cache_size' => intval( $cache_size ),
            'cache_size_mb' => round( $cache_size / 1024 / 1024, 2 ),
        );
    }

    /**
     * 清理速率限制记录
     *
     * @param int $days 保留天数
     * @return int 清理的条数
     */
    public function cleanup_rate_limit_records( $days = 7 ) {
        global $wpdb;

        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->rate_limit_table} WHERE created_at < %s",
                $cutoff_date
            )
        );

        if ( $deleted ) {
            $this->logger->info( "清理速率限制记录完成: {$deleted} 条" );
        }

        return $deleted;
    }

    /**
     * 预热缓存（预生成常用内容）
     *
     * @param array $topics 主题列表
     * @param array $options 选项
     * @return array 预热结果
     */
    public function warmup_cache( $topics, $options = array() ) {
        $defaults = array(
            'ai_model' => 'openai',
            'platform' => 'xiaohongshu',
            'cache_ttl' => 86400, // 24小时
        );

        $options = wp_parse_args( $options, $defaults );

        $results = array(
            'success' => 0,
            'failed' => 0,
            'cached' => 0,
        );

        foreach ( $topics as $topic ) {
            $cache_key = $this->generate_content_cache_key( $topic, $options['ai_model'], $options['platform'] );

            // 检查是否已缓存
            if ( $this->get_cache( $cache_key ) !== false ) {
                $results['cached']++;
                continue;
            }

            try {
                // 这里需要调用实际的内容生成函数
                // 暂时跳过，实际使用时需要集成
                $results['success']++;

            } catch ( Exception $e ) {
                $results['failed']++;
                $this->logger->error( '缓存预热失败', array(
                    'topic' => $topic,
                    'error' => $e->getMessage(),
                ) );
            }

            // 避免请求过快
            sleep( 1 );
        }

        $this->logger->info( '缓存预热完成', $results );

        return $results;
    }
}
