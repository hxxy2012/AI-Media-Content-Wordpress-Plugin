<?php
/**
 * AI服务工厂类
 *
 * @package AI_Social_Content_Generator
 */

// 如果直接访问此文件,则退出
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI服务工厂类
 */
class AISCG_AI_Service_Factory {

    /**
     * 创建AI服务实例
     *
     * @param string $model_type AI模型类型
     * @return AISCG_AI_Service_Interface
     * @throws Exception 如果模型类型不支持
     */
    public static function create( $model_type = '' ) {
        // 如果没有指定模型类型,使用默认模型
        if ( empty( $model_type ) ) {
            $ai_config = get_option( 'aiscg_ai_config', array() );
            $model_type = isset( $ai_config['default_model'] ) ? $ai_config['default_model'] : 'openai';
        }

        // 获取配置
        $ai_config = get_option( 'aiscg_ai_config', array() );
        if ( ! isset( $ai_config['models'][ $model_type ] ) ) {
            throw new Exception( sprintf( 'AI model "%s" is not configured', $model_type ) );
        }

        $config = $ai_config['models'][ $model_type ];

        // 检查是否启用
        if ( empty( $config['enabled'] ) ) {
            throw new Exception( sprintf( 'AI model "%s" is not enabled', $model_type ) );
        }

        // 检查API密钥
        if ( empty( $config['api_key'] ) ) {
            throw new Exception( sprintf( 'API key for "%s" is not configured', $model_type ) );
        }

        // 创建对应的服务实例
        $service = null;
        switch ( $model_type ) {
            case 'openai':
                $service = new AISCG_OpenAI_Service();
                break;
            case 'gemini':
                $service = new AISCG_Gemini_Service();
                break;
            case 'deepseek':
                $service = new AISCG_DeepSeek_Service();
                break;
            case 'claude':
                $service = new AISCG_Claude_Service();
                break;
            case 'qwen':
                $service = new AISCG_Qwen_Service();
                break;
            default:
                throw new Exception( sprintf( 'Unsupported AI model type: %s', $model_type ) );
        }

        // 设置API密钥和参数
        $service->set_api_key( $config['api_key'] );
        $service->set_parameters( $config );

        return $service;
    }

    /**
     * 获取所有可用的AI服务列表
     *
     * @return array
     */
    public static function get_available_services() {
        $ai_config = get_option( 'aiscg_ai_config', array() );
        $available = array();

        if ( isset( $ai_config['models'] ) && is_array( $ai_config['models'] ) ) {
            foreach ( $ai_config['models'] as $key => $config ) {
                if ( ! empty( $config['enabled'] ) && ! empty( $config['api_key'] ) ) {
                    $available[ $key ] = isset( $config['model'] ) ? $config['model'] : $key;
                }
            }
        }

        return $available;
    }
}
