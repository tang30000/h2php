<?php
namespace Lib;

/**
 * Logger — 简易日志系统
 *
 * 支持分级日志（info / warning / error / debug），按日期自动分文件。
 *
 * 用法（控制器中）：
 *   $this->log('info', '用户登录成功', ['user_id' => 5]);
 *   $this->log('error', '支付失败', ['order_id' => 123, 'reason' => $e->getMessage()]);
 *
 * 直接静态调用：
 *   Logger::write('warning', '库存不足', ['sku' => 'A001']);
 *
 * 日志文件位置：ROOT/logs/2026-02-25.log
 */
class Logger
{
    /** @var string 日志目录 */
    private static string $dir = '';

    /** @var bool 目录是否已确认存在 */
    private static bool $dirReady = false;

    /**
     * 写入日志
     *
     * @param string $level   日志级别（info / warning / error / debug）
     * @param string $message 日志消息
     * @param array  $context 附加数据（可选）
     */
    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = self::$dir ?: (defined('ROOT') ? ROOT . '/logs' : __DIR__ . '/../logs');

        if (!self::$dirReady) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            self::$dirReady = true;
        }

        $file = $dir . '/' . date('Y-m-d') . '.log';
        $time = date('Y-m-d H:i:s');
        $level = strtoupper($level);

        $line = "[{$time}] [{$level}] {$message}";
        if ($context) {
            $line .= ' ' . json_encode(self::sanitize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= PHP_EOL;

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** 过滤敏感字段，防止密码/token 等明文写入日志 */
    private static function sanitize(array $data): array
    {
        static $sensitive = ['password', 'passwd', 'pwd', 'token', 'secret', 'app_key', 'api_key', 'authorization'];
        foreach ($data as $key => &$value) {
            if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                $value = '***';
            } elseif (is_array($value)) {
                $value = self::sanitize($value);
            }
        }
        return $data;
    }

    /** @param string $dir 自定义日志目录 */
    public static function setDir(string $dir): void
    {
        self::$dir = $dir;
    }

    // ── 快捷方法 ─────────────────────────────────────────────────

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }
}
