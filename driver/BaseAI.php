<?php
namespace TypechoPlugin\AiMoniter\driver;

use Typecho\Exception;

// 防止直接运行，确保安全
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * AI 驱动基类 (Abstract Class)
 * 本类定义了所有具体的 AI 驱动（如 OpenAI, Anthropic）必须遵循的标准。
 * 它封装了通用的网络请求、参数验证、响应解析以及内容清洗逻辑。
 * @package AiMoniter
 * @author 猫东东
 * @version 2.2.0
 */
abstract class BaseAI {
    
    /**
     * 生成内容 (抽象方法)
     * 每个具体的 AI 驱动类都必须实现这个方法。
     * 用于处理不同平台特定的 API 逻辑。
     * 
     * @param array $options 包含 apiKey, apiUrl, model, text, timeout, isReasoningModel, extraParams 等配置
     * @return string AI 生成的纯文本评论
     * @throws Exception 当驱动配置错误或网络请求失败时抛出异常
     */
    abstract public function generateContent($options);
    
    /**
     * 过滤“思考模型”的思维链内容
     * 针对 DeepSeek-R1 或类似模型，AI 可能会返回包含在 <think> 标签内的思考过程。
     * 该方法会将这些内部逻辑移除，只给博主留下最终的精简评价。
     * 
     * @param string $content 原始返回文本
     * @return string 清理后的文本，已移除所有 <think>...</think> 标签及其内容，并去除首尾空白
     */
    protected function filterReasoning($content) {
        // 确保内容不为空
        if (empty($content)) return '';
        // 使用正则表达式匹配 且移除所有 <think> 标签及其内部内容（支持跨行）
        $cleanText = preg_replace('/<think>.*?<\/think>/s', '', $content);
        // 去除首尾空白字符（包括换行）后返回
        return trim($cleanText);
    }
    
    /**
     * 验证必要参数
     * 在发起 API 请求前，检查所需的配置项是否存在，避免因缺失关键参数导致请求失败。
     * 
     * @param array $options 传入的配置数组
     * @param array $required 必须存在的键名列表，如 ['apiKey', 'apiUrl']
     * @throws Exception 当任一 required 参数为空或未设置时抛出异常
     */
    protected function validateOptions($options, $required) {
        foreach ($required as $param) {
            if (empty($options[$param])) {
                throw new Exception("驱动配置错误：参数 [{$param}] 不能为空。");
            }
        }
    }
    
    /**
     * 发送统一的 HTTP POST 请求
     * 采用 Typecho 自带的 Http_Client 适配器，确保在 cURL 或 Socket 环境下都能正常工作。
     * 封装了请求头设置、超时控制、JSON 编码及错误处理等通用逻辑。
     * 
     * @param string $url 请求的 API 完整地址
     * @param array $data 要发送的 JSON 数据数组（如 model, messages 等）
     * @param array $headers 自定义请求头（如 Authorization, anthropic-version 等）
     * @param int $timeout 接口响应超时时间（秒），有效范围为 1-120，超出则默认为 15 秒
     * @return array 包含两个元素的数组：[HTTP 状态码 (int), 响应体 Body (string)]
     * @throws Exception 当 URL 格式非法、HTTP 客户端不可用或网络通信异常时抛出
     */
    protected function sendHttpRequest($url, $data, $headers = [], $timeout = 15) {
        // 1. 基础 URL 格式验证
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new Exception('API 地址格式不合法，请检查插件配置。');
        // 2. 超时时间安全加固，防止插件拖慢后台加载速度
        $timeout = ($timeout <= 0 || $timeout > 120) ? 15 : $timeout;
        try {
            // 获取 Typecho 系统定义的 Http 客户端实例
            $client = \Typecho\Http\Client::get();
            // 确保 Http 客户端可用
            if (!$client) throw new Exception('Typecho 环境不支持发送外部网络请求。');
            // 3. 设置默认请求头
            $client->setHeader('Content-Type', 'application/json');
            $client->setHeader('User-Agent', 'AiMoniter-Assistant/' . \TypechoPlugin\AiMoniter\Plugin::VERSION);
            // 4. 合并并设置额外的自定义 Header
            foreach ($headers as $key => $value) {
                $client->setHeader($key, $value);
            }
            // 5. 配置超时与负载数据（采用 UTF-8 编码的 JSON）
            $client->setTimeout($timeout);
            $client->setData(json_encode($data, JSON_UNESCAPED_UNICODE));
            // 6. 执行远程请求
            $client->send($url);
            // 7. 获取服务器响应
            $statusCode = $client->getResponseStatus();
            $responseBody = $client->getResponseBody();
            // 如果开启了 Typecho 调试模式，记录一条系统日志
            if (defined('__TYPECHO_DEBUG__') && __TYPECHO_DEBUG__) error_log(sprintf("[AiMoniter] 请求目标: %s, 状态码: %d", $url, $statusCode));
            // 返回结果
            return [$statusCode, $responseBody];
        } catch (\Exception $e) {
            // 捕获网络底层错误并重抛，便于 Plugin.php 捕获错误详情
            throw new Exception('网络通讯发生异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 解析接口返回的 JSON 响应
     * 将原始响应字符串解码为关联数组，并进行基本有效性校验。
     * 
     * @param string $responseBody 接口返回的原始字符串
     * @return array 解析后的关联数组
     * @throws Exception 当响应为空或 JSON 格式无效时抛出异常
     */
    protected function parseResponse($responseBody) {
        // 确保响应不为空
        if (empty($responseBody)) throw new Exception('接口返回了空数据，请确认 API Key 或地址是否正确。');
        // 解析 JSON
        $data = json_decode($responseBody, true);
        // 检查 JSON 语法错误
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('无法解析 API 返回的 JSON: ' . json_last_error_msg());
        // 返回结果
        return $data;
    }
}