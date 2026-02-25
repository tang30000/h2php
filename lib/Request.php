<?php
namespace Lib;

/**
 * Request — 请求封装
 * 统一获取 GET / POST / 合并参数，避免直接操作超全局变量
 */
class Request
{
    /**
     * 获取 GET 参数
     */
    public function get(string $key, $default = null)
    {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    /**
     * 获取 POST 参数
     */
    public function post(string $key, $default = null)
    {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    /**
     * 获取 GET 或 POST 参数（POST 优先）
     */
    public function input(string $key, $default = null)
    {
        if (isset($_POST[$key])) return $_POST[$key];
        if (isset($_GET[$key]))  return $_GET[$key];
        return $default;
    }

    /**
     * 获取完整 GET 数组
     */
    public function getAll(): array
    {
        return $_GET;
    }

    /**
     * 获取完整 POST 数组
     */
    public function postAll(): array
    {
        return $_POST;
    }

    /**
     * 是否是 POST 请求
     */
    public function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * 是否是 AJAX 请求
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /** @var array 受信任的代理 IP（只有这些代理发来的 X-Forwarded-For 才可信） */
    private static array $trustedProxies = [];

    /** 设置受信任的代理 IP（如 ['127.0.0.1', '10.0.0.0/8']） */
    public static function setTrustedProxies(array $ips): void
    {
        self::$trustedProxies = $ips;
    }

    /**
     * 获取客户端 IP
     *
     * 默认只信任 REMOTE_ADDR（安全）。
     * 在反代（Nginx/CDN）后面时，需先调用 setTrustedProxies() 配置信任的代理 IP，
     * 才会读取 X-Forwarded-For / X-Real-IP 头。
     */
    public function ip(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 仅当 REMOTE_ADDR 在信任列表中，才读取代理头
        if (!empty(self::$trustedProxies) && self::isProxyTrusted($remote)) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            }
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                return $_SERVER['HTTP_X_REAL_IP'];
            }
        }

        return $remote;
    }

    private static function isProxyTrusted(string $ip): bool
    {
        foreach (self::$trustedProxies as $trusted) {
            if ($trusted === $ip) return true;
        }
        return false;
    }

    /**
     * 获取原始请求方法
     */
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
}
