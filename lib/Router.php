<?php
namespace Lib;

/**
 * Router — 框架路由核心
 * 解析 URI，加载控制器，注入参数，执行方法
 */
class Router
{
    public static function run(array $config): void
    {
        // ─── 解析路由 ────────────────────────────────────────────
        // .htaccess 将 URI 作为 _route 参数传入；
        // PHP 内置服务器可用 ?goods/show/1 风格（无 = 号的 query string）
        $uri = $_GET['_route'] ?? ($_SERVER['QUERY_STRING'] ?? '');
        unset($_GET['_route']);

        // 去掉首尾斜线，分割
        $segments = array_values(array_filter(explode('/', trim($uri, '/'))));

        $defaults = $config['default'];

        // 固定位置切分：前三段 = a/b/c，其余全部作为 d 参数
        // d 参数支持字符串（slug/hash）和整数，互不影响路由识别
        $a = $segments[0] ?? $defaults['a'];   // 目录
        $b = $segments[1] ?? $defaults['b'];   // 文件
        $c = $segments[2] ?? $defaults['c'];   // 方法
        $d = array_slice($segments, 3);        // 位置参数（字符串或数字）

        // a/b/c 安全校验：只允许字母、数字、下划线（阐止路径穿越）
        $safePattern = '/^[a-zA-Z0-9_]+$/';
        if (!preg_match($safePattern, $a) ||
            !preg_match($safePattern, $b) ||
            !preg_match($safePattern, $c)) {
            self::abort(400, '非法路由参数');
        }

        // d 参数安全校验：允许字母、数字、下划线、连字符和小数点（支持 slug/hash）
        $dPattern = '/^[a-zA-Z0-9_\-.]+$/';
        foreach ($d as $seg) {
            if (!preg_match($dPattern, $seg)) {
                self::abort(400, "非法位置参数：{$seg}");
            }
        }

        // ─── 加载控制器文件 ──────────────────────────────────────
        $ctrlFile = $config['path']['app'] . "/{$a}/{$b}.php";

        if (!is_file($ctrlFile)) {
            self::abort(404, "控制器文件不存在：app/{$a}/{$b}.php");
        }

        require $ctrlFile;

        // ─── 实例化 main 类 ──────────────────────────────────────
        if (!class_exists('main')) {
            self::abort(500, "控制器文件中未找到 main 类：app/{$a}/{$b}.php");
        }

        /** @var \Lib\Core $controller */
        $controller        = new \main();
        $controller->_path = "{$a}/{$b}/{$c}";  // 供 render() 推断模板路径

        // 注入配置（通过反射写入 protected $config）
        $ref = new \ReflectionProperty(\Lib\Core::class, 'config');
        $ref->setAccessible(true);
        $ref->setValue($controller, $config);

        // ─── 调用方法 ────────────────────────────────────────────
        if (!method_exists($controller, $c)) {
            self::abort(404, "方法不存在：main::{$c}()");
        }

        $refMethod = new \ReflectionMethod($controller, $c);
        if (!$refMethod->isPublic()) {
            self::abort(403, "方法不可访问：main::{$c}()");
        }

        // 按方法参数顺序注入 d 参数（不足时用默认值）
        $params   = $refMethod->getParameters();
        $callArgs = [];
        $dIndex   = 0;

        foreach ($params as $param) {
            if (isset($d[$dIndex])) {
                $raw  = $d[$dIndex++];
                // 按形参类型提示自动转型
                $type = $param->getType();
                $typeName = $type ? $type->getName() : '';
                if ($typeName === 'int')   $raw = (int)$raw;
                elseif ($typeName === 'float') $raw = (float)$raw;
                // string / 无类型 → 原样传入
                $callArgs[] = $raw;
            } elseif ($param->isDefaultValueAvailable()) {
                $callArgs[] = $param->getDefaultValue();
            } else {
                self::abort(400, "方法参数不足：main::{$c}() 需要参数 \${$param->getName()}");
            }
        }

        // 将当前方法名写入控制器（供 skipBefore 使用）
        $controller->_method = $c;

        // ─── 构建中间件管道（洋葱模型）──────────────────────────
        // 最内层：before() → action → after()
        $core = function() use ($controller, $c, $callArgs) {
            if ($controller->shouldRunBefore()) {
                $controller->before();
            }
            $controller->$c(...$callArgs);
            $controller->after();
        };

        // 收集中间件：全局（config） + 控制器级
        $middlewares = $config['middleware'] ?? [];
        $ctrlMiddlewares = $controller->getMiddleware();
        $middlewares = array_merge($middlewares, $ctrlMiddlewares);

        // 无中间件时直接执行核心逻辑（零开销）
        if (empty($middlewares)) {
            $core();
            return;
        }

        // 从内向外包裹：最后注册的中间件最靠近核心
        $pipeline = $core;
        foreach (array_reverse($middlewares) as $mw) {
            $next = $pipeline;
            $pipeline = function() use ($mw, $next, $config) {
                $file = ($config['path']['app'] ?? APP) . "/middleware/{$mw}.php";
                if (!is_file($file)) {
                    throw new \RuntimeException("中间件文件不存在：app/middleware/{$mw}.php");
                }
                require_once $file;
                if (!class_exists($mw)) {
                    throw new \RuntimeException("中间件类不存在：{$mw}");
                }
                (new $mw())->handle($next);
            };
        }

        $pipeline();
    }

    /**
     * 终止并输出错误页面
     * 优先使用 views/_errors/{code}.html，不存在则用内置样式
     */
    public static function abort(int $code, string $message): void
    {
        http_response_code($code);

        switch ($code) {
            case 400: $title = '400 Bad Request'; break;
            case 403: $title = '403 Forbidden';   break;
            case 404: $title = '404 Not Found';   break;
            default:  $title = "{$code} Error";   break;
        }

        // 尝试自定义错误模板
        $tplFile = VIEWS . "/_errors/{$code}.html";
        if (!defined('VIEWS')) {
            $tplFile = __DIR__ . "/../views/_errors/{$code}.html";
        }

        if (is_file($tplFile)) {
            extract(['code' => $code, 'title' => $title, 'message' => $message]);
            include $tplFile;
        } else {
            // 内置兜底样式
            echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>{$title}</title>"
               . "<style>body{font-family:sans-serif;padding:40px;color:#333}"
               . "h1{color:#c0392b}p{color:#666}</style></head><body>"
               . "<h1>{$title}</h1><p>" . htmlspecialchars($message) . "</p></body></html>";
        }
        exit;
    }
}
