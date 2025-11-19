<?php
/**
 * 社交媒体分发系统
 *
 * 自动发布内容到多个社交媒体平台
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 社交媒体分发器类
 */
class AISCG_Social_Distributor {

    /**
     * 数据库操作对象
     *
     * @var AISCG_Database
     */
    private $database;

    /**
     * 支持的平台
     *
     * @var array
     */
    private $platforms = array(
        'xiaohongshu' => '小红书',
        'instagram' => 'Instagram',
        'weibo' => '微博',
        'douyin' => '抖音',
        'twitter' => 'Twitter/X',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
    );

    /**
     * 发布状态
     *
     * @var array
     */
    private $publish_statuses = array(
        'pending' => '待发布',
        'scheduled' => '已计划',
        'publishing' => '发布中',
        'published' => '已发布',
        'failed' => '失败',
        'deleted' => '已删除',
    );

    /**
     * 构造函数
     */
    public function __construct() {
        $this->database = new AISCG_Database();
        $this->create_distributions_table();
    }

    /**
     * 发布内容到平台
     *
     * @param int $content_id 内容ID
     * @param array $platforms 目标平台列表
     * @param array $options 发布选项
     *   - schedule_time: 计划发布时间
     *   - auto_publish: 是否自动发布
     *   - custom_settings: 各平台自定义设置
     *
     * @return array|WP_Error 发布结果或错误
     */
    public function distribute( $content_id, $platforms, $options = array() ) {
        global $wpdb;
        $table_posts = $wpdb->prefix . 'aiscg_posts';

        // 获取内容
        $content = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_posts WHERE id = %d", $content_id ),
            ARRAY_A
        );

        if ( ! $content ) {
            return new WP_Error( 'content_not_found', '内容不存在' );
        }

        $defaults = array(
            'schedule_time' => null,
            'auto_publish' => false,
            'custom_settings' => array(),
        );
        $options = wp_parse_args( $options, $defaults );

        $results = array();

        foreach ( $platforms as $platform ) {
            if ( ! isset( $this->platforms[ $platform ] ) ) {
                $results[ $platform ] = new WP_Error( 'invalid_platform', '无效的平台: ' . $platform );
                continue;
            }

            // 创建分发记录
            $distribution_id = $this->create_distribution( array(
                'content_id' => $content_id,
                'platform' => $platform,
                'status' => $options['schedule_time'] ? 'scheduled' : 'pending',
                'schedule_time' => $options['schedule_time'],
                'settings' => wp_json_encode( $options['custom_settings'][ $platform ] ?? array() ),
            ) );

            if ( $options['auto_publish'] && ! $options['schedule_time'] ) {
                // 立即发布
                $publish_result = $this->publish_to_platform( $distribution_id, $content, $platform, $options['custom_settings'][ $platform ] ?? array() );
                $results[ $platform ] = $publish_result;
            } else {
                $results[ $platform ] = array(
                    'distribution_id' => $distribution_id,
                    'status' => $options['schedule_time'] ? 'scheduled' : 'pending',
                );
            }
        }

        return $results;
    }

    /**
     * 发布到指定平台
     *
     * @param int $distribution_id 分发记录ID
     * @param array $content 内容数据
     * @param string $platform 平台
     * @param array $settings 平台设置
     *
     * @return array|WP_Error 发布结果或错误
     */
    private function publish_to_platform( $distribution_id, $content, $platform, $settings = array() ) {
        // 更新状态为发布中
        $this->update_distribution( $distribution_id, array( 'status' => 'publishing' ) );

        try {
            $result = null;

            switch ( $platform ) {
                case 'xiaohongshu':
                    $result = $this->publish_to_xiaohongshu( $content, $settings );
                    break;

                case 'instagram':
                    $result = $this->publish_to_instagram( $content, $settings );
                    break;

                case 'weibo':
                    $result = $this->publish_to_weibo( $content, $settings );
                    break;

                case 'douyin':
                    $result = $this->publish_to_douyin( $content, $settings );
                    break;

                case 'twitter':
                    $result = $this->publish_to_twitter( $content, $settings );
                    break;

                case 'facebook':
                    $result = $this->publish_to_facebook( $content, $settings );
                    break;

                case 'linkedin':
                    $result = $this->publish_to_linkedin( $content, $settings );
                    break;

                default:
                    throw new Exception( '不支持的平台: ' . $platform );
            }

            if ( is_wp_error( $result ) ) {
                throw new Exception( $result->get_error_message() );
            }

            // 更新为已发布
            $this->update_distribution( $distribution_id, array(
                'status' => 'published',
                'published_at' => current_time( 'mysql' ),
                'platform_post_id' => $result['post_id'] ?? '',
                'platform_url' => $result['url'] ?? '',
                'response_data' => wp_json_encode( $result ),
            ) );

            do_action( 'aiscg_content_published_to_platform', $content['id'], $platform, $result );

            return array(
                'success' => true,
                'distribution_id' => $distribution_id,
                'platform_post_id' => $result['post_id'] ?? '',
                'url' => $result['url'] ?? '',
            );

        } catch ( Exception $e ) {
            // 更新为失败
            $this->update_distribution( $distribution_id, array(
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ) );

            return new WP_Error( 'publish_failed', $e->getMessage() );
        }
    }

    /**
     * 发布到小红书
     *
     * @param array $content 内容数据
     * @param array $settings 设置
     *
     * @return array|WP_Error 发布结果
     */
    private function publish_to_xiaohongshu( $content, $settings ) {
        // 注意: 小红书没有官方API,这里提供模拟逻辑
        // 实际使用需要通过第三方服务或人工发布

        $api_key = $settings['api_key'] ?? get_option( 'aiscg_xiaohongshu_api_key' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', '未配置小红书API密钥' );
        }

        // 准备发布数据
        $post_data = array(
            'title' => $content['title'],
            'content' => $content['content'],
            'images' => $this->get_content_images( $content['id'] ),
            'tags' => json_decode( $content['hashtags'], true ),
        );

        // 这里应该调用实际的API
        // 由于小红书没有公开API,返回模拟结果
        $mock_result = array(
            'post_id' => 'xhs_' . time() . rand( 1000, 9999 ),
            'url' => 'https://www.xiaohongshu.com/explore/' . uniqid(),
            'status' => 'published',
        );

        return $mock_result;
    }

    /**
     * 发布到Instagram
     *
     * @param array $content 内容数据
     * @param array $settings 设置
     *
     * @return array|WP_Error 发布结果
     */
    private function publish_to_instagram( $content, $settings ) {
        $access_token = $settings['access_token'] ?? get_option( 'aiscg_instagram_access_token' );
        $account_id = $settings['account_id'] ?? get_option( 'aiscg_instagram_account_id' );

        if ( empty( $access_token ) || empty( $account_id ) ) {
            return new WP_Error( 'no_credentials', '未配置Instagram凭据' );
        }

        $images = $this->get_content_images( $content['id'] );
        $caption = $content['content'];
        $hashtags = json_decode( $content['hashtags'], true );

        if ( is_array( $hashtags ) ) {
            $caption .= "\n\n" . implode( ' ', $hashtags );
        }

        try {
            // 使用Instagram Graph API
            if ( count( $images ) == 1 ) {
                // 单图发布
                $result = $this->instagram_publish_single( $account_id, $access_token, $images[0], $caption );
            } elseif ( count( $images ) > 1 ) {
                // 多图发布(轮播)
                $result = $this->instagram_publish_carousel( $account_id, $access_token, $images, $caption );
            } else {
                return new WP_Error( 'no_images', '没有图片' );
            }

            return array(
                'post_id' => $result['id'],
                'url' => 'https://www.instagram.com/p/' . $result['shortcode'],
                'status' => 'published',
            );

        } catch ( Exception $e ) {
            return new WP_Error( 'api_error', $e->getMessage() );
        }
    }

    /**
     * Instagram单图发布
     *
     * @param string $account_id 账号ID
     * @param string $access_token 访问令牌
     * @param string $image_url 图片URL
     * @param string $caption 标题
     *
     * @return array API响应
     */
    private function instagram_publish_single( $account_id, $access_token, $image_url, $caption ) {
        // 创建媒体容器
        $create_url = "https://graph.facebook.com/v18.0/{$account_id}/media";
        $create_params = array(
            'image_url' => $image_url,
            'caption' => $caption,
            'access_token' => $access_token,
        );

        $creation_response = wp_remote_post( $create_url, array(
            'body' => $create_params,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $creation_response ) ) {
            throw new Exception( $creation_response->get_error_message() );
        }

        $creation_body = json_decode( wp_remote_retrieve_body( $creation_response ), true );

        if ( isset( $creation_body['error'] ) ) {
            throw new Exception( $creation_body['error']['message'] );
        }

        $container_id = $creation_body['id'];

        // 发布媒体容器
        $publish_url = "https://graph.facebook.com/v18.0/{$account_id}/media_publish";
        $publish_params = array(
            'creation_id' => $container_id,
            'access_token' => $access_token,
        );

        $publish_response = wp_remote_post( $publish_url, array(
            'body' => $publish_params,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $publish_response ) ) {
            throw new Exception( $publish_response->get_error_message() );
        }

        $publish_body = json_decode( wp_remote_retrieve_body( $publish_response ), true );

        if ( isset( $publish_body['error'] ) ) {
            throw new Exception( $publish_body['error']['message'] );
        }

        return array(
            'id' => $publish_body['id'],
            'shortcode' => $this->get_instagram_shortcode( $publish_body['id'], $access_token ),
        );
    }

    /**
     * Instagram轮播发布
     *
     * @param string $account_id 账号ID
     * @param string $access_token 访问令牌
     * @param array $image_urls 图片URL数组
     * @param string $caption 标题
     *
     * @return array API响应
     */
    private function instagram_publish_carousel( $account_id, $access_token, $image_urls, $caption ) {
        $children_ids = array();

        // 为每张图片创建子容器
        foreach ( $image_urls as $image_url ) {
            $create_url = "https://graph.facebook.com/v18.0/{$account_id}/media";
            $create_params = array(
                'image_url' => $image_url,
                'is_carousel_item' => true,
                'access_token' => $access_token,
            );

            $response = wp_remote_post( $create_url, array(
                'body' => $create_params,
                'timeout' => 30,
            ) );

            if ( is_wp_error( $response ) ) {
                throw new Exception( $response->get_error_message() );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset( $body['error'] ) ) {
                throw new Exception( $body['error']['message'] );
            }

            $children_ids[] = $body['id'];
        }

        // 创建轮播容器
        $carousel_url = "https://graph.facebook.com/v18.0/{$account_id}/media";
        $carousel_params = array(
            'media_type' => 'CAROUSEL',
            'children' => implode( ',', $children_ids ),
            'caption' => $caption,
            'access_token' => $access_token,
        );

        $carousel_response = wp_remote_post( $carousel_url, array(
            'body' => $carousel_params,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $carousel_response ) ) {
            throw new Exception( $carousel_response->get_error_message() );
        }

        $carousel_body = json_decode( wp_remote_retrieve_body( $carousel_response ), true );

        if ( isset( $carousel_body['error'] ) ) {
            throw new Exception( $carousel_body['error']['message() );
        }

        $container_id = $carousel_body['id'];

        // 发布轮播
        $publish_url = "https://graph.facebook.com/v18.0/{$account_id}/media_publish";
        $publish_params = array(
            'creation_id' => $container_id,
            'access_token' => $access_token,
        );

        $publish_response = wp_remote_post( $publish_url, array(
            'body' => $publish_params,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $publish_response ) ) {
            throw new Exception( $publish_response->get_error_message() );
        }

        $publish_body = json_decode( wp_remote_retrieve_body( $publish_response ), true );

        if ( isset( $publish_body['error'] ) ) {
            throw new Exception( $publish_body['error']['message'] );
        }

        return array(
            'id' => $publish_body['id'],
            'shortcode' => $this->get_instagram_shortcode( $publish_body['id'], $access_token ),
        );
    }

    /**
     * 获取Instagram帖子shortcode
     *
     * @param string $media_id 媒体ID
     * @param string $access_token 访问令牌
     *
     * @return string Shortcode
     */
    private function get_instagram_shortcode( $media_id, $access_token ) {
        $url = "https://graph.facebook.com/v18.0/{$media_id}?fields=shortcode&access_token={$access_token}";

        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body['shortcode'] ?? '';
    }

    /**
     * 发布到微博
     *
     * @param array $content 内容数据
     * @param array $settings 设置
     *
     * @return array|WP_Error 发布结果
     */
    private function publish_to_weibo( $content, $settings ) {
        $access_token = $settings['access_token'] ?? get_option( 'aiscg_weibo_access_token' );

        if ( empty( $access_token ) ) {
            return new WP_Error( 'no_access_token', '未配置微博访问令牌' );
        }

        $images = $this->get_content_images( $content['id'] );
        $text = $content['content'];

        try {
            if ( count( $images ) > 0 ) {
                // 带图微博
                $result = $this->weibo_publish_with_images( $access_token, $text, $images );
            } else {
                // 纯文字微博
                $result = $this->weibo_publish_text( $access_token, $text );
            }

            return array(
                'post_id' => $result['id'],
                'url' => 'https://weibo.com/' . $result['user']['id'] . '/' . $result['mid'],
                'status' => 'published',
            );

        } catch ( Exception $e ) {
            return new WP_Error( 'api_error', $e->getMessage() );
        }
    }

    /**
     * 微博发布文字
     *
     * @param string $access_token 访问令牌
     * @param string $text 文本
     *
     * @return array API响应
     */
    private function weibo_publish_text( $access_token, $text ) {
        $url = 'https://api.weibo.com/2/statuses/update.json';

        $response = wp_remote_post( $url, array(
            'body' => array(
                'access_token' => $access_token,
                'status' => $text,
            ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new Exception( $body['error'] );
        }

        return $body;
    }

    /**
     * 微博发布带图
     *
     * @param string $access_token 访问令牌
     * @param string $text 文本
     * @param array $image_urls 图片URL数组
     *
     * @return array API响应
     */
    private function weibo_publish_with_images( $access_token, $text, $image_urls ) {
        $url = 'https://api.weibo.com/2/statuses/upload.json';

        // 微博API需要图片文件,下载第一张图片
        $image_data = file_get_contents( $image_urls[0] );

        $boundary = wp_generate_password( 24 );
        $body = '';

        // 构建multipart/form-data
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"access_token\"\r\n\r\n";
        $body .= "{$access_token}\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"status\"\r\n\r\n";
        $body .= "{$text}\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"pic\"; filename=\"image.jpg\"\r\n";
        $body .= "Content-Type: image/jpeg\r\n\r\n";
        $body .= $image_data . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body' => $body,
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $response_body['error'] ) ) {
            throw new Exception( $response_body['error'] );
        }

        return $response_body;
    }

    /**
     * 发布到抖音
     */
    private function publish_to_douyin( $content, $settings ) {
        // 抖音主要是视频平台,文字内容发布受限
        // 这里返回模拟结果,实际需要视频内容
        return new WP_Error( 'not_supported', '抖音暂不支持纯文字内容发布' );
    }

    /**
     * 发布到Twitter
     */
    private function publish_to_twitter( $content, $settings ) {
        // Twitter API v2实现
        return new WP_Error( 'not_implemented', 'Twitter发布功能开发中' );
    }

    /**
     * 发布到Facebook
     */
    private function publish_to_facebook( $content, $settings ) {
        // Facebook Graph API实现
        return new WP_Error( 'not_implemented', 'Facebook发布功能开发中' );
    }

    /**
     * 发布到LinkedIn
     */
    private function publish_to_linkedin( $content, $settings ) {
        // LinkedIn API实现
        return new WP_Error( 'not_implemented', 'LinkedIn发布功能开发中' );
    }

    /**
     * 获取内容图片URLs
     *
     * @param int $content_id 内容ID
     *
     * @return array 图片URL数组
     */
    private function get_content_images( $content_id ) {
        global $wpdb;
        $table_images = $wpdb->prefix . 'aiscg_images';

        $images = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT image_url FROM $table_images WHERE post_id = %d ORDER BY image_order ASC",
                $content_id
            ),
            ARRAY_A
        );

        return wp_list_pluck( $images, 'image_url' );
    }

    /**
     * 获取分发记录列表
     *
     * @param array $args 查询参数
     *
     * @return array 分发记录列表
     */
    public function get_distributions( $args = array() ) {
        global $wpdb;
        $table_distributions = $wpdb->prefix . 'aiscg_distributions';

        $defaults = array(
            'content_id' => null,
            'platform' => null,
            'status' => null,
            'limit' => 50,
            'offset' => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array( '1=1' );

        if ( $args['content_id'] !== null ) {
            $where[] = $wpdb->prepare( 'content_id = %d', $args['content_id'] );
        }

        if ( $args['platform'] !== null ) {
            $where[] = $wpdb->prepare( 'platform = %s', $args['platform'] );
        }

        if ( $args['status'] !== null ) {
            $where[] = $wpdb->prepare( 'status = %s', $args['status'] );
        }

        $where_clause = implode( ' AND ', $where );

        $query = "SELECT * FROM $table_distributions WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $distributions = $wpdb->get_results(
            $wpdb->prepare( $query, $args['limit'], $args['offset'] ),
            ARRAY_A
        );

        // 解析JSON字段
        foreach ( $distributions as &$distribution ) {
            $distribution['settings'] = json_decode( $distribution['settings'], true );
            $distribution['response_data'] = json_decode( $distribution['response_data'], true );
        }

        return $distributions;
    }

    /**
     * 删除平台内容
     *
     * @param int $distribution_id 分发记录ID
     *
     * @return bool|WP_Error 成功返回true,失败返回错误
     */
    public function delete_from_platform( $distribution_id ) {
        global $wpdb;
        $table_distributions = $wpdb->prefix . 'aiscg_distributions';

        $distribution = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table_distributions WHERE id = %d", $distribution_id ),
            ARRAY_A
        );

        if ( ! $distribution ) {
            return new WP_Error( 'not_found', '分发记录不存在' );
        }

        // 调用平台API删除内容(这里简化处理)
        $this->update_distribution( $distribution_id, array( 'status' => 'deleted' ) );

        return true;
    }

    /**
     * 创建分发记录
     *
     * @param array $data 分发数据
     *
     * @return int 分发记录ID
     */
    private function create_distribution( $data ) {
        global $wpdb;
        $table_distributions = $wpdb->prefix . 'aiscg_distributions';

        $defaults = array(
            'content_id' => 0,
            'platform' => '',
            'status' => 'pending',
            'schedule_time' => null,
            'settings' => '{}',
            'created_at' => current_time( 'mysql' ),
        );
        $data = wp_parse_args( $data, $defaults );

        $wpdb->insert( $table_distributions, $data );

        return $wpdb->insert_id;
    }

    /**
     * 更新分发记录
     *
     * @param int $distribution_id 分发记录ID
     * @param array $data 更新数据
     */
    private function update_distribution( $distribution_id, $data ) {
        global $wpdb;
        $table_distributions = $wpdb->prefix . 'aiscg_distributions';

        $data['updated_at'] = current_time( 'mysql' );

        $wpdb->update( $table_distributions, $data, array( 'id' => $distribution_id ) );
    }

    /**
     * 创建分发表
     */
    private function create_distributions_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiscg_distributions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            content_id bigint(20) NOT NULL,
            platform varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            schedule_time datetime DEFAULT NULL,
            published_at datetime DEFAULT NULL,
            platform_post_id varchar(200) DEFAULT NULL,
            platform_url text,
            settings longtext,
            response_data longtext,
            error_message text,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY content_id (content_id),
            KEY platform (platform),
            KEY status (status),
            KEY schedule_time (schedule_time)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
