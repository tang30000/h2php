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

// ── 1. 启动 Session ─────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 2. 加载框架核心（保证顺序）──────────────────────────────────
require LIB . '/Request.php';
require LIB . '/DB.php';
require LIB . '/Core.php';
require LIB . '/Router.php';

// ── 3. 自动加载 lib/ 下其他扩展库（如 Auth.php、Mail.php 等）──
$coreFiles = ['Request.php', 'DB.php', 'Core.php', 'Router.php'];
foreach (glob(LIB . '/*.php') as $file) {
    if (!in_array(basename($file), $coreFiles)) {
        require $file;
    }
}

// ── 4. 读取配置 ──────────────────────────────────────────────────
$config = require CONFIG . '/config.php';

// 本地覆盖配置（config.local.php 不提交到 Git，适合开发环境）
// 可覆盖 db / debug / cache 等任意配置项
$localCfg = CONFIG . '/config.local.php';
if (is_file($localCfg)) {
    $local  = require $localCfg;
    $config = array_replace_recursive($config, $local);
}

if ($config['debug'] ?? false) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

$config['path'] = ['app' => APP, 'views' => VIEWS];

\Lib\Router::run($config);
