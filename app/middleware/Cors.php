<?php
/**
 * CORS 中间件 — 跨域资源共享
 *
 * 在 config.php 的 middleware 数组中注册：
 *   'middleware' => ['Cors'],
 *
 * 如需自定义允许的域名，修改下方 $allowOrigin 变量。
 */
class Cors
{
    public function handle(callable $next): void
    {
        $allowOrigin  = '*';        // 改为具体域名更安全，如 'https://example.com'
        $allowMethods = 'GET, POST, PUT, DELETE, OPTIONS';
        $allowHeaders = 'Content-Type, Authorization, X-Requested-With';

        header("Access-Control-Allow-Origin: {$allowOrigin}");
        header("Access-Control-Allow-Methods: {$allowMethods}");
        header("Access-Control-Allow-Headers: {$allowHeaders}");

        // OPTIONS 预检请求直接返回，不进入控制器
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        $next();
    }
}
