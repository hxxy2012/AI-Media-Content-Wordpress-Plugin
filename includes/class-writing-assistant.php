<?php
/**
 * Writing Assistant Class
 *
 * 智能写作助手 - AI辅助内容创作
 *
 * @package AI_Social_Content_Generator
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AISCG_Writing_Assistant Class
 */
class AISCG_Writing_Assistant {

	/**
	 * 补全类型
	 *
	 * @var array
	 */
	private $completion_types = array(
		'continue'    => '继续写作',
		'expand'      => '扩展句子',
		'paragraph'   => '生成段落',
		'title'       => '优化标题',
		'opening'     => '生成开场',
		'closing'     => '生成结尾',
		'transition'  => '过渡语句',
		'rephrase'    => '换个说法',
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
	 * 获取写作建议
	 *
	 * @param string $context 上下文内容
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	public function get_suggestions( $context, $options = array() ) {
		$defaults = array(
			'type' => 'continue', // 建议类型
			'platform' => 'xiaohongshu',
			'tone' => 'casual', // casual, professional, friendly
			'length' => 'medium', // short, medium, long
			'count' => 3, // 返回建议数量
			'ai_model' => 'gpt-3.5-turbo',
		);

		$options = wp_parse_args( $options, $defaults );

		$this->logger->info( '获取写作建议', array(
			'type' => $options['type'],
			'context_length' => mb_strlen( $context ),
		) );

		$suggestions = array();

		switch ( $options['type'] ) {
			case 'continue':
				$suggestions = $this->suggest_continuation( $context, $options );
				break;

			case 'expand':
				$suggestions = $this->suggest_expansion( $context, $options );
				break;

			case 'paragraph':
				$suggestions = $this->suggest_paragraph( $context, $options );
				break;

			case 'title':
				$suggestions = $this->suggest_title( $context, $options );
				break;

			case 'opening':
				$suggestions = $this->suggest_opening( $context, $options );
				break;

			case 'closing':
				$suggestions = $this->suggest_closing( $context, $options );
				break;

			case 'transition':
				$suggestions = $this->suggest_transition( $context, $options );
				break;

			case 'rephrase':
				$suggestions = $this->suggest_rephrase( $context, $options );
				break;
		}

		return array(
			'success' => true,
			'type' => $options['type'],
			'suggestions' => $suggestions,
			'count' => count( $suggestions ),
		);
	}

	/**
	 * 建议继续写作
	 *
	 * @param string $context 上下文
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	private function suggest_continuation( $context, $options ) {
		$prompt = $this->build_continuation_prompt( $context, $options );

		return $this->get_ai_suggestions( $prompt, $options );
	}

	/**
	 * 建议扩展句子
	 *
	 * @param string $context 句子
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	private function suggest_expansion( $context, $options ) {
		$prompt = "请将以下句子扩展得更详细、生动：\n\n";
		$prompt .= "{$context}\n\n";
		$prompt .= "要求：\n";
		$prompt .= "1. 保持原意不变\n";
		$prompt .= "2. 添加更多细节和描述\n";
		$prompt .= "3. 使用生动的语言\n";
		$prompt .= "4. 适合{$options['platform']}平台\n\n";
		$prompt .= "请提供{$options['count']}个不同的扩展版本，每个版本之间用「---」分隔。";

		return $this->get_ai_suggestions( $prompt, $options );
	}

	/**
	 * 建议生成段落
	 *
	 * @param string $context 主题或关键词
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	private function suggest_paragraph( $context, $options ) {
		$length_guide = array(
			'short' => '50-80字',
			'medium' => '100-150字',
			'long' => '180-250字',
		);

		$prompt = "请围绕以下主题写一个段落：\n\n";
		$prompt .= "{$context}\n\n";
		$prompt .= "要求：\n";
		$prompt .= "1. 长度：{$length_guide[$options['length']]}\n";
		$prompt .= "2. 风格：{$this->get_tone_description($options['tone'])}\n";
		$prompt .= "3. 适合{$options['platform']}平台\n";
		$prompt .= "4. 内容充实，有吸引力\n\n";
		$prompt .= "请提供{$options['count']}个不同角度的段落，每个段落之间用「---」分隔。";

		return $this->get_ai_suggestions( $prompt, $options );
	}

	/**
	 * 建议优化标题
	 *
	 * @param string $context 原标题或内容摘要
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	private function suggest_title( $context, $options ) {
		$prompt = "请为以下内容生成吸引人的标题：\n\n";
		$prompt .= "{$context}\n\n";
		$prompt .= "要求：\n";
		$prompt .= "1. 标题长度：15-30字\n";
		$prompt .= "2. 吸引眼球，激发好奇\n";
		$prompt .= "3. 包含关键信息\n";
		$prompt .= "4. 适合{$options['platform']}平台风格\n";
		$prompt .= "5. 可以适当使用emoji\n\n";
		$prompt .= "请提供{$options['count']}个不同风格的标题选项，每个标题单独一行。";

		return $this->get_ai_suggestions( $prompt, $options );
	}

	/**
	 * 建议开场白
	 *
	 * @param string $context 主题
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	private function suggest_opening( $context, $options ) {
		$prompt = "请为以下主题写一个吸引人的开场白：\n\n";
		$prompt .= "{$context}\n\n";
		$prompt .= "要求：\n";
		$prompt .= "1. 简短有力，2-3句话\n";
		$prompt .= "2. 引起读者兴趣\n";
		$prompt .= "3. 可以使用问句、惊叹句或故事开头\n";
		$prompt .= "4. 适合{$options['platform']}平台\n\n";
		$prompt .= "请提供{$options['count']}个不同风格的开场白，每个之间用「---」分隔。";

		return $this->get_ai_suggestions( $prompt, $options );
	}

	/**
	 * 建议结尾
	 *
	 * @param string $context 文章内容
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	private function suggest_closing( $context, $options ) {
		$prompt = "请为以下内容写一个有力的结尾：\n\n";
		$prompt .= mb_substr( $context, 0, 500 ) . "...\n\n";
		$prompt .= "要求：\n";
		$prompt .= "1. 总结要点或升华主题\n";
		$prompt .= "2. 可以包含行动号召(CTA)\n";
		$prompt .= "3. 引发互动或思考\n";
		$prompt .= "4. 2-3句话\n";
		$prompt .= "5. 适合{$options['platform']}平台\n\n";
		$prompt .= "请提供{$options['count']}个不同的结尾选项，每个之间用「---」分隔。";

		return $this->get_ai_suggestions( $prompt, $options );
	}

	/**
	 * 建议过渡语句
	 *
	 * @param string $context 前后文
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	private function suggest_transition( $context, $options ) {
		$prompt = "请为以下内容提供自然的过渡语句：\n\n";
		$prompt .= "{$context}\n\n";
		$prompt .= "要求：\n";
		$prompt .= "1. 承上启下，逻辑流畅\n";
		$prompt .= "2. 简洁自然\n";
		$prompt .= "3. 1句话\n\n";
		$prompt .= "请提供{$options['count']}个不同的过渡语句选项，每个单独一行。";

		return $this->get_ai_suggestions( $prompt, $options );
	}

	/**
	 * 建议换个说法
	 *
	 * @param string $context 原文
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	private function suggest_rephrase( $context, $options ) {
		$prompt = "请用不同的方式表达以下内容：\n\n";
		$prompt .= "{$context}\n\n";
		$prompt .= "要求：\n";
		$prompt .= "1. 保持原意\n";
		$prompt .= "2. 使用不同的词汇和句式\n";
		$prompt .= "3. 保持相似的长度\n";
		$prompt .= "4. 更加生动或专业\n\n";
		$prompt .= "请提供{$options['count']}个不同的表达方式，每个之间用「---」分隔。";

		return $this->get_ai_suggestions( $prompt, $options );
	}

	/**
	 * 构建继续写作的prompt
	 *
	 * @param string $context 上下文
	 * @param array  $options 选项
	 * @return string prompt
	 */
	private function build_continuation_prompt( $context, $options ) {
		$platform_name = $options['platform'] === 'xiaohongshu' ? '小红书' : 'Instagram';

		$length_tokens = array(
			'short' => '30-50字',
			'medium' => '80-120字',
			'long' => '150-200字',
		);

		$prompt = "请根据以下内容继续写作：\n\n";
		$prompt .= "{$context}\n\n";
		$prompt .= "要求：\n";
		$prompt .= "1. 继续的内容长度：{$length_tokens[$options['length']]}\n";
		$prompt .= "2. 保持一致的风格和语气\n";
		$prompt .= "3. 内容连贯、自然\n";
		$prompt .= "4. 适合{$platform_name}平台\n";
		$prompt .= "5. 风格：{$this->get_tone_description($options['tone'])}\n\n";
		$prompt .= "请提供{$options['count']}个不同方向的继续内容，每个之间用「---」分隔。";

		return $prompt;
	}

	/**
	 * 获取AI建议
	 *
	 * @param string $prompt prompt
	 * @param array  $options 选项
	 * @return array 建议列表
	 */
	private function get_ai_suggestions( $prompt, $options ) {
		try {
			$ai_service = AISCG_AI_Service_Factory::create( $options['ai_model'] );

			$response = $ai_service->generate( $prompt, array(
				'temperature' => 0.8,
				'max_tokens' => 800,
			) );

			// 解析响应
			$suggestions = $this->parse_suggestions( $response );

			// 限制返回数量
			$suggestions = array_slice( $suggestions, 0, $options['count'] );

			return $suggestions;

		} catch ( Exception $e ) {
			$this->logger->error( '获取AI建议失败', array(
				'error' => $e->getMessage(),
			) );

			return array();
		}
	}

	/**
	 * 解析建议
	 *
	 * @param string $response AI响应
	 * @return array 建议列表
	 */
	private function parse_suggestions( $response ) {
		// 按分隔符分割
		$parts = preg_split( '/---+/', $response );

		$suggestions = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( ! empty( $part ) ) {
				$suggestions[] = $part;
			}
		}

		// 如果没有分隔符，按换行分割
		if ( empty( $suggestions ) ) {
			$lines = explode( "\n", $response );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				// 移除序号
				$line = preg_replace( '/^[\d\.、]+\s*/', '', $line );
				if ( ! empty( $line ) && mb_strlen( $line ) > 10 ) {
					$suggestions[] = $line;
				}
			}
		}

		return $suggestions;
	}

	/**
	 * 获取语气描述
	 *
	 * @param string $tone 语气
	 * @return string 描述
	 */
	private function get_tone_description( $tone ) {
		$descriptions = array(
			'casual' => '轻松随意、口语化',
			'professional' => '专业、正式',
			'friendly' => '亲切、友好',
			'enthusiastic' => '热情、积极',
			'informative' => '信息性、客观',
		);

		return $descriptions[ $tone ] ?? $descriptions['casual'];
	}

	/**
	 * 智能改写
	 *
	 * @param string $text 原文
	 * @param array  $options 选项
	 * @return array 改写结果
	 */
	public function rewrite( $text, $options = array() ) {
		$defaults = array(
			'target' => 'improve', // improve, simplify, formalize, casualize
			'preserve_length' => true,
			'ai_model' => 'gpt-3.5-turbo',
		);

		$options = wp_parse_args( $options, $defaults );

		$prompts = array(
			'improve' => '请改进以下内容，使其更加生动、吸引人，但保持原意不变',
			'simplify' => '请简化以下内容，使用更简单易懂的语言，适合大众阅读',
			'formalize' => '请将以下内容改写得更加正式、专业',
			'casualize' => '请将以下内容改写得更加口语化、轻松随意',
		);

		$prompt = $prompts[ $options['target'] ] ?? $prompts['improve'];
		$prompt .= "：\n\n{$text}\n\n";

		if ( $options['preserve_length'] ) {
			$prompt .= "要求：保持与原文相似的长度。";
		}

		try {
			$ai_service = AISCG_AI_Service_Factory::create( $options['ai_model'] );

			$rewritten = $ai_service->generate( $prompt, array(
				'temperature' => 0.7,
				'max_tokens' => 1000,
			) );

			return array(
				'success' => true,
				'original' => $text,
				'rewritten' => trim( $rewritten ),
				'original_length' => mb_strlen( $text ),
				'rewritten_length' => mb_strlen( $rewritten ),
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * 语法检查
	 *
	 * @param string $text 文本
	 * @return array 检查结果
	 */
	public function check_grammar( $text ) {
		$issues = array();

		// 基础检查
		// 检查标点符号
		if ( preg_match( '/[a-zA-Z][，。！？]/', $text ) ) {
			$issues[] = array(
				'type' => 'punctuation',
				'message' => '英文后使用了中文标点',
				'severity' => 'warning',
			);
		}

		if ( preg_match( '/[\u4e00-\u9fa5][,.:;!?]/', $text ) ) {
			$issues[] = array(
				'type' => 'punctuation',
				'message' => '中文后使用了英文标点',
				'severity' => 'warning',
			);
		}

		// 检查空格
		if ( preg_match( '/[a-zA-Z][\u4e00-\u9fa5]|[\u4e00-\u9fa5][a-zA-Z]/', $text ) ) {
			$count = preg_match_all( '/[a-zA-Z][\u4e00-\u9fa5]|[\u4e00-\u9fa5][a-zA-Z]/', $text );
			if ( $count > 5 ) {
				$issues[] = array(
					'type' => 'spacing',
					'message' => '中英文之间建议添加空格',
					'severity' => 'info',
				);
			}
		}

		// 使用AI进行深度检查
		try {
			$ai_service = AISCG_AI_Service_Factory::create( 'gpt-3.5-turbo' );

			$prompt = "请检查以下文本的语法和用词错误：\n\n{$text}\n\n";
			$prompt .= "如果发现错误，请列出；如果没有错误，返回「无明显错误」。";

			$response = $ai_service->generate( $prompt, array(
				'temperature' => 0.3,
				'max_tokens' => 300,
			) );

			if ( $response && ! str_contains( $response, '无明显错误' ) ) {
				$ai_issues = explode( "\n", $response );
				foreach ( $ai_issues as $issue ) {
					$issue = trim( $issue );
					if ( ! empty( $issue ) ) {
						$issues[] = array(
							'type' => 'ai_detected',
							'message' => $issue,
							'severity' => 'info',
						);
					}
				}
			}
		} catch ( Exception $e ) {
			// 忽略AI检查错误
		}

		return array(
			'success' => true,
			'has_issues' => ! empty( $issues ),
			'issue_count' => count( $issues ),
			'issues' => $issues,
		);
	}

	/**
	 * 获取写作模板
	 *
	 * @param string $template_type 模板类型
	 * @param array  $variables 变量
	 * @return string 填充后的模板
	 */
	public function get_writing_template( $template_type, $variables = array() ) {
		$templates = array(
			'tutorial' => "📚 {title}\n\n嗨大家好！今天给大家分享{topic}的详细教程～\n\n✨ 步骤一：{step1}\n✨ 步骤二：{step2}\n✨ 步骤三：{step3}\n\n💡 小贴士：{tips}\n\n希望对大家有帮助！",

			'review' => "🌟 {title}\n\n最近入手了{product}，使用了{duration}后来给大家做个真实分享～\n\n✅ 优点：\n{pros}\n\n❌ 缺点：\n{cons}\n\n💯 总体评价：{rating}\n\n{conclusion}",

			'sharing' => "💖 {title}\n\n{opening}\n\n{content}\n\n{tips}\n\n{closing}",

			'list' => "📝 {title}\n\n给大家整理了{topic}，超级实用！\n\n1⃣ {item1}\n2⃣ {item2}\n3⃣ {item3}\n4⃣ {item4}\n5⃣ {item5}\n\n{conclusion}",
		);

		$template = $templates[ $template_type ] ?? '';

		// 替换变量
		foreach ( $variables as $key => $value ) {
			$template = str_replace( "{{$key}}", $value, $template );
		}

		return $template;
	}
}
