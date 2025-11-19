<?php
/**
 * AI Tag Generator Class
 *
 * AI驱动的智能标签生成器
 *
 * @package AI_Social_Content_Generator
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_AI_Tag_Generator Class
 */
class AISCG_AI_Tag_Generator {

	/**
	 * 标签策略
	 *
	 * @var array
	 */
	private $tag_strategies = array(
		'trending'    => '热门标签',
		'niche'       => '细分标签',
		'branded'     => '品牌标签',
		'descriptive' => '描述性标签',
		'actionable'  => '行动号召标签',
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
	 * 生成AI标签
	 *
	 * @param string $content 内容
	 * @param array  $options 选项
	 * @return array 标签列表
	 */
	public function generate_tags( $content, $options = array() ) {
		$defaults = array(
			'platform' => 'xiaohongshu',
			'ai_model' => 'gpt-4',
			'count' => 0, // 0表示使用平台默认数量
			'strategy' => 'mixed', // mixed, trending, niche
			'language' => 'zh-CN',
			'include_trending' => true,
		);

		$options = wp_parse_args( $options, $defaults );

		// 设置每个平台的默认标签数量
		if ( $options['count'] === 0 ) {
			$options['count'] = $options['platform'] === 'instagram' ? 25 : 8;
		}

		$this->logger->info( '开始生成AI标签', array(
			'platform' => $options['platform'],
			'count' => $options['count'],
			'strategy' => $options['strategy'],
		) );

		// 使用AI生成标签
		$ai_tags = $this->generate_with_ai( $content, $options );

		// 获取趋势标签
		$trending_tags = array();
		if ( $options['include_trending'] ) {
			$trending_tags = $this->get_trending_tags( $options['platform'], $options['language'] );
		}

		// 获取历史高效标签
		$historical_tags = $this->get_historical_performing_tags( $options['platform'] );

		// 混合策略
		$final_tags = $this->mix_tags( $ai_tags, $trending_tags, $historical_tags, $options );

		// 验证和优化标签
		$final_tags = $this->validate_and_optimize_tags( $final_tags, $options );

		$this->logger->info( '标签生成完成', array(
			'total_tags' => count( $final_tags ),
			'ai_tags' => count( $ai_tags ),
			'trending_tags' => count( $trending_tags ),
		) );

		return array(
			'success' => true,
			'tags' => $final_tags,
			'metadata' => array(
				'ai_generated' => count( $ai_tags ),
				'trending' => count( $trending_tags ),
				'historical' => count( $historical_tags ),
				'strategy' => $options['strategy'],
				'platform' => $options['platform'],
			),
		);
	}

	/**
	 * 使用AI生成标签
	 *
	 * @param string $content 内容
	 * @param array  $options 选项
	 * @return array 标签数组
	 */
	private function generate_with_ai( $content, $options ) {
		try {
			// 创建AI服务
			$ai_service = AISCG_AI_Service_Factory::create( $options['ai_model'] );

			// 构建prompt
			$prompt = $this->build_tag_generation_prompt( $content, $options );

			// 调用AI
			$response = $ai_service->generate( $prompt, array(
				'temperature' => 0.7,
				'max_tokens' => 500,
			) );

			// 解析标签
			$tags = $this->parse_ai_response( $response );

			return $tags;

		} catch ( Exception $e ) {
			$this->logger->error( 'AI标签生成失败', array(
				'error' => $e->getMessage(),
			) );

			// 降级到基于关键词的方法
			return $this->generate_keyword_based_tags( $content, $options );
		}
	}

	/**
	 * 构建标签生成prompt
	 *
	 * @param string $content 内容
	 * @param array  $options 选项
	 * @return string prompt
	 */
	private function build_tag_generation_prompt( $content, $options ) {
		$platform_name = $options['platform'] === 'xiaohongshu' ? '小红书' : 'Instagram';
		$count = $options['count'];

		$prompt = "你是一个专业的社交媒体标签专家。请为以下{$platform_name}内容生成 {$count} 个高质量的标签(hashtag)。\n\n";
		$prompt .= "内容：\n{$content}\n\n";

		$prompt .= "要求：\n";

		if ( $options['platform'] === 'xiaohongshu' ) {
			$prompt .= "1. 生成5-8个标签，包含热门标签和细分标签的组合\n";
			$prompt .= "2. 标签要简短、精准，符合小红书用户习惯\n";
			$prompt .= "3. 混合使用流量标签(如#好物推荐)和精准标签(针对具体内容)\n";
			$prompt .= "4. 考虑当前热门趋势\n";
		} else {
			$prompt .= "1. 生成20-30个标签，包含大流量和小众标签\n";
			$prompt .= "2. 使用英文标签\n";
			$prompt .= "3. 包含品牌相关、描述性和行动号召标签\n";
			$prompt .= "4. 标签要易于搜索和发现\n";
		}

		if ( $options['strategy'] === 'trending' ) {
			$prompt .= "5. 重点关注当前热门和流行的标签\n";
		} elseif ( $options['strategy'] === 'niche' ) {
			$prompt .= "5. 重点关注细分领域的精准标签\n";
		} else {
			$prompt .= "5. 平衡热门标签和细分标签\n";
		}

		$prompt .= "\n请直接返回标签列表，每行一个标签，以#开头。不要添加其他说明文字。";

		return $prompt;
	}

	/**
	 * 解析AI响应
	 *
	 * @param string $response AI响应
	 * @return array 标签数组
	 */
	private function parse_ai_response( $response ) {
		$tags = array();

		// 按行分割
		$lines = explode( "\n", $response );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// 提取#开头的标签
			if ( preg_match( '/#[\p{L}\p{N}_]+/u', $line, $matches ) ) {
				$tag = $matches[0];
				// 清理标签
				$tag = preg_replace( '/[^\p{L}\p{N}_#]/u', '', $tag );
				if ( ! empty( $tag ) && ! in_array( $tag, $tags, true ) ) {
					$tags[] = $tag;
				}
			}
		}

		return $tags;
	}

	/**
	 * 基于关键词生成标签（降级方案）
	 *
	 * @param string $content 内容
	 * @param array  $options 选项
	 * @return array 标签数组
	 */
	private function generate_keyword_based_tags( $content, $options ) {
		$tags = array();

		// 提取关键词
		$keywords = $this->extract_keywords( $content );

		// 转换为标签
		foreach ( array_slice( $keywords, 0, $options['count'] ) as $keyword ) {
			$tags[] = '#' . $keyword;
		}

		// 添加通用标签
		$generic_tags = $this->get_generic_tags( $options['platform'] );
		$tags = array_merge( $tags, array_slice( $generic_tags, 0, max( 0, $options['count'] - count( $tags ) ) ) );

		return $tags;
	}

	/**
	 * 提取关键词
	 *
	 * @param string $content 内容
	 * @return array 关键词数组
	 */
	private function extract_keywords( $content ) {
		// 移除特殊字符
		$content = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $content );

		// 分词
		$words = preg_split( '/\s+/u', $content );

		// 统计词频
		$word_freq = array();
		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( mb_strlen( $word ) >= 2 ) {
				if ( ! isset( $word_freq[ $word ] ) ) {
					$word_freq[ $word ] = 0;
				}
				$word_freq[ $word ]++;
			}
		}

		// 排序
		arsort( $word_freq );

		// 过滤停用词
		$stopwords = array( '的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一', '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好', '自己', '这', '个', '们' );

		$keywords = array();
		foreach ( array_keys( $word_freq ) as $word ) {
			if ( ! in_array( $word, $stopwords, true ) ) {
				$keywords[] = $word;
			}
		}

		return $keywords;
	}

	/**
	 * 获取通用标签
	 *
	 * @param string $platform 平台
	 * @return array 标签数组
	 */
	private function get_generic_tags( $platform ) {
		if ( $platform === 'xiaohongshu' ) {
			return array(
				'#小红书', '#种草', '#分享', '#日常', '#好物推荐',
				'#生活记录', '#干货', '#实用',
			);
		} else {
			return array(
				'#instagood', '#photooftheday', '#love', '#fashion',
				'#beautiful', '#happy', '#follow', '#like4like',
				'#instadaily', '#picoftheday',
			);
		}
	}

	/**
	 * 获取热门标签
	 *
	 * @param string $platform 平台
	 * @param string $language 语言
	 * @return array 标签数组
	 */
	private function get_trending_tags( $platform, $language ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		// 获取最近30天的热门标签
		$where = $wpdb->prepare( 'platform = %s', $platform );

		$posts = $wpdb->get_results(
			"SELECT hashtags FROM {$table}
			WHERE {$where}
			AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			ORDER BY created_at DESC
			LIMIT 200",
			ARRAY_A
		);

		$tag_freq = array();
		foreach ( $posts as $post ) {
			$hashtags = json_decode( $post['hashtags'], true );
			if ( is_array( $hashtags ) ) {
				foreach ( $hashtags as $tag ) {
					$tag = trim( $tag );
					if ( ! isset( $tag_freq[ $tag ] ) ) {
						$tag_freq[ $tag ] = 0;
					}
					$tag_freq[ $tag ]++;
				}
			}
		}

		// 排序并返回前10
		arsort( $tag_freq );

		return array_keys( array_slice( $tag_freq, 0, 10, true ) );
	}

	/**
	 * 获取历史高效标签
	 *
	 * @param string $platform 平台
	 * @return array 标签数组
	 */
	private function get_historical_performing_tags( $platform ) {
		// 如果有质量评分系统，获取高分内容的标签
		if ( ! class_exists( 'AISCG_Content_Scorer' ) ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		$where = $wpdb->prepare( 'platform = %s', $platform );

		$posts = $wpdb->get_results(
			"SELECT * FROM {$table}
			WHERE {$where}
			AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
			ORDER BY created_at DESC
			LIMIT 100",
			ARRAY_A
		);

		$scorer = new AISCG_Content_Scorer();
		$high_performing_tags = array();

		foreach ( $posts as $post ) {
			$score = $scorer->score_content( $post );

			if ( $score['overall'] >= 80 ) {
				$hashtags = json_decode( $post['hashtags'], true );
				if ( is_array( $hashtags ) ) {
					$high_performing_tags = array_merge( $high_performing_tags, $hashtags );
				}
			}
		}

		// 去重并统计
		$tag_freq = array_count_values( $high_performing_tags );
		arsort( $tag_freq );

		return array_keys( array_slice( $tag_freq, 0, 5, true ) );
	}

	/**
	 * 混合标签
	 *
	 * @param array $ai_tags AI生成的标签
	 * @param array $trending_tags 热门标签
	 * @param array $historical_tags 历史标签
	 * @param array $options 选项
	 * @return array 混合后的标签
	 */
	private function mix_tags( $ai_tags, $trending_tags, $historical_tags, $options ) {
		$mixed = array();

		// 根据策略确定比例
		if ( $options['strategy'] === 'trending' ) {
			// 60% AI, 30% 热门, 10% 历史
			$ai_count = ceil( $options['count'] * 0.6 );
			$trending_count = ceil( $options['count'] * 0.3 );
			$historical_count = $options['count'] - $ai_count - $trending_count;
		} elseif ( $options['strategy'] === 'niche' ) {
			// 80% AI, 10% 热门, 10% 历史
			$ai_count = ceil( $options['count'] * 0.8 );
			$trending_count = ceil( $options['count'] * 0.1 );
			$historical_count = $options['count'] - $ai_count - $trending_count;
		} else {
			// mixed: 70% AI, 20% 热门, 10% 历史
			$ai_count = ceil( $options['count'] * 0.7 );
			$trending_count = ceil( $options['count'] * 0.2 );
			$historical_count = $options['count'] - $ai_count - $trending_count;
		}

		// 添加AI标签
		foreach ( array_slice( $ai_tags, 0, $ai_count ) as $tag ) {
			if ( ! in_array( $tag, $mixed, true ) ) {
				$mixed[] = $tag;
			}
		}

		// 添加热门标签
		foreach ( array_slice( $trending_tags, 0, $trending_count ) as $tag ) {
			if ( ! in_array( $tag, $mixed, true ) ) {
				$mixed[] = $tag;
			}
		}

		// 添加历史标签
		foreach ( array_slice( $historical_tags, 0, $historical_count ) as $tag ) {
			if ( ! in_array( $tag, $mixed, true ) ) {
				$mixed[] = $tag;
			}
		}

		// 如果数量不够，从AI标签中补充
		if ( count( $mixed ) < $options['count'] ) {
			foreach ( $ai_tags as $tag ) {
				if ( ! in_array( $tag, $mixed, true ) ) {
					$mixed[] = $tag;
					if ( count( $mixed ) >= $options['count'] ) {
						break;
					}
				}
			}
		}

		return $mixed;
	}

	/**
	 * 验证和优化标签
	 *
	 * @param array $tags 标签数组
	 * @param array $options 选项
	 * @return array 优化后的标签
	 */
	private function validate_and_optimize_tags( $tags, $options ) {
		$optimized = array();

		foreach ( $tags as $tag ) {
			// 确保以#开头
			if ( ! str_starts_with( $tag, '#' ) ) {
				$tag = '#' . $tag;
			}

			// 移除特殊字符（保留字母、数字、下划线）
			$tag = preg_replace( '/[^\p{L}\p{N}_#]/u', '', $tag );

			// 长度验证
			$tag_length = mb_strlen( $tag );
			if ( $tag_length < 2 || $tag_length > 50 ) {
				continue;
			}

			// 去重
			if ( ! in_array( $tag, $optimized, true ) ) {
				$optimized[] = $tag;
			}

			// 达到目标数量则停止
			if ( count( $optimized ) >= $options['count'] ) {
				break;
			}
		}

		return $optimized;
	}

	/**
	 * 分析标签表现
	 *
	 * @param string $tag 标签
	 * @return array 分析结果
	 */
	public function analyze_tag_performance( $tag ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		// 清理标签
		$tag = trim( $tag, '# ' );

		// 查找使用该标签的内容
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE hashtags LIKE %s
				AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
				ORDER BY created_at DESC",
				'%' . $wpdb->esc_like( $tag ) . '%'
			),
			ARRAY_A
		);

		if ( empty( $posts ) ) {
			return array(
				'success' => false,
				'message' => '未找到使用该标签的内容',
			);
		}

		// 分析质量
		$total_score = 0;
		$score_count = 0;

		if ( class_exists( 'AISCG_Content_Scorer' ) ) {
			$scorer = new AISCG_Content_Scorer();

			foreach ( $posts as $post ) {
				$score = $scorer->score_content( $post );
				$total_score += $score['overall'];
				$score_count++;
			}
		}

		$avg_score = $score_count > 0 ? round( $total_score / $score_count, 1 ) : 0;

		// 平台分布
		$platform_dist = array();
		foreach ( $posts as $post ) {
			$platform = $post['platform'];
			if ( ! isset( $platform_dist[ $platform ] ) ) {
				$platform_dist[ $platform ] = 0;
			}
			$platform_dist[ $platform ]++;
		}

		// 使用趋势
		$monthly_usage = array();
		foreach ( $posts as $post ) {
			$month = date( 'Y-m', strtotime( $post['created_at'] ) );
			if ( ! isset( $monthly_usage[ $month ] ) ) {
				$monthly_usage[ $month ] = 0;
			}
			$monthly_usage[ $month ]++;
		}

		return array(
			'success' => true,
			'tag' => '#' . $tag,
			'statistics' => array(
				'total_usage' => count( $posts ),
				'avg_quality_score' => $avg_score,
				'platform_distribution' => $platform_dist,
				'monthly_trend' => $monthly_usage,
				'first_used' => $posts[ count( $posts ) - 1 ]['created_at'],
				'last_used' => $posts[0]['created_at'],
			),
			'recommendation' => $this->generate_tag_recommendation( count( $posts ), $avg_score ),
		);
	}

	/**
	 * 生成标签推荐
	 *
	 * @param int   $usage_count 使用次数
	 * @param float $avg_score 平均分数
	 * @return string 推荐文本
	 */
	private function generate_tag_recommendation( $usage_count, $avg_score ) {
		if ( $usage_count > 20 && $avg_score >= 80 ) {
			return '🌟 高效标签！建议继续使用';
		} elseif ( $usage_count > 10 && $avg_score >= 70 ) {
			return '👍 表现良好，可以继续使用';
		} elseif ( $usage_count > 5 && $avg_score >= 60 ) {
			return '✅ 一般表现，可适度使用';
		} elseif ( $usage_count < 5 ) {
			return '🆕 新标签，需要更多数据来评估';
		} else {
			return '⚠️ 表现欠佳，建议替换为其他标签';
		}
	}

	/**
	 * 批量生成标签
	 *
	 * @param array $contents 内容数组
	 * @param array $options 选项
	 * @return array 批量结果
	 */
	public function batch_generate_tags( $contents, $options = array() ) {
		$results = array();

		foreach ( $contents as $index => $content ) {
			$this->logger->info( "批量生成标签 {$index}/" . count( $contents ) );

			$result = $this->generate_tags( $content, $options );
			$results[] = $result;

			// 避免API限流，添加延迟
			if ( $index < count( $contents ) - 1 ) {
				sleep( 1 );
			}
		}

		return array(
			'success' => true,
			'total' => count( $contents ),
			'results' => $results,
		);
	}
}
