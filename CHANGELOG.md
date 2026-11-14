# 更新日志

本文件记录 AI课代表 (AiMoniter) 的所有重要变更。
格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循 [语义化版本 2.0.0](https://semver.org/lang/zh-CN/)。

## [2.3.0] - 2026-11-14

### ✨ 新增

- **Responses 格式支持**：新增 OpenAI 新版 `/v1/responses` 接口驱动，支持 GPT-5.x、o 系列等新一代模型
- **接口选项更名**：驱动文件重命名为 `Chat.php` / `Messages.php` / `Responses.php`，配置选项对应改为 `chat` / `messages` / `responses`；旧配置值 `openai` / `anthropicai` 自动兼容，无需手动调整
- **API 地址智能补全**：只填根路径（如 `https://api.xxx.com/v1`）时，按接口格式自动补全 `/chat/completions`、`/messages` 或 `/responses`；填完整地址（含带 token 的中转地址）则原样使用
- Responses 格式下旧写法 `max_tokens` 参数自动映射为 `max_output_tokens`

### 🐞 修复

- **文章发布不再被插件中断**：AI 请求超时、接口异常或数据库写入失败（如 `fields` 表主键 `cid + name` 冲突）时，不再向上抛出异常打断发布流程，相关错误只会记录为前台「生成失败」提示
- **主键冲突兜底**：修复以下场景可能触发的主键冲突报错——
  - 重新发布已成功生成过评价的旧文章，且此时插件配置已失效（如 API 密钥被清空）
  - 已有记录但字段值为空或损坏时误走插入路径
  - 并发/快速重复发布时的插入竞态（插入撞主键时自动降级为更新重试）

### 📝 文档

- 新增 `CHANGELOG.md`
- 超时配置提示补充说明：建议不要超过服务器 PHP 的 `max_execution_time`

## [2.2.0] - 2026-03-17

### 🔧 代码优化

- 精简现有的存储内容，取消多余判定

### 🐞 修复

- 修复部分错误

## [2.1.0] - 2026-03-17

### ✨ 新增

- Anthropic Claude 支持

### 🔌 OpenAI 兼容接口完善

- 可接入第三方兼容服务

### 🌐 中转示例

- 提供 Cloudflare/Deno 的 Gemini 中转脚本

## [2.0.0]

### 🚀 重大更新

- 插件更名为「AI课代表」

### ✨ 多AI支持

- 新增 OpenAI 支持

### 🔧 架构重构

- 采用驱动模式，易于扩展

### 🎯 配置优化

- 更友好的配置界面

### 🛠️ 代码优化

- 更好的错误处理和日志记录

## [1.0.0]

### 🎉 初始版本

- 支持 Google Gemini
