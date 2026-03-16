<div align="center">

# 🤖 AI课代表 (AiMoniter)

[![Release Version](https://img.shields.io/github/v/release/xa1st/Typecho-Plugin-AiMoniter?style=flat-square)](https://github.com/xa1st/Typecho-Plugin-AiMoniter/releases/latest)
[![许可证](https://img.shields.io/badge/许可证-木兰宽松第2版-red.svg)](https://license.coscl.org.cn/MulanPSL2)
![PHP Version](https://img.shields.io/badge/PHP-8.0+-4F5B93.svg?style=flat-square)
[![Required Typecho Version](https://img.shields.io/badge/Typecho-1.2+-167B94.svg?style=flat-square)](https://typecho.org)

**让 AI 帮你评价博客文章的 Typecho 插件**  
**支持 OpenAI 兼容接口与 Anthropic Claude（可通过中转接入 Gemini 等）**

[简体中文](README.md) | [English](README_EN.md)

</div>

<p align="center">
  <img src="https://raw.githubusercontent.com/xa1st/Typecho-Plugin-AiMoniter/main/preview.png" alt="AI课代表截图" width="720">
</p>

## ✨ 功能特性

- 🤖 **多服务支持** - OpenAI 兼容接口与 Anthropic Claude
- 🎯 **智能评价** - AI 自动为每篇文章生成个性化评论
- ⚡ **自动触发** - 文章发布后自动生成 AI 评价
- 🛠️ **高度可配置** - 支持自定义 API 地址、模型参数（JSON透传）
- 🎨 **友好界面** - 简洁直观的后台配置界面
- 🧠 **思考模型支持** - 可过滤 `<think>` 等思考内容，保留最终回答
- 🌐 **中转示例** - 提供 Cloudflare/Deno 中转脚本示例（Gemini OpenAI 协议）

## 支持的AI服务

### OpenAI 兼容接口
- 协议：`/v1/chat/completions`
- 可直连 OpenAI 官方，也可使用任意兼容该协议的服务或中转

### Anthropic Claude
- 协议：`/v1/messages`
- 需要 `x-api-key` 与 `anthropic-version` 头

## 安装

1. 下载插件到 Typecho 的插件目录：`usr/plugins/AiMoniter/`
2. 在 Typecho 后台激活插件
3. 配置 AI 服务的 API 密钥和相关参数

## 配置

### 基本配置

1. **AI 服务提供商**：选择要使用的 AI 服务（OpenAI 兼容 / Anthropic Claude）
2. **API KEY**：输入对应服务的 API 密钥
3. **模型名称**：指定要使用的具体模型
4. **API 地址**：必填，自定义 API 地址（支持中转/代理）

### 高级配置

- **温度参数**：控制输出随机性（具体范围以模型文档为准）
- **最大Token数**：限制 AI 输出长度（部分接口为必填）
- **超时时间**：请求超时设置
- **评价提示词**：自定义 AI 评价的提示词模板
- **思考模型**：开启后会过滤 `<think>...</think>` 等思考内容

## 使用

插件激活后，每当发布新文章时，AI 会自动生成评价并以JSON格式保存到数据库的 `ai_comment` 字段中。

你可以在主题中使用以下方式获取和显示 AI 评价：

```php
// 获取当前文章的 AI 评价（JSON格式）
$aiComment = json_decode($this->fields->ai_comment);
if ($aiComment && $aiComment->error === 0): ?>
    <article class="post ai-comment">
        <div class="ai-moniter-container">
            <h2>AI课代表总结 - 当前课代表: <?php echo $aiComment->ainame ?? '无名氏'; ?></h2>
            <p><?php echo $aiComment->say ?? ''; ?></p>
        </div>
    </article>
<?php endif;
```

### AI 评价数据结构

插件将AI评价以JSON格式存储，包含以下字段：

```json
{
    "error": 0,                    // 错误码：0表示成功，1表示失败
    "ainame": "AI课代表",          // AI课代表的名称
    "say": "这是AI生成的评价内容"    // AI生成的评价文本
}
```

## 注意事项

1. 确保服务器可以访问对应的 AI 服务 API
2. 注意 API 使用限额和费用
3. 建议设置合理的超时时间和 Token 限制
4. 卸载插件时可选择保留或删除生成的数据

## 更新日志

### v2.1.0
- ✨ **新增 Anthropic Claude 支持**
- 🔌 **OpenAI 兼容接口完善**：可接入第三方兼容服务
- 🌐 **中转示例**：提供 Cloudflare/Deno 的 Gemini 中转脚本

### v2.0.0
- 🚀 **重大更新**：插件更名为「AI课代表」
- ✨ **多AI支持**：新增 OpenAI 支持
- 🔧 **架构重构**：采用驱动模式，易于扩展
- 🎯 **配置优化**：更友好的配置界面
- 🛠️ **代码优化**：更好的错误处理和日志记录

### v1.0.0
- 初始版本，支持 Google Gemini

## 技术架构

```
AiMoniter/
├── Plugin.php          # 主插件文件
├── AiService.php       # AI 服务管理类
├── driver/             # AI 驱动目录
│   ├── BaseAI.php          # 基础驱动抽象类
│   ├── OpenAI.php          # OpenAI 兼容驱动
│   └── AnthropicAI.php     # Anthropic Claude 驱动
├── scripts/            # 中转平台示例
│   ├── cloudflare.worker.js # Cloudflare Workers 版本
│   └── deno.dev.js          # Deno Deploy 版本
└── README.md           # 说明文档
```

## 开发

### 添加新的 AI 服务

1. 在 `driver/` 目录下创建新的驱动类
2. 继承 `BaseAI` 抽象类
3. 实现 `generateContent()` 方法
4. 在 `AiService.php` 中注册新驱动

### 自定义提示词

支持以下占位符：
- `{title}`：文章标题
- `{text}`：文章内容（已清理HTML）
- `{url}`：文章链接

## 中转平台示例（scripts/）

为了方便使用 Gemini 的 OpenAI 兼容接口，`scripts/` 目录提供了两个可直接部署的中转示例：

- `scripts/cloudflare.worker.js`：Cloudflare Workers 版本  
  需要在环境变量中设置 `TOKEN`（必选）与 `GEMINI_URL`（默认 `https://generativelanguage.googleapis.com/v1beta/openai/chat/completions`）。

- `scripts/deno.dev.js`：Deno Deploy 版本
  同样支持 `TOKEN`（必选） 与 `GEMINI_URL` 环境变量。

两者都支持：
- `?token=YOUR_TOKEN` 方式进行简单鉴权
- CORS 处理
- 访问根路径返回健康检查信息

插件中使用时，将 **API 地址** 设置为中转地址，例如：  
`https://<你的域名>/v1/chat/completions?token=YOUR_TOKEN`

## 许可证

[MulanPSL2 License](https://github.com/xa1st/Typecho-Plugin-AiMoniter/blob/main/LICENSE)

## 作者

猫东东 <xa1st@outlook.com>

## 鸣谢

- [Typecho](https://typecho.org/)
- [冰剑](https://digu.com)

## 链接

- [GitHub 仓库](https://github.com/xa1st/Typecho-Plugin-AiMoniter)
- [问题反馈](https://github.com/xa1st/Typecho-Plugin-AiMoniter/issues)

