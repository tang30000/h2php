<?php
/**
 * CORS 跨域中间件
 *
 * 放在 app/middleware/Cors.php 即可。
 * 在 config.php 的 middleware 数组中启用：'middleware' => ['Cors'],
 *
 * 配置（可选，在 config.php 中添加 'cors' 节点）：
 *   'cors' => [
 *       'origin'      => '*',                    // 允许的域名，'*' 或 'https://example.com'
 *       'methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
 *       'headers'     => 'Content-Type, Authorization, X-Requested-With',
 *       'credentials' => false,                  // 是否允许携带 Cookie
 *       'max_age'     => 86400,                  // 预检缓存时间（秒）
 *   ],
 */
class Cors
{
    public function handle(): void
    {
        $config = $this->config['cors'] ?? [];

        $origin      = $config['origin']      ?? '*';
        $methods     = $config['methods']     ?? 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
        $headers     = $config['headers']     ?? 'Content-Type, Authorization, X-Requested-With';
        $credentials = $config['credentials'] ?? false;
        $maxAge      = $config['max_age']     ?? 86400;

        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Methods: {$methods}");
        header("Access-Control-Allow-Headers: {$headers}");
        header("Access-Control-Max-Age: {$maxAge}");

        if ($credentials) {
            header('Access-Control-Allow-Credentials: true');
        }

        // OPTIONS 预检请求直接返回
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
