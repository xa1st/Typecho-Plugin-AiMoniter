<?php
namespace TypechoPlugin\AiMoniter\driver;

use Typecho\Exception;
use TypechoPlugin\AiMoniter\driver\BaseAI;

// 防止直接运行，确保安全
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Anthropic (Claude) 协议驱动
 * 主要针对 Claude-3 (Sonnet/Opus/Haiku) 以及最新的 3.5 系列模型。
 * 该驱动处理 Anthropic 特有的 Header 认证及数据响应结构。
 * @package AiMoniter
 * @author 猫东东
 * @version 2.2.0
 */
class AnthropicAI extends BaseAI {
    
    /**
     * 生成文章评价内容
     *
     * 该方法通过调用 Anthropic 的 Messages API，使用指定模型对输入文本生成评价内容。
     * 支持通过 extraParams 动态传入 temperature、top_p 等高级参数。
     *
     * @param array $options 请求选项，必须包含以下键：
     *                       - apiKey: Anthropic API 密钥（必填）
     *                       - text: 待处理的原始文本（必填）
     *                       - model: 使用的模型名称（可选，默认为 claude-3-5-sonnet-20240620）
     *                       - apiUrl: API 地址（可选，默认为官方地址）
     *                       - timeout: 请求超时时间（可选）
     *                       - extraParams: 额外请求参数数组（可选）
     *                       - isReasoningModel: 是否启用思考过程过滤（可选）
     * @return string 生成的纯文本评论内容
     * @throws Exception 当缺少必要参数、API 请求失败、响应格式异常或未返回有效内容时抛出
     */
    public function generateContent($options) {
        // 1. 验证必要参数 (apiKey 和 text 是 Claude 请求的基础)
        $this->validateOptions($options, ['apiKey', 'text']);
        // 2. 准备请求地址 (默认为官方 Messages 接口)
        $apiUrl = $options['apiUrl'] ?: 'https://api.anthropic.com/v1/messages';
        // 3. 准备请求头
        // Anthropic 必须包含 x-api-key 和特定的版本号头
        $headers = [
            'x-api-key'         => $options['apiKey'],
            'anthropic-version' => '2023-06-01', // 协议版本，目前固定为该日期
            'Content-Type'      => 'application/json'
        ];
        // 4. 构建 Anthropic 标准请求体
        // 注意：Claude 强制要求提供 max_tokens 字段
        $requestData = [
            'model'      => $options['model'] ?: 'claude-3-5-sonnet-20240620',
            'max_tokens' => 1024,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $options['text']
                ]
            ]
        ];
        // 5. 动态合并“其它参数”
        // 这里会合并 Plugin.php 解析出来的 temperature, top_p 等 JSON 配置
        if (!empty($options['extraParams']) && is_array($options['extraParams'])) $requestData = array_merge($requestData, $options['extraParams']);
        
        // 6. 执行请求
        try {
            // 8. 发起请求
            list($statusCode, $responseBody) = $this->sendHttpRequest($apiUrl, $requestData, $headers, $options['timeout']);
            // 9. 检查状态码
            if ($statusCode !== 200) throw new Exception("Claude 服务端响应异常 [{$statusCode}]: " . $responseBody);
            // 10. 解析 JSON 响应
            $data = $this->parseResponse($responseBody);
            // 11. 检查业务错误 (Anthropic 的错误通常包含在 type 字段中)
            if (isset($data['type']) && $data['type'] === 'error') throw new Exception('Claude API 错误: ' . ($data['error']['message'] ?? '未知错误'));
            // 10. 提取内容并清洗
            $content = $this->extractAndClean($data, $options);
            // 11. 验证并返回
            if (empty($content)) throw new Exception('Claude 响应成功但未获取到有效文字内容。');
            // 12. 返回内容
            return $content;
        } catch (\Exception $e) {
            // 异常向上传递给 Plugin.php
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * 提取并清洗内容
     *
     * 从 Anthropic API 的响应中提取文本内容，并根据配置决定是否过滤模型的推理痕迹。
     * Claude 的响应内容以 content 数组形式返回，每个元素为一个 block（如 text 或 tool_use）。
     *
     * @param array $data API 响应解析后的关联数组，应包含 'content' 键
     * @param array $options 插件配置项，用于判断是否启用推理过滤（isReasoningModel）
     * @return string 合并并清理后的纯文本内容，无多余空白字符
     */
    private function extractAndClean($data, $options) {
        // Claude 的内容返回在 content 数组中，通常为 text 类型的 block
        if (empty($data['content']) || !is_array($data['content'])) return '';
        // 遍历 content 数组，将所有 text 类型的 block 内容合并
        $fullText = '';
        foreach ($data['content'] as $block) {
            if (isset($block['type']) && $block['type'] === 'text') {
                $fullText .= $block['text'];
            }
        }
        // 如果开启了思考模型过滤
        if (!empty($options['isReasoningModel'])) $fullText = $this->filterReasoning($fullText);
        // 思考模型过滤
        return trim($fullText);
    }
}