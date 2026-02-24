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

];
