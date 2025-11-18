<?php
/**
 * 批量处理器类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 批量处理器类
 */
class AISCG_Batch_Processor {

    /**
     * 数据库实例
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 内容生成器
     *
     * @var AISCG_Content_Generator
     */
    private $content_generator;

    /**
     * 图片生成器
     *
     * @var AISCG_Image_Generator
     */
    private $image_generator;

    /**
     * 批处理任务选项键
     *
     * @var string
     */
    private $batch_option_key = 'aiscg_batch_queue';

    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = new AISCG_Database();
        $this->content_generator = new AISCG_Content_Generator();
        $this->image_generator = new AISCG_Image_Generator();
    }

    /**
     * 启动批量生成
     *
     * @param array $topics     主题列表
     * @param array $options    选项
     * @return array 批处理结果
     */
    public function start_batch( $topics, $options = array() ) {
        $defaults = array(
            'platform' => 'xiaohongshu',
            'ai_model' => '',
            'image_count' => 1,
            'concurrent' => get_option( 'aiscg_concurrent_requests', 3 ),
            'delay' => 1, // 每个请求之间的延迟(秒)
        );

        $options = wp_parse_args( $options, $defaults );

        // 创建批处理任务
        $batch_id = $this->create_batch_task( $topics, $options );

        // 开始处理
        $results = $this->process_batch( $batch_id, $topics, $options );

        return array(
            'batch_id' => $batch_id,
            'total' => count( $topics ),
            'success' => $results['success'],
            'failed' => $results['failed'],
            'results' => $results['items'],
        );
    }

    /**
     * 创建批处理任务
     *
     * @param array $topics  主题列表
     * @param array $options 选项
     * @return string 批处理ID
     */
    private function create_batch_task( $topics, $options ) {
        $batch_id = 'batch_' . time() . '_' . wp_rand( 1000, 9999 );

        $task = array(
            'id' => $batch_id,
            'topics' => $topics,
            'options' => $options,
            'status' => 'pending',
            'created_at' => current_time( 'mysql' ),
            'total' => count( $topics ),
            'completed' => 0,
            'results' => array(),
        );

        // 保存到选项表
        $queue = get_option( $this->batch_option_key, array() );
        $queue[ $batch_id ] = $task;
        update_option( $this->batch_option_key, $queue );

        return $batch_id;
    }

    /**
     * 处理批量任务
     *
     * @param string $batch_id 批处理ID
     * @param array  $topics   主题列表
     * @param array  $options  选项
     * @return array 处理结果
     */
    private function process_batch( $batch_id, $topics, $options ) {
        $success = 0;
        $failed = 0;
        $items = array();

        foreach ( $topics as $index => $topic ) {
            try {
                // 生成内容
                $result = $this->content_generator->generate(
                    $topic,
                    $options['platform'],
                    $options['ai_model']
                );

                // 生成图片
                if ( $options['image_count'] > 0 ) {
                    $images = $this->image_generator->generate_for_post(
                        $result['post_id'],
                        $options['image_count']
                    );
                    $result['images'] = $images;
                }

                $items[] = array(
                    'topic' => $topic,
                    'status' => 'success',
                    'post_id' => $result['post_id'],
                    'data' => $result,
                );

                $success++;

                // 更新进度
                $this->update_batch_progress( $batch_id, $index + 1, 'processing' );

                // 记录日志
                if ( get_option( 'aiscg_enable_logging', false ) ) {
                    error_log( sprintf( 'AISCG Batch: Generated content for "%s" (Post ID: %d)', $topic, $result['post_id'] ) );
                }

                // 延迟以避免API限流
                if ( isset( $options['delay'] ) && $options['delay'] > 0 ) {
                    sleep( $options['delay'] );
                }

            } catch ( Exception $e ) {
                $items[] = array(
                    'topic' => $topic,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                );

                $failed++;

                // 记录错误
                error_log( sprintf( 'AISCG Batch Error: Failed to generate content for "%s" - %s', $topic, $e->getMessage() ) );

                // 更新进度
                $this->update_batch_progress( $batch_id, $index + 1, 'processing' );
            }
        }

        // 标记批处理完成
        $this->update_batch_progress( $batch_id, count( $topics ), 'completed' );

        return array(
            'success' => $success,
            'failed' => $failed,
            'items' => $items,
        );
    }

    /**
     * 更新批处理进度
     *
     * @param string $batch_id  批处理ID
     * @param int    $completed 已完成数量
     * @param string $status    状态
     */
    private function update_batch_progress( $batch_id, $completed, $status ) {
        $queue = get_option( $this->batch_option_key, array() );

        if ( isset( $queue[ $batch_id ] ) ) {
            $queue[ $batch_id ]['completed'] = $completed;
            $queue[ $batch_id ]['status'] = $status;
            $queue[ $batch_id ]['updated_at'] = current_time( 'mysql' );

            update_option( $this->batch_option_key, $queue );
        }
    }

    /**
     * 获取批处理状态
     *
     * @param string $batch_id 批处理ID
     * @return array|null 批处理信息
     */
    public function get_batch_status( $batch_id ) {
        $queue = get_option( $this->batch_option_key, array() );

        return isset( $queue[ $batch_id ] ) ? $queue[ $batch_id ] : null;
    }

    /**
     * 从CSV导入主题
     *
     * @param string $file_path CSV文件路径
     * @return array 主题列表
     * @throws Exception 如果文件无效
     */
    public function import_from_csv( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            throw new Exception( __( 'CSV file not found', 'ai-social-content-generator' ) );
        }

        $topics = array();
        $handle = fopen( $file_path, 'r' );

        if ( $handle === false ) {
            throw new Exception( __( 'Failed to open CSV file', 'ai-social-content-generator' ) );
        }

        // 跳过表头(如果有)
        $first_line = fgetcsv( $handle );
        if ( $first_line && ! $this->is_header_row( $first_line ) ) {
            $topics[] = $this->extract_topic_from_row( $first_line );
        }

        // 读取其余行
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $topic = $this->extract_topic_from_row( $row );
            if ( ! empty( $topic ) ) {
                $topics[] = $topic;
            }
        }

        fclose( $handle );

        return $topics;
    }

    /**
     * 判断是否为表头行
     *
     * @param array $row 行数据
     * @return bool
     */
    private function is_header_row( $row ) {
        // 简单判断:如果第一列包含"topic"、"主题"等关键词,认为是表头
        $first_cell = strtolower( trim( $row[0] ) );
        return in_array( $first_cell, array( 'topic', 'subject', '主题', '话题', 'title' ) );
    }

    /**
     * 从CSV行提取主题
     *
     * @param array $row 行数据
     * @return string 主题
     */
    private function extract_topic_from_row( $row ) {
        // 假设主题在第一列
        return isset( $row[0] ) ? trim( $row[0] ) : '';
    }

    /**
     * 清理旧的批处理任务
     *
     * @param int $days 保留天数
     */
    public function cleanup_old_batches( $days = 7 ) {
        $queue = get_option( $this->batch_option_key, array() );
        $cutoff = strtotime( "-{$days} days" );

        foreach ( $queue as $batch_id => $task ) {
            $created_time = strtotime( $task['created_at'] );
            if ( $created_time < $cutoff ) {
                unset( $queue[ $batch_id ] );
            }
        }

        update_option( $this->batch_option_key, $queue );
    }

    /**
     * 导出批处理结果
     *
     * @param string $batch_id 批处理ID
     * @param string $format   格式(csv/json)
     * @return string 文件路径
     * @throws Exception 如果导出失败
     */
    public function export_batch_results( $batch_id, $format = 'csv' ) {
        $batch = $this->get_batch_status( $batch_id );

        if ( ! $batch ) {
            throw new Exception( __( 'Batch not found', 'ai-social-content-generator' ) );
        }

        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/aiscg-exports';

        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
        }

        $filename = "batch_{$batch_id}_export." . $format;
        $filepath = $export_dir . '/' . $filename;

        if ( $format === 'csv' ) {
            $this->export_to_csv( $batch, $filepath );
        } elseif ( $format === 'json' ) {
            $this->export_to_json( $batch, $filepath );
        } else {
            throw new Exception( __( 'Unsupported export format', 'ai-social-content-generator' ) );
        }

        return $filepath;
    }

    /**
     * 导出为CSV
     *
     * @param array  $batch    批处理数据
     * @param string $filepath 文件路径
     */
    private function export_to_csv( $batch, $filepath ) {
        $handle = fopen( $filepath, 'w' );

        // 写入表头
        fputcsv( $handle, array( 'Topic', 'Status', 'Post ID', 'Title', 'Platform', 'Created At' ) );

        // 写入数据
        foreach ( $batch['results'] as $result ) {
            if ( $result['status'] === 'success' ) {
                $post = $this->db->get_post( $result['post_id'] );
                fputcsv( $handle, array(
                    $result['topic'],
                    $result['status'],
                    $result['post_id'],
                    $post ? $post->title : '',
                    $post ? $post->platform : '',
                    $post ? $post->created_at : '',
                ) );
            } else {
                fputcsv( $handle, array(
                    $result['topic'],
                    $result['status'],
                    '',
                    '',
                    '',
                    '',
                ) );
            }
        }

        fclose( $handle );
    }

    /**
     * 导出为JSON
     *
     * @param array  $batch    批处理数据
     * @param string $filepath 文件路径
     */
    private function export_to_json( $batch, $filepath ) {
        file_put_contents( $filepath, wp_json_encode( $batch, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    }
}
