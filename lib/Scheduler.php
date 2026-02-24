<?php
namespace Lib;

/**
 * Scheduler — 任务调度器（类似 Laravel Schedule）
 *
 * 使用方式：
 * 1. 在 app/schedules.php 中定义任务
 * 2. 系统 cron 中添加一条（每分钟执行一次）：
 *    * * * * * php /path/to/h2 schedule:run
 *
 * Task 文件放在 app/tasks/ 目录，类名与文件名一致，实现 handle(): void
 */
class Scheduler
{
    /** @var ScheduledTask[] */
    private array $tasks = [];

    // -------------------------------------------------------------------------
    // 注册方式
    // -------------------------------------------------------------------------

    /**
     * 注册一个 Task 类（app/tasks/{Name}.php）
     *
     * @param string $taskName Task 类名
     */
    public function call(string $taskName): ScheduledTask
    {
        $task = new ScheduledTask('task', $taskName);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * 注册一个 CLI 命令（透传给 php h2）
     *
     * @param string $command 例如 'queue:clear'
     */
    public function command(string $command): ScheduledTask
    {
        $task = new ScheduledTask('command', $command);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * 注册一个 PHP 闭包任务
     */
    public function job(callable $fn, string $name = 'closure'): ScheduledTask
    {
        $task = new ScheduledTask('closure', $name, $fn);
        $this->tasks[] = $task;
        return $task;
    }

    // -------------------------------------------------------------------------
    // 执行
    // -------------------------------------------------------------------------

    /**
     * 运行所有到期的任务（由 php h2 schedule:run 调用）
     */
    public function runDue(): void
    {
        $now = new \DateTimeImmutable();
        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                $this->runTask($task);
            }
        }
    }

    /**
     * 列出所有任务及其计划（由 php h2 schedule:list 调用）
     */
    public function listAll(): array
    {
        return $this->tasks;
    }

    private function runTask(ScheduledTask $task): void
    {
        try {
            switch ($task->type) {
                case 'task':
                    $file = defined('APP') ? APP . "/tasks/{$task->name}.php" : __DIR__ . "/../app/tasks/{$task->name}.php";
                    if (!is_file($file)) {
                        throw new \RuntimeException("Task 文件不存在：app/tasks/{$task->name}.php");
                    }
                    require_once $file;
                    if (!class_exists($task->name)) {
                        throw new \RuntimeException("Task 类不存在：{$task->name}");
                    }
                    (new $task->name())->handle();
                    break;

                case 'command':
                    $h2 = defined('ROOT') ? ROOT . '/h2' : __DIR__ . '/../h2';
                    passthru("php " . escapeshellarg($h2) . ' ' . escapeshellarg($task->name));
                    break;

                case 'closure':
                    ($task->closure)();
                    break;
            }
            echo "\033[32m✓ {$task->name}\033[0m\n";
        } catch (\Throwable $e) {
            echo "\033[31m✗ {$task->name}: {$e->getMessage()}\033[0m\n";
        }
    }
}

// =============================================================================
// ScheduledTask — 单个任务的配置（链式方法设置频率）
// =============================================================================

class ScheduledTask
{
    public string   $type;
    public string   $name;
    public ?string  $cronExpression;
    public          $closure;
    public string   $description = '';

    public function __construct(string $type, string $name, ?callable $closure = null)
    {
        $this->type    = $type;
        $this->name    = $name;
        $this->closure = $closure;
        // 默认每分钟（最高频率，is_due 由实际 expression 控制）
        $this->cronExpression = null;
    }

    // ── 频率设置 ──────────────────────────────────────────────────────────────

    /** 自定义 cron 表达式，如 '0 2 * * 0'（每周日凌晨 2 点） */
    public function cron(string $expression): self { $this->cronExpression = $expression; return $this; }

    /** 每分钟 */
    public function everyMinute(): self  { return $this->cron('* * * * *'); }

    /** 每 N 分钟 */
    public function everyMinutes(int $n): self { return $this->cron("*/{$n} * * * *"); }

    /** 每小时整点 */
    public function hourly(): self       { return $this->cron('0 * * * *'); }

    /** 每小时 N 分 */
    public function hourlyAt(int $min): self { return $this->cron("{$min} * * * *"); }

    /** 每天凌晨 0 点 */
    public function daily(): self        { return $this->cron('0 0 * * *'); }

    /** 每天指定时间，格式 'HH:MM' */
    public function dailyAt(string $time): self
    {
        [$h, $m] = explode(':', $time);
        return $this->cron(ltrim($m, '0') ?: '0') ->cron("{$m} {$h} * * *");
    }

    /** 每周一凌晨 0 点 */
    public function weekly(): self       { return $this->cron('0 0 * * 1'); }

    /** 每月 1 日凌晨 0 点 */
    public function monthly(): self      { return $this->cron('0 0 1 * *'); }

    // ── 判断是否到期 ──────────────────────────────────────────────────────────

    public function isDue(\DateTimeImmutable $now): bool
    {
        if (!$this->cronExpression) return false;

        [$min, $hour, $day, $month, $weekday] = explode(' ', $this->cronExpression);

        return self::matchField($now->format('i'), $min)
            && self::matchField($now->format('G'), $hour)
            && self::matchField($now->format('j'), $day)
            && self::matchField($now->format('n'), $month)
            && self::matchField($now->format('w'), $weekday);
    }

    private static function matchField(string $value, string $pattern): bool
    {
        if ($pattern === '*') return true;
        if (str_starts_with($pattern, '*/')) {
            $step = (int)substr($pattern, 2);
            return $step > 0 && ((int)$value % $step) === 0;
        }
        return (int)$value === (int)$pattern;
    }

    public function description(string $desc): self { $this->description = $desc; return $this; }

    public function getExpression(): string { return $this->cronExpression ?? '-'; }
}
