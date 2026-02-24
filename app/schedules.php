<?php
/**
 * H2PHP 任务调度配置
 *
 * 系统 cron 中只需添加一条（每分钟执行一次）：
 *   * * * * * php /path/to/h2 schedule:run
 *
 * Task 文件放在 app/tasks/ 目录，类名与文件名一致，实现 handle(): void
 */
return function(\Lib\Scheduler $s) {

    // 每天凌晨 2 点清理过期 Token
    // $s->call('CleanExpiredTokens')->daily()->description('清理过期 Token');

    // 每天早上 8 点发送日报
    // $s->call('SendDailyReport')->dailyAt('08:00')->description('发送日报邮件');

    // 每 15 分钟同步库存
    // $s->call('SyncInventory')->everyMinutes(15)->description('同步库存数据');

    // 每周一凌晨清理已完成的队列记录
    // $s->command('queue:clear')->weekly()->description('清理队列历史记录');

    // 自定义 cron 表达式（每周日凌晨 3 点备份数据库）
    // $s->call('BackupDatabase')->cron('0 3 * * 0')->description('数据库备份');

    // 闭包任务（适合简单逻辑）
    // $s->job(function() {
    //     // 清理临时文件
    //     foreach (glob(ROOT . '/cache/*.cache') as $f) {
    //         if (filemtime($f) < time() - 86400) unlink($f);
    //     }
    // }, 'CleanOldCache')->daily()->description('清理过期文件缓存');

};
