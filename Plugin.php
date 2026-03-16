<?php
namespace TypechoPlugin\AiMoniter;

use Typecho\Db;
use Typecho\Exception;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Plugin\PluginInterface;
use Utils\Helper;
use Utils\Markdown;
use TypechoPlugin\AiMoniter\AiService;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 
 * AI课代表 - 让AI帮你评价博客文章
 * 
 * @package AiMoniter
 *
 * @author 猫东东
 * @version 2.1.0
 * @link https://github.com/xa1st/Typecho-Plugin-AiMoniter
 * 
 */
class Plugin implements PluginInterface {
    
    /**
     * 定义当前系统的版本号常量
     * 用于标识应用程序的版本信息，便于版本管理和兼容性检查
     */
    const VERSION = '2.1.0';

    /**
     * 激活插件时绑定相关钩子
     * 
     * 该方法在插件激活时调用，用于绑定文章发布和修改后的处理钩子，
     * 当文章发布或修改完成时会触发AI评论生成功能
     * 
     * @return void
     */
    public static function activate() {
        // 绑定文章发布和修改后的钩子
        \Typecho\Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = [__CLASS__, 'generateAiComment'];
    }

    /**
     * 插件停用时执行的清理操作。
     * 
     * 当插件被停用且配置中启用了清除选项时，该方法会连接数据库并删除所有文章中与 AI 评价相关的自定义字段。
     * 
     * @return void
     */
    public static function deactivate() {
        // 卸载时删除数据
        if (Helper::options()->plugin('AiMoniter')->clear) {
            // 初始化数据库
            $db = Db::get();
            // 彻底清理所有文章关联的 AI 评价字段
            $db->query($db->delete('table.fields')->where('name = ?', 'ai_comment'));
        }
    }

    /**
     * 配置个人相关表单字段。
     *
     * @param Form $form 表单对象，用于添加或修改个人配置相关的字段。
     * @return void
     */
    public static function personalConfig(Form $form) {}

    /**
     * 配置AI课代表插件的表单设置项
     * 
     * 该方法创建并添加多个输入字段到表单中，用于配置AI课代表的各项参数，
     * 包括API连接信息、模型选择、请求参数等配置选项
     * 
     * @param Form $form 表单对象，用于添加各种配置输入项
     * @return void
     */
    public static function config(Form $form) {
        // 课代表名字
        $aiName = new Text('aiName', NULL, 'AI课代表', _t('课代表名字'), _t('前台显示的名称'));
        $form->addInput($aiName);
    
        // 课代表接口
        $aiProvider = new Radio('aiProvider', [
            'openai' => _t('OpenAI 格式 (/v1/chat/completions)'), 
            'anthropicai' => _t('AnthropicAI (/v1/messages)')
        ], 'openai', _t('接口格式'));
        $form->addInput($aiProvider);
    
        // API 地址
        $apiUrl = new Text('apiUrl', NULL, '', _t('API 地址'), _t('留空使用官方默认。Deno代理请填：https://xxx.deno.dev/v1/chat/completions?token=xx'));
        $form->addInput($apiUrl);
        
        // API 密钥
        $apiKey = new Password('apiKey', NULL, NULL, _t('API KEY'), _t('必填'));
        $form->addInput($apiKey->addRule('required', _t('必须填写API密钥')));
    
        // 模型名称
        $modelName = new Text('modelName', NULL, 'gemini-2.5-Pro', _t('模型名称'));
        $form->addInput($modelName->addRule('required', _t('必须填写模型名称')));
    
        // 是否为思考模型
        $isReasoningModel = new Radio('isReasoningModel', ['1' => _t('是'), '0' => _t('否')], 0, _t('是否为思考模型'), _t('开启后将过滤 <think> 标签并禁用 temperature 等参数'));
        $form->addInput($isReasoningModel);
        
        // 请求超时
        $timeOut = new Text('timeOut', NULL, '15', _t('请求超时（秒）'), _t('建议 15-30 秒，防止模型响应慢导致发布文章卡顿'));
        $form->addInput($timeOut);
    
        // 其它参数
        $others = new Textarea('others', NULL, '{"temperature": 0.7, "max_tokens": 800}', _t('其它参数，对于不同的模型，可能并不相同，请参考对应的官方文档'));
        $form->addInput($others);
    
        // 评价提示词
        $prompt = new Textarea('prompt', NULL, 
            _t('我写了一篇日志，标题是`{title}`，内容是`{text}`，请根据内容写一段不超过128字的评论。'),
            _t('评价提示词'), _t('可用占位符：{title}, {text}, {url}')
        );
        $form->addInput($prompt);
    
        // 卸载时是否删除数据
        $clear = new Radio('clear', ['1' => _t('是'), '0' => _t('否')], 0, _t('卸载时删除数据'));
        $form->addInput($clear);
    }

    /**
     * 生成 AI 评价核心逻辑
     *
     * 该方法根据文章内容调用 AI 接口生成评价，并将结果持久化到数据库中。
     * 支持幂等操作，避免重复请求消耗 API 额度；同时处理草稿、空内容、配置缺失等异常情况。
     *
     * @param array $content 包含文章标题和正文的关联数组，必须包含 'title' 和 'text' 键
     * @param object $widget 文章数据对象，需包含 cid（文章 ID）和 permalink（文章链接）属性，可选 status 属性用于判断是否为草稿
     * @return bool|null 成功执行并持久化结果时返回 true；若为草稿、无文章 ID 或已成功生成过评价则直接返回 null
     */
    public static function generateAiComment($content, $widget) {
        // 文章id
        $cid = $widget->cid;
        // 判定是否为草稿
        if ((isset($widget->status) && $widget->status === 'draft') || !$cid) return; // 如果是草稿或者没有文章id，直接退出，不浪费额度
        // 是否为更新
        $isUpdate = false;
        // 初始化数据库
        $db = Db::get();
        // 开始主逻辑
        try {
            // 1. 如果标题或者内容为空，则不执行
            if (empty($content['title']) || empty($content['text'])) throw new \Exception('标题或内容不能为空');
            // 2. 获取插件配置
            $options = Helper::options()->plugin('AiMoniter');
            // 3. 如果API或者url为空，则不执行，因为有些模型不用写model
            if (empty($options->apiKey) || empty($options->apiUrl)) throw new \Exception('请填写API密钥和API地址');
            // 4. 检查是否已有成功生成的记录（避免重复消耗额度）
            $exists = $db->fetchRow($db->select('str_value')->from('table.fields')->where('cid = ? AND name = ?', $cid, 'ai_comment'));
            if ($exists) {
                // 4.1 解析变量
                $comment = json_decode($exists['str_value'], true);
                // 4.2 如果已经生成过，也没错误，就直接跳过
                if (isset($comment['error']) && $comment['error'] === 0) return;
                // 4.3 如果有错误，就说明旧的存在，标记为更新，然后继续更新
                $isUpdate = true;
            }
            // 5. 文本清洗与【关键】截断
            // 5.1 很多 AI 接口在文章太长时会直接报错 400
            $cleanText = html_entity_decode(strip_tags(Markdown::convert($content['text'])), ENT_QUOTES, 'UTF-8');
            // 5.2 这里截取前 2500 字足够评价使用
            $cleanText = mb_substr($cleanText, 0, 2500, 'UTF-8');
            // 5.3 安全替换占位符
            $replaceMap = ['{title}' => $content['title'], '{url}' => $widget->permalink, '{text}' => $cleanText];
            // 5.4 尝试替换占位符
            $promptText = strtr($options->prompt, $replaceMap);
            // 6. 解析 JSON 参数
            $extraParams = json_decode($options->others, true) ?: [];
            // 5. 构造请求
            $requestOptions = [
                'apiKey'           => $options->apiKey,
                'apiUrl'           => $options->apiUrl,
                'model'            => $options->modelName,
                'text'             => $promptText,
                'timeout'          => (int)$options->timeOut,
                'isReasoningModel' => (bool)$options->isReasoningModel,
                'extraParams'      => $extraParams
            ];
            // 发送请求
            $aiResponse = AiService::sendRequest($options->aiProvider, $requestOptions);
            // 构造响应
            $res = ["error" => 0, "ainame" => $options->aiName, "say" => trim($aiResponse)];
        } catch (\Exception $e) {
            // 记录错误到数据库，以便前台提示“生成失败”而不是空白
            $res = ["error" => 1, "ainame" => $options->aiName, "say" => "AI 课代表罢工了: " . $e->getMessage()];
        }
        // 6.2 序列化, 不要序列化中文
        $res = json_encode($res, JSON_UNESCAPED_UNICODE);
        // 6.3 如果是更新，则更新
        if ($isUpdate) {
            $db->query($db->update('table.fields')->rows(['str_value' => $res])->where('cid = ? AND name = ?', $cid, 'ai_comment'));
        } else {
            $db->query($db->insert('table.fields')->rows(['cid' => $cid, 'name' => 'ai_comment', 'str_value' => $res, 'type' => 'str']));
        }
        // 7. 返回结果
        return true;
    }
}