<?php
namespace Lib;

/**
 * RateLimiter — 接口限流器
 *
 * 防止接口被恶意刷请求，支持两种后端：Redis（推荐）、文件。
 *
 * 用法（控制器中）：
 *   $limiter = new RateLimiter($this->config);
 *
 *   // 每个 IP 每分钟最多 60 次
 *   if ($limiter->tooMany('api:' . $this->request->ip(), 60, 60)) {
 *       $this->json(['error' => '请求过于频繁'], 429);
 *       return;
 *   }
 *
 *   // 登录失败限制：每个用户名每小时最多 5 次
 *   if ($limiter->tooMany('login:' . $username, 5, 3600)) {
 *       $this->flash('error', '登录失败次数过多，请稍后再试');
 *       $this->redirect('/user/login');
 *       return;
 *   }
 */
class RateLimiter
{
    private ?Redis $redis = null;
    private ?string $fileDir = null;

    public function __construct(array $config = [])
    {
        if (!empty($config['redis'])) {
            $this->redis = new Redis($config['redis']);
        } else {
            $this->fileDir = rtrim($config['cache']['dir'] ?? (defined('ROOT') ? ROOT . '/cache' : sys_get_temp_dir()), '/\\')
                           . '/ratelimit';
            if (!is_dir($this->fileDir)) {
                mkdir($this->fileDir, 0755, true);
            }
        }
    }

    /**
     * 检查是否超过限制（同时自动计数 +1）
     *
     * @param string $key       限流标识（如 'api:127.0.0.1' 或 'login:admin'）
     * @param int    $maxAttempts 最大次数
     * @param int    $windowSec   时间窗口（秒）
     * @return bool  true = 已超限，应拒绝
     */
    public function tooMany(string $key, int $maxAttempts, int $windowSec = 60): bool
    {
        $current = $this->hit($key, $windowSec);
        return $current > $maxAttempts;
    }

    /**
     * 记录一次请求并返回当前窗口内的总次数
     */
    public function hit(string $key, int $windowSec = 60): int
    {
        if ($this->redis) {
            return $this->hitRedis($key, $windowSec);
        }
        return $this->hitFile($key, $windowSec);
    }

    /**
     * 获取剩余次数
     */
    public function remaining(string $key, int $maxAttempts, int $windowSec = 60): int
    {
        $current = $this->getCurrent($key, $windowSec);
        return max(0, $maxAttempts - $current);
    }

    /**
     * 重置计数（如登录成功后清除失败计数）
     */
    public function reset(string $key): void
    {
        if ($this->redis) {
            $this->redis->del('rl:' . $key);
        } else {
            $file = $this->fileDir . '/' . md5($key) . '.json';
            if (is_file($file)) unlink($file);
        }
    }

    // ── Redis 实现（滑动窗口） ───────────────────────────────────────────────

    private function hitRedis(string $key, int $windowSec): int
    {
        $rKey = 'rl:' . $key;
        $now  = microtime(true);

        $pipe = $this->redis->connection()->multi(\Redis::PIPELINE);
        // 移除窗口外的记录
        $pipe->zRemRangeByScore($rKey, 0, $now - $windowSec);
        // 添加当前请求
        $pipe->zAdd($rKey, $now, $now . ':' . mt_rand());
        // 统计窗口内数量
        $pipe->zCard($rKey);
        // 设置整个 key 过期
        $pipe->expire($rKey, $windowSec);
        $results = $pipe->exec();

        return (int)$results[2];
    }

    // ── 文件实现（简单计数器） ───────────────────────────────────────────────

    private function hitFile(string $key, int $windowSec): int
    {
        $file = $this->fileDir . '/' . md5($key) . '.json';
        $data = $this->loadFile($file);

        // 窗口过期，重置
        if (!$data || $data['expires'] < time()) {
            $data = ['count' => 0, 'expires' => time() + $windowSec];
        }

        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return $data['count'];
    }

    private function getCurrent(string $key, int $windowSec): int
    {
        if ($this->redis) {
            $rKey = 'rl:' . $key;
            $now  = microtime(true);
            $this->redis->connection()->zRemRangeByScore($rKey, 0, $now - $windowSec);
            return $this->redis->connection()->zCard($rKey);
        }
        $file = $this->fileDir . '/' . md5($key) . '.json';
        $data = $this->loadFile($file);
        if (!$data || $data['expires'] < time()) return 0;
        return $data['count'];
    }

    private function loadFile(string $file): ?array
    {
        if (!is_file($file)) return null;
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }
}
