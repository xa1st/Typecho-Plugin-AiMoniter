<?php
namespace TypechoPlugin\AiMoniter\driver;

use Typecho\Exception;
use TypechoPlugin\AiMoniter\driver\BaseAI;

// 防止直接运行，确保安全
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Responses 协议驱动 (OpenAI 新版接口)
 * 本驱动对接 OpenAI 2025 年推出的新版 /v1/responses 接口，
 * 主要面向 GPT-5.x、o 系列等新一代模型，部分中转服务也已跟进支持该协议。
 * @package AiMoniter
 * @author 猫东东
 * @version 2.3.0
 */
class Responses extends BaseAI {

    /**
     * 生成文章评价内容
     * * @param array $options 请求选项
     * @return string 生成的纯文本评论
     * @throws Exception
     */
    public function generateContent($options) {
        // 1. 验证必要参数 (apiKey 和 text 是必须的)
        $this->validateOptions($options, ['apiKey', 'text']);
        // 2. 准备请求地址 (默认为官方 Responses 接口)
        $apiUrl = $this->resolveApiUrl($options['apiUrl'] ?: 'https://api.openai.com/v1', '/responses');
        // 3. 准备请求头
        // 认证方式与 Chat 接口相同，使用 Bearer Token
        $headers = [
            'Authorization' => 'Bearer ' . $options['apiKey'],
            'Content-Type' => 'application/json'
        ];
        // 4. 构建 Responses 标准请求体
        // 注意：Responses 接口使用 input 传递消息，不再使用 messages
        $requestData = [
            'model'    => $options['model'] ?: 'gpt-5.2',
            'input'    => [
                [
                    'role'    => 'user',
                    'content' => $options['text']
                ]
            ]
        ];
        // 5. 动态合并“其它参数”
        // Responses 接口已废弃 max_tokens，如果用户沿用旧写法，则自动映射为新参数名 max_output_tokens
        if (!empty($options['extraParams']) && is_array($options['extraParams'])) {
            // 自动映射旧参数名，方便老配置直接切换
            if (isset($options['extraParams']['max_tokens'])) {
                $options['extraParams']['max_output_tokens'] = $options['extraParams']['max_tokens'];
                unset($options['extraParams']['max_tokens']);
            }
            $requestData = array_merge($requestData, $options['extraParams']);
        }

        // 6. 执行请求 (调用 BaseAI 封装的 Typecho 客户端方法)
        try {
            // 发起请求
            list($statusCode, $responseBody) = $this->sendHttpRequest($apiUrl, $requestData, $headers, $options['timeout']);
            // 检查 HTTP 状态码
            if ($statusCode !== 200) throw new Exception("服务器返回了异常状态码 [{$statusCode}]: " . $responseBody);
            // 7. 解析 JSON 响应
            $data = $this->parseResponse($responseBody);
            // 8. 检查业务逻辑错误 (Responses 接口同样在 error 字段返回详情)
            if (!empty($data['error'])) {
                $errorMsg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
                throw new Exception('API 内部错误: ' . $errorMsg);
            }
            // 9. 提取内容并清洗
            $content = $this->extractAndClean($data, $options);
            // 10. 验证内容
            if (empty($content)) throw new Exception('API 响应成功但未提取到有效内容。');
            // 11. 返回内容
            return $content;
        } catch (Exception $e) {
            // 将错误继续向上传递，交由 Plugin.php 捕获并存入数据库
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 提取并清洗 API 返回的内容
     * * Responses 接口的内容以 output 数组形式返回，每个元素为一个 block（如 reasoning、message）。
     * 特别处理了“思考模型”可能出现的 reasoning 字段或正文中的 think 标签
     * * @param array $data API 响应数组
     * @param array $options 插件配置项
     * @return string
     */
    private function extractAndClean($data, $options) {
        // 确保数据结构正确
        if (empty($data['output']) || !is_array($data['output'])) return '';
        // 遍历 output 数组，将所有 message 类型 block 中的 output_text 合并
        $fullText = '';
        foreach ($data['output'] as $block) {
            // 只取最终回答的 message block，跳过 reasoning 等其他类型
            if (isset($block['type']) && $block['type'] === 'message' && !empty($block['content']) && is_array($block['content'])) {
                // 遍历 message 内的 content 数组，只合并 output_text 类型的片段
                foreach ($block['content'] as $piece) {
                    if (isset($piece['type']) && $piece['type'] === 'output_text') {
                        $fullText .= $piece['text'];
                    }
                }
            }
        }
        // 如果开启了思考模型过滤
        if (!empty($options['isReasoningModel'])) $fullText = $this->filterReasoning($fullText);
        // 返回内容
        return trim($fullText);
    }
}