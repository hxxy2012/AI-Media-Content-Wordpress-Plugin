<?php
/**
 * 图片生成器类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 图片生成器类
 */
class AISCG_Image_Generator {

    /**
     * 数据库实例
     *
     * @var AISCG_Database
     */
    private $db;

    /**
     * 上传目录
     *
     * @var string
     */
    private $upload_dir;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = new AISCG_Database();
        $this->init_upload_dir();
    }

    /**
     * 初始化上传目录
     */
    private function init_upload_dir() {
        $upload = wp_upload_dir();
        $this->upload_dir = $upload['basedir'] . '/aiscg-content';

        // 创建目录如果不存在
        if ( ! file_exists( $this->upload_dir ) ) {
            wp_mkdir_p( $this->upload_dir );
        }
    }

    /**
     * 为内容生成图片
     *
     * @param int   $post_id 内容ID
     * @param int   $count   图片数量
     * @param array $options 可选参数
     * @return array 生成的图片信息
     * @throws Exception 如果生成失败
     */
    public function generate_for_post( $post_id, $count = 1, $options = array() ) {
        // 获取内容
        $post = $this->db->get_post( $post_id );
        if ( ! $post ) {
            throw new Exception( __( 'Post not found', 'ai-social-content-generator' ) );
        }

        // 获取图片配置
        $image_config = get_option( 'aiscg_image_config', array() );
        $platform_config = isset( $image_config[ $post->platform ] ) ? $image_config[ $post->platform ] : array();

        // 设置默认尺寸
        $width = isset( $platform_config['width'] ) ? $platform_config['width'] : 1080;
        $height = isset( $platform_config['height'] ) ? $platform_config['height'] : 1080;

        // 合并选项
        $defaults = array(
            'width' => $width,
            'height' => $height,
            'template' => isset( $image_config['default_template'] ) ? $image_config['default_template'] : 'gradient',
            'font_family' => isset( $image_config['font_family'] ) ? $image_config['font_family'] : 'Arial',
            'watermark' => isset( $image_config['watermark_enabled'] ) ? $image_config['watermark_enabled'] : false,
            'watermark_image' => isset( $image_config['watermark_image'] ) ? $image_config['watermark_image'] : '',
        );
        $options = wp_parse_args( $options, $defaults );

        // 准备内容片段
        $content_parts = $this->split_content( $post->content, $count );

        $images = array();

        // 生成每张图片
        for ( $i = 0; $i < $count; $i++ ) {
            $text = isset( $content_parts[ $i ] ) ? $content_parts[ $i ] : $post->title;

            $image_path = $this->generate_image( $text, $options );

            // 保存到数据库
            $upload = wp_upload_dir();
            $image_url = str_replace( $upload['basedir'], $upload['baseurl'], $image_path );

            $image_id = $this->db->create_image( array(
                'post_id' => $post_id,
                'image_url' => $image_url,
                'image_path' => $image_path,
                'image_order' => $i,
                'width' => $options['width'],
                'height' => $options['height'],
            ) );

            $images[] = array(
                'id' => $image_id,
                'url' => $image_url,
                'path' => $image_path,
                'order' => $i,
            );
        }

        return $images;
    }

    /**
     * 生成单张图片
     *
     * @param string $text    文本内容
     * @param array  $options 选项
     * @return string 图片路径
     * @throws Exception 如果生成失败
     */
    private function generate_image( $text, $options ) {
        // 检查GD库是否可用
        if ( ! function_exists( 'imagecreatetruecolor' ) ) {
            throw new Exception( __( 'GD library is not available', 'ai-social-content-generator' ) );
        }

        $width = $options['width'];
        $height = $options['height'];

        // 创建图片
        $image = imagecreatetruecolor( $width, $height );

        // 根据模板类型绘制背景
        switch ( $options['template'] ) {
            case 'gradient':
                $this->draw_gradient_background( $image, $width, $height );
                break;
            case 'solid':
                $this->draw_solid_background( $image, $width, $height );
                break;
            case 'minimal':
                $this->draw_minimal_background( $image, $width, $height );
                break;
            case 'modern':
                $this->draw_modern_background( $image, $width, $height );
                break;
            case 'dark':
                $this->draw_dark_background( $image, $width, $height );
                break;
            case 'colorful':
                $this->draw_colorful_background( $image, $width, $height );
                break;
            case 'elegant':
                $this->draw_elegant_background( $image, $width, $height );
                break;
            default:
                $this->draw_gradient_background( $image, $width, $height );
        }

        // 添加文字
        $this->draw_text( $image, $text, $width, $height, $options );

        // 添加水印
        if ( $options['watermark'] && ! empty( $options['watermark_image'] ) ) {
            $this->add_watermark( $image, $options['watermark_image'], $width, $height );
        }

        // 保存图片
        $filename = 'image_' . time() . '_' . wp_rand( 1000, 9999 ) . '.png';
        $filepath = $this->upload_dir . '/' . $filename;

        if ( ! imagepng( $image, $filepath, 9 ) ) {
            imagedestroy( $image );
            throw new Exception( __( 'Failed to save image', 'ai-social-content-generator' ) );
        }

        imagedestroy( $image );

        return $filepath;
    }

    /**
     * 绘制渐变背景
     *
     * @param resource $image  图片资源
     * @param int      $width  宽度
     * @param int      $height 高度
     */
    private function draw_gradient_background( $image, $width, $height ) {
        // 创建渐变色(从紫色到粉色)
        for ( $i = 0; $i < $height; $i++ ) {
            $r = 147 + ( $i / $height ) * ( 255 - 147 );
            $g = 51 + ( $i / $height ) * ( 182 - 51 );
            $b = 234 + ( $i / $height ) * ( 193 - 234 );

            $color = imagecolorallocate( $image, $r, $g, $b );
            imagefilledrectangle( $image, 0, $i, $width, $i + 1, $color );
        }
    }

    /**
     * 绘制纯色背景
     *
     * @param resource $image  图片资源
     * @param int      $width  宽度
     * @param int      $height 高度
     */
    private function draw_solid_background( $image, $width, $height ) {
        // 使用浅蓝色背景
        $bg_color = imagecolorallocate( $image, 135, 206, 250 );
        imagefilledrectangle( $image, 0, 0, $width, $height, $bg_color );
    }

    /**
     * 绘制简约背景
     *
     * @param resource $image  图片资源
     * @param int      $width  宽度
     * @param int      $height 高度
     */
    private function draw_minimal_background( $image, $width, $height ) {
        // 使用白色背景
        $bg_color = imagecolorallocate( $image, 255, 255, 255 );
        imagefilledrectangle( $image, 0, 0, $width, $height, $bg_color );
    }

    /**
     * 绘制文字
     *
     * @param resource $image   图片资源
     * @param string   $text    文本
     * @param int      $width   图片宽度
     * @param int      $height  图片高度
     * @param array    $options 选项
     */
    private function draw_text( $image, $text, $width, $height, $options ) {
        // 文字颜色
        $text_color = imagecolorallocate( $image, 255, 255, 255 );

        // 字体大小
        $font_size = 36;

        // 使用内置字体(GD库的简单方式)
        // 对于更好的中文支持,建议使用imagettftext和TrueType字体

        // 将文本分行
        $max_width = $width - 100; // 左右各留50px边距
        $lines = $this->word_wrap_text( $text, $max_width, $font_size );

        // 计算总高度
        $line_height = $font_size + 20;
        $total_height = count( $lines ) * $line_height;

        // 垂直居中
        $y = ( $height - $total_height ) / 2;

        // 绘制每一行
        foreach ( $lines as $line ) {
            // 计算文字宽度以居中
            $text_width = strlen( $line ) * ( $font_size * 0.6 ); // 粗略估算
            $x = ( $width - $text_width ) / 2;

            // 使用内置字体
            imagestring( $image, 5, $x, $y, $line, $text_color );

            $y += $line_height;
        }
    }

    /**
     * 文字换行
     *
     * @param string $text      文本
     * @param int    $max_width 最大宽度
     * @param int    $font_size 字体大小
     * @return array 分行后的文本
     */
    private function word_wrap_text( $text, $max_width, $font_size ) {
        $lines = array();
        $paragraphs = explode( "\n", $text );

        foreach ( $paragraphs as $paragraph ) {
            if ( empty( trim( $paragraph ) ) ) {
                continue;
            }

            // 简单的字符数换行(对于中文,每个字符约等于font_size宽度)
            $chars_per_line = floor( $max_width / $font_size );
            $current_line = '';
            $chars = preg_split( '//u', $paragraph, -1, PREG_SPLIT_NO_EMPTY );

            foreach ( $chars as $char ) {
                if ( mb_strlen( $current_line ) >= $chars_per_line ) {
                    $lines[] = $current_line;
                    $current_line = $char;
                } else {
                    $current_line .= $char;
                }
            }

            if ( ! empty( $current_line ) ) {
                $lines[] = $current_line;
            }
        }

        // 限制最多10行
        return array_slice( $lines, 0, 10 );
    }

    /**
     * 添加水印
     *
     * @param resource $image           图片资源
     * @param string   $watermark_path  水印图片路径
     * @param int      $width           图片宽度
     * @param int      $height          图片高度
     */
    private function add_watermark( $image, $watermark_path, $width, $height ) {
        // 检查水印文件是否存在
        if ( ! file_exists( $watermark_path ) ) {
            return;
        }

        // 获取水印图片信息
        $watermark_info = getimagesize( $watermark_path );
        if ( ! $watermark_info ) {
            return;
        }

        // 根据文件类型创建水印图片资源
        switch ( $watermark_info[2] ) {
            case IMAGETYPE_PNG:
                $watermark = imagecreatefrompng( $watermark_path );
                break;
            case IMAGETYPE_JPEG:
                $watermark = imagecreatefromjpeg( $watermark_path );
                break;
            case IMAGETYPE_GIF:
                $watermark = imagecreatefromgif( $watermark_path );
                break;
            default:
                return;
        }

        // 获取水印尺寸
        $wm_width = imagesx( $watermark );
        $wm_height = imagesy( $watermark );

        // 缩放水印(最大宽度为图片宽度的20%)
        $max_wm_width = $width * 0.2;
        if ( $wm_width > $max_wm_width ) {
            $scale = $max_wm_width / $wm_width;
            $new_wm_width = $max_wm_width;
            $new_wm_height = $wm_height * $scale;

            $watermark_resized = imagescale( $watermark, $new_wm_width, $new_wm_height );
            imagedestroy( $watermark );
            $watermark = $watermark_resized;
            $wm_width = $new_wm_width;
            $wm_height = $new_wm_height;
        }

        // 将水印放置在右下角
        $wm_x = $width - $wm_width - 20;
        $wm_y = $height - $wm_height - 20;

        // 复制水印到主图片
        imagecopy( $image, $watermark, $wm_x, $wm_y, 0, 0, $wm_width, $wm_height );

        // 释放水印资源
        imagedestroy( $watermark );
    }

    /**
     * 将内容分割成多个部分
     *
     * @param string $content 内容
     * @param int    $count   分割数量
     * @return array 分割后的内容数组
     */
    private function split_content( $content, $count ) {
        if ( $count <= 1 ) {
            return array( $content );
        }

        // 按段落分割
        $paragraphs = array_filter( explode( "\n", $content ) );

        if ( count( $paragraphs ) <= $count ) {
            return $paragraphs;
        }

        // 将段落平均分配到各个部分
        $parts = array();
        $paragraphs_per_part = ceil( count( $paragraphs ) / $count );

        for ( $i = 0; $i < $count; $i++ ) {
            $start = $i * $paragraphs_per_part;
            $part_paragraphs = array_slice( $paragraphs, $start, $paragraphs_per_part );
            $parts[] = implode( "\n", $part_paragraphs );
        }

        return $parts;
    }

    /**
     * 绘制现代风格背景
     *
     * @param resource $image  图片资源
     * @param int      $width  宽度
     * @param int      $height 高度
     */
    private function draw_modern_background( $image, $width, $height ) {
        // 双色渐变(青色到蓝色)
        for ( $i = 0; $i < $height; $i++ ) {
            $r = 64 + ( $i / $height ) * ( 25 - 64 );
            $g = 224 + ( $i / $height ) * ( 118 - 224 );
            $b = 208 + ( $i / $height ) * ( 210 - 208 );

            $color = imagecolorallocate( $image, $r, $g, $b );
            imagefilledrectangle( $image, 0, $i, $width, $i + 1, $color );
        }

        // 添加几何图形装饰
        $circle_color = imagecolorallocatealpha( $image, 255, 255, 255, 100 );
        imagefilledellipse( $image, $width * 0.8, $height * 0.2, 200, 200, $circle_color );
        imagefilledellipse( $image, $width * 0.2, $height * 0.8, 150, 150, $circle_color );
    }

    /**
     * 绘制深色背景
     *
     * @param resource $image  图片资源
     * @param int      $width  宽度
     * @param int      $height 高度
     */
    private function draw_dark_background( $image, $width, $height ) {
        // 深色渐变(深灰到黑)
        for ( $i = 0; $i < $height; $i++ ) {
            $value = 50 - ( $i / $height ) * 30;
            $color = imagecolorallocate( $image, $value, $value, $value );
            imagefilledrectangle( $image, 0, $i, $width, $i + 1, $color );
        }

        // 添加光效
        $glow_color = imagecolorallocatealpha( $image, 100, 100, 255, 90 );
        imagefilledellipse( $image, $width / 2, $height / 2, $width * 0.6, $height * 0.6, $glow_color );
    }

    /**
     * 绘制彩色背景
     *
     * @param resource $image  图片资源
     * @param int      $width  宽度
     * @param int      $height 高度
     */
    private function draw_colorful_background( $image, $width, $height ) {
        // 多彩渐变(橙色到粉色到紫色)
        for ( $i = 0; $i < $height; $i++ ) {
            $progress = $i / $height;

            if ( $progress < 0.5 ) {
                // 橙色到粉色
                $t = $progress * 2;
                $r = 255;
                $g = 127 + $t * ( 192 - 127 );
                $b = 80 + $t * ( 203 - 80 );
            } else {
                // 粉色到紫色
                $t = ( $progress - 0.5 ) * 2;
                $r = 255 - $t * ( 255 - 147 );
                $g = 192 - $t * ( 192 - 112 );
                $b = 203 - $t * ( 203 - 219 );
            }

            $color = imagecolorallocate( $image, $r, $g, $b );
            imagefilledrectangle( $image, 0, $i, $width, $i + 1, $color );
        }
    }

    /**
     * 绘制优雅背景
     *
     * @param resource $image  图片资源
     * @param int      $width  宽度
     * @param int      $height 高度
     */
    private function draw_elegant_background( $image, $width, $height ) {
        // 米白色背景
        $bg_color = imagecolorallocate( $image, 250, 248, 245 );
        imagefilledrectangle( $image, 0, 0, $width, $height, $bg_color );

        // 添加边框
        $border_color = imagecolorallocate( $image, 212, 175, 55 );
        $border_width = 20;
        imagefilledrectangle( $image, 0, 0, $width, $border_width, $border_color );
        imagefilledrectangle( $image, 0, $height - $border_width, $width, $height, $border_color );
        imagefilledrectangle( $image, 0, 0, $border_width, $height, $border_color );
        imagefilledrectangle( $image, $width - $border_width, 0, $width, $height, $border_color );
    }

    /**
     * 获取所有可用的模板
     *
     * @return array 模板列表
     */
    public static function get_available_templates() {
        return array(
            'gradient' => __( 'Gradient (Purple to Pink)', 'ai-social-content-generator' ),
            'solid' => __( 'Solid Color (Light Blue)', 'ai-social-content-generator' ),
            'minimal' => __( 'Minimal (White)', 'ai-social-content-generator' ),
            'modern' => __( 'Modern (Cyan to Blue)', 'ai-social-content-generator' ),
            'dark' => __( 'Dark (Deep Gray)', 'ai-social-content-generator' ),
            'colorful' => __( 'Colorful (Orange to Purple)', 'ai-social-content-generator' ),
            'elegant' => __( 'Elegant (Cream with Gold Border)', 'ai-social-content-generator' ),
        );
    }

    /**
     * 删除内容的所有图片
     *
     * @param int $post_id 内容ID
     * @return bool
     */
    public function delete_post_images( $post_id ) {
        // 获取所有图片
        $images = $this->db->get_images_by_post_id( $post_id );

        // 删除文件
        foreach ( $images as $image ) {
            if ( file_exists( $image->image_path ) ) {
                unlink( $image->image_path );
            }
        }

        // 从数据库删除
        return $this->db->delete_images_by_post_id( $post_id );
    }
}
