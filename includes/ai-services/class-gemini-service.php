<?php
/**
 * Google Gemini服务类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gemini服务实现
 */
class AISCG_Gemini_Service implements AISCG_AI_Service_Interface {

    /**
     * API密钥
     *
     * @var string
     */
    private $api_key;

    /**
     * 模型名称
     *
     * @var string
     */
    private $model = 'gemini-pro';

    /**
     * 模型参数
     *
     * @var array
     */
    private $parameters = array(
        'temperature' => 0.7,
        'max_tokens' => 2000,
    );

    /**
     * API端点基础URL
     *
     * @var string
     */
    private $api_base = 'https://generativelanguage.googleapis.com/v1beta/models';

    /**
     * 生成内容
     *
     * @param string $prompt 提示词
     * @param array  $options 可选参数
     * @return string 生成的内容
     * @throws Exception 如果生成失败
     */
    public function generate( $prompt, $options = array() ) {
        $params = wp_parse_args( $options, $this->parameters );

        $url = sprintf( '%s/%s:generateContent?key=%s', $this->api_base, $this->model, $this->api_key );

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt,
                        ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'temperature' => floatval( $params['temperature'] ),
                'maxOutputTokens' => intval( $params['max_tokens'] ),
            ),
        );

        $response = $this->make_request( $url, $body );

        if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return trim( $response['candidates'][0]['content']['parts'][0]['text'] );
        }

        throw new Exception( 'Invalid response from Gemini API' );
    }

    /**
     * 测试连接
     *
     * @return string 测试结果消息
     * @throws Exception 如果连接失败
     */
    public function test_connection() {
        try {
            $test_prompt = 'Hello, this is a test message. Please respond with "OK".';
            $response = $this->generate( $test_prompt, array( 'max_tokens' => 50 ) );

            return sprintf( 'Connection successful. Model: %s', $this->model );
        } catch ( Exception $e ) {
            throw new Exception( 'Connection failed: ' . $e->getMessage() );
        }
    }

    /**
     * 获取模型名称
     *
     * @return string
     */
    public function get_model_name() {
        return $this->model;
    }

    /**
     * 设置API密钥
     *
     * @param string $api_key API密钥
     */
    public function set_api_key( $api_key ) {
        $this->api_key = $api_key;
    }

    /**
     * 设置模型参数
     *
     * @param array $params 模型参数
     */
    public function set_parameters( $params ) {
        if ( isset( $params['model'] ) ) {
            $this->model = $params['model'];
        }

        if ( isset( $params['temperature'] ) ) {
            $this->parameters['temperature'] = $params['temperature'];
        }

        if ( isset( $params['max_tokens'] ) ) {
            $this->parameters['max_tokens'] = $params['max_tokens'];
        }
    }

    /**
     * 发送API请求
     *
     * @param string $url 请求URL
     * @param array  $body 请求体
     * @return array 响应数据
     * @throws Exception 如果请求失败
     */
    private function make_request( $url, $body ) {
        $timeout = get_option( 'aiscg_request_timeout', 30 );

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( $body ),
            'timeout' => $timeout,
            'method' => 'POST',
        );

        // 记录请求日志
        if ( get_option( 'aiscg_enable_logging', false ) ) {
            error_log( 'AISCG Gemini Request: ' . wp_json_encode( $body, JSON_UNESCAPED_UNICODE ) );
        }

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            throw new Exception( 'Request failed: ' . $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // 记录响应日志
        if ( get_option( 'aiscg_enable_logging', false ) ) {
            error_log( 'AISCG Gemini Response (' . $status_code . '): ' . $response_body );
        }

        if ( $status_code !== 200 ) {
            $error_data = json_decode( $response_body, true );
            $error_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'Unknown error';
            throw new Exception( sprintf( 'API request failed with status %d: %s', $status_code, $error_message ) );
        }

        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception( 'Failed to parse API response' );
        }

        return $data;
    }
}
