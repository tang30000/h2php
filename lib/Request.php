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

    /**
     * 获取客户端 IP
     */
    public function ip(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 获取原始请求方法
     */
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
}
