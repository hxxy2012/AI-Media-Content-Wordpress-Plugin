<?php
/**
 * Content Classifier Class
 *
 * 内容智能分类系统 - 自动识别和分类内容
 *
 * @package AI_Social_Content_Generator
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_Content_Classifier Class
 */
class AISCG_Content_Classifier {

	/**
	 * 预定义分类
	 *
	 * @var array
	 */
	private $categories = array(
		'lifestyle' => array(
			'name' => '生活方式',
			'keywords' => array( '生活', '日常', 'vlog', '分享', '记录' ),
		),
		'beauty' => array(
			'name' => '美妆护肤',
			'keywords' => array( '美妆', '护肤', '化妆', '彩妆', '口红', '面膜' ),
		),
		'fashion' => array(
			'name' => '时尚穿搭',
			'keywords' => array( '穿搭', '时尚', '服装', '搭配', 'OOTD', '造型' ),
		),
		'food' => array(
			'name' => '美食',
			'keywords' => array( '美食', '吃播', '探店', '餐厅', '食谱', '烹饪' ),
		),
		'travel' => array(
			'name' => '旅游旅行',
			'keywords' => array( '旅行', '旅游', '打卡', '景点', '攻略', '行程' ),
		),
		'fitness' => array(
			'name' => '健身运动',
			'keywords' => array( '健身', '运动', '锻炼', '瑜伽', '减肥', '塑形' ),
		),
		'tech' => array(
			'name' => '科技数码',
			'keywords' => array( '科技', '数码', '手机', '电脑', '评测', '开箱' ),
		),
		'education' => array(
			'name' => '教育学习',
			'keywords' => array( '学习', '教育', '课程', '知识', '技能', '培训' ),
		),
		'entertainment' => array(
			'name' => '娱乐影视',
			'keywords' => array( '娱乐', '电影', '电视剧', '综艺', '明星', '八卦' ),
		),
		'home' => array(
			'name' => '家居家装',
			'keywords' => array( '家居', '装修', '家装', '设计', '软装', '布置' ),
		),
		'parenting' => array(
			'name' => '母婴育儿',
			'keywords' => array( '育儿', '母婴', '宝宝', '孕期', '亲子', '儿童' ),
		),
		'pets' => array(
			'name' => '宠物',
			'keywords' => array( '宠物', '猫', '狗', '养宠', '铲屎官', '萌宠' ),
		),
	);

	/**
	 * 内容类型
	 *
	 * @var array
	 */
	private $content_types = array(
		'tutorial' => '教程攻略',
		'review' => '测评推荐',
		'experience' => '经验分享',
		'showcase' => '作品展示',
		'vlog' => '日常记录',
		'tips' => '技巧干货',
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
	 * 分类内容
	 *
	 * @param mixed $content 内容（可以是ID或数组）
	 * @param array $options 选项
	 * @return array 分类结果
	 */
	public function classify( $content, $options = array() ) {
		$defaults = array(
			'use_ai' => true,
			'confidence_threshold' => 0.6,
			'return_top_n' => 3,
		);

		$options = wp_parse_args( $options, $defaults );

		// 获取内容文本
		$text = is_array( $content ) ? $content['title'] . ' ' . $content['content'] : '';
		if ( is_numeric( $content ) ) {
			$db = new AISCG_Database();
			$post = $db->get_post( $content );
			if ( $post ) {
				$text = $post['title'] . ' ' . $post['content'];
			}
		}

		if ( empty( $text ) ) {
			return array(
				'success' => false,
				'error' => '无效的内容',
			);
		}

		$this->logger->info( '开始内容分类', array(
			'length' => mb_strlen( $text ),
			'use_ai' => $options['use_ai'],
		) );

		// 使用关键词匹配分类
		$keyword_results = $this->classify_by_keywords( $text );

		// 使用AI分类（如果启用）
		$ai_results = array();
		if ( $options['use_ai'] ) {
			$ai_results = $this->classify_with_ai( $text );
		}

		// 合并结果
		$final_results = $this->merge_classification_results( $keyword_results, $ai_results, $options );

		// 识别内容类型
		$content_type = $this->identify_content_type( $text );

		// 提取主题标签
		$topics = $this->extract_topics( $text );

		$this->logger->info( '内容分类完成', array(
			'categories' => array_column( $final_results, 'category' ),
			'content_type' => $content_type,
		) );

		return array(
			'success' => true,
			'categories' => $final_results,
			'primary_category' => ! empty( $final_results ) ? $final_results[0] : null,
			'content_type' => $content_type,
			'topics' => $topics,
			'metadata' => array(
				'method' => $options['use_ai'] ? 'hybrid' : 'keyword',
				'confidence' => ! empty( $final_results ) ? $final_results[0]['confidence'] : 0,
			),
		);
	}

	/**
	 * 基于关键词分类
	 *
	 * @param string $text 文本
	 * @return array 分类结果
	 */
	private function classify_by_keywords( $text ) {
		$scores = array();

		foreach ( $this->categories as $category_id => $category_data ) {
			$score = 0;

			foreach ( $category_data['keywords'] as $keyword ) {
				// 计算关键词出现次数
				$count = mb_substr_count( $text, $keyword );
				$score += $count;
			}

			if ( $score > 0 ) {
				$scores[ $category_id ] = $score;
			}
		}

		// 排序
		arsort( $scores );

		// 转换为百分比置信度
		$max_score = ! empty( $scores ) ? max( $scores ) : 1;
		$results = array();

		foreach ( $scores as $category_id => $score ) {
			$results[] = array(
				'category' => $category_id,
				'category_name' => $this->categories[ $category_id ]['name'],
				'confidence' => round( $score / $max_score, 2 ),
				'method' => 'keyword',
			);
		}

		return $results;
	}

	/**
	 * 使用AI分类
	 *
	 * @param string $text 文本
	 * @return array 分类结果
	 */
	private function classify_with_ai( $text ) {
		try {
			// 创建AI服务
			$ai_service = AISCG_AI_Service_Factory::create( 'gpt-3.5-turbo' );

			// 构建prompt
			$categories_list = '';
			foreach ( $this->categories as $id => $data ) {
				$categories_list .= "- {$id}: {$data['name']}\n";
			}

			$prompt = "请将以下内容分类到最合适的类别中。\n\n";
			$prompt .= "可选类别：\n{$categories_list}\n";
			$prompt .= "内容：\n" . mb_substr( $text, 0, 1000 ) . "\n\n";
			$prompt .= "请返回最相关的3个类别ID及其置信度（0-1之间），格式如：category_id:confidence，每行一个。";

			// 调用AI
			$response = $ai_service->generate( $prompt, array(
				'temperature' => 0.3,
				'max_tokens' => 150,
			) );

			// 解析响应
			return $this->parse_ai_classification_response( $response );

		} catch ( Exception $e ) {
			$this->logger->error( 'AI分类失败', array(
				'error' => $e->getMessage(),
			) );

			return array();
		}
	}

	/**
	 * 解析AI分类响应
	 *
	 * @param string $response AI响应
	 * @return array 分类结果
	 */
	private function parse_ai_classification_response( $response ) {
		$results = array();
		$lines = explode( "\n", $response );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// 匹配 category_id:confidence 格式
			if ( preg_match( '/^([a-z_]+):([0-9.]+)$/i', $line, $matches ) ) {
				$category_id = $matches[1];
				$confidence = (float) $matches[2];

				if ( isset( $this->categories[ $category_id ] ) && $confidence > 0 && $confidence <= 1 ) {
					$results[] = array(
						'category' => $category_id,
						'category_name' => $this->categories[ $category_id ]['name'],
						'confidence' => $confidence,
						'method' => 'ai',
					);
				}
			}
		}

		return $results;
	}

	/**
	 * 合并分类结果
	 *
	 * @param array $keyword_results 关键词结果
	 * @param array $ai_results AI结果
	 * @param array $options 选项
	 * @return array 合并后的结果
	 */
	private function merge_classification_results( $keyword_results, $ai_results, $options ) {
		$merged = array();
		$seen_categories = array();

		// AI结果优先级更高（权重70%）
		foreach ( $ai_results as $result ) {
			if ( $result['confidence'] >= $options['confidence_threshold'] ) {
				$merged[] = $result;
				$seen_categories[] = $result['category'];
			}
		}

		// 添加关键词结果（权重30%）
		foreach ( $keyword_results as $result ) {
			if ( ! in_array( $result['category'], $seen_categories, true ) &&
			     $result['confidence'] >= $options['confidence_threshold'] ) {
				// 降低关键词结果的置信度
				$result['confidence'] *= 0.7;
				$merged[] = $result;
				$seen_categories[] = $result['category'];
			}
		}

		// 如果没有AI结果，使用关键词结果
		if ( empty( $merged ) && ! empty( $keyword_results ) ) {
			$merged = array_slice( $keyword_results, 0, $options['return_top_n'] );
		}

		// 排序并限制数量
		usort( $merged, function( $a, $b ) {
			return $b['confidence'] <=> $a['confidence'];
		});

		return array_slice( $merged, 0, $options['return_top_n'] );
	}

	/**
	 * 识别内容类型
	 *
	 * @param string $text 文本
	 * @return string 内容类型
	 */
	private function identify_content_type( $text ) {
		// 简单的规则匹配
		$patterns = array(
			'tutorial' => array( '教程', '步骤', '如何', '怎么', '方法', '攻略' ),
			'review' => array( '测评', '评测', '推荐', '好用', '值得', '不值得' ),
			'experience' => array( '经验', '心得', '分享', '总结', '感受', '体验' ),
			'showcase' => array( '展示', '作品', '成果', '打卡', '记录' ),
			'vlog' => array( 'vlog', '日常', '一天', '日记', '生活' ),
			'tips' => array( '技巧', '干货', '小技巧', '秘诀', '窍门' ),
		);

		$scores = array();
		foreach ( $patterns as $type => $keywords ) {
			$score = 0;
			foreach ( $keywords as $keyword ) {
				$score += mb_substr_count( $text, $keyword );
			}
			if ( $score > 0 ) {
				$scores[ $type ] = $score;
			}
		}

		if ( empty( $scores ) ) {
			return 'general';
		}

		arsort( $scores );
		$type = key( $scores );

		return $type;
	}

	/**
	 * 提取主题标签
	 *
	 * @param string $text 文本
	 * @return array 主题数组
	 */
	private function extract_topics( $text ) {
		// 提取关键词作为主题
		$keywords = $this->extract_keywords( $text, 10 );

		return $keywords;
	}

	/**
	 * 提取关键词
	 *
	 * @param string $text 文本
	 * @param int    $limit 限制数量
	 * @return array 关键词数组
	 */
	private function extract_keywords( $text, $limit = 10 ) {
		// 移除特殊字符
		$text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

		// 分词
		$words = preg_split( '/\s+/u', $text );

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

		// 过滤停用词
		$stopwords = array( '的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一', '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好', '自己', '这', '个', '们', '能', '这个', '那个' );

		foreach ( $stopwords as $stopword ) {
			unset( $word_freq[ $stopword ] );
		}

		// 排序
		arsort( $word_freq );

		return array_keys( array_slice( $word_freq, 0, $limit, true ) );
	}

	/**
	 * 批量分类
	 *
	 * @param array $posts 文章数组
	 * @param array $options 选项
	 * @return array 批量结果
	 */
	public function batch_classify( $posts, $options = array() ) {
		$results = array();

		foreach ( $posts as $index => $post ) {
			$this->logger->info( "批量分类 {$index}/" . count( $posts ) );

			$result = $this->classify( $post, $options );
			$results[] = array(
				'post_id' => $post['id'] ?? $index,
				'classification' => $result,
			);

			// 避免API限流
			if ( ! empty( $options['use_ai'] ) && $index < count( $posts ) - 1 ) {
				sleep( 1 );
			}
		}

		return array(
			'success' => true,
			'total' => count( $posts ),
			'results' => $results,
		);
	}

	/**
	 * 添加自定义分类
	 *
	 * @param string $category_id 分类ID
	 * @param array  $category_data 分类数据
	 * @return bool
	 */
	public function add_custom_category( $category_id, $category_data ) {
		$this->categories[ $category_id ] = array(
			'name' => $category_data['name'],
			'keywords' => $category_data['keywords'],
		);

		// 保存到数据库
		update_option( 'aiscg_custom_categories', $this->categories );

		$this->logger->info( "添加自定义分类: {$category_id}" );

		return true;
	}

	/**
	 * 获取所有分类
	 *
	 * @return array 分类列表
	 */
	public function get_categories() {
		return $this->categories;
	}

	/**
	 * 获取分类统计
	 *
	 * @return array 统计数据
	 */
	public function get_category_statistics() {
		global $wpdb;
		$table = $wpdb->prefix . 'aiscg_posts';

		// 获取所有内容并分类
		$posts = $wpdb->get_results(
			"SELECT id, title, content FROM {$table}
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			LIMIT 500",
			ARRAY_A
		);

		$category_counts = array();
		$type_counts = array();

		foreach ( $posts as $post ) {
			$classification = $this->classify( $post, array( 'use_ai' => false ) );

			if ( ! empty( $classification['primary_category'] ) ) {
				$category = $classification['primary_category']['category'];
				if ( ! isset( $category_counts[ $category ] ) ) {
					$category_counts[ $category ] = 0;
				}
				$category_counts[ $category ]++;
			}

			if ( ! empty( $classification['content_type'] ) ) {
				$type = $classification['content_type'];
				if ( ! isset( $type_counts[ $type ] ) ) {
					$type_counts[ $type ] = 0;
				}
				$type_counts[ $type ]++;
			}
		}

		arsort( $category_counts );
		arsort( $type_counts );

		return array(
			'success' => true,
			'total_analyzed' => count( $posts ),
			'by_category' => $category_counts,
			'by_type' => $type_counts,
			'top_category' => ! empty( $category_counts ) ? key( $category_counts ) : null,
			'top_type' => ! empty( $type_counts ) ? key( $type_counts ) : null,
		);
	}
}
