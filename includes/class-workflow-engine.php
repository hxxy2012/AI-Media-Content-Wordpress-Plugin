<?php
/**
 * 工作流自动化引擎
 *
 * 提供工作流定义、执行、管理功能
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 工作流引擎类
 */
class AISCG_Workflow_Engine {

    /**
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $database;

    /**
     * 支持的触发器类型
     *
     * @var array
     */
    private $trigger_types = array(
        'manual' => '手动触发',
        'schedule' => '定时触发',
        'content_created' => '内容创建时',
        'content_updated' => '内容更新时',
        'quality_check_failed' => '质量检查失败时',
        'backup_completed' => '备份完成时',
    );

    /**
     * 支持的动作类型
     *
     * @var array
     */
    private $action_types = array(
        'generate_content' => '生成内容',
        'optimize_content' => '优化内容',
        'check_quality' => '质量检测',
        'publish_content' => '发布内容',
        'send_notification' => '发送通知',
        'backup_data' => '备份数据',
        'export_content' => '导出内容',
        'tag_content' => '添加标签',
        'classify_content' => '内容分类',
        'translate_content' => '翻译内容',
        'run_custom_code' => '运行自定义代码',
    );

    /**
     * 构造函数
     */
    public function __construct() {
        $this->database = new AISCG_Database();
        $this->init_hooks();
        $this->create_workflows_table();
    }

    /**
     * 初始化WordPress钩子
     */
    private function init_hooks() {
        // 监听内容创建事件
        add_action( 'aiscg_content_created', array( $this, 'handle_content_created' ), 10, 1 );

        // 监听内容更新事件
        add_action( 'aiscg_content_updated', array( $this, 'handle_content_updated' ), 10, 1 );

        // 监听质量检查失败事件
        add_action( 'aiscg_quality_check_failed', array( $this, 'handle_quality_check_failed' ), 10, 2 );

        // 监听备份完成事件
        add_action( 'aiscg_backup_completed', array( $this, 'handle_backup_completed' ), 10, 1 );

        // 定时任务
        add_action( 'aiscg_run_scheduled_workflows', array( $this, 'run_scheduled_workflows' ) );

        // 确保定时任务已注册
        if ( ! wp_next_scheduled( 'aiscg_run_scheduled_workflows' ) ) {
            wp_schedule_event( time(), 'hourly', 'aiscg_run_scheduled_workflows' );
        }
    }

    /**
     * 创建工作流
     *
     * @param array $workflow_data 工作流数据
     *   - name: 工作流名称
     *   - description: 描述
     *   - trigger_type: 触发器类型
     *   - trigger_config: 触发器配置
     *   - actions: 动作列表
     *   - is_active: 是否启用
     *
     * @return int|WP_Error 工作流ID或错误
     */
    public function create_workflow( $workflow_data ) {
        global $wpdb;
        $table_workflows = $wpdb->prefix . 'aiscg_workflows';

        $defaults = array(
            'name' => '',
            'description' => '',
            'trigger_type' => 'manual',
            'trigger_config' => array(),
            'actions' => array(),
            'is_active' => true,
        );
        $workflow_data = wp_parse_args( $workflow_data, $defaults );

        // 验证
        if ( empty( $workflow_data['name'] ) ) {
            return new WP_Error( 'invalid_name', '工作流名称不能为空' );
        }

        if ( ! isset( $this->trigger_types[ $workflow_data['trigger_type'] ] ) ) {
            return new WP_Error( 'invalid_trigger', '无效的触发器类型' );
        }

        if ( empty( $workflow_data['actions'] ) ) {
            return new WP_Error( 'no_actions', '至少需要一个动作' );
        }

        // 插入数据库
        $result = $wpdb->insert(
            $table_workflows,
            array(
                'name' => $workflow_data['name'],
                'description' => $workflow_data['description'],
                'trigger_type' => $workflow_data['trigger_type'],
                'trigger_config' => wp_json_encode( $workflow_data['trigger_config'] ),
                'actions' => wp_json_encode( $workflow_data['actions'] ),
                'is_active' => $workflow_data['is_active'] ? 1 : 0,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            )
        );

        if ( $result === false ) {
            return new WP_Error( 'db_error', '无法创建工作流' );
        }

        return $wpdb->insert_id;
    }

    /**
     * 更新工作流
     *
     * @param int $workflow_id 工作流ID
     * @param array $workflow_data 更新数据
     *
     * @return bool|WP_Error 成功返回true,失败返回错误
     */
    public function update_workflow( $workflow_id, $workflow_data ) {
        global $wpdb;
        $table_workflows = $wpdb->prefix . 'aiscg_workflows';

        $update_data = array(
            'updated_at' => current_time( 'mysql' ),
        );

        if ( isset( $workflow_data['name'] ) ) {
            $update_data['name'] = $workflow_data['name'];
        }

        if ( isset( $workflow_data['description'] ) ) {
            $update_data['description'] = $workflow_data['description'];
        }

        if ( isset( $workflow_data['trigger_type'] ) ) {
            $update_data['trigger_type'] = $workflow_data['trigger_type'];
        }

        if ( isset( $workflow_data['trigger_config'] ) ) {
            $update_data['trigger_config'] = wp_json_encode( $workflow_data['trigger_config'] );
        }

        if ( isset( $workflow_data['actions'] ) ) {
            $update_data['actions'] = wp_json_encode( $workflow_data['actions'] );
        }

        if ( isset( $workflow_data['is_active'] ) ) {
            $update_data['is_active'] = $workflow_data['is_active'] ? 1 : 0;
        }

        $result = $wpdb->update(
            $table_workflows,
            $update_data,
            array( 'id' => $workflow_id )
        );

        if ( $result === false ) {
            return new WP_Error( 'db_error', '无法更新工作流' );
        }

        return true;
    }

    /**
     * 删除工作流
     *
     * @param int $workflow_id 工作流ID
     *
     * @return bool|WP_Error 成功返回true,失败返回错误
     */
    public function delete_workflow( $workflow_id ) {
        global $wpdb;
        $table_workflows = $wpdb->prefix . 'aiscg_workflows';

        $result = $wpdb->delete( $table_workflows, array( 'id' => $workflow_id ) );

        if ( $result === false ) {
            return new WP_Error( 'db_error', '无法删除工作流' );
        }

        return true;
    }

    /**
     * 执行工作流
     *
     * @param int $workflow_id 工作流ID
     * @param array $context 执行上下文
     *
     * @return array|WP_Error 执行结果或错误
     */
    public function execute_workflow( $workflow_id, $context = array() ) {
        global $wpdb;
        $table_workflows = $wpdb->prefix . 'aiscg_workflows';

        // 获取工作流
        $workflow = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_workflows WHERE id = %d", $workflow_id ),
            ARRAY_A
        );

        if ( ! $workflow ) {
            return new WP_Error( 'workflow_not_found', '工作流不存在' );
        }

        if ( ! $workflow['is_active'] ) {
            return new WP_Error( 'workflow_inactive', '工作流未启用' );
        }

        // 创建执行记录
        $run_id = $this->create_workflow_run( $workflow_id, $context );

        $actions = json_decode( $workflow['actions'], true );
        $results = array();
        $success = true;

        try {
            foreach ( $actions as $index => $action ) {
                $action_result = $this->execute_action( $action, $context );

                $results[] = array(
                    'action' => $action,
                    'result' => $action_result,
                    'success' => ! is_wp_error( $action_result ),
                );

                if ( is_wp_error( $action_result ) ) {
                    $success = false;

                    // 检查是否继续执行
                    if ( ! ( $action['continue_on_error'] ?? false ) ) {
                        break;
                    }
                }

                // 更新上下文
                if ( ! is_wp_error( $action_result ) && is_array( $action_result ) ) {
                    $context = array_merge( $context, $action_result );
                }
            }

            // 更新执行记录
            $this->update_workflow_run( $run_id, array(
                'status' => $success ? 'completed' : 'failed',
                'results' => $results,
                'completed_at' => current_time( 'mysql' ),
            ) );

            return array(
                'success' => $success,
                'run_id' => $run_id,
                'results' => $results,
                'workflow' => $workflow,
            );

        } catch ( Exception $e ) {
            $this->update_workflow_run( $run_id, array(
                'status' => 'error',
                'error' => $e->getMessage(),
                'completed_at' => current_time( 'mysql' ),
            ) );

            return new WP_Error( 'execution_error', $e->getMessage() );
        }
    }

    /**
     * 执行动作
     *
     * @param array $action 动作配置
     * @param array $context 上下文
     *
     * @return mixed|WP_Error 执行结果或错误
     */
    private function execute_action( $action, $context ) {
        $action_type = $action['type'] ?? '';

        switch ( $action_type ) {
            case 'generate_content':
                return $this->action_generate_content( $action, $context );

            case 'optimize_content':
                return $this->action_optimize_content( $action, $context );

            case 'check_quality':
                return $this->action_check_quality( $action, $context );

            case 'publish_content':
                return $this->action_publish_content( $action, $context );

            case 'send_notification':
                return $this->action_send_notification( $action, $context );

            case 'backup_data':
                return $this->action_backup_data( $action, $context );

            case 'export_content':
                return $this->action_export_content( $action, $context );

            case 'tag_content':
                return $this->action_tag_content( $action, $context );

            case 'classify_content':
                return $this->action_classify_content( $action, $context );

            case 'translate_content':
                return $this->action_translate_content( $action, $context );

            case 'run_custom_code':
                return $this->action_run_custom_code( $action, $context );

            default:
                return new WP_Error( 'unknown_action', '未知的动作类型: ' . $action_type );
        }
    }

    /**
     * 动作: 生成内容
     */
    private function action_generate_content( $action, $context ) {
        $generator = new AISCG_Content_Generator();

        $config = $action['config'] ?? array();
        $topic = $config['topic'] ?? $context['topic'] ?? '';

        if ( empty( $topic ) ) {
            return new WP_Error( 'no_topic', '缺少主题' );
        }

        $result = $generator->generate( array(
            'topic' => $topic,
            'platform' => $config['platform'] ?? 'xiaohongshu',
            'ai_model' => $config['ai_model'] ?? '',
        ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array( 'content_id' => $result['id'] ?? 0 );
    }

    /**
     * 动作: 优化内容
     */
    private function action_optimize_content( $action, $context ) {
        $optimizer = new AISCG_Content_Optimizer();

        $content_id = $context['content_id'] ?? ( $action['config']['content_id'] ?? 0 );

        if ( empty( $content_id ) ) {
            return new WP_Error( 'no_content_id', '缺少内容ID' );
        }

        $result = $optimizer->optimize( $content_id, $action['config'] ?? array() );

        return $result;
    }

    /**
     * 动作: 质量检测
     */
    private function action_check_quality( $action, $context ) {
        $detector = new AISCG_Quality_Detector();

        $content_id = $context['content_id'] ?? ( $action['config']['content_id'] ?? 0 );

        if ( empty( $content_id ) ) {
            return new WP_Error( 'no_content_id', '缺少内容ID' );
        }

        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';
        $content = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_posts WHERE id = %d", $content_id ),
            ARRAY_A
        );

        if ( ! $content ) {
            return new WP_Error( 'content_not_found', '内容不存在' );
        }

        $result = $detector->detect( $content, $action['config'] ?? array() );

        return array(
            'quality_score' => $result['score'],
            'issues' => $result['issues'],
            'can_publish' => $result['can_publish'],
        );
    }

    /**
     * 动作: 发布内容
     */
    private function action_publish_content( $action, $context ) {
        $content_id = $context['content_id'] ?? ( $action['config']['content_id'] ?? 0 );

        if ( empty( $content_id ) ) {
            return new WP_Error( 'no_content_id', '缺少内容ID' );
        }

        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        $result = $wpdb->update(
            $table_posts,
            array( 'status' => 'published' ),
            array( 'id' => $content_id )
        );

        if ( $result === false ) {
            return new WP_Error( 'publish_failed', '发布失败' );
        }

        do_action( 'aiscg_content_published', $content_id );

        return array( 'published' => true );
    }

    /**
     * 动作: 发送通知
     */
    private function action_send_notification( $action, $context ) {
        $notification = new AISCG_Notification();

        $config = $action['config'] ?? array();
        $message = $config['message'] ?? '工作流执行通知';

        // 替换变量
        foreach ( $context as $key => $value ) {
            if ( is_scalar( $value ) ) {
                $message = str_replace( '{' . $key . '}', $value, $message );
            }
        }

        $result = $notification->send_webhook( array(
            'message' => $message,
            'context' => $context,
        ) );

        return $result;
    }

    /**
     * 动作: 备份数据
     */
    private function action_backup_data( $action, $context ) {
        $backup_manager = new AISCG_Backup_Manager();

        $result = $backup_manager->create_backup( $action['config'] ?? array() );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array( 'backup_id' => $result['backup_id'] );
    }

    /**
     * 动作: 导出内容
     */
    private function action_export_content( $action, $context ) {
        $exporter = new AISCG_Content_Exporter();

        $config = $action['config'] ?? array();
        $format = $config['format'] ?? 'json';

        $result = $exporter->export( array(
            'format' => $format,
            'filters' => $config['filters'] ?? array(),
        ) );

        return $result;
    }

    /**
     * 动作: 添加标签
     */
    private function action_tag_content( $action, $context ) {
        $tag_generator = new AISCG_AI_Tag_Generator();

        $content_id = $context['content_id'] ?? ( $action['config']['content_id'] ?? 0 );

        if ( empty( $content_id ) ) {
            return new WP_Error( 'no_content_id', '缺少内容ID' );
        }

        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';
        $content = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_posts WHERE id = %d", $content_id ),
            ARRAY_A
        );

        if ( ! $content ) {
            return new WP_Error( 'content_not_found', '内容不存在' );
        }

        $tags = $tag_generator->generate_tags(
            $content['title'] . "\n" . $content['content'],
            $action['config'] ?? array()
        );

        // 更新标签
        $wpdb->update(
            $table_posts,
            array( 'hashtags' => wp_json_encode( $tags ) ),
            array( 'id' => $content_id )
        );

        return array( 'tags' => $tags );
    }

    /**
     * 动作: 内容分类
     */
    private function action_classify_content( $action, $context ) {
        $classifier = new AISCG_Content_Classifier();

        $content_id = $context['content_id'] ?? ( $action['config']['content_id'] ?? 0 );

        if ( empty( $content_id ) ) {
            return new WP_Error( 'no_content_id', '缺少内容ID' );
        }

        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';
        $content = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_posts WHERE id = %d", $content_id ),
            ARRAY_A
        );

        if ( ! $content ) {
            return new WP_Error( 'content_not_found', '内容不存在' );
        }

        $result = $classifier->classify(
            $content['title'] . "\n" . $content['content'],
            $action['config'] ?? array()
        );

        return array( 'category' => $result['category'] );
    }

    /**
     * 动作: 翻译内容
     */
    private function action_translate_content( $action, $context ) {
        $translator = new AISCG_Multilingual_Generator();

        $content_id = $context['content_id'] ?? ( $action['config']['content_id'] ?? 0 );
        $target_language = $action['config']['target_language'] ?? 'en';

        if ( empty( $content_id ) ) {
            return new WP_Error( 'no_content_id', '缺少内容ID' );
        }

        $result = $translator->translate_content( $content_id, $target_language );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array( 'translated_content_id' => $result['id'] ?? 0 );
    }

    /**
     * 动作: 运行自定义代码
     */
    private function action_run_custom_code( $action, $context ) {
        $code = $action['config']['code'] ?? '';

        if ( empty( $code ) ) {
            return new WP_Error( 'no_code', '缺少代码' );
        }

        // 安全执行
        try {
            ob_start();
            $result = eval( $code );
            $output = ob_get_clean();

            return array(
                'result' => $result,
                'output' => $output,
            );
        } catch ( Exception $e ) {
            ob_end_clean();
            return new WP_Error( 'code_error', $e->getMessage() );
        }
    }

    /**
     * 获取工作流列表
     *
     * @param array $args 查询参数
     *
     * @return array 工作流列表
     */
    public function get_workflows( $args = array() ) {
        global $wpdb;
        $table_workflows = $wpdb->prefix . 'aiscg_workflows';

        $defaults = array(
            'is_active' => null,
            'trigger_type' => null,
            'limit' => 50,
            'offset' => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );

        if ( $args['is_active'] !== null ) {
            $where[] = $wpdb->prepare( 'is_active = %d', $args['is_active'] ? 1 : 0 );
        }

        if ( $args['trigger_type'] !== null ) {
            $where[] = $wpdb->prepare( 'trigger_type = %s', $args['trigger_type'] );
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT * FROM $table_workflows WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $workflows = $wpdb->get_results(
            $wpdb->prepare( $query, $args['limit'], $args['offset'] ),
            ARRAY_A
        );

        // 解析JSON字段
        foreach ( $workflows as &$workflow ) {
            $workflow['trigger_config'] = json_decode( $workflow['trigger_config'], true );
            $workflow['actions'] = json_decode( $workflow['actions'], true );
            $workflow['is_active'] = (bool) $workflow['is_active'];
        }

        return $workflows;
    }

    /**
     * 获取工作流执行历史
     *
     * @param int $workflow_id 工作流ID
     * @param array $args 查询参数
     *
     * @return array 执行历史
     */
    public function get_workflow_runs( $workflow_id = null, $args = array() ) {
        global $wpdb;
        $table_runs = $wpdb->prefix . 'aiscg_workflow_runs';

        $defaults = array(
            'status' => null,
            'limit' => 50,
            'offset' => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );

        if ( $workflow_id !== null ) {
            $where[] = $wpdb->prepare( 'workflow_id = %d', $workflow_id );
        }

        if ( $args['status'] !== null ) {
            $where[] = $wpdb->prepare( 'status = %s', $args['status'] );
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT * FROM $table_runs WHERE $where_clause ORDER BY started_at DESC LIMIT %d OFFSET %d";
        $runs = $wpdb->get_results(
            $wpdb->prepare( $query, $args['limit'], $args['offset'] ),
            ARRAY_A
        );

        // 解析JSON字段
        foreach ( $runs as &$run ) {
            $run['context'] = json_decode( $run['context'], true );
            $run['results'] = json_decode( $run['results'], true );
        }

        return $runs;
    }

    /**
     * 处理内容创建事件
     *
     * @param int $content_id 内容ID
     */
    public function handle_content_created( $content_id ) {
        $this->trigger_workflows( 'content_created', array( 'content_id' => $content_id ) );
    }

    /**
     * 处理内容更新事件
     *
     * @param int $content_id 内容ID
     */
    public function handle_content_updated( $content_id ) {
        $this->trigger_workflows( 'content_updated', array( 'content_id' => $content_id ) );
    }

    /**
     * 处理质量检查失败事件
     *
     * @param int $content_id 内容ID
     * @param array $issues 问题列表
     */
    public function handle_quality_check_failed( $content_id, $issues ) {
        $this->trigger_workflows( 'quality_check_failed', array(
            'content_id' => $content_id,
            'issues' => $issues,
        ) );
    }

    /**
     * 处理备份完成事件
     *
     * @param array $backup_info 备份信息
     */
    public function handle_backup_completed( $backup_info ) {
        $this->trigger_workflows( 'backup_completed', $backup_info );
    }

    /**
     * 运行定时工作流
     */
    public function run_scheduled_workflows() {
        $workflows = $this->get_workflows( array(
            'is_active' => true,
            'trigger_type' => 'schedule',
        ) );

        foreach ( $workflows as $workflow ) {
            $config = $workflow['trigger_config'];
            $schedule = $config['schedule'] ?? 'daily';
            $last_run = $config['last_run'] ?? 0;
            $current_time = time();

            $should_run = false;

            switch ( $schedule ) {
                case 'hourly':
                    $should_run = ( $current_time - $last_run ) >= HOUR_IN_SECONDS;
                    break;
                case 'daily':
                    $should_run = ( $current_time - $last_run ) >= DAY_IN_SECONDS;
                    break;
                case 'weekly':
                    $should_run = ( $current_time - $last_run ) >= WEEK_IN_SECONDS;
                    break;
            }

            if ( $should_run ) {
                $this->execute_workflow( $workflow['id'], array( 'trigger' => 'schedule' ) );

                // 更新最后运行时间
                $config['last_run'] = $current_time;
                $this->update_workflow( $workflow['id'], array(
                    'trigger_config' => $config,
                ) );
            }
        }
    }

    /**
     * 触发工作流
     *
     * @param string $trigger_type 触发器类型
     * @param array $context 上下文
     */
    private function trigger_workflows( $trigger_type, $context = array() ) {
        $workflows = $this->get_workflows( array(
            'is_active' => true,
            'trigger_type' => $trigger_type,
        ) );

        foreach ( $workflows as $workflow ) {
            // 异步执行
            wp_schedule_single_event( time(), 'aiscg_execute_workflow', array(
                'workflow_id' => $workflow['id'],
                'context' => $context,
            ) );
        }
    }

    /**
     * 创建工作流执行记录
     *
     * @param int $workflow_id 工作流ID
     * @param array $context 上下文
     *
     * @return int 执行记录ID
     */
    private function create_workflow_run( $workflow_id, $context ) {
        global $wpdb;
        $table_runs = $wpdb->prefix . 'aiscg_workflow_runs';

        $this->create_workflow_runs_table();

        $wpdb->insert(
            $table_runs,
            array(
                'workflow_id' => $workflow_id,
                'status' => 'running',
                'context' => wp_json_encode( $context ),
                'started_at' => current_time( 'mysql' ),
            )
        );

        return $wpdb->insert_id;
    }

    /**
     * 更新工作流执行记录
     *
     * @param int $run_id 执行记录ID
     * @param array $data 更新数据
     */
    private function update_workflow_run( $run_id, $data ) {
        global $wpdb;
        $table_runs = $wpdb->prefix . 'aiscg_workflow_runs';

        $update_data = array();

        if ( isset( $data['status'] ) ) {
            $update_data['status'] = $data['status'];
        }

        if ( isset( $data['results'] ) ) {
            $update_data['results'] = wp_json_encode( $data['results'] );
        }

        if ( isset( $data['error'] ) ) {
            $update_data['error'] = $data['error'];
        }

        if ( isset( $data['completed_at'] ) ) {
            $update_data['completed_at'] = $data['completed_at'];
        }

        $wpdb->update( $table_runs, $update_data, array( 'id' => $run_id ) );
    }

    /**
     * 创建工作流表
     */
    private function create_workflows_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiscg_workflows';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text,
            trigger_type varchar(50) NOT NULL,
            trigger_config longtext,
            actions longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY trigger_type (trigger_type),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * 创建工作流执行记录表
     */
    private function create_workflow_runs_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiscg_workflow_runs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            workflow_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL,
            context longtext,
            results longtext,
            error text,
            started_at datetime NOT NULL,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
