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

    // 子目录部署时的路径前缀（如 '/h2php'）
    // 根目录部署留空 ''，子目录填写 '/your-subdir'（注意开头有斜线，结尾无斜线）
    'base_path' => '',

    // 应用密钥 — 用于 Encryption 加解密和 Cookie 加密存储
    // 必须 32 字节，可用 Str::random(32) 生成
    'app_key' => 'CHANGE_ME_TO_A_RANDOM_32_CHARS!!',

    // Redis 配置（可选）— 用于 Redis 封装和 RateLimiter
    'redis' => [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'password' => '',
        'database' => 0,
        'prefix'   => 'h2_',
        'timeout'  => 2.0,
    ],

    // 调试模式（生产环境务必设为 false！可在 config.local.php 中覆盖为 true）
    'debug' => false,

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

    // 全局中间件（按顺序执行，文件放在 app/middleware/）
    // 无需中间件时留空数组或删除此项，对性能零影响
    'middleware' => [
        // 'Cors',       // 跨域支持
        // 'AuthCheck',  // 全局登录检查
    ],

    // 邮件 SMTP 配置（可选）
    // 支持 QQ邮箱 / Gmail / 阿里企业邮 等标准 SMTP
    'mail' => [
        'host'     => 'smtp.qq.com',
        'port'     => 465,
        'user'     => '',             // SMTP 账号
        'password' => '',             // SMTP 授权码（非登录密码）
        'from'     => '',             // 发件人地址（默认同 user）
        'name'     => 'H2PHP App',    // 发件人显示名
        'ssl'      => true,
    ],

];

