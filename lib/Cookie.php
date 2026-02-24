<?php
namespace Lib;

/**
 * Cookie — 安全 Cookie 封装
 *
 * 统一管理 HttpOnly、Secure、SameSite 等安全属性，
 * 可选 AES 加密存储（需配置 app_key）。
 *
 * 用法：
 *   $cookie = new Cookie(['app_key' => '...', 'secure' => true]);
 *
 *   $cookie->set('theme', 'dark', 86400);           // 1天
 *   $cookie->get('theme');                            // 'dark'
 *   $cookie->delete('theme');
 *
 *   $cookie->setEncrypted('token', $sensitive, 3600); // 加密存储
 *   $cookie->getEncrypted('token');                   // 自动解密
 */
class Cookie
{
    private string $path     = '/';
    private string $domain   = '';
    private bool   $secure   = false;
    private bool   $httpOnly = true;
    private string $sameSite = 'Lax';
    private ?string $appKey  = null;

    public function __construct(array $config = [])
    {
        $this->path     = $config['cookie_path']     ?? '/';
        $this->domain   = $config['cookie_domain']   ?? '';
        $this->secure   = $config['cookie_secure']   ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $this->httpOnly = $config['cookie_httponly']  ?? true;
        $this->sameSite = $config['cookie_samesite']  ?? 'Lax';
        $this->appKey   = $config['app_key']          ?? null;
    }

    /**
     * 设置 Cookie
     *
     * @param int $ttl 过期秒数（0=浏览器关闭时清除）
     */
    public function set(string $name, string $value, int $ttl = 0): void
    {
        $expires = $ttl > 0 ? time() + $ttl : 0;
        setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => $this->path,
            'domain'   => $this->domain,
            'secure'   => $this->secure,
            'httponly'  => $this->httpOnly,
            'samesite' => $this->sameSite,
        ]);
        $_COOKIE[$name] = $value;
    }

    /**
     * 获取 Cookie
     */
    public function get(string $name, ?string $default = null): ?string
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * 删除 Cookie
     */
    public function delete(string $name): void
    {
        $this->set($name, '', -86400);
        unset($_COOKIE[$name]);
    }

    /**
     * 是否存在
     */
    public function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * 加密后存储
     */
    public function setEncrypted(string $name, string $value, int $ttl = 0): void
    {
        if (!$this->appKey) {
            throw new \RuntimeException('Cookie 加密需要配置 app_key');
        }
        $enc = new Encryption($this->appKey);
        $this->set($name, $enc->encrypt($value), $ttl);
    }

    /**
     * 读取并解密
     */
    public function getEncrypted(string $name): ?string
    {
        $val = $this->get($name);
        if ($val === null || !$this->appKey) return null;
        $enc = new Encryption($this->appKey);
        return $enc->decrypt($val);
    }
}
