<?php
/**
 * AI服务接口
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI服务接口
 */
interface AISCG_AI_Service_Interface {

    /**
     * 生成内容
     *
     * @param string $prompt 提示词
     * @param array  $options 可选参数
     * @return string 生成的内容
     * @throws Exception 如果生成失败
     */
    public function generate( $prompt, $options = array() );

    /**
     * 测试连接
     *
     * @return string 测试结果消息
     * @throws Exception 如果连接失败
     */
    public function test_connection();

    /**
     * 获取模型名称
     *
     * @return string
     */
    public function get_model_name();

    /**
     * 设置API密钥
     *
     * @param string $api_key API密钥
     */
    public function set_api_key( $api_key );

    /**
     * 设置模型参数
     *
     * @param array $params 模型参数
     */
    public function set_parameters( $params );
}
