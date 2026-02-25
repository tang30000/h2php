<?php
namespace Lib;

/**
 * Bootstrap — 框架启动引导
 *
 * 封装所有标准初始化逻辑，让 index.php 保持极简。
 */
class Bootstrap
{
    /**
     * 启动框架
     *
     * @param string $root 项目根目录（__DIR__）
     */
    public static function run(string $root): void
    {
        // 定义路径常量
        define('ROOT',   $root);
        define('LIB',    ROOT . '/lib');
        define('APP',    ROOT . '/app');
        define('VIEWS',  ROOT . '/views');
        define('CONFIG', ROOT . '/config');

        // ── 1. 启动 Session（安全配置）──────────────────────────
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');      // 拒绝未初始化的 Session ID
            ini_set('session.cookie_httponly', '1');       // JS 不可读取 Session Cookie
            ini_set('session.cookie_samesite', 'Lax');     // 防 CSRF
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                ini_set('session.cookie_secure', '1');     // HTTPS 时才发送
            }
            session_start();
        }

        // ── 2. Composer 第三方包（可选）───────────────────────────
        $autoload = ROOT . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require $autoload;
        }

        // ── 3. 加载框架核心（保证顺序）───────────────────────────
        require LIB . '/Request.php';
        require LIB . '/DB.php';
        require LIB . '/Core.php';
        require LIB . '/Router.php';

        // ── 4. 自动加载 lib/ 下的扩展库（按需加载）──────────────
        spl_autoload_register(function (string $class) {
            // Lib\Cache → lib/Cache.php
            if (strpos($class, 'Lib\\') === 0) {
                $file = LIB . '/' . substr($class, 4) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });

        // ── 5. 加载 .env 环境变量（可选）────────────────────────
        Env::load(ROOT . '/.env');

        // ── 6. 读取配置 ──────────────────────────────────────────
        $config = require CONFIG . '/config.php';

        // 本地覆盖配置（config.local.php 不提交到 Git）
        $localCfg = CONFIG . '/config.local.php';
        if (is_file($localCfg)) {
            $local  = require $localCfg;
            $config = array_replace_recursive($config, $local);
        }

        // ── 6. 调试模式 ──────────────────────────────────────────
        if ($config['debug'] ?? false) {
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', 0);
            error_reporting(0);
        }

        $config['path'] = ['app' => APP, 'views' => VIEWS];

        // ── 7. 启动路由 ──────────────────────────────────────────
        \Lib\Router::run($config);
    }
}
