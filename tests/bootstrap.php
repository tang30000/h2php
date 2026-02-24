<?php
/**
 * 测试引导文件
 * 加载框架核心，供测试用例使用
 */

define('ROOT',  dirname(__DIR__));
define('APP',   ROOT . '/app');
define('LIB',   ROOT . '/lib');
define('VIEWS', ROOT . '/views');

// 启动 Session（部分测试可能用到）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 加载框架核心
require LIB . '/Request.php';
require LIB . '/DB.php';
require LIB . '/Cache.php';
require LIB . '/Core.php';
require LIB . '/Router.php';
require LIB . '/Validator.php';

// 加载测试配置（覆盖 config/config.php）
// 测试环境使用独立数据库，避免污染生产数据
$testConfig = ROOT . '/tests/config.php';
if (file_exists($testConfig)) {
    define('TEST_CONFIG', require $testConfig);
}
