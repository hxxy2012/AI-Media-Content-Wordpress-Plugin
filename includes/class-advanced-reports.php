<?php
/**
 * Advanced Reports Class
 *
 * 高级数据分析和报表系统
 *
 * @package AI_Social_Content_Generator
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_Advanced_Reports Class
 */
class AISCG_Advanced_Reports {

	/**
	 * 报表类型
	 *
	 * @var array
	 */
	private $report_types = array(
		'overview'     => '总览报表',
		'performance'  => '性能报表',
		'trends'       => '趋势报表',
		'comparison'   => '对比报表',
		'quality'      => '质量报表',
		'productivity' => '生产力报表',
		'seo'          => 'SEO报表',
		'multilingual' => '多语言报表',
	);

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
		$this->logger = new AISCG_Logger();
		$this->db = new AISCG_Database();
	}

	/**
	 * 生成报表
	 *
	 * @param string $type 报表类型
	 * @param array  $args 参数
	 * @return array 报表数据
	 */
	public function generate_report( $type, $args = array() ) {
		$defaults = array(
			'date_from' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			'date_to' => date( 'Y-m-d' ),
			'platform' => '',
			'user_id' => 0,
			'format' => 'array', // array, json, csv, html
		);

		$args = wp_parse_args( $args, $defaults );

		$this->logger->info( "生成{$type}报表", $args );

		$report_data = array();

		switch ( $type ) {
			case 'overview':
				$report_data = $this->generate_overview_report( $args );
				break;
			case 'performance':
				$report_data = $this->generate_performance_report( $args );
				break;
			case 'trends':
				$report_data = $this->generate_trends_report( $args );
				break;
			case 'comparison':
				$report_data = $this->generate_comparison_report( $args );
				break;
			case 'quality':
				$report_data = $this->generate_quality_report( $args );
				break;
			case 'productivity':
				$report_data = $this->generate_productivity_report( $args );
				break;
			case 'seo':
				$report_data = $this->generate_seo_report( $args );
				break;
			case 'multilingual':
				$report_data = $this->generate_multilingual_report( $args );
				break;
			default:
				return array(
					'success' => false,
					'error' => '未知的报表类型',
				);
		}

		// 格式化输出
		return $this->format_report( $report_data, $args['format'] );
	}

	/**
	 * 生成总览报表
	 *
	 * @param array $args 参数
	 * @return array 报表数据
	 */
	private function generate_overview_report( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		$where = $this->build_where_clause( $args );

		// 总内容数
		$total_posts = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where['clause']}" );

		// 各平台分布
		$platform_stats = $wpdb->get_results(
			"SELECT platform, COUNT(*) as count FROM {$table} WHERE {$where['clause']} GROUP BY platform",
			ARRAY_A
		);

		// 各状态分布
		$status_stats = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$table} WHERE {$where['clause']} GROUP BY status",
			ARRAY_A
		);

		// AI模型使用统计
		$ai_model_stats = $wpdb->get_results(
			"SELECT ai_model, COUNT(*) as count FROM {$table} WHERE {$where['clause']} GROUP BY ai_model",
			ARRAY_A
		);

		// 每日生成趋势
		$daily_trend = $wpdb->get_results(
			"SELECT DATE(created_at) as date, COUNT(*) as count
			FROM {$table}
			WHERE {$where['clause']}
			GROUP BY DATE(created_at)
			ORDER BY date ASC",
			ARRAY_A
		);

		return array(
			'success' => true,
			'type' => 'overview',
			'period' => array(
				'from' => $args['date_from'],
				'to' => $args['date_to'],
			),
			'summary' => array(
				'total_posts' => (int) $total_posts,
				'avg_per_day' => $this->calculate_avg_per_day( $total_posts, $args ),
			),
			'platforms' => $this->format_stats( $platform_stats ),
			'statuses' => $this->format_stats( $status_stats ),
			'ai_models' => $this->format_stats( $ai_model_stats ),
			'daily_trend' => $daily_trend,
		);
	}

	/**
	 * 生成性能报表
	 *
	 * @param array $args 参数
	 * @return array 报表数据
	 */
	private function generate_performance_report( $args ) {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'aiscg_logs';

		// 获取所有日志中的性能数据
		$where = "1=1";
		if ( ! empty( $args['date_from'] ) ) {
			$where .= $wpdb->prepare( " AND created_at >= %s", $args['date_from'] );
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where .= $wpdb->prepare( " AND created_at <= %s", $args['date_to'] . ' 23:59:59' );
		}

		// 错误率统计
		$total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE {$where}" );
		$error_logs = $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE {$where} AND level = 'ERROR'" );
		$warning_logs = $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE {$where} AND level = 'WARNING'" );

		// API调用统计（从日志中提取）
		$api_calls = $wpdb->get_results(
			"SELECT DATE(created_at) as date, COUNT(*) as count
			FROM {$logs_table}
			WHERE {$where} AND message LIKE '%API%'
			GROUP BY DATE(created_at)
			ORDER BY date ASC",
			ARRAY_A
		);

		// 缓存命中率（如果有缓存日志）
		$cache_hits = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$logs_table} WHERE {$where} AND message LIKE '%cache hit%'"
		);
		$cache_misses = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$logs_table} WHERE {$where} AND message LIKE '%cache miss%'"
		);

		$cache_hit_rate = 0;
		if ( ( $cache_hits + $cache_misses ) > 0 ) {
			$cache_hit_rate = round( ( $cache_hits / ( $cache_hits + $cache_misses ) ) * 100, 2 );
		}

		return array(
			'success' => true,
			'type' => 'performance',
			'period' => array(
				'from' => $args['date_from'],
				'to' => $args['date_to'],
			),
			'error_rate' => array(
				'total_logs' => (int) $total_logs,
				'errors' => (int) $error_logs,
				'warnings' => (int) $warning_logs,
				'error_percentage' => $total_logs > 0 ? round( ( $error_logs / $total_logs ) * 100, 2 ) : 0,
			),
			'api_calls' => array(
				'daily_trend' => $api_calls,
				'total' => array_sum( array_column( $api_calls, 'count' ) ),
			),
			'cache' => array(
				'hits' => (int) $cache_hits,
				'misses' => (int) $cache_misses,
				'hit_rate' => $cache_hit_rate,
			),
		);
	}

	/**
	 * 生成趋势报表
	 *
	 * @param array $args 参数
	 * @return array 报表数据
	 */
	private function generate_trends_report( $args ) {
		// 使用趋势分析器
		if ( class_exists( 'AISCG_Trend_Analyzer' ) ) {
			$analyzer = new AISCG_Trend_Analyzer();
			$trends = $analyzer->analyze_trends( array(
				'days' => 30,
				'platform' => $args['platform'],
			) );

			return array(
				'success' => true,
				'type' => 'trends',
				'period' => array(
					'from' => $args['date_from'],
					'to' => $args['date_to'],
				),
				'data' => $trends,
			);
		}

		return array(
			'success' => false,
			'error' => '趋势分析器不可用',
		);
	}

	/**
	 * 生成对比报表
	 *
	 * @param array $args 参数
	 * @return array 报表数据
	 */
	private function generate_comparison_report( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		// 对比当前周期和上一周期
		$current_period = array(
			'from' => $args['date_from'],
			'to' => $args['date_to'],
		);

		$days_diff = ( strtotime( $args['date_to'] ) - strtotime( $args['date_from'] ) ) / 86400;
		$previous_period = array(
			'from' => date( 'Y-m-d', strtotime( $args['date_from'] . " -{$days_diff} days" ) ),
			'to' => $args['date_from'],
		);

		// 当前周期数据
		$current_data = $this->get_period_stats( $current_period, $args );

		// 上一周期数据
		$previous_data = $this->get_period_stats( $previous_period, $args );

		// 计算变化
		$changes = array(
			'total_posts' => $this->calculate_change( $previous_data['total_posts'], $current_data['total_posts'] ),
			'avg_per_day' => $this->calculate_change( $previous_data['avg_per_day'], $current_data['avg_per_day'] ),
		);

		return array(
			'success' => true,
			'type' => 'comparison',
			'current_period' => $current_period,
			'previous_period' => $previous_period,
			'current_data' => $current_data,
			'previous_data' => $previous_data,
			'changes' => $changes,
		);
	}

	/**
	 * 生成质量报表
	 *
	 * @param array $args 参数
	 * @return array 报表数据
	 */
	private function generate_quality_report( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';
		$where = $this->build_where_clause( $args );

		// 获取所有内容
		$posts = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where['clause']} LIMIT 1000",
			ARRAY_A
		);

		if ( ! class_exists( 'AISCG_Content_Scorer' ) ) {
			return array(
				'success' => false,
				'error' => '内容评分器不可用',
			);
		}

		$scorer = new AISCG_Content_Scorer();
		$quality_scores = array();
		$total_score = 0;

		foreach ( $posts as $post ) {
			$score = $scorer->score_content( $post );
			$quality_scores[] = array(
				'post_id' => $post['id'],
				'title' => $post['title'],
				'overall_score' => $score['overall'],
				'grade' => $score['grade'],
			);
			$total_score += $score['overall'];
		}

		// 按分数排序
		usort( $quality_scores, function( $a, $b ) {
			return $b['overall_score'] <=> $a['overall_score'];
		});

		// 等级分布
		$grade_distribution = array(
			'A' => 0,
			'B' => 0,
			'C' => 0,
			'D' => 0,
		);

		foreach ( $quality_scores as $score ) {
			$grade = $score['grade'];
			if ( isset( $grade_distribution[ $grade ] ) ) {
				$grade_distribution[ $grade ]++;
			}
		}

		return array(
			'success' => true,
			'type' => 'quality',
			'period' => array(
				'from' => $args['date_from'],
				'to' => $args['date_to'],
			),
			'summary' => array(
				'total_posts' => count( $posts ),
				'avg_score' => count( $posts ) > 0 ? round( $total_score / count( $posts ), 1 ) : 0,
			),
			'grade_distribution' => $grade_distribution,
			'top_content' => array_slice( $quality_scores, 0, 10 ),
			'bottom_content' => array_slice( $quality_scores, -10 ),
		);
	}

	/**
	 * 生成生产力报表
	 *
	 * @param array $args 参数
	 * @return array 报表数据
	 */
	private function generate_productivity_report( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		$where = $this->build_where_clause( $args );

		// 用户生产力统计
		$user_stats = $wpdb->get_results(
			"SELECT user_id, COUNT(*) as post_count,
			MIN(created_at) as first_post,
			MAX(created_at) as last_post
			FROM {$table}
			WHERE {$where['clause']} AND user_id > 0
			GROUP BY user_id
			ORDER BY post_count DESC",
			ARRAY_A
		);

		// 丰富用户信息
		foreach ( $user_stats as &$stat ) {
			$user = get_userdata( $stat['user_id'] );
			if ( $user ) {
				$stat['user_name'] = $user->display_name;
				$stat['user_email'] = $user->user_email;
			}

			// 计算活跃天数
			$days = ( strtotime( $stat['last_post'] ) - strtotime( $stat['first_post'] ) ) / 86400;
			$stat['active_days'] = max( 1, ceil( $days ) );
			$stat['avg_per_day'] = round( $stat['post_count'] / $stat['active_days'], 2 );
		}

		// 时间分布统计
		$hourly_distribution = $wpdb->get_results(
			"SELECT HOUR(created_at) as hour, COUNT(*) as count
			FROM {$table}
			WHERE {$where['clause']}
			GROUP BY HOUR(created_at)
			ORDER BY hour ASC",
			ARRAY_A
		);

		// 星期分布
		$weekday_distribution = $wpdb->get_results(
			"SELECT DAYOFWEEK(created_at) as weekday, COUNT(*) as count
			FROM {$table}
			WHERE {$where['clause']}
			GROUP BY DAYOFWEEK(created_at)
			ORDER BY weekday ASC",
			ARRAY_A
		);

		// 转换星期数字为名称
		$weekday_names = array( '', '周日', '周一', '周二', '周三', '周四', '周五', '周六' );
		foreach ( $weekday_distribution as &$day ) {
			$day['weekday_name'] = $weekday_names[ $day['weekday'] ];
		}

		return array(
			'success' => true,
			'type' => 'productivity',
			'period' => array(
				'from' => $args['date_from'],
				'to' => $args['date_to'],
			),
			'user_stats' => $user_stats,
			'hourly_distribution' => $hourly_distribution,
			'weekday_distribution' => $weekday_distribution,
			'insights' => $this->generate_productivity_insights( $user_stats, $hourly_distribution, $weekday_distribution ),
		);
	}

	/**
	 * 生成SEO报表
	 *
	 * @param array $args 参数
	 * @return array 报表数据
	 */
	private function generate_seo_report( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';
		$where = $this->build_where_clause( $args );

		$posts = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where['clause']} LIMIT 500",
			ARRAY_A
		);

		if ( ! class_exists( 'AISCG_SEO_Optimizer' ) ) {
			return array(
				'success' => false,
				'error' => 'SEO优化器不可用',
			);
		}

		$seo_optimizer = new AISCG_SEO_Optimizer();
		$seo_scores = array();
		$total_score = 0;

		foreach ( $posts as $post ) {
			$analysis = $seo_optimizer->analyze_seo( $post );
			$seo_scores[] = array(
				'post_id' => $post['id'],
				'title' => $post['title'],
				'overall_score' => $analysis['overall_score'],
				'recommendations' => count( $analysis['recommendations'] ),
			);
			$total_score += $analysis['overall_score'];
		}

		// 分数段分布
		$score_ranges = array(
			'90-100' => 0,
			'80-89' => 0,
			'70-79' => 0,
			'60-69' => 0,
			'0-59' => 0,
		);

		foreach ( $seo_scores as $score ) {
			$s = $score['overall_score'];
			if ( $s >= 90 ) {
				$score_ranges['90-100']++;
			} elseif ( $s >= 80 ) {
				$score_ranges['80-89']++;
			} elseif ( $s >= 70 ) {
				$score_ranges['70-79']++;
			} elseif ( $s >= 60 ) {
				$score_ranges['60-69']++;
			} else {
				$score_ranges['0-59']++;
			}
		}

		return array(
			'success' => true,
			'type' => 'seo',
			'period' => array(
				'from' => $args['date_from'],
				'to' => $args['date_to'],
			),
			'summary' => array(
				'total_posts' => count( $posts ),
				'avg_score' => count( $posts ) > 0 ? round( $total_score / count( $posts ), 1 ) : 0,
			),
			'score_distribution' => $score_ranges,
			'top_performing' => array_slice( $seo_scores, 0, 10 ),
		);
	}

	/**
	 * 生成多语言报表
	 *
	 * @param array $args 参数
	 * @return array 报表数据
	 */
	private function generate_multilingual_report( $args ) {
		// 从素材库表中统计多语言内容
		global $wpdb;
		$media_table = $wpdb->prefix . 'aiscg_media_library';

		$language_stats = $wpdb->get_results(
			"SELECT language, COUNT(*) as count FROM {$media_table} GROUP BY language ORDER BY count DESC",
			ARRAY_A
		);

		// 翻译活动统计（从日志中）
		$logs_table = $wpdb->prefix . 'aiscg_logs';
		$translation_logs = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$logs_table} WHERE message LIKE '%翻译%' OR message LIKE '%translation%'"
		);

		return array(
			'success' => true,
			'type' => 'multilingual',
			'period' => array(
				'from' => $args['date_from'],
				'to' => $args['date_to'],
			),
			'language_distribution' => $language_stats,
			'translation_count' => (int) $translation_logs,
		);
	}

	/**
	 * 导出报表
	 *
	 * @param array  $report_data 报表数据
	 * @param string $format 格式 (csv, json, html, pdf)
	 * @return string|array 导出结果
	 */
	public function export_report( $report_data, $format = 'csv' ) {
		switch ( $format ) {
			case 'csv':
				return $this->export_to_csv( $report_data );
			case 'json':
				return wp_json_encode( $report_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			case 'html':
				return $this->export_to_html( $report_data );
			default:
				return $report_data;
		}
	}

	/**
	 * 导出为CSV
	 *
	 * @param array $report_data 报表数据
	 * @return string CSV内容
	 */
	private function export_to_csv( $report_data ) {
		if ( ! isset( $report_data['type'] ) ) {
			return '';
		}

		$output = '';

		// 标题
		$output .= "报表类型," . $report_data['type'] . "\n";
		$output .= "生成时间," . current_time( 'Y-m-d H:i:s' ) . "\n";

		if ( isset( $report_data['period'] ) ) {
			$output .= "开始日期," . $report_data['period']['from'] . "\n";
			$output .= "结束日期," . $report_data['period']['to'] . "\n";
		}

		$output .= "\n";

		// 数据部分（根据报表类型）
		$output .= $this->format_data_to_csv( $report_data );

		return $output;
	}

	/**
	 * 格式化数据为CSV
	 *
	 * @param array $data 数据
	 * @return string CSV内容
	 */
	private function format_data_to_csv( $data ) {
		$output = '';

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$output .= "\n{$key}\n";
				if ( $this->is_assoc_array( $value ) ) {
					foreach ( $value as $k => $v ) {
						$output .= "{$k}," . ( is_array( $v ) ? wp_json_encode( $v ) : $v ) . "\n";
					}
				} else {
					// 数字索引数组
					if ( ! empty( $value ) && is_array( $value[0] ) ) {
						// 表格数据
						$headers = array_keys( $value[0] );
						$output .= implode( ',', $headers ) . "\n";
						foreach ( $value as $row ) {
							$output .= implode( ',', array_map( function( $v ) {
								return is_array( $v ) ? wp_json_encode( $v ) : $v;
							}, $row ) ) . "\n";
						}
					}
				}
			} else {
				$output .= "{$key},{$value}\n";
			}
		}

		return $output;
	}

	/**
	 * 导出为HTML
	 *
	 * @param array $report_data 报表数据
	 * @return string HTML内容
	 */
	private function export_to_html( $report_data ) {
		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
		$html .= '<title>' . ( $report_data['type'] ?? '报表' ) . '</title>';
		$html .= '<style>
			body { font-family: Arial, sans-serif; margin: 20px; }
			h1 { color: #333; }
			table { border-collapse: collapse; width: 100%; margin: 20px 0; }
			th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
			th { background-color: #4CAF50; color: white; }
			.summary { background-color: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #4CAF50; }
		</style></head><body>';

		$html .= '<h1>' . ( $this->report_types[ $report_data['type'] ] ?? $report_data['type'] ) . '</h1>';
		$html .= '<p>生成时间: ' . current_time( 'Y-m-d H:i:s' ) . '</p>';

		if ( isset( $report_data['period'] ) ) {
			$html .= '<div class="summary">';
			$html .= '<strong>报表周期:</strong> ' . $report_data['period']['from'] . ' 至 ' . $report_data['period']['to'];
			$html .= '</div>';
		}

		$html .= $this->format_data_to_html( $report_data );

		$html .= '</body></html>';

		return $html;
	}

	/**
	 * 格式化数据为HTML
	 *
	 * @param array $data 数据
	 * @return string HTML内容
	 */
	private function format_data_to_html( $data ) {
		$html = '';

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'success', 'type', 'period' ), true ) ) {
				continue;
			}

			$html .= '<h2>' . ucfirst( $key ) . '</h2>';

			if ( is_array( $value ) ) {
				if ( ! empty( $value ) && is_array( $value[0] ) && isset( $value[0] ) ) {
					// 表格数据
					$html .= '<table>';
					$headers = array_keys( $value[0] );
					$html .= '<tr>';
					foreach ( $headers as $header ) {
						$html .= '<th>' . esc_html( $header ) . '</th>';
					}
					$html .= '</tr>';

					foreach ( $value as $row ) {
						$html .= '<tr>';
						foreach ( $row as $cell ) {
							$html .= '<td>' . esc_html( is_array( $cell ) ? wp_json_encode( $cell ) : $cell ) . '</td>';
						}
						$html .= '</tr>';
					}
					$html .= '</table>';
				} else {
					// 键值对
					$html .= '<div class="summary">';
					foreach ( $value as $k => $v ) {
						$html .= '<p><strong>' . esc_html( $k ) . ':</strong> ' . esc_html( is_array( $v ) ? wp_json_encode( $v ) : $v ) . '</p>';
					}
					$html .= '</div>';
				}
			} else {
				$html .= '<p>' . esc_html( $value ) . '</p>';
			}
		}

		return $html;
	}

	/**
	 * 构建WHERE子句
	 *
	 * @param array $args 参数
	 * @return array WHERE子句和值
	 */
	private function build_where_clause( $args ) {
		global $wpdb;

		$where = array( '1=1' );

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', $args['date_to'] . ' 23:59:59' );
		}

		if ( ! empty( $args['platform'] ) ) {
			$where[] = $wpdb->prepare( 'platform = %s', $args['platform'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}

		return array(
			'clause' => implode( ' AND ', $where ),
		);
	}

	/**
	 * 格式化统计数据
	 *
	 * @param array $stats 统计数据
	 * @return array 格式化后的数据
	 */
	private function format_stats( $stats ) {
		$total = array_sum( array_column( $stats, 'count' ) );
		$formatted = array();

		foreach ( $stats as $stat ) {
			$key = $stat[ array_keys( $stat )[0] ]; // 第一个字段作为键
			$count = (int) $stat['count'];
			$formatted[ $key ] = array(
				'count' => $count,
				'percentage' => $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0,
			);
		}

		return $formatted;
	}

	/**
	 * 计算每日平均
	 *
	 * @param int   $total 总数
	 * @param array $args 参数
	 * @return float 平均值
	 */
	private function calculate_avg_per_day( $total, $args ) {
		$days = ( strtotime( $args['date_to'] ) - strtotime( $args['date_from'] ) ) / 86400;
		$days = max( 1, $days );
		return round( $total / $days, 2 );
	}

	/**
	 * 获取周期统计
	 *
	 * @param array $period 周期
	 * @param array $args 参数
	 * @return array 统计数据
	 */
	private function get_period_stats( $period, $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		$where = $this->build_where_clause( array_merge( $args, $period ) );

		$total_posts = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where['clause']}" );

		return array(
			'total_posts' => (int) $total_posts,
			'avg_per_day' => $this->calculate_avg_per_day( $total_posts, $period ),
		);
	}

	/**
	 * 计算变化百分比
	 *
	 * @param float $old_value 旧值
	 * @param float $new_value 新值
	 * @return array 变化数据
	 */
	private function calculate_change( $old_value, $new_value ) {
		$change = $new_value - $old_value;
		$percentage = $old_value > 0 ? round( ( $change / $old_value ) * 100, 1 ) : 0;

		return array(
			'absolute' => $change,
			'percentage' => $percentage,
			'direction' => $change > 0 ? 'up' : ( $change < 0 ? 'down' : 'stable' ),
		);
	}

	/**
	 * 生成生产力洞察
	 *
	 * @param array $user_stats 用户统计
	 * @param array $hourly_dist 小时分布
	 * @param array $weekday_dist 星期分布
	 * @return array 洞察数据
	 */
	private function generate_productivity_insights( $user_stats, $hourly_dist, $weekday_dist ) {
		$insights = array();

		// 最活跃用户
		if ( ! empty( $user_stats ) ) {
			$top_user = $user_stats[0];
			$insights[] = "最活跃用户: {$top_user['user_name']} (共 {$top_user['post_count']} 篇)";
		}

		// 最活跃时段
		if ( ! empty( $hourly_dist ) ) {
			usort( $hourly_dist, function( $a, $b ) {
				return $b['count'] <=> $a['count'];
			});
			$peak_hour = $hourly_dist[0];
			$insights[] = "最活跃时段: {$peak_hour['hour']}:00 (共 {$peak_hour['count']} 篇)";
		}

		// 最活跃星期
		if ( ! empty( $weekday_dist ) ) {
			usort( $weekday_dist, function( $a, $b ) {
				return $b['count'] <=> $a['count'];
			});
			$peak_day = $weekday_dist[0];
			$insights[] = "最活跃星期: {$peak_day['weekday_name']} (共 {$peak_day['count']} 篇)";
		}

		return $insights;
	}

	/**
	 * 格式化报表输出
	 *
	 * @param array  $data 报表数据
	 * @param string $format 格式
	 * @return mixed 格式化后的数据
	 */
	private function format_report( $data, $format ) {
		switch ( $format ) {
			case 'json':
				return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			case 'csv':
				return $this->export_to_csv( $data );
			case 'html':
				return $this->export_to_html( $data );
			default:
				return $data;
		}
	}

	/**
	 * 判断是否为关联数组
	 *
	 * @param array $arr 数组
	 * @return bool
	 */
	private function is_assoc_array( $arr ) {
		if ( ! is_array( $arr ) || array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
