<?php
namespace Lib;

/**
 * Env — .env 环境变量加载器
 *
 * 解析项目根目录的 .env 文件，将变量加载到 $_ENV 和 getenv()。
 * 用于分离敏感配置（数据库密码、API 密钥等），不提交到 Git。
 *
 * .env 文件格式：
 *   DB_HOST=localhost
 *   DB_NAME=myapp
 *   DB_USER=root
 *   DB_PASS=secret
 *   APP_KEY=your-32-character-secret-key!!
 *   APP_DEBUG=true
 *   # 这是注释
 *
 * 用法：
 *   // Bootstrap 已自动加载，直接使用
 *   Env::get('DB_HOST');             // 'localhost'
 *   Env::get('DB_PORT', 3306);       // 带默认值
 *   Env::get('APP_DEBUG');           // true（自动转布尔）
 *
 * config/config.php 中使用：
 *   'db' => [
 *       'dsn'  => 'mysql:host=' . Lib\Env::get('DB_HOST') . ';dbname=' . Lib\Env::get('DB_NAME'),
 *       'user' => Lib\Env::get('DB_USER'),
 *       'password' => Lib\Env::get('DB_PASS'),
 *   ],
 */
class Env
{
    private static bool  $loaded = false;
    private static array $vars   = [];

    /**
     * 加载 .env 文件
     */
    public static function load(string $path): void
    {
        if (!is_file($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // 跳过注释
            if ($line === '' || $line[0] === '#') continue;

            if (strpos($line, '=') === false) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // 去掉引号
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
                $value = $m[2];
            }

            self::$vars[$key] = $value;
            $_ENV[$key]       = $value;
            putenv("{$key}={$value}");
        }

        self::$loaded = true;
    }

    /**
     * 获取环境变量
     *
     * @param mixed $default 默认值
     * @return mixed 自动转换 true/false/null
     */
    public static function get(string $key, $default = null)
    {
        // 优先使用 .env 文件加载的值（不回退到 $_ENV/getenv，防止污染）
        if (!isset(self::$vars[$key])) {
            return $default;
        }

        $value = self::$vars[$key];

        // 自动类型转换
        switch (strtolower($value)) {
            case 'true':  return true;
            case 'false': return false;
            case 'null':  return null;
            case 'empty': return '';
        }

        return $value;
    }

    /**
     * 是否已加载
     */
    public static function loaded(): bool
    {
        return self::$loaded;
    }
}
