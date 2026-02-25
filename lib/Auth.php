<?php
namespace Lib;

/**
 * Auth — 鉴权与密码工具
 *
 * 提供密码哈希、Session 登录管理、JWT Token 生成/验证。
 *
 * 用法（控制器快捷方式）：
 *   // 密码
 *   $hash = Auth::hashPassword('123456');
 *   Auth::verifyPassword('123456', $hash);  // true
 *
 *   // Session 登录
 *   Auth::login(['id' => 1, 'username' => 'admin']);
 *   Auth::check();    // true
 *   Auth::user();     // ['id' => 1, ...]
 *   Auth::logout();
 *
 *   // JWT
 *   $token = Auth::jwtEncode(['user_id' => 1], 'secret', 7200);
 *   $data  = Auth::jwtDecode($token, 'secret');
 */
class Auth
{
    // =========================================================================
    // 密码哈希
    // =========================================================================

    /**
     * 生成密码哈希（bcrypt）
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * 验证密码
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 哈希是否需要重新生成（算法升级时）
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT);
    }

    // =========================================================================
    // Session 登录管理
    // =========================================================================

    /**
     * 登录：将用户数据存入 Session
     */
    public static function login(array $user, string $key = 'user'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION[$key] = $user;
        // 防止 Session 固定攻击
        session_regenerate_id(true);
    }

    /**
     * 登出
     */
    public static function logout(string $key = 'user'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        unset($_SESSION[$key]);
        // 安全：轮换 Session ID，防止旧 ID 被重用
        session_regenerate_id(true);
    }

    /**
     * 是否已登录
     */
    public static function check(string $key = 'user'): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return !empty($_SESSION[$key]);
    }

    /**
     * 获取当前登录用户
     */
    public static function user(string $key = 'user'): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return $_SESSION[$key] ?? null;
    }

    /**
     * 获取当前登录用户 ID
     */
    public static function id(string $key = 'user'): ?int
    {
        $user = self::user($key);
        return $user['id'] ?? null;
    }

    // =========================================================================
    // JWT Token（无依赖实现）
    // =========================================================================

    /**
     * 生成 JWT Token
     *
     * @param array  $payload 自定义数据
     * @param string $secret  签名密钥
     * @param int    $ttl     有效期（秒），0=永不过期
     */
    public static function jwtEncode(array $payload, string $secret, int $ttl = 3600): string
    {
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));

        if ($ttl > 0) {
            $payload['exp'] = time() + $ttl;
        }
        $payload['iat'] = time();
        $body = self::base64UrlEncode(json_encode($payload));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );

        return "{$header}.{$body}.{$signature}";
    }

    /**
     * 验证并解码 JWT Token
     *
     * @return array|null 解码后的 payload，失败返回 null
     */
    public static function jwtDecode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $signature] = $parts;

        // 安全：校验 alg 头，防止 alg:none 攻击
        $headerData = json_decode(self::base64UrlDecode($header), true);
        if (!is_array($headerData) || ($headerData['alg'] ?? '') !== 'HS256') {
            return null;
        }

        // 验证签名
        $expected = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );
        if (!hash_equals($expected, $signature)) return null;

        $payload = json_decode(self::base64UrlDecode($body), true);
        if (!is_array($payload)) return null;

        // 检查过期
        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
