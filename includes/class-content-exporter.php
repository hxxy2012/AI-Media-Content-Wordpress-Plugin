<?php
/**
 * 内容导出类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 内容导出类
 */
class AISCG_Content_Exporter {

    /**
     * 数据库实例
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 导出目录
     *
     * @var string
     */
    private $export_dir;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = new AISCG_Database();
        $this->init_export_dir();
    }

    /**
     * 初始化导出目录
     */
    private function init_export_dir() {
        $upload = wp_upload_dir();
        $this->export_dir = $upload['basedir'] . '/aiscg-exports';

        if ( ! file_exists( $this->export_dir ) ) {
            wp_mkdir_p( $this->export_dir );
        }
    }

    /**
     * 导出单个内容
     *
     * @param int    $post_id 内容ID
     * @param string $format  格式(txt/json/csv/markdown)
     * @return string 文件路径
     * @throws Exception 如果导出失败
     */
    public function export_single( $post_id, $format = 'txt' ) {
        $post = $this->db->get_post( $post_id );

        if ( ! $post ) {
            throw new Exception( __( 'Post not found', 'ai-social-content-generator' ) );
        }

        // 获取图片
        $post->images = $this->db->get_images_by_post_id( $post_id );

        $filename = 'post_' . $post_id . '_' . time() . '.' . $format;
        $filepath = $this->export_dir . '/' . $filename;

        switch ( $format ) {
            case 'txt':
                $this->export_to_txt( $post, $filepath );
                break;
            case 'json':
                $this->export_to_json( array( $post ), $filepath );
                break;
            case 'csv':
                $this->export_to_csv( array( $post ), $filepath );
                break;
            case 'markdown':
            case 'md':
                $this->export_to_markdown( $post, $filepath );
                break;
            default:
                throw new Exception( __( 'Unsupported export format', 'ai-social-content-generator' ) );
        }

        return $filepath;
    }

    /**
     * 导出多个内容
     *
     * @param array  $post_ids 内容ID列表
     * @param string $format   格式
     * @return string 文件路径
     * @throws Exception 如果导出失败
     */
    public function export_multiple( $post_ids, $format = 'json' ) {
        $posts = array();

        foreach ( $post_ids as $post_id ) {
            $post = $this->db->get_post( $post_id );
            if ( $post ) {
                $post->images = $this->db->get_images_by_post_id( $post_id );
                $posts[] = $post;
            }
        }

        if ( empty( $posts ) ) {
            throw new Exception( __( 'No posts found', 'ai-social-content-generator' ) );
        }

        $filename = 'posts_export_' . time() . '.' . $format;
        $filepath = $this->export_dir . '/' . $filename;

        switch ( $format ) {
            case 'json':
                $this->export_to_json( $posts, $filepath );
                break;
            case 'csv':
                $this->export_to_csv( $posts, $filepath );
                break;
            case 'zip':
                return $this->export_to_zip( $posts );
            default:
                throw new Exception( __( 'Unsupported export format', 'ai-social-content-generator' ) );
        }

        return $filepath;
    }

    /**
     * 导出为TXT格式
     *
     * @param object $post     内容对象
     * @param string $filepath 文件路径
     */
    private function export_to_txt( $post, $filepath ) {
        $content = '';

        $content .= "=================================\n";
        $content .= strtoupper( $post->platform ) . " CONTENT\n";
        $content .= "=================================\n\n";

        $content .= "TITLE:\n";
        $content .= $post->title . "\n\n";

        $content .= "CONTENT:\n";
        $content .= $post->content . "\n\n";

        if ( ! empty( $post->hashtags ) ) {
            $content .= "HASHTAGS:\n";
            $hashtags = is_array( $post->hashtags ) ? $post->hashtags : json_decode( $post->hashtags, true );
            $content .= implode( ' ', $hashtags ) . "\n\n";
        }

        $content .= "METADATA:\n";
        $content .= "AI Model: " . $post->ai_model . "\n";
        $content .= "Created: " . $post->created_at . "\n";
        $content .= "Status: " . $post->status . "\n";

        if ( ! empty( $post->images ) ) {
            $content .= "\nIMAGES:\n";
            foreach ( $post->images as $image ) {
                $content .= "- " . $image->image_url . "\n";
            }
        }

        file_put_contents( $filepath, $content );
    }

    /**
     * 导出为JSON格式
     *
     * @param array  $posts    内容列表
     * @param string $filepath 文件路径
     */
    private function export_to_json( $posts, $filepath ) {
        $data = array(
            'export_date' => current_time( 'mysql' ),
            'total_posts' => count( $posts ),
            'posts' => $posts,
        );

        file_put_contents( $filepath, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    }

    /**
     * 导出为CSV格式
     *
     * @param array  $posts    内容列表
     * @param string $filepath 文件路径
     */
    private function export_to_csv( $posts, $filepath ) {
        $handle = fopen( $filepath, 'w' );

        // BOM for UTF-8
        fprintf( $handle, chr(0xEF).chr(0xBB).chr(0xBF) );

        // 表头
        fputcsv( $handle, array(
            'ID',
            'Platform',
            'Title',
            'Content',
            'Hashtags',
            'AI Model',
            'Status',
            'Created At',
            'Image Count',
        ) );

        // 数据行
        foreach ( $posts as $post ) {
            $hashtags = is_array( $post->hashtags ) ? $post->hashtags : json_decode( $post->hashtags, true );
            $hashtags_str = is_array( $hashtags ) ? implode( ' ', $hashtags ) : '';

            fputcsv( $handle, array(
                $post->id,
                $post->platform,
                $post->title,
                $post->content,
                $hashtags_str,
                $post->ai_model,
                $post->status,
                $post->created_at,
                isset( $post->images ) ? count( $post->images ) : 0,
            ) );
        }

        fclose( $handle );
    }

    /**
     * 导出为Markdown格式
     *
     * @param object $post     内容对象
     * @param string $filepath 文件路径
     */
    private function export_to_markdown( $post, $filepath ) {
        $content = '';

        $content .= "# " . $post->title . "\n\n";

        $content .= "> Platform: " . strtoupper( $post->platform ) . "  \n";
        $content .= "> AI Model: " . $post->ai_model . "  \n";
        $content .= "> Created: " . $post->created_at . "  \n";
        $content .= "> Status: " . $post->status . "\n\n";

        $content .= "## Content\n\n";
        $content .= $post->content . "\n\n";

        if ( ! empty( $post->hashtags ) ) {
            $content .= "## Hashtags\n\n";
            $hashtags = is_array( $post->hashtags ) ? $post->hashtags : json_decode( $post->hashtags, true );
            foreach ( $hashtags as $tag ) {
                $content .= "- " . $tag . "\n";
            }
            $content .= "\n";
        }

        if ( ! empty( $post->images ) ) {
            $content .= "## Images\n\n";
            foreach ( $post->images as $index => $image ) {
                $content .= "![Image " . ( $index + 1 ) . "](" . $image->image_url . ")\n\n";
            }
        }

        file_put_contents( $filepath, $content );
    }

    /**
     * 导出为ZIP格式(包含文本和图片)
     *
     * @param array $posts 内容列表
     * @return string ZIP文件路径
     * @throws Exception 如果导出失败
     */
    private function export_to_zip( $posts ) {
        $zip_filename = $this->export_dir . '/posts_export_' . time() . '.zip';
        $zip = new ZipArchive();

        if ( $zip->open( $zip_filename, ZipArchive::CREATE ) !== true ) {
            throw new Exception( __( 'Failed to create ZIP file', 'ai-social-content-generator' ) );
        }

        foreach ( $posts as $index => $post ) {
            $folder_name = 'post_' . $post->id . '/';

            // 添加文本文件
            $text_content = $this->generate_text_content( $post );
            $zip->addFromString( $folder_name . 'content.txt', $text_content );

            // 添加JSON文件
            $zip->addFromString( $folder_name . 'data.json', wp_json_encode( $post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

            // 添加图片
            if ( ! empty( $post->images ) ) {
                foreach ( $post->images as $img_index => $image ) {
                    if ( file_exists( $image->image_path ) ) {
                        $ext = pathinfo( $image->image_path, PATHINFO_EXTENSION );
                        $zip->addFile( $image->image_path, $folder_name . 'images/image_' . ( $img_index + 1 ) . '.' . $ext );
                    }
                }
            }
        }

        $zip->close();

        return $zip_filename;
    }

    /**
     * 生成文本内容
     *
     * @param object $post 内容对象
     * @return string 文本内容
     */
    private function generate_text_content( $post ) {
        $content = $post->title . "\n\n";
        $content .= $post->content . "\n\n";

        if ( ! empty( $post->hashtags ) ) {
            $hashtags = is_array( $post->hashtags ) ? $post->hashtags : json_decode( $post->hashtags, true );
            $content .= implode( ' ', $hashtags ) . "\n";
        }

        return $content;
    }

    /**
     * 导出全部内容(按条件筛选)
     *
     * @param array  $filters 筛选条件
     * @param string $format  格式
     * @return string 文件路径
     */
    public function export_all( $filters = array(), $format = 'json' ) {
        $posts = $this->db->get_posts( $filters );

        if ( empty( $posts ) ) {
            throw new Exception( __( 'No posts found', 'ai-social-content-generator' ) );
        }

        // 获取每个内容的图片
        foreach ( $posts as $post ) {
            $post->images = $this->db->get_images_by_post_id( $post->id );
        }

        $filename = 'all_posts_export_' . time() . '.' . $format;
        $filepath = $this->export_dir . '/' . $filename;

        switch ( $format ) {
            case 'json':
                $this->export_to_json( $posts, $filepath );
                break;
            case 'csv':
                $this->export_to_csv( $posts, $filepath );
                break;
            case 'zip':
                return $this->export_to_zip( $posts );
            default:
                throw new Exception( __( 'Unsupported export format', 'ai-social-content-generator' ) );
        }

        return $filepath;
    }

    /**
     * 清理旧的导出文件
     *
     * @param int $days 保留天数
     */
    public function cleanup_old_exports( $days = 7 ) {
        $cutoff = time() - ( $days * DAY_IN_SECONDS );

        $files = glob( $this->export_dir . '/*' );

        foreach ( $files as $file ) {
            if ( is_file( $file ) && filemtime( $file ) < $cutoff ) {
                unlink( $file );
            }
        }
    }

    /**
     * 获取导出目录URL
     *
     * @return string 目录URL
     */
    public function get_export_dir_url() {
        $upload = wp_upload_dir();
        return $upload['baseurl'] . '/aiscg-exports';
    }

    /**
     * 获取最近的导出文件列表
     *
     * @param int $limit 限制数量
     * @return array 文件列表
     */
    public function get_recent_exports( $limit = 10 ) {
        $files = glob( $this->export_dir . '/*' );

        // 按修改时间排序
        usort( $files, function( $a, $b ) {
            return filemtime( $b ) - filemtime( $a );
        } );

        $files = array_slice( $files, 0, $limit );

        $exports = array();
        $base_url = $this->get_export_dir_url();

        foreach ( $files as $file ) {
            $exports[] = array(
                'filename' => basename( $file ),
                'url' => $base_url . '/' . basename( $file ),
                'size' => filesize( $file ),
                'modified' => filemtime( $file ),
                'formatted_size' => size_format( filesize( $file ) ),
                'formatted_date' => date( 'Y-m-d H:i:s', filemtime( $file ) ),
            );
        }

        return $exports;
    }
}
