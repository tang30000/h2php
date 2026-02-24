<?php
/**
 * 本地开发环境配置覆盖
 *
 * 此文件不提交到 Git（已加入 .gitignore）。
 * 返回的数组会与 config.php 深度合并，可覆盖任意配置项。
 *
 * 使用方式：
 *   cp config/config.local.example.php config/config.local.php
 *   然后修改下方的配置值
 */
return [

    // 开发环境通常开启 debug
    'debug' => true,

    // 覆盖数据库连接（例如本地测试库）
    // 'db' => [
    //     'dsn'      => 'mysql:host=127.0.0.1;dbname=h2php_dev;charset=utf8mb4',
    //     'user'     => 'root',
    //     'password' => '',
    // ],

    // 覆盖队列驱动（开发时用 database，不需要 Redis）
    // 'queue' => [
    //     'driver' => 'database',
    // ],

    // 覆盖缓存驱动（开发时用 file）
    // 'cache' => [
    //     'driver' => 'file',
    // ],

];
