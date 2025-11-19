<?php
/**
 * Content Quality Detector Class
 *
 * 内容质量自动检测器 - 发布前自动检测质量问题
 *
 * @package AI_Social_Content_Generator
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_Quality_Detector Class
 */
class AISCG_Quality_Detector {

	/**
	 * 检测级别
	 *
	 * @var array
	 */
	private $severity_levels = array(
		'critical' => '严重',
		'warning'  => '警告',
		'info'     => '提示',
	);

	/**
	 * 敏感词库
	 *
	 * @var array
	 */
	private $sensitive_words = array(
		// 政治敏感
		'政治', '敏感', '违禁',
		// 违法违规
		'赌博', '诈骗', '色情', '暴力',
		// 广告营销
		'加微信', '扫码', '免费领取',
	);

	/**
	 * 平台规范
	 *
	 * @var array
	 */
	private $platform_rules = array(
		'xiaohongshu' => array(
			'min_length' => 50,
			'max_length' => 2000,
			'max_hashtags' => 10,
			'min_hashtags' => 3,
			'forbidden_patterns' => array( '微信', 'VX', 'V信', '外链', 'http' ),
		),
		'instagram' => array(
			'min_length' => 50,
			'max_length' => 2200,
			'max_hashtags' => 30,
			'min_hashtags' => 5,
			'forbidden_patterns' => array(),
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
	 * 检测内容质量
	 *
	 * @param mixed $content 内容（数组或ID）
	 * @param array $options 选项
	 * @return array 检测结果
	 */
	public function detect( $content, $options = array() ) {
		$defaults = array(
			'auto_fix' => false,
			'strict_mode' => false,
			'check_ai' => false,
		);

		$options = wp_parse_args( $options, $defaults );

		// 获取内容数据
		$post = is_array( $content ) ? $content : $this->get_post_data( $content );

		if ( ! $post ) {
			return array(
				'success' => false,
				'error' => '无效的内容',
			);
		}

		$this->logger->info( '开始质量检测', array(
			'post_id' => $post['id'] ?? 'new',
			'platform' => $post['platform'] ?? '',
		) );

		$issues = array();

		// 1. 敏感词检测
		$issues = array_merge( $issues, $this->check_sensitive_words( $post ) );

		// 2. 平台规范检测
		$issues = array_merge( $issues, $this->check_platform_rules( $post ) );

		// 3. 格式问题检测
		$issues = array_merge( $issues, $this->check_format_issues( $post ) );

		// 4. 可读性检测
		$issues = array_merge( $issues, $this->check_readability( $post ) );

		// 5. SEO问题检测
		$issues = array_merge( $issues, $this->check_seo_issues( $post ) );

		// 6. AI内容检测（如果启用）
		if ( $options['check_ai'] ) {
			$issues = array_merge( $issues, $this->check_ai_quality( $post ) );
		}

		// 计算总体评分
		$score = $this->calculate_quality_score( $issues );

		// 生成修复建议
		$fixes = $this->generate_fix_suggestions( $issues, $post );

		// 自动修复（如果启用）
		$fixed_content = null;
		if ( $options['auto_fix'] && ! empty( $fixes ) ) {
			$fixed_content = $this->auto_fix_issues( $post, $fixes );
		}

		$result = array(
			'success' => true,
			'quality_score' => $score,
			'total_issues' => count( $issues ),
			'issues' => $issues,
			'issues_by_severity' => $this->group_by_severity( $issues ),
			'can_publish' => $this->can_publish( $issues, $score, $options['strict_mode'] ),
			'fixes' => $fixes,
			'fixed_content' => $fixed_content,
			'summary' => $this->generate_summary( $issues, $score ),
		);

		$this->logger->info( '质量检测完成', array(
			'score' => $score,
			'issues' => count( $issues ),
			'can_publish' => $result['can_publish'],
		) );

		return $result;
	}

	/**
	 * 检测敏感词
	 *
	 * @param array $post 文章数据
	 * @return array 问题列表
	 */
	private function check_sensitive_words( $post ) {
		$issues = array();
		$text = $post['title'] . ' ' . $post['content'];

		foreach ( $this->sensitive_words as $word ) {
			if ( mb_stripos( $text, $word ) !== false ) {
				$issues[] = array(
					'type' => 'sensitive_word',
					'severity' => 'critical',
					'message' => "检测到敏感词: {$word}",
					'detail' => "内容中包含敏感词「{$word}」，可能违反平台规范",
					'location' => $this->find_word_location( $text, $word ),
					'fixable' => true,
				);
			}
		}

		return $issues;
	}

	/**
	 * 检测平台规范
	 *
	 * @param array $post 文章数据
	 * @return array 问题列表
	 */
	private function check_platform_rules( $post ) {
		$issues = array();
		$platform = $post['platform'] ?? '';

		if ( ! isset( $this->platform_rules[ $platform ] ) ) {
			return $issues;
		}

		$rules = $this->platform_rules[ $platform ];
		$content_length = mb_strlen( $post['content'] );

		// 检查长度
		if ( $content_length < $rules['min_length'] ) {
			$issues[] = array(
				'type' => 'length_too_short',
				'severity' => 'warning',
				'message' => "内容过短（{$content_length}字）",
				'detail' => "建议内容长度至少 {$rules['min_length']} 字，当前 {$content_length} 字",
				'fixable' => false,
			);
		}

		if ( $content_length > $rules['max_length'] ) {
			$issues[] = array(
				'type' => 'length_too_long',
				'severity' => 'warning',
				'message' => "内容过长（{$content_length}字）",
				'detail' => "建议内容长度不超过 {$rules['max_length']} 字，当前 {$content_length} 字",
				'fixable' => true,
			);
		}

		// 检查标签数量
		$hashtags = json_decode( $post['hashtags'] ?? '[]', true );
		$hashtag_count = is_array( $hashtags ) ? count( $hashtags ) : 0;

		if ( $hashtag_count < $rules['min_hashtags'] ) {
			$issues[] = array(
				'type' => 'hashtags_too_few',
				'severity' => 'warning',
				'message' => "标签过少（{$hashtag_count}个）",
				'detail' => "建议使用至少 {$rules['min_hashtags']} 个标签，当前 {$hashtag_count} 个",
				'fixable' => true,
			);
		}

		if ( $hashtag_count > $rules['max_hashtags'] ) {
			$issues[] = array(
				'type' => 'hashtags_too_many',
				'severity' => 'warning',
				'message' => "标签过多（{$hashtag_count}个）",
				'detail' => "建议标签数量不超过 {$rules['max_hashtags']} 个，当前 {$hashtag_count} 个",
				'fixable' => true,
			);
		}

		// 检查违禁模式
		$text = $post['title'] . ' ' . $post['content'];
		foreach ( $rules['forbidden_patterns'] as $pattern ) {
			if ( mb_stripos( $text, $pattern ) !== false ) {
				$issues[] = array(
					'type' => 'forbidden_pattern',
					'severity' => 'critical',
					'message' => "包含违禁内容: {$pattern}",
					'detail' => "{$platform}平台禁止包含「{$pattern}」等内容",
					'fixable' => true,
				);
			}
		}

		return $issues;
	}

	/**
	 * 检测格式问题
	 *
	 * @param array $post 文章数据
	 * @return array 问题列表
	 */
	private function check_format_issues( $post ) {
		$issues = array();
		$content = $post['content'];

		// 检查过多换行
		if ( substr_count( $content, "\n\n\n" ) > 0 ) {
			$issues[] = array(
				'type' => 'excessive_linebreaks',
				'severity' => 'info',
				'message' => '存在过多空行',
				'detail' => '内容中有连续3个以上空行，影响阅读体验',
				'fixable' => true,
			);
		}

		// 检查过多emoji
		$emoji_count = preg_match_all( '/[\x{1F300}-\x{1F9FF}]/u', $content );
		if ( $emoji_count > 20 ) {
			$issues[] = array(
				'type' => 'excessive_emoji',
				'severity' => 'info',
				'message' => "emoji使用过多（{$emoji_count}个）",
				'detail' => '建议emoji数量控制在15个以内，过多会显得杂乱',
				'fixable' => false,
			);
		}

		// 检查是否有标题
		if ( empty( trim( $post['title'] ) ) ) {
			$issues[] = array(
				'type' => 'missing_title',
				'severity' => 'critical',
				'message' => '缺少标题',
				'detail' => '内容必须有标题',
				'fixable' => false,
			);
		}

		// 检查标点符号
		if ( preg_match( '/[！]{3,}|[？]{3,}/', $content ) ) {
			$issues[] = array(
				'type' => 'excessive_punctuation',
				'severity' => 'info',
				'message' => '标点符号使用过多',
				'detail' => '连续使用3个以上相同标点符号，建议精简',
				'fixable' => true,
			);
		}

		return $issues;
	}

	/**
	 * 检测可读性
	 *
	 * @param array $post 文章数据
	 * @return array 问题列表
	 */
	private function check_readability( $post ) {
		$issues = array();
		$content = $post['content'];

		// 检查段落长度
		$paragraphs = explode( "\n\n", $content );
		$long_paragraphs = 0;

		foreach ( $paragraphs as $para ) {
			if ( mb_strlen( trim( $para ) ) > 200 ) {
				$long_paragraphs++;
			}
		}

		if ( $long_paragraphs > count( $paragraphs ) / 2 ) {
			$issues[] = array(
				'type' => 'long_paragraphs',
				'severity' => 'warning',
				'message' => '段落过长影响阅读',
				'detail' => '超过一半的段落长度超过200字，建议分段',
				'fixable' => true,
			);
		}

		// 检查句子长度
		$sentences = preg_split( '/[。！？]/', $content );
		$long_sentences = 0;

		foreach ( $sentences as $sentence ) {
			if ( mb_strlen( trim( $sentence ) ) > 80 ) {
				$long_sentences++;
			}
		}

		if ( $long_sentences > count( $sentences ) / 3 ) {
			$issues[] = array(
				'type' => 'long_sentences',
				'severity' => 'info',
				'message' => '句子较长',
				'detail' => '较多句子超过80字，建议使用更多标点分隔',
				'fixable' => false,
			);
		}

		return $issues;
	}

	/**
	 * 检测SEO问题
	 *
	 * @param array $post 文章数据
	 * @return array 问题列表
	 */
	private function check_seo_issues( $post ) {
		$issues = array();

		// 检查标题长度
		$title_length = mb_strlen( $post['title'] );
		if ( $title_length < 10 ) {
			$issues[] = array(
				'type' => 'title_too_short',
				'severity' => 'warning',
				'message' => '标题过短',
				'detail' => "标题长度建议10-60字，当前 {$title_length} 字",
				'fixable' => false,
			);
		}

		if ( $title_length > 60 ) {
			$issues[] = array(
				'type' => 'title_too_long',
				'severity' => 'warning',
				'message' => '标题过长',
				'detail' => "标题长度建议10-60字，当前 {$title_length} 字",
				'fixable' => true,
			);
		}

		// 检查关键词重复
		$content = $post['content'];
		$words = preg_split( '/\s+/u', $content );
		$word_freq = array_count_values( $words );
		arsort( $word_freq );

		foreach ( array_slice( $word_freq, 0, 5, true ) as $word => $freq ) {
			if ( mb_strlen( $word ) >= 2 && $freq > 10 ) {
				$issues[] = array(
					'type' => 'keyword_stuffing',
					'severity' => 'warning',
					'message' => "关键词「{$word}」重复过多",
					'detail' => "「{$word}」出现 {$freq} 次，可能被判定为关键词堆砌",
					'fixable' => false,
				);
			}
		}

		return $issues;
	}

	/**
	 * AI质量检测
	 *
	 * @param array $post 文章数据
	 * @return array 问题列表
	 */
	private function check_ai_quality( $post ) {
		$issues = array();

		try {
			// 使用AI分析内容质量
			$ai_service = AISCG_AI_Service_Factory::create( 'gpt-3.5-turbo' );

			$prompt = "请分析以下内容的质量问题，包括：语法错误、逻辑问题、表达不清等。\n\n";
			$prompt .= "标题：{$post['title']}\n\n";
			$prompt .= "内容：\n" . mb_substr( $post['content'], 0, 1000 ) . "\n\n";
			$prompt .= "请简洁列出主要问题（如果有），每行一个问题。如果没有问题，返回「无明显问题」。";

			$response = $ai_service->generate( $prompt, array(
				'temperature' => 0.3,
				'max_tokens' => 300,
			) );

			// 解析AI响应
			if ( $response && ! str_contains( $response, '无明显问题' ) ) {
				$ai_issues = explode( "\n", $response );
				foreach ( $ai_issues as $issue ) {
					$issue = trim( $issue );
					if ( ! empty( $issue ) && $issue !== '无明显问题' ) {
						$issues[] = array(
							'type' => 'ai_detected',
							'severity' => 'info',
							'message' => 'AI检测到问题',
							'detail' => $issue,
							'fixable' => false,
						);
					}
				}
			}
		} catch ( Exception $e ) {
			$this->logger->warning( 'AI质量检测失败', array(
				'error' => $e->getMessage(),
			) );
		}

		return $issues;
	}

	/**
	 * 计算质量分数
	 *
	 * @param array $issues 问题列表
	 * @return int 分数（0-100）
	 */
	private function calculate_quality_score( $issues ) {
		$score = 100;

		foreach ( $issues as $issue ) {
			switch ( $issue['severity'] ) {
				case 'critical':
					$score -= 20;
					break;
				case 'warning':
					$score -= 10;
					break;
				case 'info':
					$score -= 5;
					break;
			}
		}

		return max( 0, $score );
	}

	/**
	 * 按严重程度分组
	 *
	 * @param array $issues 问题列表
	 * @return array 分组结果
	 */
	private function group_by_severity( $issues ) {
		$grouped = array(
			'critical' => array(),
			'warning' => array(),
			'info' => array(),
		);

		foreach ( $issues as $issue ) {
			$severity = $issue['severity'] ?? 'info';
			$grouped[ $severity ][] = $issue;
		}

		return $grouped;
	}

	/**
	 * 判断是否可以发布
	 *
	 * @param array $issues 问题列表
	 * @param int   $score 质量分数
	 * @param bool  $strict_mode 严格模式
	 * @return bool
	 */
	private function can_publish( $issues, $score, $strict_mode ) {
		// 检查是否有严重问题
		$has_critical = false;
		foreach ( $issues as $issue ) {
			if ( $issue['severity'] === 'critical' ) {
				$has_critical = true;
				break;
			}
		}

		if ( $has_critical ) {
			return false;
		}

		// 严格模式下分数必须>=80
		if ( $strict_mode && $score < 80 ) {
			return false;
		}

		// 普通模式下分数>=60即可
		return $score >= 60;
	}

	/**
	 * 生成修复建议
	 *
	 * @param array $issues 问题列表
	 * @param array $post 文章数据
	 * @return array 修复建议
	 */
	private function generate_fix_suggestions( $issues, $post ) {
		$fixes = array();

		foreach ( $issues as $issue ) {
			if ( ! $issue['fixable'] ) {
				continue;
			}

			$fix = array(
				'issue_type' => $issue['type'],
				'action' => '',
			);

			switch ( $issue['type'] ) {
				case 'sensitive_word':
					$fix['action'] = '删除或替换敏感词';
					$fix['auto_fixable'] = true;
					break;

				case 'length_too_long':
					$fix['action'] = '精简内容，删除冗余部分';
					$fix['auto_fixable'] = false;
					break;

				case 'hashtags_too_few':
					$fix['action'] = '使用AI生成更多相关标签';
					$fix['auto_fixable'] = true;
					break;

				case 'hashtags_too_many':
					$fix['action'] = '删除部分不相关的标签';
					$fix['auto_fixable'] = true;
					break;

				case 'forbidden_pattern':
					$fix['action'] = '删除违禁内容';
					$fix['auto_fixable'] = true;
					break;

				case 'excessive_linebreaks':
					$fix['action'] = '删除多余空行';
					$fix['auto_fixable'] = true;
					break;

				case 'excessive_punctuation':
					$fix['action'] = '简化标点符号';
					$fix['auto_fixable'] = true;
					break;

				case 'long_paragraphs':
					$fix['action'] = '分段，每段控制在200字以内';
					$fix['auto_fixable'] = false;
					break;
			}

			if ( ! empty( $fix['action'] ) ) {
				$fixes[] = $fix;
			}
		}

		return $fixes;
	}

	/**
	 * 自动修复问题
	 *
	 * @param array $post 文章数据
	 * @param array $fixes 修复列表
	 * @return array 修复后的内容
	 */
	private function auto_fix_issues( $post, $fixes ) {
		$fixed = $post;

		foreach ( $fixes as $fix ) {
			if ( empty( $fix['auto_fixable'] ) ) {
				continue;
			}

			switch ( $fix['issue_type'] ) {
				case 'sensitive_word':
					$fixed['content'] = $this->remove_sensitive_words( $fixed['content'] );
					break;

				case 'hashtags_too_many':
					$hashtags = json_decode( $fixed['hashtags'], true );
					if ( is_array( $hashtags ) ) {
						$platform = $fixed['platform'] ?? 'xiaohongshu';
						$max = $this->platform_rules[ $platform ]['max_hashtags'] ?? 10;
						$hashtags = array_slice( $hashtags, 0, $max );
						$fixed['hashtags'] = wp_json_encode( $hashtags );
					}
					break;

				case 'forbidden_pattern':
					$platform = $fixed['platform'] ?? 'xiaohongshu';
					$patterns = $this->platform_rules[ $platform ]['forbidden_patterns'] ?? array();
					foreach ( $patterns as $pattern ) {
						$fixed['content'] = str_ireplace( $pattern, '***', $fixed['content'] );
						$fixed['title'] = str_ireplace( $pattern, '***', $fixed['title'] );
					}
					break;

				case 'excessive_linebreaks':
					$fixed['content'] = preg_replace( '/\n{3,}/', "\n\n", $fixed['content'] );
					break;

				case 'excessive_punctuation':
					$fixed['content'] = preg_replace( '/([！？]){3,}/', '$1$1', $fixed['content'] );
					break;
			}
		}

		return $fixed;
	}

	/**
	 * 移除敏感词
	 *
	 * @param string $text 文本
	 * @return string 处理后的文本
	 */
	private function remove_sensitive_words( $text ) {
		foreach ( $this->sensitive_words as $word ) {
			$text = str_ireplace( $word, str_repeat( '*', mb_strlen( $word ) ), $text );
		}
		return $text;
	}

	/**
	 * 查找词语位置
	 *
	 * @param string $text 文本
	 * @param string $word 词语
	 * @return array 位置信息
	 */
	private function find_word_location( $text, $word ) {
		$pos = mb_stripos( $text, $word );
		if ( $pos === false ) {
			return null;
		}

		$start = max( 0, $pos - 20 );
		$end = min( mb_strlen( $text ), $pos + mb_strlen( $word ) + 20 );

		return array(
			'position' => $pos,
			'context' => mb_substr( $text, $start, $end - $start ),
		);
	}

	/**
	 * 生成摘要
	 *
	 * @param array $issues 问题列表
	 * @param int   $score 分数
	 * @return string 摘要
	 */
	private function generate_summary( $issues, $score ) {
		$critical_count = 0;
		$warning_count = 0;
		$info_count = 0;

		foreach ( $issues as $issue ) {
			switch ( $issue['severity'] ) {
				case 'critical':
					$critical_count++;
					break;
				case 'warning':
					$warning_count++;
					break;
				case 'info':
					$info_count++;
					break;
			}
		}

		if ( $score >= 90 ) {
			return "✅ 内容质量优秀（{$score}分），可以发布";
		} elseif ( $score >= 80 ) {
			return "👍 内容质量良好（{$score}分），建议修复 {$warning_count} 个警告后发布";
		} elseif ( $score >= 60 ) {
			return "⚠️ 内容质量一般（{$score}分），建议修复 {$warning_count} 个警告";
		} else {
			return "❌ 内容质量较差（{$score}分），发现 {$critical_count} 个严重问题，不建议发布";
		}
	}

	/**
	 * 获取文章数据
	 *
	 * @param int $post_id 文章ID
	 * @return array|false
	 */
	private function get_post_data( $post_id ) {
		$db = new AISCG_Database();
		return $db->get_post( $post_id );
	}

	/**
	 * 批量检测
	 *
	 * @param array $post_ids 文章ID数组
	 * @param array $options 选项
	 * @return array 批量结果
	 */
	public function batch_detect( $post_ids, $options = array() ) {
		$results = array();

		foreach ( $post_ids as $post_id ) {
			$result = $this->detect( $post_id, $options );
			$results[] = array(
				'post_id' => $post_id,
				'detection' => $result,
			);
		}

		return array(
			'success' => true,
			'total' => count( $post_ids ),
			'results' => $results,
		);
	}

	/**
	 * 获取检测统计
	 *
	 * @return array 统计数据
	 */
	public function get_detection_statistics() {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		$posts = $wpdb->get_results(
			"SELECT * FROM {$table} LIMIT 100",
			ARRAY_A
		);

		$total_issues = 0;
		$issue_types = array();
		$avg_score = 0;

		foreach ( $posts as $post ) {
			$result = $this->detect( $post, array( 'check_ai' => false ) );

			$total_issues += $result['total_issues'];
			$avg_score += $result['quality_score'];

			foreach ( $result['issues'] as $issue ) {
				$type = $issue['type'];
				if ( ! isset( $issue_types[ $type ] ) ) {
					$issue_types[ $type ] = 0;
				}
				$issue_types[ $type ]++;
			}
		}

		arsort( $issue_types );

		return array(
			'success' => true,
			'posts_checked' => count( $posts ),
			'total_issues' => $total_issues,
			'avg_issues_per_post' => round( $total_issues / count( $posts ), 1 ),
			'avg_quality_score' => round( $avg_score / count( $posts ), 1 ),
			'common_issues' => array_slice( $issue_types, 0, 10, true ),
		);
	}
}
