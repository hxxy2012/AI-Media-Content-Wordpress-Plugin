<?php
/**
 * Performance Monitor Class
 *
 * 性能监控和优化建议
 *
 * @package AI_Social_Content_Generator
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_Performance_Monitor Class
 */
class AISCG_Performance_Monitor {

	/**
	 * 性能指标
	 *
	 * @var array
	 */
	private $metrics = array();

	/**
	 * 开始时间
	 *
	 * @var float
	 */
	private $start_time = 0;

	/**
	 * 开始内存
	 *
	 * @var int
	 */
	private $start_memory = 0;

	/**
	 * 日志对象
	 *
	 * @var AISCG_Logger
	 */
	private $logger;

	/**
	 * 性能阈值
	 *
	 * @var array
	 */
	private $thresholds = array(
		'query_time' => 1.0,       // 数据库查询时间 (秒)
		'api_time' => 5.0,          // API调用时间 (秒)
		'memory_limit' => 67108864, // 内存限制 (64MB)
		'cpu_usage' => 80,          // CPU使用率 (%)
	);

	/**
	 * 构造函数
	 */
	public function __construct() {
		$this->logger = new AISCG_Logger();
		$this->start_time = microtime( true );
		$this->start_memory = memory_get_usage();
	}

	/**
	 * 开始性能跟踪
	 *
	 * @param string $label 标签
	 */
	public function start_tracking( $label ) {
		$this->metrics[ $label ] = array(
			'start_time' => microtime( true ),
			'start_memory' => memory_get_usage(),
		);
	}

	/**
	 * 结束性能跟踪
	 *
	 * @param string $label 标签
	 * @return array|false 性能数据
	 */
	public function end_tracking( $label ) {
		if ( ! isset( $this->metrics[ $label ] ) ) {
			return false;
		}

		$end_time = microtime( true );
		$end_memory = memory_get_usage();

		$this->metrics[ $label ]['end_time'] = $end_time;
		$this->metrics[ $label ]['end_memory'] = $end_memory;
		$this->metrics[ $label ]['duration'] = round( $end_time - $this->metrics[ $label ]['start_time'], 4 );
		$this->metrics[ $label ]['memory_used'] = $end_memory - $this->metrics[ $label ]['start_memory'];
		$this->metrics[ $label ]['memory_peak'] = memory_get_peak_usage();

		// 检查是否超过阈值
		$this->check_thresholds( $label );

		return $this->metrics[ $label ];
	}

	/**
	 * 记录数据库查询
	 *
	 * @param string $query SQL查询
	 * @param float  $duration 执行时间
	 */
	public function log_query( $query, $duration ) {
		$metric = array(
			'type' => 'database_query',
			'query' => $query,
			'duration' => $duration,
			'timestamp' => current_time( 'mysql' ),
		);

		// 检查慢查询
		if ( $duration > $this->thresholds['query_time'] ) {
			$this->logger->warning( '检测到慢查询', $metric );
		}

		// 保存到性能日志
		$this->save_performance_metric( $metric );
	}

	/**
	 * 记录API调用
	 *
	 * @param string $service 服务名称
	 * @param string $endpoint 端点
	 * @param float  $duration 执行时间
	 * @param bool   $success 是否成功
	 */
	public function log_api_call( $service, $endpoint, $duration, $success = true ) {
		$metric = array(
			'type' => 'api_call',
			'service' => $service,
			'endpoint' => $endpoint,
			'duration' => $duration,
			'success' => $success,
			'timestamp' => current_time( 'mysql' ),
		);

		// 检查慢API调用
		if ( $duration > $this->thresholds['api_time'] ) {
			$this->logger->warning( '检测到慢API调用', $metric );
		}

		// 保存到性能日志
		$this->save_performance_metric( $metric );
	}

	/**
	 * 获取系统性能
	 *
	 * @return array 系统性能数据
	 */
	public function get_system_performance() {
		$performance = array(
			'memory' => $this->get_memory_stats(),
			'database' => $this->get_database_stats(),
			'cache' => $this->get_cache_stats(),
			'php' => $this->get_php_stats(),
			'wordpress' => $this->get_wordpress_stats(),
		);

		return $performance;
	}

	/**
	 * 获取内存统计
	 *
	 * @return array 内存统计
	 */
	private function get_memory_stats() {
		$memory_limit = ini_get( 'memory_limit' );
		$memory_usage = memory_get_usage();
		$memory_peak = memory_get_peak_usage();

		// 转换memory_limit为字节
		$limit_bytes = $this->convert_to_bytes( $memory_limit );

		return array(
			'current' => $memory_usage,
			'current_formatted' => size_format( $memory_usage ),
			'peak' => $memory_peak,
			'peak_formatted' => size_format( $memory_peak ),
			'limit' => $limit_bytes,
			'limit_formatted' => $memory_limit,
			'usage_percentage' => $limit_bytes > 0 ? round( ( $memory_usage / $limit_bytes ) * 100, 2 ) : 0,
			'available' => $limit_bytes - $memory_usage,
			'available_formatted' => size_format( $limit_bytes - $memory_usage ),
		);
	}

	/**
	 * 获取数据库统计
	 *
	 * @return array 数据库统计
	 */
	private function get_database_stats() {
		global $wpdb;

		// 获取查询次数
		$num_queries = $wpdb->num_queries;

		// 获取插件表的大小
		$tables = array(
			$wpdb->prefix . 'aiscg_posts',
			$wpdb->prefix . 'aiscg_images',
			$wpdb->prefix . 'aiscg_settings',
			$wpdb->prefix . 'aiscg_logs',
			$wpdb->prefix . 'aiscg_templates',
			$wpdb->prefix . 'aiscg_versions',
			$wpdb->prefix . 'aiscg_rate_limits',
			$wpdb->prefix . 'aiscg_cache',
			$wpdb->prefix . 'aiscg_notifications',
			$wpdb->prefix . 'aiscg_media_library',
			$wpdb->prefix . 'aiscg_publishing_plans',
		);

		$total_size = 0;
		$table_sizes = array();

		foreach ( $tables as $table ) {
			$size = $wpdb->get_var( "SELECT (data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '{$table}'" );
			if ( $size ) {
				$total_size += $size;
				$table_sizes[ $table ] = array(
					'size' => (int) $size,
					'size_formatted' => size_format( $size ),
				);
			}
		}

		return array(
			'num_queries' => $num_queries,
			'total_size' => $total_size,
			'total_size_formatted' => size_format( $total_size ),
			'table_sizes' => $table_sizes,
		);
	}

	/**
	 * 获取缓存统计
	 *
	 * @return array 缓存统计
	 */
	private function get_cache_stats() {
		global $wpdb;

		$cache_table = $wpdb->prefix . 'aiscg_cache';

		// 缓存项数量
		$cache_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$cache_table}" );

		// 过期缓存数量
		$expired_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$cache_table} WHERE expires_at < %s",
			current_time( 'mysql' )
		) );

		// 有效缓存数量
		$valid_count = $cache_count - $expired_count;

		return array(
			'total_items' => (int) $cache_count,
			'valid_items' => (int) $valid_count,
			'expired_items' => (int) $expired_count,
			'hit_rate' => $this->calculate_cache_hit_rate(),
		);
	}

	/**
	 * 获取PHP统计
	 *
	 * @return array PHP统计
	 */
	private function get_php_stats() {
		return array(
			'version' => PHP_VERSION,
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'max_input_vars' => ini_get( 'max_input_vars' ),
			'post_max_size' => ini_get( 'post_max_size' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'extensions' => array(
				'curl' => extension_loaded( 'curl' ),
				'gd' => extension_loaded( 'gd' ),
				'mbstring' => extension_loaded( 'mbstring' ),
				'json' => extension_loaded( 'json' ),
			),
		);
	}

	/**
	 * 获取WordPress统计
	 *
	 * @return array WordPress统计
	 */
	private function get_wordpress_stats() {
		global $wp_version;

		return array(
			'version' => $wp_version,
			'debug_mode' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'multisite' => is_multisite(),
			'permalink_structure' => get_option( 'permalink_structure' ),
			'active_plugins' => count( get_option( 'active_plugins', array() ) ),
			'active_theme' => wp_get_theme()->get( 'Name' ),
		);
	}

	/**
	 * 获取性能分析
	 *
	 * @param array $args 参数
	 * @return array 性能分析结果
	 */
	public function analyze_performance( $args = array() ) {
		$defaults = array(
			'days' => 7,
		);

		$args = wp_parse_args( $args, $defaults );

		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_settings';
		$key = 'performance_metrics';

		// 获取性能指标
		$metrics_json = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( ! $metrics_json ) {
			return array(
				'success' => false,
				'error' => '暂无性能数据',
			);
		}

		$all_metrics = json_decode( $metrics_json, true );
		if ( ! is_array( $all_metrics ) ) {
			$all_metrics = array();
		}

		// 过滤最近N天的数据
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$args['days']} days" ) );
		$recent_metrics = array_filter( $all_metrics, function( $metric ) use ( $cutoff_date ) {
			return isset( $metric['timestamp'] ) && $metric['timestamp'] >= $cutoff_date;
		});

		// 分析数据库查询
		$db_queries = array_filter( $recent_metrics, function( $m ) {
			return isset( $m['type'] ) && $m['type'] === 'database_query';
		});

		$avg_query_time = 0;
		$slow_queries = 0;

		if ( ! empty( $db_queries ) ) {
			$total_time = array_sum( array_column( $db_queries, 'duration' ) );
			$avg_query_time = round( $total_time / count( $db_queries ), 4 );
			$slow_queries = count( array_filter( $db_queries, function( $q ) {
				return $q['duration'] > $this->thresholds['query_time'];
			}));
		}

		// 分析API调用
		$api_calls = array_filter( $recent_metrics, function( $m ) {
			return isset( $m['type'] ) && $m['type'] === 'api_call';
		});

		$avg_api_time = 0;
		$slow_api_calls = 0;
		$failed_api_calls = 0;

		if ( ! empty( $api_calls ) ) {
			$total_time = array_sum( array_column( $api_calls, 'duration' ) );
			$avg_api_time = round( $total_time / count( $api_calls ), 4 );
			$slow_api_calls = count( array_filter( $api_calls, function( $a ) {
				return $a['duration'] > $this->thresholds['api_time'];
			}));
			$failed_api_calls = count( array_filter( $api_calls, function( $a ) {
				return ! $a['success'];
			}));
		}

		// 生成优化建议
		$recommendations = $this->generate_recommendations( array(
			'avg_query_time' => $avg_query_time,
			'slow_queries' => $slow_queries,
			'avg_api_time' => $avg_api_time,
			'slow_api_calls' => $slow_api_calls,
			'failed_api_calls' => $failed_api_calls,
		));

		return array(
			'success' => true,
			'period' => array(
				'days' => $args['days'],
				'from' => $cutoff_date,
				'to' => current_time( 'mysql' ),
			),
			'database' => array(
				'total_queries' => count( $db_queries ),
				'avg_query_time' => $avg_query_time,
				'slow_queries' => $slow_queries,
				'slow_query_percentage' => count( $db_queries ) > 0 ? round( ( $slow_queries / count( $db_queries ) ) * 100, 2 ) : 0,
			),
			'api' => array(
				'total_calls' => count( $api_calls ),
				'avg_call_time' => $avg_api_time,
				'slow_calls' => $slow_api_calls,
				'failed_calls' => $failed_api_calls,
				'success_rate' => count( $api_calls ) > 0 ? round( ( ( count( $api_calls ) - $failed_api_calls ) / count( $api_calls ) ) * 100, 2 ) : 100,
			),
			'recommendations' => $recommendations,
		);
	}

	/**
	 * 生成优化建议
	 *
	 * @param array $stats 统计数据
	 * @return array 优化建议
	 */
	private function generate_recommendations( $stats ) {
		$recommendations = array();

		// 数据库优化建议
		if ( $stats['slow_queries'] > 10 ) {
			$recommendations[] = array(
				'category' => 'database',
				'priority' => 'high',
				'issue' => "检测到 {$stats['slow_queries']} 个慢查询",
				'suggestion' => '考虑添加数据库索引或优化查询语句',
			);
		}

		if ( $stats['avg_query_time'] > 0.5 ) {
			$recommendations[] = array(
				'category' => 'database',
				'priority' => 'medium',
				'issue' => "平均查询时间为 {$stats['avg_query_time']} 秒",
				'suggestion' => '启用查询缓存以提高性能',
			);
		}

		// API优化建议
		if ( $stats['failed_api_calls'] > 5 ) {
			$recommendations[] = array(
				'category' => 'api',
				'priority' => 'high',
				'issue' => "检测到 {$stats['failed_api_calls']} 个失败的API调用",
				'suggestion' => '检查API密钥配置和网络连接',
			);
		}

		if ( $stats['slow_api_calls'] > 10 ) {
			$recommendations[] = array(
				'category' => 'api',
				'priority' => 'medium',
				'issue' => "检测到 {$stats['slow_api_calls']} 个慢速API调用",
				'suggestion' => '考虑增加请求超时时间或使用更快的AI服务',
			);
		}

		// 通用建议
		$memory_stats = $this->get_memory_stats();
		if ( $memory_stats['usage_percentage'] > 80 ) {
			$recommendations[] = array(
				'category' => 'memory',
				'priority' => 'high',
				'issue' => "内存使用率达到 {$memory_stats['usage_percentage']}%",
				'suggestion' => '增加PHP内存限制或优化代码以减少内存使用',
			);
		}

		if ( empty( $recommendations ) ) {
			$recommendations[] = array(
				'category' => 'general',
				'priority' => 'info',
				'issue' => '插件运行良好',
				'suggestion' => '继续保持良好的性能',
			);
		}

		return $recommendations;
	}

	/**
	 * 清理过期缓存
	 *
	 * @return int 清理数量
	 */
	public function cleanup_expired_cache() {
		global $wpdb;

		$cache_table = $wpdb->prefix . 'aiscg_cache';

		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$cache_table} WHERE expires_at < %s",
			current_time( 'mysql' )
		) );

		$this->logger->info( "清理过期缓存: {$deleted} 条" );

		return (int) $deleted;
	}

	/**
	 * 清理旧日志
	 *
	 * @param int $days 保留天数
	 * @return int 清理数量
	 */
	public function cleanup_old_logs( $days = 30 ) {
		global $wpdb;

		$logs_table = $wpdb->prefix . 'aiscg_logs';
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$logs_table} WHERE created_at < %s",
			$cutoff_date
		) );

		$this->logger->info( "清理旧日志: {$deleted} 条" );

		return (int) $deleted;
	}

	/**
	 * 优化数据库表
	 *
	 * @return array 优化结果
	 */
	public function optimize_database_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'aiscg_posts',
			$wpdb->prefix . 'aiscg_images',
			$wpdb->prefix . 'aiscg_settings',
			$wpdb->prefix . 'aiscg_logs',
			$wpdb->prefix . 'aiscg_templates',
			$wpdb->prefix . 'aiscg_versions',
			$wpdb->prefix . 'aiscg_rate_limits',
			$wpdb->prefix . 'aiscg_cache',
			$wpdb->prefix . 'aiscg_notifications',
			$wpdb->prefix . 'aiscg_media_library',
			$wpdb->prefix . 'aiscg_publishing_plans',
		);

		$results = array();

		foreach ( $tables as $table ) {
			$result = $wpdb->query( "OPTIMIZE TABLE {$table}" );
			$results[ $table ] = $result !== false;
		}

		$this->logger->info( '数据库表优化完成', $results );

		return $results;
	}

	/**
	 * 检查性能阈值
	 *
	 * @param string $label 标签
	 */
	private function check_thresholds( $label ) {
		if ( ! isset( $this->metrics[ $label ] ) ) {
			return;
		}

		$metric = $this->metrics[ $label ];

		// 检查执行时间
		if ( isset( $metric['duration'] ) && $metric['duration'] > 5.0 ) {
			$this->logger->warning( "性能警告: {$label} 执行时间过长", array(
				'duration' => $metric['duration'],
				'label' => $label,
			) );
		}

		// 检查内存使用
		if ( isset( $metric['memory_used'] ) && $metric['memory_used'] > $this->thresholds['memory_limit'] ) {
			$this->logger->warning( "性能警告: {$label} 内存使用过高", array(
				'memory_used' => $metric['memory_used'],
				'memory_used_formatted' => size_format( $metric['memory_used'] ),
				'label' => $label,
			) );
		}
	}

	/**
	 * 保存性能指标
	 *
	 * @param array $metric 指标数据
	 */
	private function save_performance_metric( $metric ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiscg_settings';
		$key = 'performance_metrics';

		// 获取现有指标
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		) );

		$metrics = array();
		if ( $existing ) {
			$metrics = json_decode( $existing, true );
			if ( ! is_array( $metrics ) ) {
				$metrics = array();
			}
		}

		// 添加新指标
		$metrics[] = $metric;

		// 只保留最近1000条
		if ( count( $metrics ) > 1000 ) {
			$metrics = array_slice( $metrics, -1000 );
		}

		// 保存
		$value = wp_json_encode( $metrics );

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( $exists ) {
			$wpdb->update(
				$table,
				array( 'setting_value' => $value ),
				array( 'setting_key' => $key ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'setting_key' => $key,
					'setting_value' => $value,
				),
				array( '%s', '%s' )
			);
		}
	}

	/**
	 * 计算缓存命中率
	 *
	 * @return float 命中率百分比
	 */
	private function calculate_cache_hit_rate() {
		global $wpdb;

		$logs_table = $wpdb->prefix . 'aiscg_logs';

		// 最近7天的缓存日志
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		$hits = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$logs_table} WHERE created_at >= %s AND message LIKE %s",
			$cutoff_date,
			'%cache hit%'
		) );

		$misses = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$logs_table} WHERE created_at >= %s AND message LIKE %s",
			$cutoff_date,
			'%cache miss%'
		) );

		$total = $hits + $misses;

		if ( $total === 0 ) {
			return 0;
		}

		return round( ( $hits / $total ) * 100, 2 );
	}

	/**
	 * 转换为字节
	 *
	 * @param string $value 值 (如 128M, 1G)
	 * @return int 字节数
	 */
	private function convert_to_bytes( $value ) {
		$value = trim( $value );
		$last = strtolower( $value[ strlen( $value ) - 1 ] );
		$value = (int) $value;

		switch ( $last ) {
			case 'g':
				$value *= 1024;
				// Fall through.
			case 'm':
				$value *= 1024;
				// Fall through.
			case 'k':
				$value *= 1024;
		}

		return $value;
	}

	/**
	 * 获取性能报告
	 *
	 * @return array 性能报告
	 */
	public function get_performance_report() {
		return array(
			'system' => $this->get_system_performance(),
			'analysis' => $this->analyze_performance( array( 'days' => 7 ) ),
			'metrics' => $this->metrics,
		);
	}
}
