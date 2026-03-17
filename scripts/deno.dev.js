// 在 Deno Deploy 的 Settings -> Environment Variables 中设置这下面的值
// 变量名: TOKEN，值：你自己定， 说明：必须，这个变量用于验证用户是否有权使用该接口
// 变量名：GEMINI_URL，值：GEMINI的OPENAI请求接口，说明：可选，不设置就是默认的官方接口

// 你自己的AUTH_TOKEN，防止泄露后被滥用
const TOKEN = Deno.env.get("TOKEN") || "";

// GEMINI的OPENAI请求接口
const GEMINI_URL = Deno.env.get("GEMINI_URL") || "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions";

Deno.serve(async (req: Request) => {
    // 获取请求的 URL
    const url = new URL(req.url);
    // 获取请求的 URL 路径
    const path = url.pathname;
    // 获取请求的 TOKEN
    const token = url.searchParams.get("token");

    // --- 1. 处理 OPTIONS 预检请求 (CORS) ---
    if (req.method === "OPTIONS") {
        return new Response(null, {
            status: 204,
            headers: {
                "Access-Control-Allow-Origin": "*",
                "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
                "Access-Control-Allow-Headers": "Content-Type, Authorization",
            },
        });
    } 

    // --- 2. 根路径或非 API 路径：显示状态信息 ---
    if (path === "/" || !path.includes("/v1/chat/completions")) {
        return new Response(
            JSON.stringify({
                status: "healthy",
                message: "Gemini API 代理服务正在运行",
                timestamp: new Date().toISOString(),
                note: "API调用需要token参数",
                usage: {
                    format: "?token=YOUR_TOKEN",
                    example: `https://${url.hostname}/v1/chat/completions?token=${TOKEN}`,
                },
            }),
            {
                status: 200, 
                headers: { "Content-Type": "application/json; charset=utf-8" }
            }
        );
    }

    // --- 3. 校验自定义 Token ---
    if (token !== TOKEN && token != "") {
        return new Response(JSON.stringify({ error: "Token不正确" }), {
            status: 401,
            headers: { "Content-Type": "application/json" },
        });
    }

    // --- 4. 转发请求到 Google Gemini ---
  
    // 复制原始请求的 Headers
    const headers = new Headers(req.headers);
    
    // 移除 Host 以防 Google 拒接
    headers.delete("host");

    try {
        const response = await fetch(GEMINI_URL, {
            method: "POST",
            headers: headers,
            body: req.body, // 直接流式转发 body
            redirect: "follow",
        });

        // 构造返回给客户端的 Response
        const newResponse = new Response(response.body, response);
        // 允许跨域
        newResponse.headers.set("Access-Control-Allow-Origin", "*");
        // 返回响应
        return newResponse;
    } catch (err) {
        return new Response(
            JSON.stringify({ error: "Proxy Error", details: err.message }), 
            {
                status: 500,
                headers: { "Content-Type": "application/json" }
            }
        );
    }
});