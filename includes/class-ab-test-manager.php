<?php
/**
 * A/B Test Manager Class
 *
 * A/B测试管理系统 - 测试和优化内容策略
 *
 * @package AI_Social_Content_Generator
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_AB_Test_Manager Class
 */
class AISCG_AB_Test_Manager {

	/**
	 * 测试状态
	 *
	 * @var array
	 */
	private $test_statuses = array(
		'draft'    => '草稿',
		'running'  => '进行中',
		'paused'   => '已暂停',
		'completed' => '已完成',
		'archived' => '已归档',
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
	 * 创建A/B测试
	 *
	 * @param array $data 测试数据
	 * @return array 创建结果
	 */
	public function create_test( $data ) {
		$defaults = array(
			'name' => '',
			'description' => '',
			'test_type' => 'content', // content, hashtags, style, time
			'variants' => array(), // A和B两个变体
			'success_metric' => 'quality_score', // quality_score, engagement, etc.
			'duration_days' => 7,
			'auto_declare_winner' => true,
			'confidence_level' => 0.95,
		);

		$data = wp_parse_args( $data, $defaults );

		// 验证
		if ( count( $data['variants'] ) < 2 ) {
			return array(
				'success' => false,
				'error' => 'A/B测试至少需要2个变体',
			);
		}

		// 创建测试
		$test = array(
			'id' => $this->generate_test_id(),
			'name' => sanitize_text_field( $data['name'] ),
			'description' => sanitize_textarea_field( $data['description'] ),
			'test_type' => sanitize_text_field( $data['test_type'] ),
			'variants' => $data['variants'],
			'success_metric' => sanitize_text_field( $data['success_metric'] ),
			'status' => 'draft',
			'start_date' => null,
			'end_date' => null,
			'duration_days' => (int) $data['duration_days'],
			'auto_declare_winner' => (bool) $data['auto_declare_winner'],
			'confidence_level' => (float) $data['confidence_level'],
			'results' => array(),
			'winner' => null,
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		// 保存测试
		$this->save_test( $test );

		$this->logger->info( "创建A/B测试: {$test['name']}", array(
			'test_id' => $test['id'],
			'type' => $test['test_type'],
		) );

		return array(
			'success' => true,
			'test' => $test,
		);
	}

	/**
	 * 开始测试
	 *
	 * @param string $test_id 测试ID
	 * @return array 操作结果
	 */
	public function start_test( $test_id ) {
		$test = $this->get_test( $test_id );

		if ( ! $test ) {
			return array(
				'success' => false,
				'error' => '测试不存在',
			);
		}

		if ( $test['status'] !== 'draft' && $test['status'] !== 'paused' ) {
			return array(
				'success' => false,
				'error' => '只能启动草稿或暂停的测试',
			);
		}

		// 更新状态
		$test['status'] = 'running';
		$test['start_date'] = current_time( 'mysql' );
		$test['end_date'] = date( 'Y-m-d H:i:s', strtotime( "+{$test['duration_days']} days" ) );
		$test['updated_at'] = current_time( 'mysql' );

		// 初始化结果
		foreach ( $test['variants'] as $index => $variant ) {
			$test['results'][ $index ] = array(
				'variant_id' => $variant['id'] ?? "variant_{$index}",
				'variant_name' => $variant['name'] ?? "变体 " . chr( 65 + $index ),
				'impressions' => 0,
				'samples' => array(),
				'metrics' => array(),
			);
		}

		$this->save_test( $test );

		$this->logger->info( "启动A/B测试: {$test['name']}", array(
			'test_id' => $test_id,
			'end_date' => $test['end_date'],
		) );

		return array(
			'success' => true,
			'test' => $test,
		);
	}

	/**
	 * 记录测试数据
	 *
	 * @param string $test_id 测试ID
	 * @param int    $variant_index 变体索引
	 * @param array  $data 数据
	 * @return array 操作结果
	 */
	public function record_test_data( $test_id, $variant_index, $data ) {
		$test = $this->get_test( $test_id );

		if ( ! $test || $test['status'] !== 'running' ) {
			return array(
				'success' => false,
				'error' => '测试不存在或未在运行中',
			);
		}

		// 记录数据
		if ( ! isset( $test['results'][ $variant_index ] ) ) {
			return array(
				'success' => false,
				'error' => '无效的变体索引',
			);
		}

		// 添加样本数据
		$test['results'][ $variant_index ]['samples'][] = array(
			'data' => $data,
			'timestamp' => current_time( 'mysql' ),
		);

		$test['results'][ $variant_index ]['impressions']++;

		// 更新统计指标
		$this->update_variant_metrics( $test, $variant_index );

		$test['updated_at'] = current_time( 'mysql' );

		// 检查是否应该结束测试
		if ( strtotime( $test['end_date'] ) <= time() ) {
			$this->complete_test( $test_id );
		}

		$this->save_test( $test );

		return array(
			'success' => true,
		);
	}

	/**
	 * 更新变体指标
	 *
	 * @param array $test 测试数据
	 * @param int   $variant_index 变体索引
	 */
	private function update_variant_metrics( &$test, $variant_index ) {
		$samples = $test['results'][ $variant_index ]['samples'];

		if ( empty( $samples ) ) {
			return;
		}

		$metric = $test['success_metric'];

		// 计算平均值
		$values = array();
		foreach ( $samples as $sample ) {
			if ( isset( $sample['data'][ $metric ] ) ) {
				$values[] = $sample['data'][ $metric ];
			}
		}

		if ( ! empty( $values ) ) {
			$test['results'][ $variant_index ]['metrics'] = array(
				'mean' => array_sum( $values ) / count( $values ),
				'min' => min( $values ),
				'max' => max( $values ),
				'stddev' => $this->calculate_stddev( $values ),
				'sample_size' => count( $values ),
			);
		}
	}

	/**
	 * 完成测试
	 *
	 * @param string $test_id 测试ID
	 * @return array 操作结果
	 */
	public function complete_test( $test_id ) {
		$test = $this->get_test( $test_id );

		if ( ! $test ) {
			return array(
				'success' => false,
				'error' => '测试不存在',
			);
		}

		$test['status'] = 'completed';
		$test['updated_at'] = current_time( 'mysql' );

		// 分析结果并宣布获胜者
		if ( $test['auto_declare_winner'] ) {
			$analysis = $this->analyze_results( $test );
			$test['winner'] = $analysis['winner'];
			$test['analysis'] = $analysis;
		}

		$this->save_test( $test );

		$this->logger->info( "完成A/B测试: {$test['name']}", array(
			'test_id' => $test_id,
			'winner' => $test['winner'],
		) );

		return array(
			'success' => true,
			'test' => $test,
			'analysis' => $test['analysis'] ?? null,
		);
	}

	/**
	 * 分析测试结果
	 *
	 * @param array $test 测试数据
	 * @return array 分析结果
	 */
	private function analyze_results( $test ) {
		if ( count( $test['results'] ) < 2 ) {
			return array(
				'winner' => null,
				'confidence' => 0,
				'message' => '数据不足以进行分析',
			);
		}

		// 获取两个变体的数据
		$variant_a = $test['results'][0];
		$variant_b = $test['results'][1];

		// 检查样本量
		if ( empty( $variant_a['metrics'] ) || empty( $variant_b['metrics'] ) ) {
			return array(
				'winner' => null,
				'confidence' => 0,
				'message' => '没有足够的数据',
			);
		}

		if ( $variant_a['metrics']['sample_size'] < 5 || $variant_b['metrics']['sample_size'] < 5 ) {
			return array(
				'winner' => null,
				'confidence' => 0,
				'message' => '样本量太小（至少需要5个样本）',
			);
		}

		// 计算t检验
		$t_test_result = $this->perform_t_test(
			$variant_a['metrics'],
			$variant_b['metrics']
		);

		// 确定获胜者
		$winner = null;
		$improvement = 0;

		if ( $t_test_result['significant'] ) {
			if ( $variant_a['metrics']['mean'] > $variant_b['metrics']['mean'] ) {
				$winner = $variant_a['variant_id'];
				$improvement = ( ( $variant_a['metrics']['mean'] - $variant_b['metrics']['mean'] ) / $variant_b['metrics']['mean'] ) * 100;
			} else {
				$winner = $variant_b['variant_id'];
				$improvement = ( ( $variant_b['metrics']['mean'] - $variant_a['metrics']['mean'] ) / $variant_a['metrics']['mean'] ) * 100;
			}
		}

		return array(
			'winner' => $winner,
			'confidence' => $t_test_result['confidence'],
			'p_value' => $t_test_result['p_value'],
			'improvement' => round( $improvement, 2 ),
			'variant_a' => array(
				'id' => $variant_a['variant_id'],
				'name' => $variant_a['variant_name'],
				'mean' => round( $variant_a['metrics']['mean'], 2 ),
				'sample_size' => $variant_a['metrics']['sample_size'],
			),
			'variant_b' => array(
				'id' => $variant_b['variant_id'],
				'name' => $variant_b['variant_name'],
				'mean' => round( $variant_b['metrics']['mean'], 2 ),
				'sample_size' => $variant_b['metrics']['sample_size'],
			),
			'message' => $winner ?
				"变体 {$winner} 获胜，提升 {$improvement}%" :
				'没有统计学上的显著差异',
		);
	}

	/**
	 * 执行t检验
	 *
	 * @param array $metrics_a 变体A的指标
	 * @param array $metrics_b 变体B的指标
	 * @return array t检验结果
	 */
	private function perform_t_test( $metrics_a, $metrics_b ) {
		$mean_a = $metrics_a['mean'];
		$mean_b = $metrics_b['mean'];
		$std_a = $metrics_a['stddev'];
		$std_b = $metrics_b['stddev'];
		$n_a = $metrics_a['sample_size'];
		$n_b = $metrics_b['sample_size'];

		// 计算t统计量
		$pooled_std = sqrt( ( ( $n_a - 1 ) * $std_a * $std_a + ( $n_b - 1 ) * $std_b * $std_b ) / ( $n_a + $n_b - 2 ) );
		$t = abs( $mean_a - $mean_b ) / ( $pooled_std * sqrt( 1 / $n_a + 1 / $n_b ) );

		// 自由度
		$df = $n_a + $n_b - 2;

		// 简化的p值估算（实际应使用t分布表）
		// 这里使用简单的近似
		$p_value = $this->estimate_p_value( $t, $df );

		return array(
			't_statistic' => round( $t, 4 ),
			'degrees_of_freedom' => $df,
			'p_value' => round( $p_value, 4 ),
			'significant' => $p_value < 0.05,
			'confidence' => round( ( 1 - $p_value ) * 100, 1 ),
		);
	}

	/**
	 * 估算p值
	 *
	 * @param float $t t统计量
	 * @param int   $df 自由度
	 * @return float p值
	 */
	private function estimate_p_value( $t, $df ) {
		// 简化的p值估算
		// 实际应用中应使用更精确的方法或库
		if ( $t > 2.576 ) {
			return 0.01;
		} elseif ( $t > 1.96 ) {
			return 0.05;
		} elseif ( $t > 1.645 ) {
			return 0.10;
		} else {
			return 0.20;
		}
	}

	/**
	 * 计算标准差
	 *
	 * @param array $values 数值数组
	 * @return float 标准差
	 */
	private function calculate_stddev( $values ) {
		if ( count( $values ) < 2 ) {
			return 0;
		}

		$mean = array_sum( $values ) / count( $values );
		$variance = array_sum( array_map( function( $v ) use ( $mean ) {
			return pow( $v - $mean, 2 );
		}, $values ) ) / ( count( $values ) - 1 );

		return sqrt( $variance );
	}

	/**
	 * 获取测试
	 *
	 * @param string $test_id 测试ID
	 * @return array|false 测试数据
	 */
	public function get_test( $test_id ) {
		$tests = $this->get_all_tests();

		foreach ( $tests as $test ) {
			if ( $test['id'] === $test_id ) {
				return $test;
			}
		}

		return false;
	}

	/**
	 * 获取所有测试
	 *
	 * @param array $args 参数
	 * @return array 测试列表
	 */
	public function get_all_tests( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aiscg_settings';
		$key = 'ab_tests';

		$value = $wpdb->get_var( $wpdb->prepare(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		) );

		if ( ! $value ) {
			return array();
		}

		$tests = json_decode( $value, true );
		if ( ! is_array( $tests ) ) {
			return array();
		}

		// 过滤
		if ( ! empty( $args['status'] ) ) {
			$tests = array_filter( $tests, function( $t ) use ( $args ) {
				return $t['status'] === $args['status'];
			});
		}

		return array_values( $tests );
	}

	/**
	 * 保存测试
	 *
	 * @param array $test 测试数据
	 * @return bool
	 */
	private function save_test( $test ) {
		$tests = $this->get_all_tests();

		// 查找并更新
		$found = false;
		foreach ( $tests as $index => $existing_test ) {
			if ( $existing_test['id'] === $test['id'] ) {
				$tests[ $index ] = $test;
				$found = true;
				break;
			}
		}

		// 如果不存在，添加
		if ( ! $found ) {
			$tests[] = $test;
		}

		// 保存到数据库
		global $wpdb;

		$table = $wpdb->prefix . 'aiscg_settings';
		$key = 'ab_tests';
		$value = wp_json_encode( $tests );

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
	 * 生成测试ID
	 *
	 * @return string 测试ID
	 */
	private function generate_test_id() {
		return 'test_' . uniqid() . '_' . time();
	}

	/**
	 * 删除测试
	 *
	 * @param string $test_id 测试ID
	 * @return bool
	 */
	public function delete_test( $test_id ) {
		$tests = $this->get_all_tests();

		$tests = array_filter( $tests, function( $t ) use ( $test_id ) {
			return $t['id'] !== $test_id;
		});

		// 保存
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_settings';
		$key = 'ab_tests';

		$wpdb->update(
			$table,
			array( 'setting_value' => wp_json_encode( array_values( $tests ) ) ),
			array( 'setting_key' => $key ),
			array( '%s' ),
			array( '%s' )
		);

		$this->logger->info( "删除A/B测试: {$test_id}" );

		return true;
	}

	/**
	 * 获取测试报告
	 *
	 * @param string $test_id 测试ID
	 * @return array 报告数据
	 */
	public function get_test_report( $test_id ) {
		$test = $this->get_test( $test_id );

		if ( ! $test ) {
			return array(
				'success' => false,
				'error' => '测试不存在',
			);
		}

		$report = array(
			'success' => true,
			'test_info' => array(
				'id' => $test['id'],
				'name' => $test['name'],
				'type' => $test['test_type'],
				'status' => $test['status'],
				'start_date' => $test['start_date'],
				'end_date' => $test['end_date'],
			),
			'results' => $test['results'],
			'winner' => $test['winner'],
			'analysis' => $test['analysis'] ?? null,
		);

		// 添加可视化数据
		if ( $test['status'] === 'completed' && ! empty( $test['results'] ) ) {
			$report['chart_data'] = $this->prepare_chart_data( $test );
		}

		return $report;
	}

	/**
	 * 准备图表数据
	 *
	 * @param array $test 测试数据
	 * @return array 图表数据
	 */
	private function prepare_chart_data( $test ) {
		$chart_data = array(
			'labels' => array(),
			'datasets' => array(),
		);

		foreach ( $test['results'] as $result ) {
			$chart_data['labels'][] = $result['variant_name'];

			if ( ! empty( $result['metrics'] ) ) {
				$chart_data['datasets'][] = array(
					'label' => $result['variant_name'],
					'value' => round( $result['metrics']['mean'], 2 ),
					'sample_size' => $result['metrics']['sample_size'],
				);
			}
		}

		return $chart_data;
	}
}
