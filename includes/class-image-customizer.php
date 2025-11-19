<?php
/**
 * Image Customizer Class
 *
 * 提供高级图片自定义和美化功能
 *
 * @package AI_Social_Content_Generator
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AISCG_Image_Customizer Class
 */
class AISCG_Image_Customizer {

    /**
     * 日志对象
     *
     * @var AISCG_Logger
     */
    private $logger;

    /**
     * 默认配置
     *
     * @var array
     */
    private $defaults;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->logger = new AISCG_Logger();

        $this->defaults = array(
            // 尺寸
            'width' => 1080,
            'height' => 1080,

            // 颜色方案
            'color_scheme' => 'custom',
            'background_color' => '#ffffff',
            'primary_color' => '#333333',
            'secondary_color' => '#666666',
            'accent_color' => '#ff6b6b',

            // 渐变
            'use_gradient' => true,
            'gradient_type' => 'linear', // linear, radial
            'gradient_direction' => 'vertical', // horizontal, vertical, diagonal
            'gradient_colors' => array( '#667eea', '#764ba2' ),

            // 文字
            'title_size' => 40,
            'content_size' => 24,
            'title_color' => '#333333',
            'content_color' => '#666666',
            'text_align' => 'center', // left, center, right
            'line_height' => 1.5,

            // 布局
            'padding' => 60,
            'title_margin_bottom' => 40,
            'content_spacing' => 20,

            // 装饰元素
            'show_border' => false,
            'border_width' => 5,
            'border_color' => '#333333',
            'border_radius' => 20,

            // 阴影
            'show_shadow' => false,
            'shadow_color' => 'rgba(0,0,0,0.2)',
            'shadow_blur' => 10,
            'shadow_offset_x' => 5,
            'shadow_offset_y' => 5,

            // 图案/纹理
            'use_pattern' => false,
            'pattern_type' => 'dots', // dots, lines, grid, circles

            // 水印
            'watermark_enabled' => false,
            'watermark_text' => '',
            'watermark_position' => 'bottom-right', // top-left, top-right, bottom-left, bottom-right, center
            'watermark_opacity' => 30,
            'watermark_size' => 14,

            // 滤镜效果
            'filter' => 'none', // none, grayscale, sepia, blur, brightness, contrast
            'filter_strength' => 50,

            // 背景图片
            'background_image' => '',
            'background_opacity' => 100,
            'background_blur' => 0,
        );
    }

    /**
     * 创建自定义图片
     *
     * @param string $title 标题
     * @param string $content 内容文本
     * @param array $options 自定义选项
     * @return array|false 图片信息或false
     */
    public function create_custom_image( $title, $content, $options = array() ) {
        $options = wp_parse_args( $options, $this->defaults );

        $width = intval( $options['width'] );
        $height = intval( $options['height'] );

        // 创建画布
        $image = imagecreatetruecolor( $width, $height );

        if ( ! $image ) {
            $this->logger->error( '创建图片失败：无法创建画布' );
            return false;
        }

        // 启用抗锯齿
        imageantialias( $image, true );

        try {
            // 绘制背景
            $this->draw_background( $image, $width, $height, $options );

            // 绘制图案
            if ( $options['use_pattern'] ) {
                $this->draw_pattern( $image, $width, $height, $options );
            }

            // 绘制边框
            if ( $options['show_border'] ) {
                $this->draw_border( $image, $width, $height, $options );
            }

            // 绘制文字
            $this->draw_text( $image, $title, $content, $width, $height, $options );

            // 绘制水印
            if ( $options['watermark_enabled'] && ! empty( $options['watermark_text'] ) ) {
                $this->draw_watermark( $image, $width, $height, $options );
            }

            // 应用滤镜
            if ( $options['filter'] !== 'none' ) {
                $this->apply_filter( $image, $options );
            }

            // 保存图片
            $result = $this->save_image( $image, $options );

            imagedestroy( $image );

            if ( $result ) {
                $this->logger->info( '创建自定义图片成功', array(
                    'width' => $width,
                    'height' => $height,
                    'options' => $options,
                ) );
            }

            return $result;

        } catch ( Exception $e ) {
            $this->logger->error( '创建自定义图片失败', array(
                'error' => $e->getMessage(),
            ) );

            imagedestroy( $image );
            return false;
        }
    }

    /**
     * 绘制背景
     *
     * @param resource $image 图片资源
     * @param int $width 宽度
     * @param int $height 高度
     * @param array $options 选项
     */
    private function draw_background( $image, $width, $height, $options ) {
        if ( $options['use_gradient'] ) {
            $this->draw_gradient( $image, $width, $height, $options );
        } else {
            // 纯色背景
            $bg_color = $this->hex_to_rgb( $options['background_color'] );
            $color = imagecolorallocate( $image, $bg_color[0], $bg_color[1], $bg_color[2] );
            imagefilledrectangle( $image, 0, 0, $width, $height, $color );
        }

        // 如果有背景图片
        if ( ! empty( $options['background_image'] ) && file_exists( $options['background_image'] ) ) {
            $this->draw_background_image( $image, $width, $height, $options );
        }
    }

    /**
     * 绘制渐变背景
     *
     * @param resource $image 图片资源
     * @param int $width 宽度
     * @param int $height 高度
     * @param array $options 选项
     */
    private function draw_gradient( $image, $width, $height, $options ) {
        $colors = $options['gradient_colors'];
        if ( count( $colors ) < 2 ) {
            $colors = array( '#667eea', '#764ba2' );
        }

        $color1 = $this->hex_to_rgb( $colors[0] );
        $color2 = $this->hex_to_rgb( $colors[1] );

        if ( $options['gradient_type'] === 'linear' ) {
            if ( $options['gradient_direction'] === 'horizontal' ) {
                // 水平渐变
                for ( $i = 0; $i < $width; $i++ ) {
                    $r = $color1[0] + ( $color2[0] - $color1[0] ) * $i / $width;
                    $g = $color1[1] + ( $color2[1] - $color1[1] ) * $i / $width;
                    $b = $color1[2] + ( $color2[2] - $color1[2] ) * $i / $width;
                    $color = imagecolorallocate( $image, $r, $g, $b );
                    imagefilledrectangle( $image, $i, 0, $i + 1, $height, $color );
                }
            } elseif ( $options['gradient_direction'] === 'diagonal' ) {
                // 对角渐变
                for ( $i = 0; $i < $width + $height; $i++ ) {
                    $progress = $i / ( $width + $height );
                    $r = $color1[0] + ( $color2[0] - $color1[0] ) * $progress;
                    $g = $color1[1] + ( $color2[1] - $color1[1] ) * $progress;
                    $b = $color1[2] + ( $color2[2] - $color1[2] ) * $progress;
                    $color = imagecolorallocate( $image, $r, $g, $b );

                    // 绘制斜线
                    for ( $x = 0; $x < $width; $x++ ) {
                        $y = $i - $x;
                        if ( $y >= 0 && $y < $height ) {
                            imagesetpixel( $image, $x, $y, $color );
                        }
                    }
                }
            } else {
                // 垂直渐变（默认）
                for ( $i = 0; $i < $height; $i++ ) {
                    $r = $color1[0] + ( $color2[0] - $color1[0] ) * $i / $height;
                    $g = $color1[1] + ( $color2[1] - $color1[1] ) * $i / $height;
                    $b = $color1[2] + ( $color2[2] - $color1[2] ) * $i / $height;
                    $color = imagecolorallocate( $image, $r, $g, $b );
                    imagefilledrectangle( $image, 0, $i, $width, $i + 1, $color );
                }
            }
        } elseif ( $options['gradient_type'] === 'radial' ) {
            // 径向渐变
            $center_x = $width / 2;
            $center_y = $height / 2;
            $max_distance = sqrt( $center_x * $center_x + $center_y * $center_y );

            for ( $y = 0; $y < $height; $y++ ) {
                for ( $x = 0; $x < $width; $x++ ) {
                    $distance = sqrt( pow( $x - $center_x, 2 ) + pow( $y - $center_y, 2 ) );
                    $progress = min( $distance / $max_distance, 1 );

                    $r = $color1[0] + ( $color2[0] - $color1[0] ) * $progress;
                    $g = $color1[1] + ( $color2[1] - $color1[1] ) * $progress;
                    $b = $color1[2] + ( $color2[2] - $color1[2] ) * $progress;
                    $color = imagecolorallocate( $image, $r, $g, $b );

                    imagesetpixel( $image, $x, $y, $color );
                }
            }
        }
    }

    /**
     * 绘制图案
     *
     * @param resource $image 图片资源
     * @param int $width 宽度
     * @param int $height 高度
     * @param array $options 选项
     */
    private function draw_pattern( $image, $width, $height, $options ) {
        $pattern_color = imagecolorallocatealpha( $image, 255, 255, 255, 100 );

        switch ( $options['pattern_type'] ) {
            case 'dots':
                // 圆点图案
                $spacing = 40;
                for ( $y = 0; $y < $height; $y += $spacing ) {
                    for ( $x = 0; $x < $width; $x += $spacing ) {
                        imagefilledellipse( $image, $x, $y, 5, 5, $pattern_color );
                    }
                }
                break;

            case 'lines':
                // 斜线图案
                $spacing = 30;
                for ( $i = -$height; $i < $width; $i += $spacing ) {
                    imageline( $image, $i, 0, $i + $height, $height, $pattern_color );
                }
                break;

            case 'grid':
                // 网格图案
                $spacing = 50;
                for ( $x = 0; $x < $width; $x += $spacing ) {
                    imageline( $image, $x, 0, $x, $height, $pattern_color );
                }
                for ( $y = 0; $y < $height; $y += $spacing ) {
                    imageline( $image, 0, $y, $width, $y, $pattern_color );
                }
                break;

            case 'circles':
                // 圆圈图案
                $spacing = 80;
                for ( $y = 0; $y < $height; $y += $spacing ) {
                    for ( $x = 0; $x < $width; $x += $spacing ) {
                        imageellipse( $image, $x, $y, 40, 40, $pattern_color );
                    }
                }
                break;
        }
    }

    /**
     * 绘制边框
     *
     * @param resource $image 图片资源
     * @param int $width 宽度
     * @param int $height 高度
     * @param array $options 选项
     */
    private function draw_border( $image, $width, $height, $options ) {
        $border_rgb = $this->hex_to_rgb( $options['border_color'] );
        $border_color = imagecolorallocate( $image, $border_rgb[0], $border_rgb[1], $border_rgb[2] );
        $border_width = intval( $options['border_width'] );

        for ( $i = 0; $i < $border_width; $i++ ) {
            imagerectangle( $image, $i, $i, $width - $i - 1, $height - $i - 1, $border_color );
        }
    }

    /**
     * 绘制文字
     *
     * @param resource $image 图片资源
     * @param string $title 标题
     * @param string $content 内容
     * @param int $width 宽度
     * @param int $height 高度
     * @param array $options 选项
     */
    private function draw_text( $image, $title, $content, $width, $height, $options ) {
        $title_rgb = $this->hex_to_rgb( $options['title_color'] );
        $content_rgb = $this->hex_to_rgb( $options['content_color'] );

        $title_color = imagecolorallocate( $image, $title_rgb[0], $title_rgb[1], $title_rgb[2] );
        $content_color = imagecolorallocate( $image, $content_rgb[0], $content_rgb[1], $content_rgb[2] );

        $padding = intval( $options['padding'] );
        $available_width = $width - ( $padding * 2 );

        $y_offset = $padding + 60;

        // 绘制标题
        if ( ! empty( $title ) ) {
            $title_size = intval( $options['title_size'] );
            $wrapped_title = $this->wrap_text( $title, $title_size, $available_width );

            foreach ( $wrapped_title as $line ) {
                $bbox = imagettfbbox( $title_size, 0, $this->get_font_path(), $line );
                $text_width = $bbox[2] - $bbox[0];

                $x = $this->get_text_x_position( $text_width, $width, $padding, $options['text_align'] );

                imagettftext( $image, $title_size, 0, $x, $y_offset, $title_color, $this->get_font_path(), $line );
                $y_offset += $title_size * $options['line_height'];
            }

            $y_offset += intval( $options['title_margin_bottom'] );
        }

        // 绘制内容
        if ( ! empty( $content ) ) {
            $content_size = intval( $options['content_size'] );
            $wrapped_content = $this->wrap_text( $content, $content_size, $available_width );

            foreach ( $wrapped_content as $line ) {
                if ( $y_offset > $height - $padding ) {
                    break; // 超出画布范围
                }

                $bbox = imagettfbbox( $content_size, 0, $this->get_font_path(), $line );
                $text_width = $bbox[2] - $bbox[0];

                $x = $this->get_text_x_position( $text_width, $width, $padding, $options['text_align'] );

                imagettftext( $image, $content_size, 0, $x, $y_offset, $content_color, $this->get_font_path(), $line );
                $y_offset += $content_size * $options['line_height'];
            }
        }
    }

    /**
     * 绘制水印
     *
     * @param resource $image 图片资源
     * @param int $width 宽度
     * @param int $height 高度
     * @param array $options 选项
     */
    private function draw_watermark( $image, $width, $height, $options ) {
        $watermark_text = $options['watermark_text'];
        $watermark_size = intval( $options['watermark_size'] );
        $opacity = intval( $options['watermark_opacity'] );

        $alpha = floor( 127 - ( $opacity / 100 * 127 ) );
        $watermark_color = imagecolorallocatealpha( $image, 255, 255, 255, $alpha );

        $bbox = imagettfbbox( $watermark_size, 0, $this->get_font_path(), $watermark_text );
        $text_width = $bbox[2] - $bbox[0];
        $text_height = $bbox[1] - $bbox[7];

        list( $x, $y ) = $this->get_watermark_position(
            $width,
            $height,
            $text_width,
            $text_height,
            $options['watermark_position']
        );

        imagettftext( $image, $watermark_size, 0, $x, $y, $watermark_color, $this->get_font_path(), $watermark_text );
    }

    /**
     * 应用滤镜
     *
     * @param resource $image 图片资源
     * @param array $options 选项
     */
    private function apply_filter( $image, $options ) {
        $strength = intval( $options['filter_strength'] );

        switch ( $options['filter'] ) {
            case 'grayscale':
                imagefilter( $image, IMG_FILTER_GRAYSCALE );
                break;

            case 'sepia':
                imagefilter( $image, IMG_FILTER_GRAYSCALE );
                imagefilter( $image, IMG_FILTER_COLORIZE, 100, 50, 0 );
                break;

            case 'blur':
                for ( $i = 0; $i < $strength / 10; $i++ ) {
                    imagefilter( $image, IMG_FILTER_GAUSSIAN_BLUR );
                }
                break;

            case 'brightness':
                imagefilter( $image, IMG_FILTER_BRIGHTNESS, $strength - 50 );
                break;

            case 'contrast':
                imagefilter( $image, IMG_FILTER_CONTRAST, $strength - 50 );
                break;
        }
    }

    /**
     * 保存图片
     *
     * @param resource $image 图片资源
     * @param array $options 选项
     * @return array|false 图片信息或false
     */
    private function save_image( $image, $options ) {
        $upload_dir = wp_upload_dir();
        $image_dir = $upload_dir['basedir'] . '/aiscg-images/custom/';

        if ( ! file_exists( $image_dir ) ) {
            wp_mkdir_p( $image_dir );
        }

        $filename = 'custom_' . time() . '_' . wp_rand( 1000, 9999 ) . '.png';
        $filepath = $image_dir . $filename;
        $file_url = $upload_dir['baseurl'] . '/aiscg-images/custom/' . $filename;

        if ( imagepng( $image, $filepath, 9 ) ) {
            return array(
                'image_url' => $file_url,
                'image_path' => $filepath,
                'width' => $options['width'],
                'height' => $options['height'],
            );
        }

        return false;
    }

    /**
     * 文字换行
     *
     * @param string $text 文字
     * @param int $size 字号
     * @param int $max_width 最大宽度
     * @return array 换行后的文字数组
     */
    private function wrap_text( $text, $size, $max_width ) {
        $words = mb_str_split( $text );
        $lines = array();
        $current_line = '';

        foreach ( $words as $word ) {
            $test_line = $current_line . $word;
            $bbox = imagettfbbox( $size, 0, $this->get_font_path(), $test_line );
            $test_width = $bbox[2] - $bbox[0];

            if ( $test_width > $max_width && ! empty( $current_line ) ) {
                $lines[] = $current_line;
                $current_line = $word;
            } else {
                $current_line = $test_line;
            }
        }

        if ( ! empty( $current_line ) ) {
            $lines[] = $current_line;
        }

        return $lines;
    }

    /**
     * 获取文字X坐标
     *
     * @param int $text_width 文字宽度
     * @param int $canvas_width 画布宽度
     * @param int $padding 内边距
     * @param string $align 对齐方式
     * @return int X坐标
     */
    private function get_text_x_position( $text_width, $canvas_width, $padding, $align ) {
        switch ( $align ) {
            case 'left':
                return $padding;
            case 'right':
                return $canvas_width - $text_width - $padding;
            case 'center':
            default:
                return ( $canvas_width - $text_width ) / 2;
        }
    }

    /**
     * 获取水印位置
     *
     * @param int $width 画布宽度
     * @param int $height 画布高度
     * @param int $text_width 文字宽度
     * @param int $text_height 文字高度
     * @param string $position 位置
     * @return array [x, y]
     */
    private function get_watermark_position( $width, $height, $text_width, $text_height, $position ) {
        $margin = 20;

        switch ( $position ) {
            case 'top-left':
                return array( $margin, $margin + $text_height );
            case 'top-right':
                return array( $width - $text_width - $margin, $margin + $text_height );
            case 'bottom-left':
                return array( $margin, $height - $margin );
            case 'bottom-right':
                return array( $width - $text_width - $margin, $height - $margin );
            case 'center':
                return array( ( $width - $text_width ) / 2, ( $height + $text_height ) / 2 );
            default:
                return array( $width - $text_width - $margin, $height - $margin );
        }
    }

    /**
     * 获取字体路径
     *
     * @return string 字体文件路径
     */
    private function get_font_path() {
        // 优先使用自定义字体
        $custom_font = AISCG_PLUGIN_DIR . 'assets/fonts/NotoSansSC-Regular.ttf';
        if ( file_exists( $custom_font ) ) {
            return $custom_font;
        }

        // 使用系统字体
        $system_fonts = array(
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            'C:/Windows/Fonts/arial.ttf',
        );

        foreach ( $system_fonts as $font ) {
            if ( file_exists( $font ) ) {
                return $font;
            }
        }

        return ''; // 如果都不存在，GD会使用内置字体
    }

    /**
     * 十六进制颜色转RGB
     *
     * @param string $hex 十六进制颜色
     * @return array RGB数组
     */
    private function hex_to_rgb( $hex ) {
        $hex = str_replace( '#', '', $hex );

        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return array(
            hexdec( substr( $hex, 0, 2 ) ),
            hexdec( substr( $hex, 2, 2 ) ),
            hexdec( substr( $hex, 4, 2 ) ),
        );
    }

    /**
     * 获取预设颜色方案
     *
     * @return array 颜色方案列表
     */
    public function get_color_schemes() {
        return array(
            'ocean' => array(
                'name' => '海洋',
                'gradient_colors' => array( '#667eea', '#764ba2' ),
                'title_color' => '#ffffff',
                'content_color' => '#f0f0f0',
            ),
            'sunset' => array(
                'name' => '日落',
                'gradient_colors' => array( '#ff6b6b', '#feca57' ),
                'title_color' => '#ffffff',
                'content_color' => '#ffffff',
            ),
            'forest' => array(
                'name' => '森林',
                'gradient_colors' => array( '#11998e', '#38ef7d' ),
                'title_color' => '#ffffff',
                'content_color' => '#f0f0f0',
            ),
            'night' => array(
                'name' => '夜空',
                'gradient_colors' => array( '#2c3e50', '#3498db' ),
                'title_color' => '#ffffff',
                'content_color' => '#ecf0f1',
            ),
            'pink' => array(
                'name' => '粉色梦幻',
                'gradient_colors' => array( '#ee9ca7', '#ffdde1' ),
                'title_color' => '#333333',
                'content_color' => '#666666',
            ),
            'minimal' => array(
                'name' => '简约',
                'background_color' => '#ffffff',
                'title_color' => '#333333',
                'content_color' => '#666666',
                'use_gradient' => false,
            ),
        );
    }
}
