/**
 * Gemini API 代理脚本 (Cloudflare Workers 版)
 * * 功能：将 OpenAI 格式的请求转发至 Gemini 官方接口，并支持自定义 Token 校验
 * * 部署说明：
 * 1. 在 Cloudflare Workers 后台创建一个新 Worker。
 * 2. 在 Settings -> Variables 中添加两个环境变量：
 * - TOKEN: 你自定义的校验密钥。
 * - GEMINI_URL: 默认为 https://generativelanguage.googleapis.com/v1beta/openai/chat/completions
 */

export default {
    async fetch(request, env, ctx) {
        // --- 1. 获取配置 (从 CF 环境变量中读取) ---
        const TOKEN = env.TOKEN || "";
        const GEMINI_URL = env.GEMINI_URL || "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions";

        const url = new URL(request.url); // 获取请求的 URL
        const path = url.pathname; // 获取请求的 URL 路径
        const token = url.searchParams.get("token"); // 获取请求的 URL 参数
        
        // --- 2. 处理 OPTIONS 预检请求 (CORS) ---
        // 确保 Typecho 插件跨域请求时不会被拦截
        if (request.method === "OPTIONS") {
            return new Response(null, {
                status: 204,
                headers: {
                    "Access-Control-Allow-Origin": "*",
                    "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
                    "Access-Control-Allow-Headers": "Content-Type, Authorization",
                    "Access-Control-Max-Age": "86400",
                },
            });
        }
        // --- 3. 根路径或非 API 路径：显示状态信息 ---
        if (path === "/" || !path.includes("/v1/chat/completions")) {
            const statusInfo = {
                status: "healthy",
                message: "Cloudflare Gemini 代理服务正在运行",
                timestamp: new Date().toISOString(),
                note: "API调用需要token参数",
                usage: {
                    format: "?token=YOUR_TOKEN",
                    example: `https://${url.hostname}/v1/chat/completions?token=${TOKEN}`,
                },
            };
            return new Response(JSON.stringify(statusInfo, null, 2), {
                status: 200,
                headers: { "Content-Type": "application/json; charset=utf-8" },
            });
        }
        // --- 4. 校验自定义 Token ---
        // 增加了一个简单的安全层，防止 API 被恶意刷额度
        if (TOKEN !== "" && token !== TOKEN) {
            return new Response(JSON.stringify({ error: "Token不正确或缺失" }), {
                status: 401,
                headers: {
                    "Content-Type": "application/json",
                    "Access-Control-Allow-Origin": "*",
                },
            });
        }
        // --- 5. 转发请求到 Google Gemini ---
        try {
            // 复制原始请求的 Headers (使用 Headers 对象)
            const newHeaders = new Headers(request.headers);
            // 必须移除 host 头部，否则 Google 的反向代理会拒绝服务
            newHeaders.delete("host");
            // 发起 fetch 请求转发
            const response = await fetch(GEMINI_URL, {
                method: "POST",
                headers: newHeaders,
                body: request.body, // 直接流式转发 Body 数据
                redirect: "follow",
            });
            // 构造返回响应
            // 注意：Cloudflare 要求重新构造 Response 才能修改 Headers
            const modifiedResponse = new Response(response.body, response);
            // 强制添加跨域头，确保前端或插件能正常读取返回结果
            modifiedResponse.headers.set("Access-Control-Allow-Origin", "*");

            return modifiedResponse;

        } catch (err) {
            return new Response(
                JSON.stringify({error: "Cloudflare Proxy Error", details: err.message}),
                {
                    status: 500, 
                    headers: {"Content-Type": "application/json", "Access-Control-Allow-Origin": "*"}
                },
            );
        }
    },
};