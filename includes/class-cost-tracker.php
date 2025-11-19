<?php
/**
 * Cost Tracker Class
 *
 * API成本追踪和预算管理系统
 *
 * @package AI_Social_Content_Generator
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_Cost_Tracker Class
 */
class AISCG_Cost_Tracker {

	/**
	 * AI服务价格表（每1000 tokens的价格，美元）
	 *
	 * @var array
	 */
	private $pricing = array(
		'gpt-4' => array(
			'input' => 0.03,
			'output' => 0.06,
		),
		'gpt-4-turbo' => array(
			'input' => 0.01,
			'output' => 0.03,
		),
		'gpt-3.5-turbo' => array(
			'input' => 0.0005,
			'output' => 0.0015,
		),
		'gemini-pro' => array(
			'input' => 0.00025,
			'output' => 0.0005,
		),
		'gemini-pro-vision' => array(
			'input' => 0.00025,
			'output' => 0.0005,
		),
		'deepseek-chat' => array(
			'input' => 0.0003,
			'output' => 0.0006,
		),
		'deepseek-coder' => array(
			'input' => 0.0003,
			'output' => 0.0006,
		),
		'claude-3-opus' => array(
			'input' => 0.015,
			'output' => 0.075,
		),
		'claude-3-sonnet' => array(
			'input' => 0.003,
			'output' => 0.015,
		),
		'claude-3-haiku' => array(
			'input' => 0.00025,
			'output' => 0.00125,
		),
		'qwen-max' => array(
			'input' => 0.02,
			'output' => 0.06,
		),
		'qwen-plus' => array(
			'input' => 0.004,
			'output' => 0.012,
		),
		'qwen-turbo' => array(
			'input' => 0.0008,
			'output' => 0.002,
		),
	);

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
		$this->logger = new AISCG_Logger();
	}

	/**
	 * 记录API调用成本
	 *
	 * @param string $model AI模型
	 * @param int    $input_tokens 输入tokens
	 * @param int    $output_tokens 输出tokens
	 * @param array  $metadata 元数据
	 * @return array 成本记录
	 */
	public function track_api_call( $model, $input_tokens, $output_tokens, $metadata = array() ) {
		// 计算成本
		$cost = $this->calculate_cost( $model, $input_tokens, $output_tokens );

		// 构建记录
		$record = array(
			'model' => $model,
			'input_tokens' => $input_tokens,
			'output_tokens' => $output_tokens,
			'total_tokens' => $input_tokens + $output_tokens,
			'cost_usd' => $cost,
			'user_id' => get_current_user_id(),
			'metadata' => wp_json_encode( $metadata ),
			'created_at' => current_time( 'mysql' ),
		);

		// 保存到数据库
		$this->save_cost_record( $record );

		// 检查预算限制
		$this->check_budget_limits();

		$this->logger->info( "API成本记录: {$model}", array(
			'tokens' => $input_tokens + $output_tokens,
			'cost' => '$' . number_format( $cost, 4 ),
		) );

		return $record;
	}

	/**
	 * 计算成本
	 *
	 * @param string $model 模型名称
	 * @param int    $input_tokens 输入tokens
	 * @param int    $output_tokens 输出tokens
	 * @return float 成本（美元）
	 */
	public function calculate_cost( $model, $input_tokens, $output_tokens ) {
		// 查找模型价格
		$price = null;
		foreach ( $this->pricing as $model_key => $model_price ) {
			if ( stripos( $model, $model_key ) !== false ) {
				$price = $model_price;
				break;
			}
		}

		if ( ! $price ) {
			// 如果找不到价格，使用GPT-3.5的价格作为默认
			$price = $this->pricing['gpt-3.5-turbo'];
			$this->logger->warning( "未找到模型 {$model} 的价格，使用默认价格" );
		}

		// 计算成本
		$input_cost = ( $input_tokens / 1000 ) * $price['input'];
		$output_cost = ( $output_tokens / 1000 ) * $price['output'];

		return round( $input_cost + $output_cost, 6 );
	}

	/**
	 * 保存成本记录
	 *
	 * @param array $record 记录数据
	 * @return bool
	 */
	private function save_cost_record( $record ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiscg_settings';
		$key = 'cost_records';

		// 获取现有记录
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		) );

		$records = array();
		if ( $existing ) {
			$records = json_decode( $existing, true );
			if ( ! is_array( $records ) ) {
				$records = array();
			}
		}

		// 添加新记录
		$records[] = $record;

		// 只保留最近10000条记录
		if ( count( $records ) > 10000 ) {
			$records = array_slice( $records, -10000 );
		}

		// 保存
		$value = wp_json_encode( $records );

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( $exists ) {
			return $wpdb->update(
				$table,
				array( 'setting_value' => $value ),
				array( 'setting_key' => $key ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			return $wpdb->insert(
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
	 * 获取成本统计
	 *
	 * @param array $args 参数
	 * @return array 统计数据
	 */
	public function get_cost_statistics( $args = array() ) {
		$defaults = array(
			'start_date' => date( 'Y-m-01' ), // 本月第一天
			'end_date' => date( 'Y-m-d' ), // 今天
			'model' => '', // 空表示所有模型
			'user_id' => 0, // 0表示所有用户
			'group_by' => 'day', // day, week, month, model
		);

		$args = wp_parse_args( $args, $defaults );

		// 获取所有记录
		$records = $this->get_cost_records( $args['start_date'], $args['end_date'] );

		// 过滤记录
		if ( ! empty( $args['model'] ) ) {
			$records = array_filter( $records, function( $r ) use ( $args ) {
				return stripos( $r['model'], $args['model'] ) !== false;
			});
		}

		if ( ! empty( $args['user_id'] ) ) {
			$records = array_filter( $records, function( $r ) use ( $args ) {
				return $r['user_id'] == $args['user_id'];
			});
		}

		// 计算总计
		$total_cost = 0;
		$total_tokens = 0;
		$total_calls = count( $records );

		foreach ( $records as $record ) {
			$total_cost += $record['cost_usd'];
			$total_tokens += $record['total_tokens'];
		}

		// 按模型统计
		$by_model = array();
		foreach ( $records as $record ) {
			$model = $record['model'];
			if ( ! isset( $by_model[ $model ] ) ) {
				$by_model[ $model ] = array(
					'calls' => 0,
					'tokens' => 0,
					'cost' => 0,
				);
			}

			$by_model[ $model ]['calls']++;
			$by_model[ $model ]['tokens'] += $record['total_tokens'];
			$by_model[ $model ]['cost'] += $record['cost_usd'];
		}

		// 排序
		uasort( $by_model, function( $a, $b ) {
			return $b['cost'] <=> $a['cost'];
		});

		// 按时间统计
		$by_time = $this->group_by_time( $records, $args['group_by'] );

		// 按用户统计
		$by_user = array();
		foreach ( $records as $record ) {
			$user_id = $record['user_id'];
			if ( ! isset( $by_user[ $user_id ] ) ) {
				$user = get_userdata( $user_id );
				$by_user[ $user_id ] = array(
					'user_id' => $user_id,
					'user_name' => $user ? $user->display_name : 'Unknown',
					'calls' => 0,
					'tokens' => 0,
					'cost' => 0,
				);
			}

			$by_user[ $user_id ]['calls']++;
			$by_user[ $user_id ]['tokens'] += $record['total_tokens'];
			$by_user[ $user_id ]['cost'] += $record['cost_usd'];
		}

		// 排序
		uasort( $by_user, function( $a, $b ) {
			return $b['cost'] <=> $a['cost'];
		});

		return array(
			'success' => true,
			'period' => array(
				'start_date' => $args['start_date'],
				'end_date' => $args['end_date'],
				'days' => ( strtotime( $args['end_date'] ) - strtotime( $args['start_date'] ) ) / 86400 + 1,
			),
			'summary' => array(
				'total_cost' => round( $total_cost, 4 ),
				'total_cost_formatted' => '$' . number_format( $total_cost, 4 ),
				'total_tokens' => $total_tokens,
				'total_calls' => $total_calls,
				'avg_cost_per_call' => $total_calls > 0 ? round( $total_cost / $total_calls, 4 ) : 0,
				'avg_tokens_per_call' => $total_calls > 0 ? round( $total_tokens / $total_calls ) : 0,
			),
			'by_model' => $by_model,
			'by_time' => $by_time,
			'by_user' => array_values( $by_user ),
			'budget_status' => $this->get_budget_status(),
		);
	}

	/**
	 * 获取成本记录
	 *
	 * @param string $start_date 开始日期
	 * @param string $end_date 结束日期
	 * @return array 记录数组
	 */
	private function get_cost_records( $start_date, $end_date ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiscg_settings';
		$key = 'cost_records';

		$value = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( ! $value ) {
			return array();
		}

		$all_records = json_decode( $value, true );
		if ( ! is_array( $all_records ) ) {
			return array();
		}

		// 过滤日期范围
		$filtered = array_filter( $all_records, function( $record ) use ( $start_date, $end_date ) {
			$record_date = date( 'Y-m-d', strtotime( $record['created_at'] ) );
			return $record_date >= $start_date && $record_date <= $end_date;
		});

		return array_values( $filtered );
	}

	/**
	 * 按时间分组
	 *
	 * @param array  $records 记录数组
	 * @param string $group_by 分组方式
	 * @return array 分组结果
	 */
	private function group_by_time( $records, $group_by ) {
		$grouped = array();

		foreach ( $records as $record ) {
			$timestamp = strtotime( $record['created_at'] );

			switch ( $group_by ) {
				case 'hour':
					$key = date( 'Y-m-d H:00', $timestamp );
					break;
				case 'day':
					$key = date( 'Y-m-d', $timestamp );
					break;
				case 'week':
					$key = date( 'Y-W', $timestamp );
					break;
				case 'month':
					$key = date( 'Y-m', $timestamp );
					break;
				default:
					$key = date( 'Y-m-d', $timestamp );
			}

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array(
					'period' => $key,
					'calls' => 0,
					'tokens' => 0,
					'cost' => 0,
				);
			}

			$grouped[ $key ]['calls']++;
			$grouped[ $key ]['tokens'] += $record['total_tokens'];
			$grouped[ $key ]['cost'] += $record['cost_usd'];
		}

		// 按时间排序
		ksort( $grouped );

		return array_values( $grouped );
	}

	/**
	 * 设置预算限制
	 *
	 * @param array $budget 预算配置
	 * @return bool
	 */
	public function set_budget( $budget ) {
		$defaults = array(
			'daily_limit' => 0, // 0表示无限制
			'monthly_limit' => 0,
			'alert_threshold' => 80, // 达到预算的百分比时发送警告
			'notify_email' => get_option( 'admin_email' ),
		);

		$budget = wp_parse_args( $budget, $defaults );

		// 保存预算设置
		update_option( 'aiscg_budget_settings', $budget );

		$this->logger->info( '预算设置已更新', $budget );

		return true;
	}

	/**
	 * 获取预算设置
	 *
	 * @return array 预算设置
	 */
	public function get_budget() {
		return get_option( 'aiscg_budget_settings', array(
			'daily_limit' => 0,
			'monthly_limit' => 0,
			'alert_threshold' => 80,
			'notify_email' => get_option( 'admin_email' ),
		) );
	}

	/**
	 * 获取预算状态
	 *
	 * @return array 预算状态
	 */
	public function get_budget_status() {
		$budget = $this->get_budget();

		// 获取今日成本
		$today_stats = $this->get_cost_statistics( array(
			'start_date' => date( 'Y-m-d' ),
			'end_date' => date( 'Y-m-d' ),
		) );

		// 获取本月成本
		$month_stats = $this->get_cost_statistics( array(
			'start_date' => date( 'Y-m-01' ),
			'end_date' => date( 'Y-m-d' ),
		) );

		$daily_used = $today_stats['summary']['total_cost'];
		$monthly_used = $month_stats['summary']['total_cost'];

		$status = array(
			'daily' => array(
				'limit' => $budget['daily_limit'],
				'used' => $daily_used,
				'remaining' => max( 0, $budget['daily_limit'] - $daily_used ),
				'percentage' => $budget['daily_limit'] > 0 ? round( ( $daily_used / $budget['daily_limit'] ) * 100, 1 ) : 0,
				'exceeded' => $budget['daily_limit'] > 0 && $daily_used > $budget['daily_limit'],
			),
			'monthly' => array(
				'limit' => $budget['monthly_limit'],
				'used' => $monthly_used,
				'remaining' => max( 0, $budget['monthly_limit'] - $monthly_used ),
				'percentage' => $budget['monthly_limit'] > 0 ? round( ( $monthly_used / $budget['monthly_limit'] ) * 100, 1 ) : 0,
				'exceeded' => $budget['monthly_limit'] > 0 && $monthly_used > $budget['monthly_limit'],
			),
			'alert_threshold' => $budget['alert_threshold'],
		);

		return $status;
	}

	/**
	 * 检查预算限制
	 *
	 * @return bool
	 */
	private function check_budget_limits() {
		$status = $this->get_budget_status();
		$budget = $this->get_budget();

		// 检查日预算
		if ( $status['daily']['percentage'] >= $budget['alert_threshold'] ) {
			$this->send_budget_alert( 'daily', $status['daily'] );
		}

		// 检查月预算
		if ( $status['monthly']['percentage'] >= $budget['alert_threshold'] ) {
			$this->send_budget_alert( 'monthly', $status['monthly'] );
		}

		// 如果超过预算，记录警告
		if ( $status['daily']['exceeded'] || $status['monthly']['exceeded'] ) {
			$this->logger->warning( 'API预算已超限', $status );
			return false;
		}

		return true;
	}

	/**
	 * 发送预算警告
	 *
	 * @param string $type 类型 (daily/monthly)
	 * @param array  $data 数据
	 */
	private function send_budget_alert( $type, $data ) {
		// 检查是否已经发送过警告（避免重复发送）
		$alert_key = "budget_alert_{$type}_" . date( 'Y-m-d' );
		if ( get_transient( $alert_key ) ) {
			return;
		}

		$budget = $this->get_budget();
		$type_name = $type === 'daily' ? '日' : '月';

		$subject = "[AI Content Generator] {$type_name}预算警告";
		$message = "您的API {$type_name}预算已使用 {$data['percentage']}%\n\n";
		$message .= "已使用: \${$data['used']}\n";
		$message .= "预算: \${$data['limit']}\n";
		$message .= "剩余: \${$data['remaining']}\n\n";

		if ( $data['exceeded'] ) {
			$message .= "⚠️ 警告：已超过预算限制！\n";
		} else {
			$message .= "提醒：即将达到预算限制。\n";
		}

		// 发送邮件
		wp_mail( $budget['notify_email'], $subject, $message );

		// 设置transient，24小时内不再发送
		set_transient( $alert_key, true, 86400 );

		$this->logger->warning( "{$type_name}预算警告已发送", array(
			'percentage' => $data['percentage'],
			'used' => $data['used'],
		) );
	}

	/**
	 * 估算成本
	 *
	 * @param string $model 模型
	 * @param string $content 内容
	 * @return array 估算结果
	 */
	public function estimate_cost( $model, $content ) {
		// 估算token数量（简单估算：中文约1.5字符/token，英文约4字符/token）
		$char_count = mb_strlen( $content );

		// 检测语言
		$is_chinese = preg_match( '/[\x{4e00}-\x{9fa5}]/u', $content );

		$estimated_tokens = $is_chinese ?
			ceil( $char_count / 1.5 ) :
			ceil( $char_count / 4 );

		// 估算输出tokens（通常是输入的1.5-2倍）
		$output_tokens = ceil( $estimated_tokens * 1.7 );

		// 计算成本
		$cost = $this->calculate_cost( $model, $estimated_tokens, $output_tokens );

		return array(
			'model' => $model,
			'content_length' => $char_count,
			'estimated_input_tokens' => $estimated_tokens,
			'estimated_output_tokens' => $output_tokens,
			'estimated_total_tokens' => $estimated_tokens + $output_tokens,
			'estimated_cost' => round( $cost, 4 ),
			'estimated_cost_formatted' => '$' . number_format( $cost, 4 ),
		);
	}

	/**
	 * 导出成本报告
	 *
	 * @param array  $args 参数
	 * @param string $format 格式 (csv, json)
	 * @return string 导出内容
	 */
	public function export_cost_report( $args = array(), $format = 'csv' ) {
		$stats = $this->get_cost_statistics( $args );

		if ( $format === 'json' ) {
			return wp_json_encode( $stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}

		// CSV格式
		$output = "AI Content Generator - 成本报告\n";
		$output .= "生成时间: " . current_time( 'Y-m-d H:i:s' ) . "\n";
		$output .= "报告期间: {$stats['period']['start_date']} 至 {$stats['period']['end_date']}\n\n";

		$output .= "总体统计\n";
		$output .= "总成本,总Tokens,总调用次数,平均每次成本,平均每次Tokens\n";
		$output .= "{$stats['summary']['total_cost']},{$stats['summary']['total_tokens']},{$stats['summary']['total_calls']},{$stats['summary']['avg_cost_per_call']},{$stats['summary']['avg_tokens_per_call']}\n\n";

		$output .= "按模型统计\n";
		$output .= "模型,调用次数,总Tokens,总成本(USD)\n";
		foreach ( $stats['by_model'] as $model => $data ) {
			$output .= "{$model},{$data['calls']},{$data['tokens']},{$data['cost']}\n";
		}

		$output .= "\n按时间统计\n";
		$output .= "时间,调用次数,总Tokens,总成本(USD)\n";
		foreach ( $stats['by_time'] as $period ) {
			$output .= "{$period['period']},{$period['calls']},{$period['tokens']},{$period['cost']}\n";
		}

		return $output;
	}

	/**
	 * 清理旧记录
	 *
	 * @param int $days 保留天数
	 * @return int 删除数量
	 */
	public function cleanup_old_records( $days = 90 ) {
		$cutoff_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );

		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_settings';
		$key = 'cost_records';

		$value = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( ! $value ) {
			return 0;
		}

		$all_records = json_decode( $value, true );
		if ( ! is_array( $all_records ) ) {
			return 0;
		}

		$original_count = count( $all_records );

		// 保留指定日期之后的记录
		$kept_records = array_filter( $all_records, function( $record ) use ( $cutoff_date ) {
			$record_date = date( 'Y-m-d', strtotime( $record['created_at'] ) );
			return $record_date >= $cutoff_date;
		});

		$deleted_count = $original_count - count( $kept_records );

		// 更新数据库
		$wpdb->update(
			$table,
			array( 'setting_value' => wp_json_encode( array_values( $kept_records ) ) ),
			array( 'setting_key' => $key ),
			array( '%s' ),
			array( '%s' )
		);

		$this->logger->info( "清理旧成本记录: {$deleted_count} 条" );

		return $deleted_count;
	}
}
