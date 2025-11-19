<?php
/**
 * 内容合规检查器
 *
 * 检查内容是否符合法律法规和平台规范
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 合规检查器类
 */
class AISCG_Compliance_Checker {

    /**
     * AI工厂
     *
     * @var AISCG_AI_Service_Factory
     */
    private $ai_factory;

    /**
     * 检查类型
     *
     * @var array
     */
    private $check_types = array(
        'legal' => '法律合规',
        'copyright' => '版权检查',
        'advertising' => '广告法规',
        'platform_policy' => '平台政策',
        'content_rating' => '内容分级',
        'data_privacy' => '数据隐私',
    );

    /**
     * 违规严重程度
     *
     * @var array
     */
    private $severity_levels = array(
        'critical' => '严重违规',
        'high' => '高风险',
        'medium' => '中等风险',
        'low' => '低风险',
        'info' => '提示信息',
    );

    /**
     * 广告法禁用词
     *
     * @var array
     */
    private $advertising_banned_words = array(
        // 极限词
        '最', '第一', '首个', '最佳', '最优', '顶级', '极致', '终极',
        '国家级', '世界级', '全球领先', '行业领先',

        // 绝对化用语
        '100%', '完全', '绝对', '永久', '万能', '全能',

        // 虚假承诺
        '包治百病', '保证', '承诺', '必定', '100%有效',
    );

    /**
     * 构造函数
     */
    public function __construct() {
        $this->ai_factory = new AISCG_AI_Service_Factory();
    }

    /**
     * 全面合规检查
     *
     * @param int|array $content 内容ID或内容数据
     * @param array $options 检查选项
     *   - checks: 需要执行的检查类型数组
     *   - use_ai: 是否使用AI辅助检查
     *   - strict_mode: 严格模式
     *
     * @return array 检查结果
     */
    public function check_compliance( $content, $options = array() ) {
        // 获取内容数据
        if ( is_numeric( $content ) ) {
            global $wpdb;
            $table_posts = $wpdb->prefix . 'aiscg_posts';
            $content = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM $table_posts WHERE id = %d", $content ),
                ARRAY_A
            );

            if ( ! $content ) {
                return array( 'error' => '内容不存在' );
            }
        }

        $defaults = array(
            'checks' => array( 'legal', 'copyright', 'advertising', 'platform_policy' ),
            'use_ai' => true,
            'strict_mode' => false,
        );
        $options = wp_parse_args( $options, $defaults );

        $results = array(
            'content_id' => $content['id'] ?? 0,
            'checked_at' => current_time( 'mysql' ),
            'checks' => array(),
            'violations' => array(),
            'warnings' => array(),
            'compliance_score' => 100,
            'can_publish' => true,
        );

        // 执行各项检查
        if ( in_array( 'legal', $options['checks'] ) ) {
            $results['checks']['legal'] = $this->check_legal_compliance( $content );
        }

        if ( in_array( 'copyright', $options['checks'] ) ) {
            $results['checks']['copyright'] = $this->check_copyright( $content );
        }

        if ( in_array( 'advertising', $options['checks'] ) ) {
            $results['checks']['advertising'] = $this->check_advertising_law( $content );
        }

        if ( in_array( 'platform_policy', $options['checks'] ) ) {
            $results['checks']['platform_policy'] = $this->check_platform_policy( $content );
        }

        if ( in_array( 'content_rating', $options['checks'] ) ) {
            $results['checks']['content_rating'] = $this->check_content_rating( $content );
        }

        if ( in_array( 'data_privacy', $options['checks'] ) ) {
            $results['checks']['data_privacy'] = $this->check_data_privacy( $content );
        }

        // 使用AI进行深度检查
        if ( $options['use_ai'] ) {
            $results['ai_compliance_check'] = $this->ai_compliance_check( $content );
        }

        // 汇总违规和警告
        foreach ( $results['checks'] as $check_type => $check_result ) {
            if ( isset( $check_result['violations'] ) ) {
                $results['violations'] = array_merge( $results['violations'], $check_result['violations'] );
            }
            if ( isset( $check_result['warnings'] ) ) {
                $results['warnings'] = array_merge( $results['warnings'], $check_result['warnings'] );
            }
        }

        // 计算合规得分
        $results['compliance_score'] = $this->calculate_compliance_score( $results );

        // 判断是否可以发布
        $critical_count = count( array_filter( $results['violations'], function( $v ) {
            return $v['severity'] === 'critical';
        }));

        $high_count = count( array_filter( $results['violations'], function( $v ) {
            return $v['severity'] === 'high';
        }));

        if ( $critical_count > 0 ) {
            $results['can_publish'] = false;
            $results['reason'] = '存在严重违规问题';
        } elseif ( $options['strict_mode'] && $high_count > 0 ) {
            $results['can_publish'] = false;
            $results['reason'] = '严格模式下存在高风险问题';
        }

        // 保存检查记录
        $this->save_compliance_record( $content['id'] ?? 0, $results );

        return $results;
    }

    /**
     * 检查法律合规性
     *
     * @param array $content 内容数据
     *
     * @return array 检查结果
     */
    private function check_legal_compliance( $content ) {
        $violations = array();
        $warnings = array();

        $text = $content['title'] . ' ' . $content['content'];

        // 检查政治敏感内容
        $political_keywords = array( '政治敏感词1', '政治敏感词2' ); // 实际应更完善
        foreach ( $political_keywords as $keyword ) {
            if ( stripos( $text, $keyword ) !== false ) {
                $violations[] = array(
                    'type' => 'political',
                    'severity' => 'critical',
                    'message' => '内容包含政治敏感词: ' . $keyword,
                    'location' => $this->find_keyword_location( $text, $keyword ),
                );
            }
        }

        // 检查暴力血腥内容
        $violence_keywords = array( '暴力', '血腥', '残忍', '虐待' );
        foreach ( $violence_keywords as $keyword ) {
            if ( stripos( $text, $keyword ) !== false ) {
                $warnings[] = array(
                    'type' => 'violence',
                    'severity' => 'high',
                    'message' => '内容可能包含暴力元素: ' . $keyword,
                    'suggestion' => '请确保内容符合平台内容分级要求',
                );
            }
        }

        // 检查色情低俗内容
        $nsfw_keywords = array( '色情', '低俗', '裸露' );
        foreach ( $nsfw_keywords as $keyword ) {
            if ( stripos( $text, $keyword ) !== false ) {
                $violations[] = array(
                    'type' => 'nsfw',
                    'severity' => 'critical',
                    'message' => '内容包含不适宜词汇: ' . $keyword,
                    'action' => '必须删除此类内容',
                );
            }
        }

        return array(
            'passed' => empty( $violations ),
            'violations' => $violations,
            'warnings' => $warnings,
        );
    }

    /**
     * 检查版权
     *
     * @param array $content 内容数据
     *
     * @return array 检查结果
     */
    private function check_copyright( $content ) {
        $violations = array();
        $warnings = array();

        $text = $content['content'];

        // 检查品牌名称使用
        $brand_keywords = array( '苹果', 'Apple', '华为', 'Huawei', '三星', 'Samsung' );
        foreach ( $brand_keywords as $brand ) {
            if ( stripos( $text, $brand ) !== false ) {
                $warnings[] = array(
                    'type' => 'brand_mention',
                    'severity' => 'low',
                    'message' => '内容提及品牌名称: ' . $brand,
                    'suggestion' => '确保未侵犯品牌商标权,避免虚假宣传',
                );
            }
        }

        // 检查是否包含"转载"但未注明出处
        if ( stripos( $text, '转载' ) !== false || stripos( $text, '来源' ) !== false ) {
            if ( ! preg_match( '/来源[:：]\s*\S+/', $text ) ) {
                $violations[] = array(
                    'type' => 'copyright_attribution',
                    'severity' => 'medium',
                    'message' => '转载内容未注明来源',
                    'action' => '请添加原文链接或作者信息',
                );
            }
        }

        // 检查图片版权声明(简化检查)
        global $wpdb;
        $table_images = $wpdb->prefix . 'aiscg_images';
        $image_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_images WHERE post_id = %d",
                $content['id']
            )
        );

        if ( $image_count > 0 ) {
            $warnings[] = array(
                'type' => 'image_copyright',
                'severity' => 'medium',
                'message' => '请确认所有图片拥有使用权',
                'suggestion' => '建议添加图片来源说明',
            );
        }

        return array(
            'passed' => empty( $violations ),
            'violations' => $violations,
            'warnings' => $warnings,
        );
    }

    /**
     * 检查广告法合规
     *
     * @param array $content 内容数据
     *
     * @return array 检查结果
     */
    private function check_advertising_law( $content ) {
        $violations = array();
        $warnings = array();

        $text = $content['title'] . ' ' . $content['content'];

        // 检查广告法禁用词
        foreach ( $this->advertising_banned_words as $banned_word ) {
            if ( stripos( $text, $banned_word ) !== false ) {
                $violations[] = array(
                    'type' => 'advertising_violation',
                    'severity' => 'high',
                    'message' => '使用了广告法禁用词: ' . $banned_word,
                    'location' => $this->find_keyword_location( $text, $banned_word ),
                    'action' => '必须删除或替换此词',
                    'suggestion' => $this->suggest_alternative_word( $banned_word ),
                );
            }
        }

        // 检查虚假宣传
        $false_claims = array( '包治', '包好', '药到病除', '立竿见影', '速效' );
        foreach ( $false_claims as $claim ) {
            if ( stripos( $text, $claim ) !== false ) {
                $violations[] = array(
                    'type' => 'false_advertising',
                    'severity' => 'critical',
                    'message' => '涉嫌虚假宣传: ' . $claim,
                    'action' => '必须删除虚假承诺内容',
                );
            }
        }

        // 检查医疗健康相关内容
        $medical_keywords = array( '治疗', '疾病', '药物', '医院', '医生' );
        $medical_count = 0;
        foreach ( $medical_keywords as $keyword ) {
            if ( stripos( $text, $keyword ) !== false ) {
                $medical_count++;
            }
        }

        if ( $medical_count >= 2 ) {
            $warnings[] = array(
                'type' => 'medical_content',
                'severity' => 'high',
                'message' => '内容涉及医疗健康领域',
                'suggestion' => '请确保内容准确,避免误导用户,可能需要医疗资质',
            );
        }

        return array(
            'passed' => empty( $violations ),
            'violations' => $violations,
            'warnings' => $warnings,
        );
    }

    /**
     * 检查平台政策
     *
     * @param array $content 内容数据
     *
     * @return array 检查结果
     */
    private function check_platform_policy( $content ) {
        $violations = array();
        $warnings = array();

        $platform = $content['platform'];
        $text = $content['content'];

        // 平台特定规则
        if ( $platform === 'xiaohongshu' ) {
            // 小红书禁止导流
            $drainage_keywords = array( '微信', '加我', 'VX', 'WeChat', 'http', 'www', '.com' );
            foreach ( $drainage_keywords as $keyword ) {
                if ( stripos( $text, $keyword ) !== false ) {
                    $violations[] = array(
                        'type' => 'platform_violation',
                        'severity' => 'critical',
                        'message' => '小红书禁止站外导流: ' . $keyword,
                        'action' => '必须删除导流信息',
                    );
                }
            }

            // 检查标签数量
            $hashtags = json_decode( $content['hashtags'], true );
            if ( is_array( $hashtags ) && count( $hashtags ) > 10 ) {
                $violations[] = array(
                    'type' => 'hashtag_limit',
                    'severity' => 'medium',
                    'message' => '小红书标签数量超过限制(最多10个)',
                    'current' => count( $hashtags ),
                    'action' => '请减少标签数量',
                );
            }
        }

        if ( $platform === 'instagram' ) {
            // Instagram标签限制
            $hashtags = json_decode( $content['hashtags'], true );
            if ( is_array( $hashtags ) && count( $hashtags ) > 30 ) {
                $violations[] = array(
                    'type' => 'hashtag_limit',
                    'severity' => 'medium',
                    'message' => 'Instagram标签数量超过限制(最多30个)',
                    'current' => count( $hashtags ),
                    'action' => '请减少标签数量',
                );
            }
        }

        // 检查垃圾内容特征
        if ( $this->is_spam_content( $text ) ) {
            $violations[] = array(
                'type' => 'spam',
                'severity' => 'high',
                'message' => '内容可能被识别为垃圾信息',
                'suggestion' => '减少重复内容,避免过度使用emoji和特殊符号',
            );
        }

        return array(
            'passed' => empty( $violations ),
            'violations' => $violations,
            'warnings' => $warnings,
        );
    }

    /**
     * 检查内容分级
     *
     * @param array $content 内容数据
     *
     * @return array 检查结果
     */
    private function check_content_rating( $content ) {
        $text = $content['title'] . ' ' . $content['content'];

        $rating = 'G'; // General - 所有年龄
        $reasons = array();

        // 检查不适宜内容
        $pg_keywords = array( '酒', '烟', '赌' );
        foreach ( $pg_keywords as $keyword ) {
            if ( stripos( $text, $keyword ) !== false ) {
                $rating = 'PG'; // Parental Guidance - 建议家长指导
                $reasons[] = '包含: ' . $keyword;
            }
        }

        return array(
            'rating' => $rating,
            'reasons' => $reasons,
            'suitable_for_all_ages' => $rating === 'G',
        );
    }

    /**
     * 检查数据隐私
     *
     * @param array $content 内容数据
     *
     * @return array 检查结果
     */
    private function check_data_privacy( $content ) {
        $violations = array();
        $warnings = array();

        $text = $content['content'];

        // 检查个人信息泄露
        $privacy_patterns = array(
            '/\d{11}/' => '手机号码',
            '/\d{15,18}/' => '身份证号',
            '/\d{16,19}/' => '银行卡号',
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '电子邮箱',
        );

        foreach ( $privacy_patterns as $pattern => $type ) {
            if ( preg_match( $pattern, $text ) ) {
                $violations[] = array(
                    'type' => 'privacy_leak',
                    'severity' => 'critical',
                    'message' => '内容可能包含个人隐私信息: ' . $type,
                    'action' => '必须删除或脱敏处理',
                );
            }
        }

        // 检查地址信息
        if ( preg_match( '/详细地址|家庭住址|具体位置/', $text ) ) {
            $warnings[] = array(
                'type' => 'location_privacy',
                'severity' => 'medium',
                'message' => '内容可能包含详细地址信息',
                'suggestion' => '避免透露具体位置,保护个人安全',
            );
        }

        return array(
            'passed' => empty( $violations ),
            'violations' => $violations,
            'warnings' => $warnings,
        );
    }

    /**
     * AI合规检查
     *
     * @param array $content 内容数据
     *
     * @return array AI检查结果
     */
    private function ai_compliance_check( $content ) {
        try {
            $ai_service = $this->ai_factory->get_service();

            $text = $content['title'] . "\n\n" . $content['content'];

            $prompt = "作为内容合规审查专家,请检查以下内容是否存在合规风险:\n\n";
            $prompt .= "标题: {$content['title']}\n";
            $prompt .= "内容: " . mb_substr( $content['content'], 0, 500 ) . "...\n\n";
            $prompt .= "请从以下角度评估:\n";
            $prompt .= "1. 是否存在法律法规风险?\n";
            $prompt .= "2. 是否违反广告法?\n";
            $prompt .= "3. 是否有虚假宣传?\n";
            $prompt .= "4. 内容是否健康积极?\n";
            $prompt .= "5. 其他潜在风险\n\n";
            $prompt .= "请简要回答,每点1-2句话。如果发现问题,请明确指出。";

            $ai_result = $ai_service->generate_content( $prompt );

            return array(
                'checked' => true,
                'result' => $ai_result,
                'timestamp' => current_time( 'mysql' ),
            );

        } catch ( Exception $e ) {
            return array(
                'checked' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * 计算合规得分
     *
     * @param array $results 检查结果
     *
     * @return int 合规得分 0-100
     */
    private function calculate_compliance_score( $results ) {
        $score = 100;

        // 严重违规每项扣30分
        $critical_count = count( array_filter( $results['violations'], function( $v ) {
            return $v['severity'] === 'critical';
        }));
        $score -= $critical_count * 30;

        // 高风险每项扣15分
        $high_count = count( array_filter( $results['violations'], function( $v ) {
            return $v['severity'] === 'high';
        }));
        $score -= $high_count * 15;

        // 中等风险每项扣8分
        $medium_count = count( array_filter( $results['violations'], function( $v ) {
            return $v['severity'] === 'medium';
        }));
        $score -= $medium_count * 8;

        // 低风险每项扣3分
        $low_count = count( array_filter( $results['violations'], function( $v ) {
            return $v['severity'] === 'low';
        }));
        $score -= $low_count * 3;

        return max( 0, $score );
    }

    /**
     * 查找关键词位置
     *
     * @param string $text 文本
     * @param string $keyword 关键词
     *
     * @return array 位置信息
     */
    private function find_keyword_location( $text, $keyword ) {
        $pos = stripos( $text, $keyword );

        if ( $pos === false ) {
            return null;
        }

        $start = max( 0, $pos - 20 );
        $length = mb_strlen( $keyword ) + 40;
        $context = mb_substr( $text, $start, $length );

        return array(
            'position' => $pos,
            'context' => '...' . $context . '...',
        );
    }

    /**
     * 建议替代词
     *
     * @param string $banned_word 禁用词
     *
     * @return string 建议词
     */
    private function suggest_alternative_word( $banned_word ) {
        $alternatives = array(
            '最' => '非常/很/十分',
            '第一' => '优秀/领先',
            '最佳' => '优质/推荐',
            '100%' => '高效/显著',
            '保证' => '力求/致力于',
        );

        return $alternatives[ $banned_word ] ?? '请使用更准确的描述';
    }

    /**
     * 判断是否为垃圾内容
     *
     * @param string $text 文本
     *
     * @return bool 是否垃圾
     */
    private function is_spam_content( $text ) {
        // 检查重复字符
        if ( preg_match( '/(.)\1{10,}/', $text ) ) {
            return true;
        }

        // 检查过度emoji (超过20%)
        $emoji_count = preg_match_all( '/[\x{1F300}-\x{1F9FF}]/u', $text );
        $total_chars = mb_strlen( $text );
        if ( $total_chars > 0 && ( $emoji_count / $total_chars ) > 0.2 ) {
            return true;
        }

        return false;
    }

    /**
     * 保存合规检查记录
     *
     * @param int $content_id 内容ID
     * @param array $results 检查结果
     */
    private function save_compliance_record( $content_id, $results ) {
        global $wpdb;
        $table_records = $wpdb->prefix . 'aiscg_compliance_records';

        $wpdb->insert(
            $table_records,
            array(
                'content_id' => $content_id,
                'compliance_score' => $results['compliance_score'],
                'can_publish' => $results['can_publish'] ? 1 : 0,
                'violations_count' => count( $results['violations'] ),
                'warnings_count' => count( $results['warnings'] ),
                'check_data' => wp_json_encode( $results ),
                'checked_at' => current_time( 'mysql' ),
            )
        );
    }

    /**
     * 获取合规历史记录
     *
     * @param int $content_id 内容ID
     *
     * @return array 历史记录
     */
    public function get_compliance_history( $content_id ) {
        global $wpdb;
        $table_records = $wpdb->prefix . 'aiscg_compliance_records';

        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_records WHERE content_id = %d ORDER BY checked_at DESC",
                $content_id
            ),
            ARRAY_A
        );

        foreach ( $records as &$record ) {
            $record['check_data'] = json_decode( $record['check_data'], true );
        }

        return $records;
    }

    /**
     * 创建合规记录表
     */
    private function create_compliance_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiscg_compliance_records';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            content_id bigint(20) NOT NULL,
            compliance_score int NOT NULL,
            can_publish tinyint(1) DEFAULT 1,
            violations_count int DEFAULT 0,
            warnings_count int DEFAULT 0,
            check_data longtext,
            checked_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY content_id (content_id),
            KEY checked_at (checked_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
