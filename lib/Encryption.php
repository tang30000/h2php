<?php
namespace Lib;

/**
 * Encryption — 数据加解密（AES-256-CBC）
 *
 * 用于加密敏感数据（用户信息、API 密钥等）。
 *
 * config/config.php:
 *   'app_key' => 'your-32-character-secret-key!!'   // 必须 32 字节
 *
 * 用法：
 *   $enc = new Encryption($config['app_key']);
 *   $cipher = $enc->encrypt('sensitive data');   // Base64 密文
 *   $plain  = $enc->decrypt($cipher);           // 原文
 *
 *   // 静态快捷方式（需先 Encryption::setKey()）
 *   Encryption::setKey($config['app_key']);
 *   $cipher = Encryption::enc('data');
 *   $plain  = Encryption::dec($cipher);
 */
class Encryption
{
    private string $key;
    private string $cipher = 'aes-256-cbc';

    private static ?string $globalKey = null;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * 加密
     *
     * @return string Base64 编码的密文（格式：IV.密文）
     */
    public function encrypt(string $plaintext): string
    {
        $iv   = random_bytes(openssl_cipher_iv_length($this->cipher));
        $data = openssl_encrypt($plaintext, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        $mac  = hash_hmac('sha256', $iv . $data, $this->key, true);
        return base64_encode($iv . $mac . $data);
    }

    /**
     * 解密
     *
     * @return string|null 解密失败返回 null
     */
    public function decrypt(string $ciphertext): ?string
    {
        $raw = base64_decode($ciphertext, true);
        if ($raw === false) return null;

        $ivLen = openssl_cipher_iv_length($this->cipher);
        if (strlen($raw) < $ivLen + 32) return null;

        $iv   = substr($raw, 0, $ivLen);
        $mac  = substr($raw, $ivLen, 32);
        $data = substr($raw, $ivLen + 32);

        // 验证 HMAC 防止篡改
        $expected = hash_hmac('sha256', $iv . $data, $this->key, true);
        if (!hash_equals($expected, $mac)) return null;

        $result = openssl_decrypt($data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        return $result === false ? null : $result;
    }

    // ── 静态快捷方式 ─────────────────────────────────────────────────────────

    public static function setKey(string $key): void
    {
        self::$globalKey = $key;
    }

    public static function enc(string $plaintext): string
    {
        return (new self(self::$globalKey))->encrypt($plaintext);
    }

    public static function dec(string $ciphertext): ?string
    {
        return (new self(self::$globalKey))->decrypt($ciphertext);
    }
}
