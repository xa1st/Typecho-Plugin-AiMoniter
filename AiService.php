<?php
namespace TypechoPlugin\AiMoniter;

use Typecho\Exception;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * AI 服务中转类 (Factory)
 * 负责参数验证并分发请求到具体的驱动实例
 */
class AiService {
    /**
     * 发送 AI 请求
     *
     * @param string $provider 接口协议，支持 'chat'、'messages' 或 'responses'
     * @param array $options 请求选项，必须包含 'apiKey' 和 'text' 键
     * @return string AI 生成的响应内容
     * @throws Exception 当缺少必要参数、驱动类不存在或配置错误时抛出异常
     */
    public static function sendRequest($provider, $options = []) {
        // 1. 基本验证
        if (empty($provider)) throw new Exception('未指定 AI 服务提供商');
        // 2. 验证必要参数
        if (empty($options['apiKey'])) throw new Exception('配置错误：API 密钥缺失');
        // 3. 验证文本
        if (empty($options['text'])) throw new Exception('内容错误：待处理文本为空');
        // 4. 获取并验证驱动类
        $driverClass = self::getDriverClass($provider);
        // 5. 验证驱动类
        if (!$driverClass || !class_exists($driverClass)) throw new Exception("驱动加载失败：找不到服务商 [{$provider}] 对应的处理类");
        // 6. 实例化驱动并执行
        $driver = new $driverClass();
        // 直接透传所有 options，驱动内部通过 $options['extraParams'] 获取额外参数
        return $driver->generateContent($options);
    }
    
    /**
     * 映射提供商到具体的类名
     *
     * @param string $provider 服务提供商名称（不区分大小写）
     * @return string|null 对应的驱动类全限定名，若不支持则返回 null
     */
    private static function getDriverClass($provider) {
        // 映射关系
        // openai / anthropicai 为历史版本遗留的配置值，保留别名兼容，避免老用户升级后配置失效
        $driverMap = [
            'chat'        => \TypechoPlugin\AiMoniter\driver\Chat::class,
            'messages'    => \TypechoPlugin\AiMoniter\driver\Messages::class,
            'responses'   => \TypechoPlugin\AiMoniter\driver\Responses::class,
            'openai'      => \TypechoPlugin\AiMoniter\driver\Chat::class,      // 兼容旧版 openai
            'anthropicai' => \TypechoPlugin\AiMoniter\driver\Messages::class, // 兼容旧版 anthropicai
        ];
        // 映射
        return $driverMap[strtolower($provider)] ?? null;
    }

    /**
     * 获取支持的列表 (用于同步 Plugin.php 的 UI 展示)
     *
     * @return array 支持的服务提供商列表，键为标识符，值为显示名称
     */
    public static function getSupportedProviders() {
        // 返回支持的服务提供商列表
        return [
            'chat'      => 'Chat (OpenAI Compatible)',
            'messages'  => 'Messages (Anthropic Claude)',
            'responses' => 'Responses (OpenAI New)',
        ];
    }
}