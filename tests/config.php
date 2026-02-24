<?php
/**
 * tests/config.php — 测试环境配置
 * 在此配置测试数据库（建议使用独立的 test_* 数据库）
 */
return [
    'db' => [
        'dsn'      => 'mysql:host=localhost;dbname=test_h2php;charset=utf8mb4',
        'user'     => 'root',
        'password' => '',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],
    'default' => ['a' => 'home', 'b' => 'index', 'c' => 'index'],
    'debug'   => true,
    'cache'   => ['driver' => 'file', 'prefix' => 'test_'],
];
