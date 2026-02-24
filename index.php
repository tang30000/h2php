<?php
/**
 * H2PHP — 单入口
 * 所有初始化逻辑已封装到 lib/Bootstrap.php
 */

// 静态文件直通（PHP 内置服务器）
require __DIR__ . '/lib/StaticFile.php';
\Lib\StaticFile::serve(__DIR__);

// 启动框架
require __DIR__ . '/lib/Bootstrap.php';
\Lib\Bootstrap::run(__DIR__);
