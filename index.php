<?php
/**
 * H2PHP — 单入口
 * 框架路由逻辑在 lib/Router.php，通常无需修改此文件。
 */

define('ROOT',   __DIR__);
define('LIB',    ROOT . '/lib');
define('APP',    ROOT . '/app');
define('VIEWS',  ROOT . '/views');
define('CONFIG', ROOT . '/config');

require LIB . '/Request.php';
require LIB . '/DB.php';
require LIB . '/Core.php';
require LIB . '/Router.php';

$config = require CONFIG . '/config.php';

// 错误显示
if ($config['debug'] ?? false) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// 补全路径配置
$config['path'] = ['app' => APP, 'views' => VIEWS];

\Lib\Router::run($config);
