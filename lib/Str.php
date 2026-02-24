<?php
namespace Lib;

/**
 * Str — 字符串工具函数集
 *
 * 用法：
 *   Str::slug('Hello World');          // 'hello-world'
 *   Str::random(32);                   // 'a1b2c3...'（安全随机）
 *   Str::contains('hello world', 'lo'); // true
 *   Str::limit('很长的文本...', 50);    // 截断+省略号
 *   Str::camel('user_name');           // 'userName'
 *   Str::snake('userName');            // 'user_name'
 */
class Str
{
    /**
     * 生成 URL 友好的 slug
     *
     * 用法：Str::slug('Hello World!') → 'hello-world'
     *       Str::slug('第一篇文章')   → '第一篇文章'（中文保留）
     */
    public static function slug(string $title, string $separator = '-'): string
    {
        $title = mb_strtolower(trim($title));
        // 替换非字母数字和非中文为分隔符
        $title = preg_replace('/[^\p{L}\p{N}]+/u', $separator, $title);
        return trim($title, $separator);
    }

    /**
     * 生成安全随机字符串
     *
     * @param int    $length 长度
     * @param string $type   hex|alpha|alnum
     */
    public static function random(int $length = 32, string $type = 'alnum'): string
    {
        if ($type === 'hex') {
            return substr(bin2hex(random_bytes((int)ceil($length / 2))), 0, $length);
        }
        $chars = $type === 'alpha'
            ? 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
            : 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }

    /**
     * UUID v4
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 是否包含子串
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) !== false;
    }

    /**
     * 是否以某串开头
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }

    /**
     * 是否以某串结尾
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * 截断字符串（支持多字节）
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) return $value;
        return mb_substr($value, 0, $limit) . $end;
    }

    /**
     * 驼峰命名 user_name → userName
     */
    public static function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value))));
    }

    /**
     * 大驼峰 user_name → UserName
     */
    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    /**
     * 蛇形命名 userName → user_name
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $value = preg_replace('/([A-Z])/', $delimiter . '$1', $value);
        return mb_strtolower(ltrim($value, $delimiter));
    }

    /**
     * 短横线命名 userName → user-name
     */
    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    /**
     * 遮罩敏感信息
     *
     * Str::mask('13812345678', 3, 4) → '138****5678'
     * Str::mask('admin@qq.com', 2, 4) → 'ad****qq.com'
     */
    public static function mask(string $value, int $start, int $length, string $char = '*'): string
    {
        $masked = mb_substr($value, 0, $start)
                . str_repeat($char, $length)
                . mb_substr($value, $start + $length);
        return $masked;
    }

    /**
     * 判断是否是有效 Email
     */
    public static function isEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 判断是否是有效 URL
     */
    public static function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 判断是否是有效 JSON
     */
    public static function isJson(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 提取数字
     */
    public static function digits(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * 单词数
     */
    public static function wordCount(string $value): int
    {
        // 支持中文
        return preg_match_all('/[\p{L}\p{N}]+/u', $value);
    }
}
