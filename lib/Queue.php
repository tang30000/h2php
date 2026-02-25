<?php
namespace Lib;

/**
 * Queue — 持久化任务队列
 *
 * 支持驱动：database（默认，零额外依赖）| redis（高性能，推荐生产使用）
 *
 * config/config.php 中配置：
 *   'queue' => [
 *       'driver'      => 'database',  // database | redis
 *       'host'        => '127.0.0.1', // redis 用
 *       'port'        => 6379,
 *       'password'    => '',
 *       'key'         => 'h2_jobs',   // redis list key
 *       'max_attempts'=> 3,           // 最大重试次数
 *   ],
 *
 * Job 文件放在 app/jobs/ 目录，命名与类名一致：
 *   app/jobs/SendWelcomeEmail.php → class SendWelcomeEmail { public function handle(array $payload):void {} }
 *
 * 用法：
 *   // 控制器内入队（使用 Core 快捷方法）
 *   $this->queue('SendWelcomeEmail', ['user_id' => 5]);
 *
 *   // 或直接调用
 *   Queue::push('SendWelcomeEmail', ['user_id' => 5], $config);
 *
 *   // 延迟入队（3600 秒后执行）
 *   $this->queue('SendReminder', ['user_id' => 5], delay: 3600);
 *
 *   // Worker（后台持续运行）
 *   php h2 queue:work
 *
 *   // Cron 模式（每分钟执行一次）
 *   php h2 queue:work --once
 */
class Queue
{
    // -------------------------------------------------------------------------
    // 入队
    // -------------------------------------------------------------------------

    /**
     * 将任务推入队列
     *
     * @param string $jobName  Job 类名
     * @param array  $payload  传给 handle() 的数据
     * @param array  $config   框架配置
     * @param int    $delay    延迟秒数（0 = 立即可用）
     */
    public static function push(string $jobName, array $payload, array $config, int $delay = 0): void
    {
        $qCfg  = $config['queue'] ?? [];
        $driver = $qCfg['driver'] ?? 'database';

        if ($driver === 'redis') {
            self::redisPush($jobName, $payload, $qCfg, $delay);
        } else {
            self::dbPush($jobName, $payload, $config, $delay);
        }
    }

    // -------------------------------------------------------------------------
    // Worker 相关（由 h2 CLI 调用）
    // -------------------------------------------------------------------------

    /**
     * 处理一个待处理任务（database 驱动）
     * 返回 true=有任务处理，false=队列为空
     */
    public static function processOne(array $config): bool
    {
        $qCfg  = $config['queue'] ?? [];
        $driver = $qCfg['driver'] ?? 'database';

        if ($driver === 'redis') {
            return self::redisProcess($config, $qCfg);
        } else {
            return self::dbProcess($config, $qCfg);
        }
    }

    /**
     * 获取队列状态（仅 database 驱动）
     */
    public static function status(array $config): array
    {
        $pdo = self::pdo($config);
        self::ensureTable($pdo);

        $rows = $pdo->query(
            "SELECT status, COUNT(*) as cnt FROM `_jobs` GROUP BY status"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        return [
            'pending'    => (int)($rows['pending']    ?? 0),
            'processing' => (int)($rows['processing'] ?? 0),
            'done'       => (int)($rows['done']       ?? 0),
            'failed'     => (int)($rows['failed']     ?? 0),
        ];
    }

    /**
     * 清除指定状态的任务（默认清除 done + failed）
     */
    public static function clear(array $config, array $statuses = ['done', 'failed']): int
    {
        $pdo = self::pdo($config);
        self::ensureTable($pdo);

        $in   = implode(',', array_fill(0, count($statuses), '?'));
        $stmt = $pdo->prepare("DELETE FROM `_jobs` WHERE status IN ({$in})");
        $stmt->execute($statuses);
        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // Database 驱动
    // -------------------------------------------------------------------------

    private static function dbPush(string $jobName, array $payload, array $config, int $delay = 0): void
    {
        $pdo = self::pdo($config);
        self::ensureTable($pdo);

        $availableAt = $delay > 0 ? date('Y-m-d H:i:s', time() + $delay) : date('Y-m-d H:i:s');

        $pdo->prepare(
            "INSERT INTO `_jobs` (name, payload, max_attempts, available_at) VALUES (?, ?, ?, ?)"
        )->execute([
            $jobName,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $config['queue']['max_attempts'] ?? 3,
            $availableAt,
        ]);
    }

    private static function dbProcess(array $config, array $qCfg): bool
    {
        $pdo = self::pdo($config);
        self::ensureTable($pdo);

        // 取一条 pending 且已到期的任务并锁定
        $pdo->beginTransaction();
        $job = $pdo->query(
            "SELECT * FROM `_jobs` WHERE status='pending' AND available_at <= NOW() ORDER BY id LIMIT 1 FOR UPDATE"
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$job) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare(
            "UPDATE `_jobs` SET status='processing', attempts=attempts+1 WHERE id=?"
        )->execute([$job['id']]);
        $pdo->commit();

        try {
            self::runJob($job['name'], json_decode($job['payload'], true));
            $pdo->prepare("UPDATE `_jobs` SET status='done', ran_at=NOW() WHERE id=?")->execute([$job['id']]);
        } catch (\Throwable $e) {
            $max = (int)($job['max_attempts'] ?? $qCfg['max_attempts'] ?? 3);
            $newStatus = ($job['attempts'] + 1) >= $max ? 'failed' : 'pending';
            $pdo->prepare(
                "UPDATE `_jobs` SET status=?, error=?, ran_at=NOW() WHERE id=?"
            )->execute([$newStatus, substr($e->getMessage(), 0, 500), $job['id']]);
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Redis 驱动
    // -------------------------------------------------------------------------

    private static function redisConn(array $qCfg): \Redis
    {
        $r = new \Redis();
        $r->pconnect($qCfg['host'] ?? '127.0.0.1', (int)($qCfg['port'] ?? 6379));
        if (!empty($qCfg['password'])) {
            $r->auth($qCfg['password']);
        }
        return $r;
    }

    private static function redisPush(string $jobName, array $payload, array $qCfg, int $delay = 0): void
    {
        $r   = self::redisConn($qCfg);
        $key = $qCfg['key'] ?? 'h2_jobs';

        $item = json_encode([
            'name'         => $jobName,
            'payload'      => $payload,
            'available_at' => time() + $delay,
        ], JSON_UNESCAPED_UNICODE);

        if ($delay > 0) {
            // 延迟任务存入 sorted set，score = 可执行时间戳
            $r->zAdd("{$key}:delayed", time() + $delay, $item);
        } else {
            $r->rPush($key, $item);
        }
    }

    private static function redisProcess(array $config, array $qCfg): bool
    {
        $r   = self::redisConn($qCfg);
        $key = $qCfg['key'] ?? 'h2_jobs';

        // BRPOP 阻塞最多 2 秒等待任务
        $item = $r->bRPop([$key], 2);
        if (!$item) {
            return false;
        }

        $data = json_decode($item[1], true);
        try {
            self::runJob($data['name'], $data['payload'] ?? []);
        } catch (\Throwable $e) {
            // Redis 驱动：失败的任务推入 {key}:failed
            $r->rPush("{$key}:failed", json_encode([
                'job'     => $data,
                'error'   => $e->getMessage(),
                'failed_at' => date('Y-m-d H:i:s'),
            ]));
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // 执行 Job
    // -------------------------------------------------------------------------

    private static function runJob(string $name, array $payload): void
    {
        // 安全校验：Job 名只允许字母、数字、下划线
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \RuntimeException("非法 Job 名称：{$name}");
        }

        $file = defined('APP') ? APP . "/jobs/{$name}.php" : __DIR__ . "/../app/jobs/{$name}.php";

        if (!is_file($file)) {
            throw new \RuntimeException("Job 文件不存在：app/jobs/{$name}.php");
        }

        require_once $file;

        if (!class_exists($name)) {
            throw new \RuntimeException("Job 类不存在：{$name}");
        }

        $job = new $name();
        if (!method_exists($job, 'handle')) {
            throw new \RuntimeException("Job 缺少 handle() 方法：{$name}");
        }

        $job->handle($payload);
    }

    // -------------------------------------------------------------------------
    // 辅助
    // -------------------------------------------------------------------------

    private static ?array $pdoCache = null;
    private static ?\PDO   $pdoInstance = null;

    private static function pdo(array $config): \PDO
    {
        if (self::$pdoInstance === null) {
            $db = $config['db'];
            self::$pdoInstance = new \PDO(
                $db['dsn'], $db['user'], $db['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                 \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
            );
        }
        return self::$pdoInstance;
    }

    private static bool $tableEnsured = false;

    private static function ensureTable(\PDO $pdo): void
    {
        if (self::$tableEnsured) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS `_jobs` (
            `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name`         VARCHAR(100) NOT NULL,
            `payload`      TEXT NOT NULL,
            `status`       ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
            `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
            `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `error`        TEXT NULL,
            `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ran_at`       DATETIME NULL,
            INDEX idx_status_avail (status, available_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$tableEnsured = true;
    }
}
