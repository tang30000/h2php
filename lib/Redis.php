<?php
namespace Lib;

/**
 * Redis — 轻量级 Redis 封装
 *
 * 基于 phpredis 扩展，提供常用数据结构操作的便捷接口。
 * 支持：字符串、哈希、列表、集合、有序集合、分布式锁、发布/订阅、自增计数器等。
 *
 * 用法：
 *   // 方式一：在控制器中通过 $this->redis 懒加载访问
 *   $this->redis->set('key', 'value', 3600);
 *
 *   // 方式二：手动创建
 *   $redis = new \Lib\Redis($config['redis']);
 *   $redis->set('key', 'value');
 *
 * config/config.php 配置：
 *   'redis' => [
 *       'host'     => '127.0.0.1',
 *       'port'     => 6379,
 *       'password' => '',        // 无密码留空
 *       'database' => 0,         // 默认实例 0
 *       'prefix'   => 'h2_',     // key 前缀
 *       'timeout'  => 2.0,       // 连接超时（秒）
 *   ],
 */
class Redis
{
    private \Redis $conn;
    private string $prefix;

    public function __construct(array $config = [])
    {
        $host     = $config['host']     ?? '127.0.0.1';
        $port     = (int)($config['port'] ?? 6379);
        $password = $config['password'] ?? '';
        $database = (int)($config['database'] ?? 0);
        $timeout  = (float)($config['timeout'] ?? 2.0);
        $this->prefix = $config['prefix'] ?? 'h2_';

        $this->conn = new \Redis();
        $this->conn->connect($host, $port, $timeout);

        if ($password !== '') {
            $this->conn->auth($password);
        }
        if ($database > 0) {
            $this->conn->select($database);
        }
    }

    // =========================================================================
    // 字符串（String）
    // =========================================================================

    /**
     * 设置值
     *
     * @param string $key
     * @param mixed  $value  自动序列化非标量值
     * @param int    $ttl    过期秒数，0=永不过期
     */
    public function set(string $key, $value, int $ttl = 0): bool
    {
        $val = is_scalar($value) ? $value : serialize($value);
        if ($ttl > 0) {
            return $this->conn->setex($this->prefix . $key, $ttl, $val);
        }
        return $this->conn->set($this->prefix . $key, $val);
    }

    /**
     * 获取值
     *
     * @return mixed 未命中返回 null
     */
    public function get(string $key)
    {
        $val = $this->conn->get($this->prefix . $key);
        if ($val === false) return null;
        $unserialized = @unserialize($val);
        return $unserialized !== false ? $unserialized : $val;
    }

    /**
     * 删除一个或多个 key
     */
    public function del(string ...$keys): int
    {
        $keys = array_map(fn($k) => $this->prefix . $k, $keys);
        return $this->conn->del($keys);
    }

    /**
     * 判断 key 是否存在
     */
    public function exists(string $key): bool
    {
        return (bool)$this->conn->exists($this->prefix . $key);
    }

    /**
     * 设置过期时间
     */
    public function expire(string $key, int $seconds): bool
    {
        return $this->conn->expire($this->prefix . $key, $seconds);
    }

    /**
     * 获取剩余 TTL（秒），-1 永不过期，-2 key 不存在
     */
    public function ttl(string $key): int
    {
        return $this->conn->ttl($this->prefix . $key);
    }

    // =========================================================================
    // 计数器（Increment / Decrement）
    // =========================================================================

    /**
     * 自增（默认步长 1）
     */
    public function incr(string $key, int $step = 1): int
    {
        return $this->conn->incrBy($this->prefix . $key, $step);
    }

    /**
     * 自减
     */
    public function decr(string $key, int $step = 1): int
    {
        return $this->conn->decrBy($this->prefix . $key, $step);
    }

    // =========================================================================
    // 哈希（Hash）
    // =========================================================================

    /**
     * 设置哈希字段
     *
     * 用法：$redis->hSet('user:1', 'name', 'Tom');
     */
    public function hSet(string $key, string $field, $value): bool
    {
        return (bool)$this->conn->hSet($this->prefix . $key, $field, is_scalar($value) ? $value : serialize($value));
    }

    /**
     * 获取哈希字段
     */
    public function hGet(string $key, string $field)
    {
        $val = $this->conn->hGet($this->prefix . $key, $field);
        return $val === false ? null : $val;
    }

    /**
     * 批量设置哈希字段
     *
     * 用法：$redis->hMSet('user:1', ['name' => 'Tom', 'age' => 25]);
     */
    public function hMSet(string $key, array $data): bool
    {
        return $this->conn->hMSet($this->prefix . $key, $data);
    }

    /**
     * 获取哈希所有字段
     */
    public function hGetAll(string $key): array
    {
        return $this->conn->hGetAll($this->prefix . $key) ?: [];
    }

    /**
     * 删除哈希字段
     */
    public function hDel(string $key, string ...$fields): int
    {
        return $this->conn->hDel($this->prefix . $key, ...$fields);
    }

    /**
     * 哈希字段是否存在
     */
    public function hExists(string $key, string $field): bool
    {
        return $this->conn->hExists($this->prefix . $key, $field);
    }

    /**
     * 哈希字段自增
     */
    public function hIncr(string $key, string $field, int $step = 1): int
    {
        return $this->conn->hIncrBy($this->prefix . $key, $field, $step);
    }

    // =========================================================================
    // 列表（List）
    // =========================================================================

    /**
     * 从左端推入
     */
    public function lPush(string $key, ...$values): int
    {
        return $this->conn->lPush($this->prefix . $key, ...$values);
    }

    /**
     * 从右端推入
     */
    public function rPush(string $key, ...$values): int
    {
        return $this->conn->rPush($this->prefix . $key, ...$values);
    }

    /**
     * 从左端弹出
     */
    public function lPop(string $key)
    {
        $val = $this->conn->lPop($this->prefix . $key);
        return $val === false ? null : $val;
    }

    /**
     * 从右端弹出
     */
    public function rPop(string $key)
    {
        $val = $this->conn->rPop($this->prefix . $key);
        return $val === false ? null : $val;
    }

    /**
     * 获取列表长度
     */
    public function lLen(string $key): int
    {
        return $this->conn->lLen($this->prefix . $key);
    }

    /**
     * 获取列表范围
     *
     * 用法：$redis->lRange('queue', 0, -1); // 全部
     */
    public function lRange(string $key, int $start = 0, int $end = -1): array
    {
        return $this->conn->lRange($this->prefix . $key, $start, $end);
    }

    // =========================================================================
    // 集合（Set）
    // =========================================================================

    /**
     * 添加成员
     */
    public function sAdd(string $key, ...$members): int
    {
        return $this->conn->sAdd($this->prefix . $key, ...$members);
    }

    /**
     * 获取全部成员
     */
    public function sMembers(string $key): array
    {
        return $this->conn->sMembers($this->prefix . $key);
    }

    /**
     * 是否是成员
     */
    public function sIsMember(string $key, $member): bool
    {
        return $this->conn->sIsMember($this->prefix . $key, $member);
    }

    /**
     * 移除成员
     */
    public function sRem(string $key, ...$members): int
    {
        return $this->conn->sRem($this->prefix . $key, ...$members);
    }

    /**
     * 集合大小
     */
    public function sCard(string $key): int
    {
        return $this->conn->sCard($this->prefix . $key);
    }

    // =========================================================================
    // 有序集合（Sorted Set）
    // =========================================================================

    /**
     * 添加成员（带分值）
     *
     * 用法：$redis->zAdd('leaderboard', 100, 'player1');
     */
    public function zAdd(string $key, float $score, $member): int
    {
        return $this->conn->zAdd($this->prefix . $key, $score, $member);
    }

    /**
     * 按分值范围获取（从低到高）
     */
    public function zRange(string $key, int $start = 0, int $end = -1, bool $withScores = false): array
    {
        return $this->conn->zRange($this->prefix . $key, $start, $end, $withScores);
    }

    /**
     * 按分值范围获取（从高到低）
     */
    public function zRevRange(string $key, int $start = 0, int $end = -1, bool $withScores = false): array
    {
        return $this->conn->zRevRange($this->prefix . $key, $start, $end, $withScores);
    }

    /**
     * 获取成员排名（从低到高，0 起始）
     */
    public function zRank(string $key, $member): ?int
    {
        $rank = $this->conn->zRank($this->prefix . $key, $member);
        return $rank === false ? null : $rank;
    }

    /**
     * 获取成员分值
     */
    public function zScore(string $key, $member): ?float
    {
        $score = $this->conn->zScore($this->prefix . $key, $member);
        return $score === false ? null : $score;
    }

    /**
     * 移除成员
     */
    public function zRem(string $key, ...$members): int
    {
        return $this->conn->zRem($this->prefix . $key, ...$members);
    }

    /**
     * 有序集合大小
     */
    public function zCard(string $key): int
    {
        return $this->conn->zCard($this->prefix . $key);
    }

    /**
     * 成员分值自增
     *
     * 用法：$redis->zIncrBy('leaderboard', 10, 'player1');
     */
    public function zIncrBy(string $key, float $increment, $member): float
    {
        return $this->conn->zIncrBy($this->prefix . $key, $increment, $member);
    }

    // =========================================================================
    // 分布式锁
    // =========================================================================

    /**
     * 获取锁
     *
     * @param string $name    锁名称
     * @param int    $ttl     自动释放时间（秒），防止死锁
     * @param string $token   锁令牌（留空自动生成），释放时需要
     * @return string|false   成功返回 token，失败返回 false
     *
     * 用法：
     *   $token = $redis->lock('order:create', 10);
     *   if ($token) {
     *       // 执行互斥操作...
     *       $redis->unlock('order:create', $token);
     *   }
     */
    public function lock(string $name, int $ttl = 10, string $token = ''): string
    {
        $token = $token ?: bin2hex(random_bytes(16));
        $key   = $this->prefix . 'lock:' . $name;
        $ok    = $this->conn->set($key, $token, ['NX', 'EX' => $ttl]);
        return $ok ? $token : false;
    }

    /**
     * 释放锁（安全：仅持有者可释放）
     */
    public function unlock(string $name, string $token): bool
    {
        $key = $this->prefix . 'lock:' . $name;
        // Lua 脚本保证原子性
        $script = <<<'LUA'
if redis.call('get', KEYS[1]) == ARGV[1] then
    return redis.call('del', KEYS[1])
else
    return 0
end
LUA;
        return (bool)$this->conn->eval($script, [$key, $token], 1);
    }

    // =========================================================================
    // 发布/订阅
    // =========================================================================

    /**
     * 发布消息
     *
     * 用法：$redis->publish('chat', json_encode($msg));
     */
    public function publish(string $channel, string $message): int
    {
        return $this->conn->publish($this->prefix . $channel, $message);
    }

    /**
     * 订阅频道（阻塞）
     *
     * 用法：
     *   $redis->subscribe(['chat'], function($redis, $channel, $msg) {
     *       echo "收到: {$msg}\n";
     *   });
     */
    public function subscribe(array $channels, callable $callback): void
    {
        $channels = array_map(fn($c) => $this->prefix . $c, $channels);
        $this->conn->subscribe($channels, $callback);
    }

    // =========================================================================
    // 管道（Pipeline）
    // =========================================================================

    /**
     * 管道批量执行（减少网络往返）
     *
     * 用法：
     *   $results = $redis->pipeline(function($pipe) {
     *       $pipe->set('a', '1');
     *       $pipe->set('b', '2');
     *       $pipe->get('a');
     *   });
     */
    public function pipeline(callable $callback): array
    {
        $pipe = $this->conn->multi(\Redis::PIPELINE);
        $callback($pipe);
        return $pipe->exec();
    }

    // =========================================================================
    // 辅助
    // =========================================================================

    /**
     * 按前缀模式查找 key
     *
     * 用法：$keys = $redis->keys('user:*');
     * 注意：生产环境大数据量慎用，推荐 SCAN
     */
    public function keys(string $pattern = '*'): array
    {
        return $this->conn->keys($this->prefix . $pattern);
    }

    /**
     * 清空当前数据库
     */
    public function flushDb(): bool
    {
        return $this->conn->flushDB();
    }

    /**
     * 获取底层 \Redis 对象（高级操作）
     */
    public function connection(): \Redis
    {
        return $this->conn;
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        $this->conn->close();
    }
}
