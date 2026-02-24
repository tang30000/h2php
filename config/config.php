<?php
/**
 * H2PHP 框架配置文件
 */
return [

    // 数据库配置（PDO DSN）
    'db' => [
        'dsn'      => 'mysql:host=localhost;dbname=test;charset=utf8mb4',
        'user'     => 'root',
        'password' => '',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],

    // 路由缺省值
    'default' => [
        'a' => 'home',   // 默认目录
        'b' => 'index',  // 默认文件
        'c' => 'index',  // 默认方法
    ],

    // 调试模式（true 时显示详细错误）
    'debug' => true,

    // 缓存配置（可选）
    // driver: file | redis | memcache | memcached
    // 不配置或 driver=file 时使用文件缓存，无需额外扩展
    'cache' => [
        'driver'   => 'file',          // 切换为 'redis' 或 'memcache'
        'host'     => '127.0.0.1',
        'port'     => 6379,            // Redis 默认 6379，Memcache 默认 11211
        'prefix'   => 'h2_',           // key 前缀，防止多项目冲突
        'password' => '',              // Redis 密码（无密码留空或删除此行）
        // 'dir'   => '/tmp/h2cache',  // file 驱动缓存目录（默认 ROOT/cache）
    ],

    // 队列配置（可选）
    // driver: database（默认，零依赖）| redis（高性能，推荐生产）
    // Job 文件放在 app/jobs/ 目录
    'queue' => [
        'driver'       => 'database',  // 切换为 'redis' 即可
        'host'         => '127.0.0.1',
        'port'         => 6379,
        'password'     => '',
        'key'          => 'h2_jobs',   // redis list key
        'max_attempts' => 3,           // 失败后最多重试次数
    ],

];

