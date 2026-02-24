<?php
/**
 * H2PHP — 单入口路由器
 *
 * 路由规则：/a/b/c/d1/d2
 *   a  → app/ 下的目录
 *   b  → 目录下的 PHP 文件（含 main 类）
 *   c  → main 类中的公开方法
 *   d* → 纯数字位置参数，按顺序作为方法参数传入
 */

define('ROOT',    __DIR__);
define('LIB',     ROOT . '/lib');
define('APP',     ROOT . '/app');
define('VIEWS',   ROOT . '/views');
define('CONFIG',  ROOT . '/config');

// ─── 加载核心库 ────────────────────────────────────────────────
require LIB . '/Request.php';
require LIB . '/DB.php';
require LIB . '/Core.php';

// ─── 读取配置 ──────────────────────────────────────────────────
$config = require CONFIG . '/config.php';

// ─── 错误处理 ──────────────────────────────────────────────────
if ($config['debug']) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// 补全 config 路径（供 Core 使用）
$config['path'] = [
    'app'   => APP,
    'views' => VIEWS,
];

// ─── 解析路由 ──────────────────────────────────────────────────
// .htaccess 将 URI 作为 _route 参数传入；
// PHP 内置服务器可用 ?goods/show/1 风格（无 = 号的 query string）
$uri = $_GET['_route'] ?? ($_SERVER['QUERY_STRING'] ?? '');
// 如果是 ?path 风格，QUERY_STRING 里不含 = ，直接当路由用
// 如果是 ?_route=path 风格，_route 已被读走，清除掉避免污染
unset($_GET['_route']);

// 去掉首尾斜线，分割
$segments = array_filter(explode('/', trim($uri, '/')));
$segments = array_values($segments);

$defaults = $config['default'];

// 分离字符串段（a/b/c）与数字段（d1/d2/...）
$strSegs = [];
$numSegs = [];
$dMode   = false;  // 一旦遇到数字段，后续都视为数字

foreach ($segments as $seg) {
    if (!$dMode && !ctype_digit($seg)) {
        $strSegs[] = $seg;
    } else {
        $dMode = true;
        if (ctype_digit($seg)) {
            $numSegs[] = (int)$seg;
        }
    }
}

$a = $strSegs[0] ?? $defaults['a'];      // 目录
$b = $strSegs[1] ?? $defaults['b'];      // 文件
$c = $strSegs[2] ?? $defaults['c'];      // 方法
$d = $numSegs;                            // 数字参数数组

// 安全校验：只允许字母、数字、下划线（防止路径穿越）
$safePattern = '/^[a-zA-Z0-9_]+$/';
if (!preg_match($safePattern, $a) ||
    !preg_match($safePattern, $b) ||
    !preg_match($safePattern, $c)) {
    h2_abort(400, '非法路由参数');
}

// ─── 加载控制器文件 ────────────────────────────────────────────
$ctrlFile = APP . "/{$a}/{$b}.php";

if (!is_file($ctrlFile)) {
    h2_abort(404, "控制器文件不存在：app/{$a}/{$b}.php");
}

require $ctrlFile;

// ─── 实例化 main 类 ────────────────────────────────────────────
if (!class_exists('main')) {
    h2_abort(500, "控制器文件中未找到 main 类：app/{$a}/{$b}.php");
}

/** @var \Lib\Core $controller */
$controller          = new main();
$controller->_path   = "{$a}/{$b}";  // 供 render() 自动推断模板路径

// 注入配置（通过反射写入 protected $config）
$ref = new ReflectionProperty(\Lib\Core::class, 'config');
$ref->setAccessible(true);
$ref->setValue($controller, $config);

// ─── 调用方法 ──────────────────────────────────────────────────
if (!method_exists($controller, $c)) {
    h2_abort(404, "方法不存在：main::{$c}()");
}

$refMethod = new ReflectionMethod($controller, $c);
if (!$refMethod->isPublic()) {
    h2_abort(403, "方法不可访问：main::{$c}()");
}

// 按方法参数顺序注入 d 参数（不足时用默认值）
$params     = $refMethod->getParameters();
$callArgs   = [];
$dIndex     = 0;

foreach ($params as $param) {
    if (isset($d[$dIndex])) {
        $callArgs[] = $d[$dIndex++];
    } elseif ($param->isDefaultValueAvailable()) {
        $callArgs[] = $param->getDefaultValue();
    } else {
        h2_abort(400, "方法参数不足：main::{$c}() 需要参数 \${$param->getName()}");
    }
}

// ─── 执行 before → action → after ─────────────────────────────
$controller->before();
$controller->$c(...$callArgs);
$controller->after();


// ─── 工具函数 ─────────────────────────────────────────────────

/**
 * 终止并输出错误页面
 */
function h2_abort(int $code, string $message): void
{
    http_response_code($code);
    switch ($code) {
        case 400: $title = '400 Bad Request'; break;
        case 403: $title = '403 Forbidden';   break;
        case 404: $title = '404 Not Found';   break;
        default:  $title = "{$code} Error";   break;
    }
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>{$title}</title>"
       . "<style>body{font-family:sans-serif;padding:40px;color:#333}"
       . "h1{color:#c0392b}p{color:#666}</style></head><body>"
       . "<h1>{$title}</h1><p>" . htmlspecialchars($message) . "</p></body></html>";
    exit;
}
