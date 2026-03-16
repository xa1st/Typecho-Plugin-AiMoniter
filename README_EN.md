<div align="center">

# 🤖 AI Class Monitor (AiMoniter)

[![Release Version](https://img.shields.io/github/v/release/xa1st/Typecho-Plugin-AiMoniter?style=flat-square)](https://github.com/xa1st/Typecho-Plugin-AiMoniter/releases/latest)
[![GitHub license](https://img.shields.io/github/license/xa1st/Typecho-Plugin-AiMoniter?style=flat-square)](LICENSE)
![PHP Version](https://img.shields.io/badge/PHP-7.2+-4F5B93.svg?style=flat-square)
[![Required Typecho Version](https://img.shields.io/badge/Typecho-1.1+-167B94.svg?style=flat-square)](https://typecho.org)

**A Typecho plugin that lets AI automatically review your blog posts**  
**Supports OpenAI-compatible APIs and Anthropic Claude (Gemini via proxy)**

[🇨🇳 简体中文](README.md) | [🌏 English](README_EN.md)

</div>

<p align="center">
  <img src="https://raw.githubusercontent.com/xa1st/Typecho-Plugin-AiMoniter/main/screenshot.png" alt="AI Class Monitor Screenshot" width="720">
</p>

## ✨ Features

- 🤖 **Multi-Service Support** - OpenAI-compatible APIs and Anthropic Claude
- 🎯 **Smart Reviews** - AI automatically generates personalized comments for each article
- ⚡ **Auto-Trigger** - Reviews are automatically generated after publishing an article
- 🛠️ **Highly Configurable** - Custom API endpoints and model parameters (JSON passthrough)
- 🎨 **User-Friendly Interface** - Clean and intuitive admin configuration panel
- 🧠 **Reasoning Model Support** - Filters `<think>` and keeps the final answer
- 🌐 **Proxy Examples** - Cloudflare/Deno proxy scripts (Gemini OpenAI-compatible)

## Supported AI Services

### OpenAI-Compatible API
- Endpoint: `/v1/chat/completions`
- Works with OpenAI or any service/proxy compatible with this protocol

### Anthropic Claude
- Endpoint: `/v1/messages`
- Requires `x-api-key` and `anthropic-version` headers

## Installation

1. Download the plugin to your Typecho plugins directory: `usr/plugins/AiMoniter/`
2. Activate the plugin in the Typecho admin panel
3. Configure the AI service API keys and related parameters

## Configuration

### Basic Configuration

1. **AI Service Provider**: Choose the AI service (OpenAI-compatible / Anthropic Claude)
2. **API KEY**: Enter the API key for the selected service
3. **Model Name**: Specify the exact model you want to use
4. **API URL**: Required, custom API endpoint (proxy supported)

### Advanced Configuration

- **Temperature**: Control randomness (range depends on the model)
- **Max Tokens**: Limit output length (required by some APIs)
- **Timeout**: Request timeout setting
- **Review Prompt**: Customize the prompt template for AI reviews
- **Reasoning Model**: Filters `<think>...</think>` reasoning traces

## Usage

After activating the plugin, the AI will automatically generate a review whenever a new article is published and save it in JSON format to the `ai_comment` field in the database.

You can use the following code in your theme to display the AI review:

```php
// Get AI review for the current article (JSON format)
$aiComment = json_decode($this->fields->ai_comment);
if ($aiComment && $aiComment->error === 0): ?>
    <article class="post ai-comment">
        <div class="ai-moniter-container">
            <h2>AI Class Monitor Summary - Current Monitor: <?php echo $aiComment->ainame ?? 'Anonymous'; ?></h2>
            <p><?php echo $aiComment->say ?? ''; ?></p>
        </div>
    </article>
<?php endif;
```

### AI Review Data Structure

The plugin stores AI reviews in JSON format with the following fields:

```json
{
    "error": 0,                    // Error code: 0 for success, 1 for failure
    "ainame": "AI Class Monitor",   // Name of the AI monitor
    "say": "AI generated review content"  // AI generated review text
}
```

## Notes

1. Ensure your server can access the corresponding AI service APIs
2. Be aware of API usage quotas and costs
3. Set reasonable timeout values and token limits
4. When uninstalling, you can choose to preserve or delete the generated data

## Changelog

### v2.1.0
- ✨ **Anthropic Claude support**
- 🔌 **Improved OpenAI-compatible support**: Works with third-party compatible services
- 🌐 **Proxy examples**: Cloudflare/Deno scripts for Gemini

### v2.0.0
- 🚀 **Major Update**: Plugin renamed to "AI Class Monitor"
- ✨ **Multiple AI Support**: Added OpenAI support
- 🔧 **Architecture Refactor**: Using driver pattern for easy extension
- 🎯 **Configuration Improvements**: More user-friendly configuration interface
- 🛠️ **Code Optimization**: Better error handling and logging

### v1.0.0
- Initial release with Google Gemini support

## Technical Architecture

```
AiMoniter/
├── Plugin.php          # Main plugin file
├── AiService.php       # AI service management class
├── driver/             # AI drivers directory
│   ├── BaseAI.php          # Base driver abstract class
│   ├── OpenAI.php          # OpenAI-compatible driver
│   └── AnthropicAI.php     # Anthropic Claude driver
├── scripts/            # Proxy examples
│   ├── cloudflare.worker.js # Cloudflare Workers
│   └── deno.dev.js          # Deno Deploy
└── README.md           # Documentation
```

## Development

### Adding a New AI Service

1. Create a new driver class in the `driver/` directory
2. Extend the `BaseAI` abstract class
3. Implement the `generateContent()` method
4. Register the new driver in `AiService.php`

### Custom Prompts

The following placeholders are supported:
- `{title}`: Article title
- `{text}`: Article content (HTML cleaned)
- `{url}`: Article URL

## Proxy Examples (scripts/)

To use Gemini with the OpenAI-compatible endpoint, the `scripts/` directory provides two ready-to-deploy proxy examples:

- `scripts/cloudflare.worker.js`: Cloudflare Workers version  
  Set environment variables `TOKEN` (optional) and `GEMINI_URL` (default: `https://generativelanguage.googleapis.com/v1beta/openai/chat/completions`).
- `scripts/deno.dev.js`: Deno Deploy version  
  Also supports `TOKEN` and `GEMINI_URL` environment variables.

Both support:
- `?token=YOUR_TOKEN` for simple auth
- CORS handling
- Health check response on root path

In the plugin, set **API URL** to the proxy endpoint, for example:  
`https://<your-domain>/v1/chat/completions?token=YOUR_TOKEN`

## License

[MulanPSL2 License](https://github.com/xa1st/Typecho-Plugin-AiMoniter/blob/main/LICENSE)

## Author

Cat DongDong (猫东东) <xa1st@outlook.com>

## Thanks
- [Typecho](https://typecho.org/)
- [Binjoo](https://digu.com)

## Links

- [GitHub Repository](https://github.com/xa1st/Typecho-Plugin-AiMoniter)
- [Issue Tracker](https://github.com/xa1st/Typecho-Plugin-AiMoniter/issues)

