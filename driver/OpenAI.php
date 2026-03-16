<?php
namespace TypechoPlugin\AiMoniter\driver;

use Typecho\Exception;
use TypechoPlugin\AiMoniter\driver\BaseAI;

// 防止直接运行，确保安全
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * OpenAI 协议 API 驱动
 * * 本驱动不仅支持原生的 OpenAI 服务，也兼容所有遵循 OpenAI 接口规范的三方服务
 * 如：DeepSeek, 月之暗面 (Kimi), 通义千问, Gemini (通过代理) 等。
 * * @package AiMoniter
 * @author 猫东东
 * @version 2.1.0
 */
class OpenAI extends BaseAI {
    
    /**
     * 生成文章评价内容
     * * @param array $options 请求选项
     * @return string 生成的纯文本评论
     * @throws Exception
     */
    public function generateContent($options) {
        // 1. 验证必要参数 (apiKey 和 text 是必须的)
        $this->validateOptions($options, ['apiKey', 'text']);
        // 2. 准备请求地址
        // 如果用户没填 API 地址，则尝试猜测（虽然 Plugin.php 已经做了必填校验，这里做二重保险）
        $apiUrl = $options['apiUrl'] ?: 'https://api.openai.com/v1/chat/completions';
        // 3. 准备请求头
        $headers = [
            'Authorization' => 'Bearer ' . $options['apiKey'],
            'Content-Type' => 'application/json'
        ];
        // 4. 构建 OpenAI 标准的 Chat 消息体
        $requestData = [
            'model'    => $options['model'] ?: 'gpt-5.2',
            'messages' => [
                [
                    'role'    => 'user',
                    'content' => $options['text']
                ]
            ]
        ];
        // 5. 动态合并“其它参数”
        // 只有非思考模型才合并温度等参数；或者如果用户在后台填了 JSON，则优先尊重用户的 JSON
        if (!empty($options['extraParams']) && is_array($options['extraParams'])) $requestData = array_merge($requestData, $options['extraParams']);
        
        // 6. 执行请求 (调用 BaseAI 封装的 Typecho 客户端方法)
        try {
            // 发起请求
            list($statusCode, $responseBody) = $this->sendHttpRequest($apiUrl, $requestData, $headers, $options['timeout']);
            // 检查 HTTP 状态码
            if ($statusCode !== 200) throw new Exception("服务器返回了异常状态码 [{$statusCode}]: " . $responseBody);
            // 7. 解析 JSON 响应
            $data = $this->parseResponse($responseBody);
            // 8. 检查业务逻辑错误 (OpenAI 协议通常在 error 字段返回详情)
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
     * * 特别处理了“思考模型”可能出现的 reasoning 字段或正文中的 think 标签
     * * @param array $data API 响应数组
     * @param array $options 插件配置项
     * @return string
     */
    private function extractAndClean($data, $options) {
        // 确保数据结构正确
        if (empty($data['choices'][0]['message'])) return '';
        // 获取消息体
        $message = $data['choices'][0]['message'];
        // 情况 A：某些 API (如 DeepSeek 官方) 将思考过程放在独立的 reasoning_content 字段
        // 我们直接取最终回答 content 即可
        $content = $message['content'] ?? '';
        // 情况 B：如果 API 直接将思考过程混在 content 里 (如 <think>...</think>)
        // 则调用基类的过滤方法进行清洗
        if (!empty($options['isReasoningModel'])) {
            // 调用基类的过滤方法进行清洗
            $content = $this->filterReasoning($content);
            // 额外针对 OpenAI 风格的自定义思考标记进行清洗 (扩展自你的代码)
            $extraPatterns = [
                '/\*\*?思考\*\*?[\s\S]*?\*\*?回答\*\*?[:\s]*/i',
                '/```thinking[\s\S]*?```/i',
                '/\[思考\][\s\S]*?\[回答\][:\s]*/i'
            ];
            // 尝试匹配并清理额外的标记
            $content = preg_replace($extraPatterns, '', $content);
        }
        // 返回内容
        return trim($content);
    }
}