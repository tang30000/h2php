<?php
namespace Lib;

/**
 * Event — 请求内事件总线（发布/订阅）
 *
 * 事件在当前请求内有效，不跨请求持久化。
 * 需要跨请求的异步处理，请使用 Queue。
 *
 * 用法：
 *   // 监听（通常在 index.php 或控制器 before() 里注册）
 *   Event::on('user.registered', function($user) {
 *       // 发送欢迎邮件、记录日志等
 *   });
 *
 *   // 触发（控制器里）
 *   Event::fire('user.registered', $user);
 *
 *   // 或直接用 Core 快捷方法（在控制器内）：
 *   $this->on('user.registered', fn($u) => ...);
 *   $this->fire('user.registered', $user);
 */
class Event
{
    /** @var array<string, callable[]> */
    private static array $listeners = [];

    /**
     * 注册事件监听器
     *
     * @param string   $event    事件名，建议用点号分隔，如 'user.registered'
     * @param callable $listener 回调，接收 fire() 传入的 $payload
     */
    public static function on(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    /**
     * 触发事件，依次调用所有监听器
     *
     * @param string $event   事件名
     * @param mixed  $payload 传给监听器的数据（任意类型）
     */
    public static function fire(string $event, $payload = null): void
    {
        foreach (self::$listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }

    /**
     * 移除指定事件的所有监听器
     */
    public static function forget(string $event): void
    {
        unset(self::$listeners[$event]);
    }

    /**
     * 清空所有监听器
     */
    public static function flushAll(): void
    {
        self::$listeners = [];
    }
}
