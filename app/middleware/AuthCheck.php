<?php
/**
 * AuthCheck 中间件 — 全局或控制器级登录检查
 *
 * 全局注册（所有请求都检查）：
 *   'middleware' => ['AuthCheck'],
 *
 * 控制器级注册（仅指定控制器检查）：
 *   protected array $middleware = ['AuthCheck'];
 *
 * 注意：此中间件在 before() 之前执行，适合做全局鉴权。
 * 如果只需要控制器级别的鉴权，建议使用 before() + skipBefore 方式。
 */
class AuthCheck
{
    public function handle(callable $next): void
    {
        // 排除不需要登录的路由
        $public = [
            'home/index/index',
            'user/login/index',
            'user/login/submit',
        ];

        // 获取当前路由路径
        $uri = $_GET['_route'] ?? ($_SERVER['QUERY_STRING'] ?? '');
        $uri = trim($uri, '/');

        if (!in_array($uri, $public) && empty($_SESSION['user'])) {
            header('Location: /user/login');
            exit;
        }

        $next();
    }
}
