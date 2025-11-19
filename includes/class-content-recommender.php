<?php
/**
 * Content Recommender Class
 *
 * 智能内容推荐引擎 - 基于历史数据和趋势分析推荐内容主题
 *
 * @package AI_Social_Content_Generator
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_Content_Recommender Class
 */
class AISCG_Content_Recommender {

	/**
	 * 推荐类型
	 *
	 * @var array
	 */
	private $recommendation_types = array(
		'trending_topics'    => '热门话题',
		'best_time'          => '最佳发布时间',
		'hashtag_suggestions' => '标签建议',
		'content_ideas'      => '内容创意',
		'platform_strategy'  => '平台策略',
		'optimization_tips'  => '优化建议',
	);

	/**
	 * 日志对象
	 *
	 * @var AISCG_Logger
	 */
	private $logger;

	/**
	 * 数据库对象
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
	 * 获取智能推荐
	 *
	 * @param array $args 参数
	 * @return array 推荐结果
	 */
	public function get_recommendations( $args = array() ) {
		$defaults = array(
			'platform' => '', // xiaohongshu, instagram, 或空表示所有
			'limit' => 10,
			'type' => 'all', // all 或具体类型
			'user_id' => get_current_user_id(),
		);

		$args = wp_parse_args( $args, $defaults );

		$recommendations = array(
			'success' => true,
			'generated_at' => current_time( 'mysql' ),
			'platform' => $args['platform'] ?: 'all',
		);

		// 根据类型获取不同的推荐
		if ( $args['type'] === 'all' || $args['type'] === 'trending_topics' ) {
			$recommendations['trending_topics'] = $this->recommend_trending_topics( $args );
		}

		if ( $args['type'] === 'all' || $args['type'] === 'best_time' ) {
			$recommendations['best_time'] = $this->recommend_best_time( $args );
		}

		if ( $args['type'] === 'all' || $args['type'] === 'hashtag_suggestions' ) {
			$recommendations['hashtag_suggestions'] = $this->recommend_hashtags( $args );
		}

		if ( $args['type'] === 'all' || $args['type'] === 'content_ideas' ) {
			$recommendations['content_ideas'] = $this->recommend_content_ideas( $args );
		}

		if ( $args['type'] === 'all' || $args['type'] === 'platform_strategy' ) {
			$recommendations['platform_strategy'] = $this->recommend_platform_strategy( $args );
		}

		if ( $args['type'] === 'all' || $args['type'] === 'optimization_tips' ) {
			$recommendations['optimization_tips'] = $this->recommend_optimization_tips( $args );
		}

		$this->logger->info( '生成智能推荐', array(
			'type' => $args['type'],
			'platform' => $args['platform'],
		) );

		return $recommendations;
	}

	/**
	 * 推荐热门话题
	 *
	 * @param array $args 参数
	 * @return array 话题推荐
	 */
	private function recommend_trending_topics( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		// 分析最近30天的热门关键词
		$where = '1=1';
		if ( ! empty( $args['platform'] ) ) {
			$where .= $wpdb->prepare( ' AND platform = %s', $args['platform'] );
		}

		$posts = $wpdb->get_results(
			"SELECT title, content, hashtags, created_at
			FROM {$table}
			WHERE {$where}
			AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			ORDER BY created_at DESC
			LIMIT 500",
			ARRAY_A
		);

		// 提取关键词
		$keyword_freq = array();
		foreach ( $posts as $post ) {
			$text = $post['title'] . ' ' . $post['content'];
			$keywords = $this->extract_keywords( $text );

			foreach ( $keywords as $keyword ) {
				if ( ! isset( $keyword_freq[ $keyword ] ) ) {
					$keyword_freq[ $keyword ] = 0;
				}
				$keyword_freq[ $keyword ]++;
			}
		}

		// 按频率排序
		arsort( $keyword_freq );

		// 计算趋势分数
		$trending_topics = array();
		$recent_posts = array_slice( $posts, 0, 100 );
		$older_posts = array_slice( $posts, 100 );

		foreach ( array_slice( $keyword_freq, 0, $args['limit'], true ) as $keyword => $total_freq ) {
			// 计算最近和早期的频率
			$recent_freq = 0;
			foreach ( $recent_posts as $post ) {
				if ( mb_stripos( $post['title'] . ' ' . $post['content'], $keyword ) !== false ) {
					$recent_freq++;
				}
			}

			$older_freq = 0;
			foreach ( $older_posts as $post ) {
				if ( mb_stripos( $post['title'] . ' ' . $post['content'], $keyword ) !== false ) {
					$older_freq++;
				}
			}

			// 计算增长率
			$growth_rate = 0;
			if ( $older_freq > 0 ) {
				$growth_rate = ( ( $recent_freq - $older_freq ) / $older_freq ) * 100;
			} elseif ( $recent_freq > 0 ) {
				$growth_rate = 100;
			}

			$trending_topics[] = array(
				'topic' => $keyword,
				'total_mentions' => $total_freq,
				'recent_mentions' => $recent_freq,
				'growth_rate' => round( $growth_rate, 1 ),
				'trend' => $growth_rate > 0 ? 'up' : ( $growth_rate < 0 ? 'down' : 'stable' ),
				'recommendation' => $this->generate_topic_recommendation( $keyword, $growth_rate ),
			);
		}

		return $trending_topics;
	}

	/**
	 * 推荐最佳发布时间
	 *
	 * @param array $args 参数
	 * @return array 时间推荐
	 */
	private function recommend_best_time( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		$where = '1=1';
		if ( ! empty( $args['platform'] ) ) {
			$where .= $wpdb->prepare( ' AND platform = %s', $args['platform'] );
		}

		// 分析发布时间分布
		$hourly_dist = $wpdb->get_results(
			"SELECT HOUR(created_at) as hour, COUNT(*) as count, AVG(LENGTH(content)) as avg_length
			FROM {$table}
			WHERE {$where}
			AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
			GROUP BY HOUR(created_at)
			ORDER BY count DESC",
			ARRAY_A
		);

		$weekday_dist = $wpdb->get_results(
			"SELECT DAYOFWEEK(created_at) as weekday, COUNT(*) as count
			FROM {$table}
			WHERE {$where}
			AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
			GROUP BY DAYOFWEEK(created_at)
			ORDER BY count DESC",
			ARRAY_A
		);

		// 获取平台最佳实践
		$platform_best_times = array(
			'xiaohongshu' => array(
				'hours' => array( 9, 12, 18, 21 ),
				'weekdays' => array( 1, 2, 3, 4, 5 ), // 周一到周五
			),
			'instagram' => array(
				'hours' => array( 10, 14, 19, 22 ),
				'weekdays' => array( 3, 4, 5, 6 ), // 周三到周六
			),
		);

		$platform = $args['platform'] ?: 'xiaohongshu';
		$best_practice = isset( $platform_best_times[ $platform ] )
			? $platform_best_times[ $platform ]
			: $platform_best_times['xiaohongshu'];

		// 生成推荐
		$top_hours = array_slice( $hourly_dist, 0, 3 );
		$top_weekdays = array_slice( $weekday_dist, 0, 3 );

		$weekday_names = array( '', '周日', '周一', '周二', '周三', '周四', '周五', '周六' );

		return array(
			'recommended_hours' => array_map( function( $h ) {
				return array(
					'hour' => $h['hour'],
					'time_range' => $h['hour'] . ':00-' . ( $h['hour'] + 1 ) . ':00',
					'post_count' => (int) $h['count'],
					'avg_content_length' => (int) $h['avg_length'],
				);
			}, $top_hours ),
			'recommended_weekdays' => array_map( function( $w ) use ( $weekday_names ) {
				return array(
					'weekday' => (int) $w['weekday'],
					'weekday_name' => $weekday_names[ $w['weekday'] ],
					'post_count' => (int) $w['count'],
				);
			}, $top_weekdays ),
			'platform_best_practice' => array(
				'hours' => $best_practice['hours'],
				'weekdays' => array_map( function( $w ) use ( $weekday_names ) {
					return array(
						'weekday' => $w,
						'weekday_name' => $weekday_names[ $w ],
					);
				}, $best_practice['weekdays'] ),
			),
			'next_optimal_times' => $this->calculate_next_optimal_times( $best_practice ),
		);
	}

	/**
	 * 推荐标签
	 *
	 * @param array $args 参数
	 * @return array 标签推荐
	 */
	private function recommend_hashtags( $args ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		$where = '1=1';
		if ( ! empty( $args['platform'] ) ) {
			$where .= $wpdb->prepare( ' AND platform = %s', $args['platform'] );
		}

		// 获取最近的标签
		$posts = $wpdb->get_results(
			"SELECT hashtags FROM {$table}
			WHERE {$where}
			AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			ORDER BY created_at DESC
			LIMIT 500",
			ARRAY_A
		);

		$hashtag_freq = array();
		foreach ( $posts as $post ) {
			$hashtags = json_decode( $post['hashtags'], true );
			if ( is_array( $hashtags ) ) {
				foreach ( $hashtags as $tag ) {
					$tag = trim( $tag, '# ' );
					if ( ! isset( $hashtag_freq[ $tag ] ) ) {
						$hashtag_freq[ $tag ] = 0;
					}
					$hashtag_freq[ $tag ]++;
				}
			}
		}

		// 排序
		arsort( $hashtag_freq );

		// 生成推荐
		$recommended_tags = array();
		$count = 0;
		foreach ( $hashtag_freq as $tag => $freq ) {
			if ( $count >= $args['limit'] ) {
				break;
			}

			$recommended_tags[] = array(
				'hashtag' => '#' . $tag,
				'usage_count' => $freq,
				'category' => $this->categorize_hashtag( $tag ),
				'recommendation_score' => $this->calculate_hashtag_score( $tag, $freq, count( $posts ) ),
			);
			$count++;
		}

		// 添加AI生成的新标签建议
		$recommended_tags[] = array(
			'type' => 'ai_suggestions',
			'tags' => $this->generate_ai_hashtag_suggestions( $args['platform'] ),
		);

		return $recommended_tags;
	}

	/**
	 * 推荐内容创意
	 *
	 * @param array $args 参数
	 * @return array 创意推荐
	 */
	private function recommend_content_ideas( $args ) {
		// 基于历史表现推荐内容创意
		$ideas = array();

		// 分析高质量内容的特征
		if ( class_exists( 'AISCG_Content_Scorer' ) ) {
			$scorer = new AISCG_Content_Scorer();
			global $wpdb;
			$table = $wpdb->prefix . 'aiscg_posts';

			$where = '1=1';
			if ( ! empty( $args['platform'] ) ) {
				$where .= $wpdb->prepare( ' AND platform = %s', $args['platform'] );
			}

			$posts = $wpdb->get_results(
				"SELECT * FROM {$table}
				WHERE {$where}
				AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
				ORDER BY created_at DESC
				LIMIT 100",
				ARRAY_A
			);

			// 找出高分内容的共同特征
			$high_scoring_patterns = array();
			foreach ( $posts as $post ) {
				$score = $scorer->score_content( $post );
				if ( $score['overall'] >= 80 ) {
					// 分析模式
					$patterns = $this->analyze_content_patterns( $post );
					foreach ( $patterns as $pattern => $value ) {
						if ( ! isset( $high_scoring_patterns[ $pattern ] ) ) {
							$high_scoring_patterns[ $pattern ] = array();
						}
						$high_scoring_patterns[ $pattern ][] = $value;
					}
				}
			}

			// 生成创意建议
			$ideas[] = array(
				'category' => '高质量内容特征',
				'ideas' => $this->generate_ideas_from_patterns( $high_scoring_patterns ),
			);
		}

		// 基于趋势的创意
		if ( class_exists( 'AISCG_Trend_Analyzer' ) ) {
			$analyzer = new AISCG_Trend_Analyzer();
			$trends = $analyzer->analyze_trends( array(
				'platform' => $args['platform'],
				'days' => 30,
			) );

			if ( ! empty( $trends['hot_topics'] ) ) {
				$trend_ideas = array();
				foreach ( array_slice( $trends['hot_topics'], 0, 5 ) as $topic ) {
					$trend_ideas[] = array(
						'topic' => $topic['topic'],
						'idea' => "创作关于「{$topic['topic']}」的内容，当前热度增长{$topic['growth_rate']}%",
						'potential' => 'high',
					);
				}

				$ideas[] = array(
					'category' => '热门趋势创意',
					'ideas' => $trend_ideas,
				);
			}
		}

		// 通用创意模板
		$ideas[] = array(
			'category' => '内容类型建议',
			'ideas' => $this->get_generic_content_templates( $args['platform'] ),
		);

		return $ideas;
	}

	/**
	 * 推荐平台策略
	 *
	 * @param array $args 参数
	 * @return array 策略推荐
	 */
	private function recommend_platform_strategy( $args ) {
		$strategies = array();

		$platform = $args['platform'] ?: 'xiaohongshu';

		// 小红书策略
		if ( $platform === 'xiaohongshu' || $platform === '' ) {
			$strategies['xiaohongshu'] = array(
				'content_style' => array(
					'emoji_usage' => '适度使用emoji增强视觉吸引力（建议3-5个）',
					'paragraph_structure' => '短段落，每段2-3行，增强可读性',
					'opening' => '使用吸引眼球的开场，如疑问句、惊叹句',
					'closing' => '以互动性问题或行动号召结尾',
				),
				'hashtag_strategy' => array(
					'count' => '建议使用5-8个相关标签',
					'mix' => '混合使用热门标签(2-3个)和细分标签(3-5个)',
					'placement' => '标签放在内容末尾',
				),
				'posting_frequency' => '每天1-2篇，保持规律性',
				'best_length' => '500-800字为最佳长度',
			);
		}

		// Instagram策略
		if ( $platform === 'instagram' || $platform === '' ) {
			$strategies['instagram'] = array(
				'content_style' => array(
					'caption_length' => '前125个字符最重要（预览部分）',
					'line_breaks' => '使用换行增强可读性',
					'storytelling' => '讲故事式的内容更容易引发共鸣',
					'call_to_action' => '明确的行动号召',
				),
				'hashtag_strategy' => array(
					'count' => '建议使用20-30个标签',
					'research' => '研究相关话题的热门标签',
					'branded' => '创建品牌专属标签',
					'placement' => '可在内容中或首条评论中',
				),
				'posting_frequency' => '每天1篇或每周5-7篇',
				'best_length' => '300-500字为最佳长度',
			);
		}

		// 通用策略
		$strategies['general_tips'] = array(
			'consistency' => '保持发布频率的一致性',
			'engagement' => '及时回复评论，提高互动率',
			'quality_over_quantity' => '质量优先于数量',
			'analyze_performance' => '定期分析内容表现，调整策略',
			'trend_awareness' => '关注平台趋势和热点',
		);

		return $strategies;
	}

	/**
	 * 推荐优化建议
	 *
	 * @param array $args 参数
	 * @return array 优化建议
	 */
	private function recommend_optimization_tips( $args ) {
		$tips = array();

		// 分析用户的内容表现
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		$where = '1=1';
		if ( ! empty( $args['platform'] ) ) {
			$where .= $wpdb->prepare( ' AND platform = %s', $args['platform'] );
		}
		if ( ! empty( $args['user_id'] ) ) {
			$where .= $wpdb->prepare( ' AND user_id = %d', $args['user_id'] );
		}

		$posts = $wpdb->get_results(
			"SELECT * FROM {$table}
			WHERE {$where}
			AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			ORDER BY created_at DESC
			LIMIT 50",
			ARRAY_A
		);

		if ( empty( $posts ) ) {
			return array(
				array(
					'category' => '开始创作',
					'tip' => '还没有足够的内容数据，开始创作更多内容以获得个性化建议',
					'priority' => 'info',
				),
			);
		}

		// 分析平均长度
		$avg_length = 0;
		foreach ( $posts as $post ) {
			$avg_length += mb_strlen( $post['content'] );
		}
		$avg_length = round( $avg_length / count( $posts ) );

		$optimal_length = $args['platform'] === 'instagram' ? 400 : 650;
		if ( abs( $avg_length - $optimal_length ) > 200 ) {
			$tips[] = array(
				'category' => '内容长度',
				'tip' => "您的平均内容长度为 {$avg_length} 字，建议调整到 {$optimal_length} 字左右以获得更好效果",
				'priority' => 'medium',
				'current' => $avg_length,
				'recommended' => $optimal_length,
			);
		}

		// 分析标签使用
		$total_hashtags = 0;
		foreach ( $posts as $post ) {
			$hashtags = json_decode( $post['hashtags'], true );
			if ( is_array( $hashtags ) ) {
				$total_hashtags += count( $hashtags );
			}
		}
		$avg_hashtags = round( $total_hashtags / count( $posts ) );

		$optimal_hashtags = $args['platform'] === 'instagram' ? 25 : 6;
		if ( abs( $avg_hashtags - $optimal_hashtags ) > 3 ) {
			$tips[] = array(
				'category' => '标签使用',
				'tip' => "您平均使用 {$avg_hashtags} 个标签，建议使用 {$optimal_hashtags} 个左右",
				'priority' => 'medium',
				'current' => $avg_hashtags,
				'recommended' => $optimal_hashtags,
			);
		}

		// 发布频率
		$first_post_date = strtotime( $posts[ count( $posts ) - 1 ]['created_at'] );
		$days_active = max( 1, ( time() - $first_post_date ) / 86400 );
		$posts_per_day = count( $posts ) / $days_active;

		if ( $posts_per_day < 0.5 ) {
			$tips[] = array(
				'category' => '发布频率',
				'tip' => sprintf( '您的发布频率为每天 %.1f 篇，建议增加到每天1-2篇', $posts_per_day ),
				'priority' => 'high',
				'current' => round( $posts_per_day, 1 ),
				'recommended' => 1.5,
			);
		}

		// 内容质量
		if ( class_exists( 'AISCG_Content_Scorer' ) ) {
			$scorer = new AISCG_Content_Scorer();
			$total_score = 0;
			$low_score_count = 0;

			foreach ( array_slice( $posts, 0, 20 ) as $post ) {
				$score = $scorer->score_content( $post );
				$total_score += $score['overall'];
				if ( $score['overall'] < 70 ) {
					$low_score_count++;
				}
			}

			$avg_score = round( $total_score / min( 20, count( $posts ) ) );

			if ( $avg_score < 75 ) {
				$tips[] = array(
					'category' => '内容质量',
					'tip' => "您的平均内容质量评分为 {$avg_score}分，建议使用内容优化器提升质量",
					'priority' => 'high',
					'current' => $avg_score,
					'recommended' => 80,
				);
			}

			if ( $low_score_count > 5 ) {
				$tips[] = array(
					'category' => '低质量内容',
					'tip' => "检测到 {$low_score_count} 篇低质量内容，建议进行优化或重新生成",
					'priority' => 'medium',
					'action' => 'optimize',
				);
			}
		}

		// 添加通用优化建议
		if ( empty( $tips ) ) {
			$tips[] = array(
				'category' => '表现良好',
				'tip' => '您的内容表现良好，继续保持！',
				'priority' => 'info',
			);
		}

		return $tips;
	}

	/**
	 * 提取关键词
	 *
	 * @param string $text 文本
	 * @return array 关键词数组
	 */
	private function extract_keywords( $text ) {
		// 移除特殊字符
		$text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

		// 分词（简单的空格分割）
		$words = preg_split( '/\s+/u', $text );

		// 过滤停用词和短词
		$stopwords = array( '的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一', '一个', '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好', '自己', '这' );
		$keywords = array();

		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( mb_strlen( $word ) >= 2 && ! in_array( $word, $stopwords, true ) ) {
				$keywords[] = $word;
			}
		}

		return $keywords;
	}

	/**
	 * 生成话题推荐
	 *
	 * @param string $topic 话题
	 * @param float  $growth_rate 增长率
	 * @return string 推荐文本
	 */
	private function generate_topic_recommendation( $topic, $growth_rate ) {
		if ( $growth_rate > 50 ) {
			return "🔥 热度飙升！这是当前最热门的话题之一，建议立即创作相关内容";
		} elseif ( $growth_rate > 20 ) {
			return "📈 持续升温中，是创作的好时机";
		} elseif ( $growth_rate > 0 ) {
			return "✅ 稳定增长，可以考虑创作";
		} elseif ( $growth_rate === 0 ) {
			return "➡️ 热度平稳，常青话题";
		} else {
			return "📉 热度下降，建议观望";
		}
	}

	/**
	 * 计算下一个最佳发布时间
	 *
	 * @param array $best_practice 最佳实践
	 * @return array 时间列表
	 */
	private function calculate_next_optimal_times( $best_practice ) {
		$now = current_time( 'timestamp' );
		$optimal_times = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$day_timestamp = $now + ( $i * 86400 );
			$weekday = (int) date( 'N', $day_timestamp ); // 1=周一, 7=周日
			$weekday_formatted = ( $weekday % 7 ) + 1; // 转换为1=周日格式

			if ( in_array( $weekday_formatted, $best_practice['weekdays'], true ) ) {
				foreach ( $best_practice['hours'] as $hour ) {
					$time_timestamp = strtotime( date( 'Y-m-d', $day_timestamp ) . " {$hour}:00:00" );
					if ( $time_timestamp > $now ) {
						$optimal_times[] = array(
							'datetime' => date( 'Y-m-d H:i:s', $time_timestamp ),
							'formatted' => date( 'Y-m-d H:i', $time_timestamp ),
							'relative' => human_time_diff( $now, $time_timestamp ) . '后',
						);

						if ( count( $optimal_times ) >= 10 ) {
							break 2;
						}
					}
				}
			}
		}

		return $optimal_times;
	}

	/**
	 * 分类标签
	 *
	 * @param string $tag 标签
	 * @return string 分类
	 */
	private function categorize_hashtag( $tag ) {
		// 简单的分类逻辑
		$categories = array(
			'lifestyle' => array( '生活', '日常', '分享', 'vlog', '记录' ),
			'beauty' => array( '美妆', '护肤', '彩妆', '化妆' ),
			'food' => array( '美食', '吃播', '探店', '餐厅' ),
			'travel' => array( '旅行', '旅游', '打卡', '景点' ),
			'fashion' => array( '穿搭', '时尚', '服装', '搭配' ),
		);

		foreach ( $categories as $category => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( mb_stripos( $tag, $keyword ) !== false ) {
					return $category;
				}
			}
		}

		return 'general';
	}

	/**
	 * 计算标签评分
	 *
	 * @param string $tag 标签
	 * @param int    $freq 频率
	 * @param int    $total 总数
	 * @return float 评分
	 */
	private function calculate_hashtag_score( $tag, $freq, $total ) {
		// 使用频率百分比
		$usage_rate = ( $freq / $total ) * 100;

		// 标签长度（较短的标签通常更好）
		$length_score = max( 0, 100 - mb_strlen( $tag ) * 2 );

		// 综合评分
		return round( ( $usage_rate * 0.7 + $length_score * 0.3 ), 1 );
	}

	/**
	 * 生成AI标签建议
	 *
	 * @param string $platform 平台
	 * @return array 标签数组
	 */
	private function generate_ai_hashtag_suggestions( $platform ) {
		// 基于平台的通用热门标签
		$suggestions = array(
			'xiaohongshu' => array(
				'#小红书', '#种草', '#分享', '#日常', '#好物推荐',
				'#生活记录', '#今日分享', '#干货', '#实用', '#必看',
			),
			'instagram' => array(
				'#instagood', '#photooftheday', '#love', '#fashion', '#beautiful',
				'#happy', '#follow', '#like4like', '#instadaily', '#picoftheday',
			),
		);

		return isset( $suggestions[ $platform ] ) ? $suggestions[ $platform ] : $suggestions['xiaohongshu'];
	}

	/**
	 * 分析内容模式
	 *
	 * @param array $post 文章
	 * @return array 模式数组
	 */
	private function analyze_content_patterns( $post ) {
		return array(
			'emoji_count' => preg_match_all( '/[\x{1F300}-\x{1F9FF}]/u', $post['content'] ),
			'paragraph_count' => substr_count( $post['content'], "\n\n" ) + 1,
			'avg_paragraph_length' => mb_strlen( $post['content'] ) / max( 1, substr_count( $post['content'], "\n\n" ) + 1 ),
			'has_question' => strpos( $post['content'], '？' ) !== false || strpos( $post['content'], '?' ) !== false,
			'hashtag_count' => count( json_decode( $post['hashtags'], true ) ?: array() ),
		);
	}

	/**
	 * 从模式生成创意
	 *
	 * @param array $patterns 模式数组
	 * @return array 创意数组
	 */
	private function generate_ideas_from_patterns( $patterns ) {
		$ideas = array();

		// 分析emoji使用
		if ( isset( $patterns['emoji_count'] ) && ! empty( $patterns['emoji_count'] ) ) {
			$avg_emoji = round( array_sum( $patterns['emoji_count'] ) / count( $patterns['emoji_count'] ) );
			$ideas[] = array(
				'idea' => "高质量内容平均使用 {$avg_emoji} 个emoji",
				'action' => '适度使用emoji增强视觉吸引力',
			);
		}

		// 分析段落
		if ( isset( $patterns['paragraph_count'] ) && ! empty( $patterns['paragraph_count'] ) ) {
			$avg_para = round( array_sum( $patterns['paragraph_count'] ) / count( $patterns['paragraph_count'] ) );
			$ideas[] = array(
				'idea' => "高质量内容平均分为 {$avg_para} 个段落",
				'action' => '使用短段落提高可读性',
			);
		}

		// 互动问题
		if ( isset( $patterns['has_question'] ) ) {
			$question_rate = ( array_sum( $patterns['has_question'] ) / count( $patterns['has_question'] ) ) * 100;
			if ( $question_rate > 50 ) {
				$ideas[] = array(
					'idea' => round( $question_rate ) . '% 的高质量内容包含互动问题',
					'action' => '在内容中加入问题增强互动性',
				);
			}
		}

		return $ideas;
	}

	/**
	 * 获取通用内容模板
	 *
	 * @param string $platform 平台
	 * @return array 模板数组
	 */
	private function get_generic_content_templates( $platform ) {
		$templates = array(
			array(
				'type' => '教程攻略',
				'description' => '分享实用技巧和步骤指南',
				'example' => '「3步教你XXX」、「新手必看的XXX攻略」',
			),
			array(
				'type' => '经验分享',
				'description' => '个人体验和心得总结',
				'example' => '「我的XXX经验分享」、「踩坑总结：XXX」',
			),
			array(
				'type' => '好物推荐',
				'description' => '产品测评和推荐',
				'example' => '「真香警告！这个XXX太好用了」、「XXX好物清单」',
			),
			array(
				'type' => '对比评测',
				'description' => '多个选项的对比分析',
				'example' => '「XXX vs XXX，到底哪个更好？」',
			),
			array(
				'type' => '日常记录',
				'description' => '生活点滴和日常vlog',
				'example' => '「今日份XXX」、「记录XXX的一天」',
			),
		);

		if ( $platform === 'instagram' ) {
			$templates[] = array(
				'type' => 'Story Telling',
				'description' => '讲述引人入胜的故事',
				'example' => '「Behind the scenes of...」、「My journey to...」',
			);
		}

		return $templates;
	}
}
