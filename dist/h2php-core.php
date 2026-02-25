<?php
/**
 * H2PHP Framework - Single File Distribution
 * Version: 1.1.0
 * Built: 2026-02-25 01:33:36
 * Source: https://github.com/tang30000/h2php
 * License: MIT
 * Usage: require __DIR__ . '/h2php-core.php';
 */

// ------------------------------------------------------------
// Env (102 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Env — .env 环境变量加载器
 *
 * 解析项目根目录的 .env 文件，将变量加载到 $_ENV 和 getenv()。
 * 用于分离敏感配置（数据库密码、API 密钥等），不提交到 Git。
 *
 * .env 文件格式：
 *   DB_HOST=localhost
 *   DB_NAME=myapp
 *   DB_USER=root
 *   DB_PASS=secret
 *   APP_KEY=your-32-character-secret-key!!
 *   APP_DEBUG=true
 *   # 这是注释
 *
 * 用法：
 *   // Bootstrap 已自动加载，直接使用
 *   Env::get('DB_HOST');             // 'localhost'
 *   Env::get('DB_PORT', 3306);       // 带默认值
 *   Env::get('APP_DEBUG');           // true（自动转布尔）
 *
 * config/config.php 中使用：
 *   'db' => [
 *       'dsn'  => 'mysql:host=' . Lib\Env::get('DB_HOST') . ';dbname=' . Lib\Env::get('DB_NAME'),
 *       'user' => Lib\Env::get('DB_USER'),
 *       'password' => Lib\Env::get('DB_PASS'),
 *   ],
 */
class Env
{
    private static bool  $loaded = false;
    private static array $vars   = [];

    /**
     * 加载 .env 文件
     */
    public static function load(string $path): void
    {
        if (!is_file($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // 跳过注释
            if ($line === '' || $line[0] === '#') continue;

            if (strpos($line, '=') === false) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // 去掉引号
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
                $value = $m[2];
            }

            self::$vars[$key] = $value;
            $_ENV[$key]       = $value;
            putenv("{$key}={$value}");
        }

        self::$loaded = true;
    }

    /**
     * 获取环境变量
     *
     * @param mixed $default 默认值
     * @return mixed 自动转换 true/false/null
     */
    public static function get(string $key, $default = null)
    {
        $value = self::$vars[$key] ?? $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        // 自动类型转换
        switch (strtolower($value)) {
            case 'true':  return true;
            case 'false': return false;
            case 'null':  return null;
            case 'empty': return '';
        }

        return $value;
    }

    /**
     * 是否已加载
     */
    public static function loaded(): bool
    {
        return self::$loaded;
    }
}

// ------------------------------------------------------------
// Logger (79 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Logger — 简易日志系统
 *
 * 支持分级日志（info / warning / error / debug），按日期自动分文件。
 *
 * 用法（控制器中）：
 *   $this->log('info', '用户登录成功', ['user_id' => 5]);
 *   $this->log('error', '支付失败', ['order_id' => 123, 'reason' => $e->getMessage()]);
 *
 * 直接静态调用：
 *   Logger::write('warning', '库存不足', ['sku' => 'A001']);
 *
 * 日志文件位置：ROOT/logs/2026-02-25.log
 */
class Logger
{
    /** @var string 日志目录 */
    private static string $dir = '';

    /**
     * 写入日志
     *
     * @param string $level   日志级别（info / warning / error / debug）
     * @param string $message 日志消息
     * @param array  $context 附加数据（可选）
     */
    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = self::$dir ?: (defined('ROOT') ? ROOT . '/logs' : __DIR__ . '/../logs');

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . date('Y-m-d') . '.log';
        $time = date('Y-m-d H:i:s');
        $level = strtoupper($level);

        $line = "[{$time}] [{$level}] {$message}";
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= PHP_EOL;

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** @param string $dir 自定义日志目录 */
    public static function setDir(string $dir): void
    {
        self::$dir = $dir;
    }

    // ── 快捷方法 ─────────────────────────────────────────────────

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }
}

// ------------------------------------------------------------
// StaticFile (66 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * StaticFile — PHP 内置服务器静态文件直通
 *
 * 当使用 php -S 开发服务器时，所有请求都经过 index.php。
 * 静态资源（css/js/图片/字体等）应直接返回，不走路由。
 * 生产环境（Apache/Nginx）有 Rewrite 规则，此类不会生效。
 *
 * 用法（在 index.php 顶部调用）：
 *   require __DIR__ . '/lib/StaticFile.php';
 *   \Lib\StaticFile::serve();
 */
class StaticFile
{
    private static array $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'mp4'   => 'video/mp4',
        'pdf'   => 'application/pdf',
        'zip'   => 'application/zip',
        'xml'   => 'application/xml',
        'txt'   => 'text/plain',
        'map'   => 'application/json',
    ];

    /**
     * 检测并直接输出静态文件，命中则 exit
     *
     * @param string|null $docRoot 文档根目录，默认为 index.php 所在目录
     */
    public static function serve(?string $docRoot = null): void
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $root = $docRoot ?? dirname($_SERVER['SCRIPT_FILENAME']);
        $file = $root . $uri;

        if ($uri === '/' || !is_file($file)) {
            return;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!isset(self::$mimeTypes[$ext])) {
            return;
        }

        header('Content-Type: ' . self::$mimeTypes[$ext]);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// ------------------------------------------------------------
// Str (186 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Str — 字符串工具函数集
 *
 * 用法：
 *   Str::slug('Hello World');          // 'hello-world'
 *   Str::random(32);                   // 'a1b2c3...'（安全随机）
 *   Str::contains('hello world', 'lo'); // true
 *   Str::limit('很长的文本...', 50);    // 截断+省略号
 *   Str::camel('user_name');           // 'userName'
 *   Str::snake('userName');            // 'user_name'
 */
class Str
{
    /**
     * 生成 URL 友好的 slug
     *
     * 用法：Str::slug('Hello World!') → 'hello-world'
     *       Str::slug('第一篇文章')   → '第一篇文章'（中文保留）
     */
    public static function slug(string $title, string $separator = '-'): string
    {
        $title = mb_strtolower(trim($title));
        // 替换非字母数字和非中文为分隔符
        $title = preg_replace('/[^\p{L}\p{N}]+/u', $separator, $title);
        return trim($title, $separator);
    }

    /**
     * 生成安全随机字符串
     *
     * @param int    $length 长度
     * @param string $type   hex|alpha|alnum
     */
    public static function random(int $length = 32, string $type = 'alnum'): string
    {
        if ($type === 'hex') {
            return substr(bin2hex(random_bytes((int)ceil($length / 2))), 0, $length);
        }
        $chars = $type === 'alpha'
            ? 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
            : 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }

    /**
     * UUID v4
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 是否包含子串
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) !== false;
    }

    /**
     * 是否以某串开头
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }

    /**
     * 是否以某串结尾
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * 截断字符串（支持多字节）
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) return $value;
        return mb_substr($value, 0, $limit) . $end;
    }

    /**
     * 驼峰命名 user_name → userName
     */
    public static function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value))));
    }

    /**
     * 大驼峰 user_name → UserName
     */
    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    /**
     * 蛇形命名 userName → user_name
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $value = preg_replace('/([A-Z])/', $delimiter . '$1', $value);
        return mb_strtolower(ltrim($value, $delimiter));
    }

    /**
     * 短横线命名 userName → user-name
     */
    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    /**
     * 遮罩敏感信息
     *
     * Str::mask('13812345678', 3, 4) → '138****5678'
     * Str::mask('admin@qq.com', 2, 4) → 'ad****qq.com'
     */
    public static function mask(string $value, int $start, int $length, string $char = '*'): string
    {
        $masked = mb_substr($value, 0, $start)
                . str_repeat($char, $length)
                . mb_substr($value, $start + $length);
        return $masked;
    }

    /**
     * 判断是否是有效 Email
     */
    public static function isEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 判断是否是有效 URL
     */
    public static function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 判断是否是有效 JSON
     */
    public static function isJson(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 提取数字
     */
    public static function digits(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value);
    }

    /**
     * 单词数
     */
    public static function wordCount(string $value): int
    {
        // 支持中文
        return preg_match_all('/[\p{L}\p{N}]+/u', $value);
    }
}

// ------------------------------------------------------------
// Request (91 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Request — 请求封装
 * 统一获取 GET / POST / 合并参数，避免直接操作超全局变量
 */
class Request
{
    /**
     * 获取 GET 参数
     */
    public function get(string $key, $default = null)
    {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    /**
     * 获取 POST 参数
     */
    public function post(string $key, $default = null)
    {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    /**
     * 获取 GET 或 POST 参数（POST 优先）
     */
    public function input(string $key, $default = null)
    {
        if (isset($_POST[$key])) return $_POST[$key];
        if (isset($_GET[$key]))  return $_GET[$key];
        return $default;
    }

    /**
     * 获取完整 GET 数组
     */
    public function getAll(): array
    {
        return $_GET;
    }

    /**
     * 获取完整 POST 数组
     */
    public function postAll(): array
    {
        return $_POST;
    }

    /**
     * 是否是 POST 请求
     */
    public function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * 是否是 AJAX 请求
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * 获取客户端 IP
     */
    public function ip(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 获取原始请求方法
     */
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
}

// ------------------------------------------------------------
// Response (135 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Response — 统一 HTTP 响应封装
 *
 * 用法：
 *   $response = new Response();
 *   $response->status(201)->header('X-Custom', 'value')->json(['id' => 1]);
 *   $response->download('/path/to/file.pdf', '报表.pdf');
 *   $response->text('Hello');
 *   $response->html('<h1>Hi</h1>');
 *   $response->redirect('/home');
 */
class Response
{
    private int   $statusCode = 200;
    private array $headers    = [];

    /**
     * 设置 HTTP 状态码
     */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * 设置响应头
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * 批量设置响应头
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * 输出 JSON
     */
    public function json($data, int $status = 0): void
    {
        if ($status) $this->statusCode = $status;
        $this->header('Content-Type', 'application/json; charset=utf-8');
        $this->send(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 输出纯文本
     */
    public function text(string $content, int $status = 0): void
    {
        if ($status) $this->statusCode = $status;
        $this->header('Content-Type', 'text/plain; charset=utf-8');
        $this->send($content);
    }

    /**
     * 输出 HTML
     */
    public function html(string $content, int $status = 0): void
    {
        if ($status) $this->statusCode = $status;
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $this->send($content);
    }

    /**
     * 下载文件
     */
    public function download(string $filePath, string $fileName = ''): void
    {
        if (!is_file($filePath)) {
            $this->status(404)->text('File not found');
            return;
        }
        $fileName = $fileName ?: basename($filePath);
        $this->header('Content-Type', 'application/octet-stream');
        $this->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $this->header('Content-Length', (string)filesize($filePath));
        $this->sendHeaders();
        readfile($filePath);
        exit;
    }

    /**
     * 重定向
     */
    public function redirect(string $url, int $status = 302): void
    {
        $this->statusCode = $status;
        $this->header('Location', $url);
        $this->sendHeaders();
        exit;
    }

    /**
     * 无内容响应（常用于 DELETE 成功）
     */
    public function noContent(): void
    {
        $this->statusCode = 204;
        $this->sendHeaders();
        exit;
    }

    // ── 内部 ─────────────────────────────────────────────────────────────────

    private function send(string $body): void
    {
        $this->sendHeaders();
        echo $body;
        exit;
    }

    private function sendHeaders(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
    }
}

// ------------------------------------------------------------
// Cookie (106 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Cookie — 安全 Cookie 封装
 *
 * 统一管理 HttpOnly、Secure、SameSite 等安全属性，
 * 可选 AES 加密存储（需配置 app_key）。
 *
 * 用法：
 *   $cookie = new Cookie(['app_key' => '...', 'secure' => true]);
 *
 *   $cookie->set('theme', 'dark', 86400);           // 1天
 *   $cookie->get('theme');                            // 'dark'
 *   $cookie->delete('theme');
 *
 *   $cookie->setEncrypted('token', $sensitive, 3600); // 加密存储
 *   $cookie->getEncrypted('token');                   // 自动解密
 */
class Cookie
{
    private string $path     = '/';
    private string $domain   = '';
    private bool   $secure   = false;
    private bool   $httpOnly = true;
    private string $sameSite = 'Lax';
    private ?string $appKey  = null;

    public function __construct(array $config = [])
    {
        $this->path     = $config['cookie_path']     ?? '/';
        $this->domain   = $config['cookie_domain']   ?? '';
        $this->secure   = $config['cookie_secure']   ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $this->httpOnly = $config['cookie_httponly']  ?? true;
        $this->sameSite = $config['cookie_samesite']  ?? 'Lax';
        $this->appKey   = $config['app_key']          ?? null;
    }

    /**
     * 设置 Cookie
     *
     * @param int $ttl 过期秒数（0=浏览器关闭时清除）
     */
    public function set(string $name, string $value, int $ttl = 0): void
    {
        $expires = $ttl > 0 ? time() + $ttl : 0;
        setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => $this->path,
            'domain'   => $this->domain,
            'secure'   => $this->secure,
            'httponly'  => $this->httpOnly,
            'samesite' => $this->sameSite,
        ]);
        $_COOKIE[$name] = $value;
    }

    /**
     * 获取 Cookie
     */
    public function get(string $name, ?string $default = null): ?string
    {
        return $_COOKIE[$name] ?? $default;
    }

    /**
     * 删除 Cookie
     */
    public function delete(string $name): void
    {
        $this->set($name, '', -86400);
        unset($_COOKIE[$name]);
    }

    /**
     * 是否存在
     */
    public function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * 加密后存储
     */
    public function setEncrypted(string $name, string $value, int $ttl = 0): void
    {
        if (!$this->appKey) {
            throw new \RuntimeException('Cookie 加密需要配置 app_key');
        }
        $enc = new Encryption($this->appKey);
        $this->set($name, $enc->encrypt($value), $ttl);
    }

    /**
     * 读取并解密
     */
    public function getEncrypted(string $name): ?string
    {
        $val = $this->get($name);
        if ($val === null || !$this->appKey) return null;
        $enc = new Encryption($this->appKey);
        return $enc->decrypt($val);
    }
}

// ------------------------------------------------------------
// Auth (170 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Auth — 鉴权与密码工具
 *
 * 提供密码哈希、Session 登录管理、JWT Token 生成/验证。
 *
 * 用法（控制器快捷方式）：
 *   // 密码
 *   $hash = Auth::hashPassword('123456');
 *   Auth::verifyPassword('123456', $hash);  // true
 *
 *   // Session 登录
 *   Auth::login(['id' => 1, 'username' => 'admin']);
 *   Auth::check();    // true
 *   Auth::user();     // ['id' => 1, ...]
 *   Auth::logout();
 *
 *   // JWT
 *   $token = Auth::jwtEncode(['user_id' => 1], 'secret', 7200);
 *   $data  = Auth::jwtDecode($token, 'secret');
 */
class Auth
{
    // =========================================================================
    // 密码哈希
    // =========================================================================

    /**
     * 生成密码哈希（bcrypt）
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * 验证密码
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 哈希是否需要重新生成（算法升级时）
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT);
    }

    // =========================================================================
    // Session 登录管理
    // =========================================================================

    /**
     * 登录：将用户数据存入 Session
     */
    public static function login(array $user, string $key = 'user'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION[$key] = $user;
        // 防止 Session 固定攻击
        session_regenerate_id(true);
    }

    /**
     * 登出
     */
    public static function logout(string $key = 'user'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        unset($_SESSION[$key]);
    }

    /**
     * 是否已登录
     */
    public static function check(string $key = 'user'): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return !empty($_SESSION[$key]);
    }

    /**
     * 获取当前登录用户
     */
    public static function user(string $key = 'user'): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return $_SESSION[$key] ?? null;
    }

    /**
     * 获取当前登录用户 ID
     */
    public static function id(string $key = 'user'): ?int
    {
        $user = self::user($key);
        return $user['id'] ?? null;
    }

    // =========================================================================
    // JWT Token（无依赖实现）
    // =========================================================================

    /**
     * 生成 JWT Token
     *
     * @param array  $payload 自定义数据
     * @param string $secret  签名密钥
     * @param int    $ttl     有效期（秒），0=永不过期
     */
    public static function jwtEncode(array $payload, string $secret, int $ttl = 3600): string
    {
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));

        if ($ttl > 0) {
            $payload['exp'] = time() + $ttl;
        }
        $payload['iat'] = time();
        $body = self::base64UrlEncode(json_encode($payload));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );

        return "{$header}.{$body}.{$signature}";
    }

    /**
     * 验证并解码 JWT Token
     *
     * @return array|null 解码后的 payload，失败返回 null
     */
    public static function jwtDecode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $signature] = $parts;

        // 验证签名
        $expected = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );
        if (!hash_equals($expected, $signature)) return null;

        $payload = json_decode(self::base64UrlDecode($body), true);
        if (!is_array($payload)) return null;

        // 检查过期
        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

// ------------------------------------------------------------
// Encryption (89 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Encryption — 数据加解密（AES-256-CBC）
 *
 * 用于加密敏感数据（用户信息、API 密钥等）。
 *
 * config/config.php:
 *   'app_key' => 'your-32-character-secret-key!!'   // 必须 32 字节
 *
 * 用法：
 *   $enc = new Encryption($config['app_key']);
 *   $cipher = $enc->encrypt('sensitive data');   // Base64 密文
 *   $plain  = $enc->decrypt($cipher);           // 原文
 *
 *   // 静态快捷方式（需先 Encryption::setKey()）
 *   Encryption::setKey($config['app_key']);
 *   $cipher = Encryption::enc('data');
 *   $plain  = Encryption::dec($cipher);
 */
class Encryption
{
    private string $key;
    private string $cipher = 'aes-256-cbc';

    private static ?string $globalKey = null;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * 加密
     *
     * @return string Base64 编码的密文（格式：IV.密文）
     */
    public function encrypt(string $plaintext): string
    {
        $iv   = random_bytes(openssl_cipher_iv_length($this->cipher));
        $data = openssl_encrypt($plaintext, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        $mac  = hash_hmac('sha256', $iv . $data, $this->key, true);
        return base64_encode($iv . $mac . $data);
    }

    /**
     * 解密
     *
     * @return string|null 解密失败返回 null
     */
    public function decrypt(string $ciphertext): ?string
    {
        $raw = base64_decode($ciphertext, true);
        if ($raw === false) return null;

        $ivLen = openssl_cipher_iv_length($this->cipher);
        if (strlen($raw) < $ivLen + 32) return null;

        $iv   = substr($raw, 0, $ivLen);
        $mac  = substr($raw, $ivLen, 32);
        $data = substr($raw, $ivLen + 32);

        // 验证 HMAC 防止篡改
        $expected = hash_hmac('sha256', $iv . $data, $this->key, true);
        if (!hash_equals($expected, $mac)) return null;

        $result = openssl_decrypt($data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        return $result === false ? null : $result;
    }

    // ── 静态快捷方式 ─────────────────────────────────────────────────────────

    public static function setKey(string $key): void
    {
        self::$globalKey = $key;
    }

    public static function enc(string $plaintext): string
    {
        return (new self(self::$globalKey))->encrypt($plaintext);
    }

    public static function dec(string $ciphertext): ?string
    {
        return (new self(self::$globalKey))->decrypt($ciphertext);
    }
}

// ------------------------------------------------------------
// RateLimiter (149 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * RateLimiter — 接口限流器
 *
 * 防止接口被恶意刷请求，支持两种后端：Redis（推荐）、文件。
 *
 * 用法（控制器中）：
 *   $limiter = new RateLimiter($this->config);
 *
 *   // 每个 IP 每分钟最多 60 次
 *   if ($limiter->tooMany('api:' . $this->request->ip(), 60, 60)) {
 *       $this->json(['error' => '请求过于频繁'], 429);
 *       return;
 *   }
 *
 *   // 登录失败限制：每个用户名每小时最多 5 次
 *   if ($limiter->tooMany('login:' . $username, 5, 3600)) {
 *       $this->flash('error', '登录失败次数过多，请稍后再试');
 *       $this->redirect('/user/login');
 *       return;
 *   }
 */
class RateLimiter
{
    private ?Redis $redis = null;
    private ?string $fileDir = null;

    public function __construct(array $config = [])
    {
        if (!empty($config['redis'])) {
            $this->redis = new Redis($config['redis']);
        } else {
            $this->fileDir = rtrim($config['cache']['dir'] ?? (defined('ROOT') ? ROOT . '/cache' : sys_get_temp_dir()), '/\\')
                           . '/ratelimit';
            if (!is_dir($this->fileDir)) {
                mkdir($this->fileDir, 0755, true);
            }
        }
    }

    /**
     * 检查是否超过限制（同时自动计数 +1）
     *
     * @param string $key       限流标识（如 'api:127.0.0.1' 或 'login:admin'）
     * @param int    $maxAttempts 最大次数
     * @param int    $windowSec   时间窗口（秒）
     * @return bool  true = 已超限，应拒绝
     */
    public function tooMany(string $key, int $maxAttempts, int $windowSec = 60): bool
    {
        $current = $this->hit($key, $windowSec);
        return $current > $maxAttempts;
    }

    /**
     * 记录一次请求并返回当前窗口内的总次数
     */
    public function hit(string $key, int $windowSec = 60): int
    {
        if ($this->redis) {
            return $this->hitRedis($key, $windowSec);
        }
        return $this->hitFile($key, $windowSec);
    }

    /**
     * 获取剩余次数
     */
    public function remaining(string $key, int $maxAttempts, int $windowSec = 60): int
    {
        $current = $this->getCurrent($key, $windowSec);
        return max(0, $maxAttempts - $current);
    }

    /**
     * 重置计数（如登录成功后清除失败计数）
     */
    public function reset(string $key): void
    {
        if ($this->redis) {
            $this->redis->del('rl:' . $key);
        } else {
            $file = $this->fileDir . '/' . md5($key) . '.json';
            if (is_file($file)) unlink($file);
        }
    }

    // ── Redis 实现（滑动窗口） ───────────────────────────────────────────────

    private function hitRedis(string $key, int $windowSec): int
    {
        $rKey = 'rl:' . $key;
        $now  = microtime(true);

        $pipe = $this->redis->connection()->multi(\Redis::PIPELINE);
        // 移除窗口外的记录
        $pipe->zRemRangeByScore($rKey, 0, $now - $windowSec);
        // 添加当前请求
        $pipe->zAdd($rKey, $now, $now . ':' . mt_rand());
        // 统计窗口内数量
        $pipe->zCard($rKey);
        // 设置整个 key 过期
        $pipe->expire($rKey, $windowSec);
        $results = $pipe->exec();

        return (int)$results[2];
    }

    // ── 文件实现（简单计数器） ───────────────────────────────────────────────

    private function hitFile(string $key, int $windowSec): int
    {
        $file = $this->fileDir . '/' . md5($key) . '.json';
        $data = $this->loadFile($file);

        // 窗口过期，重置
        if (!$data || $data['expires'] < time()) {
            $data = ['count' => 0, 'expires' => time() + $windowSec];
        }

        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return $data['count'];
    }

    private function getCurrent(string $key, int $windowSec): int
    {
        if ($this->redis) {
            $rKey = 'rl:' . $key;
            $now  = microtime(true);
            $this->redis->connection()->zRemRangeByScore($rKey, 0, $now - $windowSec);
            return $this->redis->connection()->zCard($rKey);
        }
        $file = $this->fileDir . '/' . md5($key) . '.json';
        $data = $this->loadFile($file);
        if (!$data || $data['expires'] < time()) return 0;
        return $data['count'];
    }

    private function loadFile(string $file): ?array
    {
        if (!is_file($file)) return null;
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }
}

// ------------------------------------------------------------
// Cache (209 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Cache — 多驱动缓存封装
 *
 * 支持驱动：memcache | memcached | redis | file
 *
 * config/config.php 中配置：
 *   'cache' => [
 *       'driver'  => 'redis',       // memcache | memcached | redis | file
 *       'host'    => '127.0.0.1',
 *       'port'    => 6379,          // Redis 默认 6379，Memcache 默认 11211
 *       'prefix'  => 'h2_',        // key 前缀，防止多项目冲突
 *       'dir'     => '',            // file 驱动缓存目录（默认 ROOT/cache）
 *   ],
 */
class Cache
{
    private static ?self $instance = null;

    private string $driver;
    private string $prefix;
    private ?string $dir;

    /** @var \Memcache|\Memcached|\Redis|null */
    private $conn;

    private function __construct(array $config)
    {
        $this->driver = $config['driver']  ?? 'file';
        $this->prefix = $config['prefix']  ?? 'h2_';
        $this->dir    = $config['dir']     ?? null;

        $host = $config['host'] ?? '127.0.0.1';
        $port = (int)($config['port'] ?? 11211);

        switch ($this->driver) {
            case 'memcache':
                $this->conn = new \Memcache();
                $this->conn->connect($host, $port);
                break;

            case 'memcached':
                $this->conn = new \Memcached();
                $this->conn->addServer($host, $port);
                break;

            case 'redis':
                $this->conn = new \Redis();
                $this->conn->connect($host, $config['port'] ?? 6379);
                if (!empty($config['password'])) {
                    $this->conn->auth($config['password']);
                }
                break;

            case 'file':
            default:
                $this->driver = 'file';
                $this->dir    = rtrim($this->dir ?? (defined('ROOT') ? ROOT . '/cache' : sys_get_temp_dir()), '/\\');
                if (!is_dir($this->dir)) {
                    mkdir($this->dir, 0755, true);
                }
                break;
        }
    }

    /** 获取单例（由 DB 内部调用） */
    public static function instance(array $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // 公开接口
    // -------------------------------------------------------------------------

    /**
     * 读取缓存，未命中返回 null
     */
    public function get(string $key)
    {
        $key = $this->prefix . $key;

        switch ($this->driver) {
            case 'memcache':
                $val = $this->conn->get($key);
                return $val === false ? null : $val;

            case 'memcached':
                $val = $this->conn->get($key);
                return $this->conn->getResultCode() === \Memcached::RES_NOTFOUND ? null : $val;

            case 'redis':
                $val = $this->conn->get($key);
                return $val === false ? null : unserialize($val);

            case 'file':
                return $this->fileGet($key);
        }
        return null;
    }

    /**
     * 写入缓存
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl   秒数，0 = 永不过期
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $key = $this->prefix . $key;

        switch ($this->driver) {
            case 'memcache':
                return $this->conn->set($key, $value, 0, $ttl);

            case 'memcached':
                return $this->conn->set($key, $value, $ttl);

            case 'redis':
                $res = $this->conn->set($key, serialize($value));
                if ($ttl > 0) {
                    $this->conn->expire($key, $ttl);
                }
                return $res;

            case 'file':
                return $this->fileSet($key, $value, $ttl);
        }
        return false;
    }

    /**
     * 删除指定 key
     */
    public function delete(string $key): bool
    {
        $key = $this->prefix . $key;
        switch ($this->driver) {
            case 'memcache':  return $this->conn->delete($key);
            case 'memcached': return $this->conn->delete($key);
            case 'redis':     return (bool)$this->conn->del($key);
            case 'file':      return $this->fileDelete($key);
        }
        return false;
    }

    /**
     * 清空所有缓存（谨慎使用）
     */
    public function flush(): void
    {
        switch ($this->driver) {
            case 'memcache':  $this->conn->flush(); break;
            case 'memcached': $this->conn->flush(); break;
            case 'redis':     $this->conn->flushAll(); break;
            case 'file':      $this->fileFlush(); break;
        }
    }

    // -------------------------------------------------------------------------
    // File 驱动实现
    // -------------------------------------------------------------------------

    private function filePath(string $key): string
    {
        return $this->dir . '/' . md5($key) . '.cache';
    }

    private function fileGet(string $key)
    {
        $path = $this->filePath($key);
        if (!is_file($path)) return null;
        $data = unserialize(file_get_contents($path));
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            unlink($path);
            return null;
        }
        return $data['value'];
    }

    private function fileSet(string $key, $value, int $ttl): bool
    {
        $data = [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value'   => $value,
        ];
        return file_put_contents($this->filePath($key), serialize($data), LOCK_EX) !== false;
    }

    private function fileDelete(string $key): bool
    {
        $path = $this->filePath($key);
        return is_file($path) ? unlink($path) : true;
    }

    private function fileFlush(): void
    {
        foreach (glob($this->dir . '/*.cache') as $f) {
            unlink($f);
        }
    }
}

// ------------------------------------------------------------
// DB (551 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * DB — PDO 数据库封装
 * 提供简洁的链式查询接口和直接 SQL 执行
 *
 * 支持数据库：MySQL / MariaDB / PostgreSQL / SQLite
 */
class DB
{
    private \PDO $pdo;
    /** @var string 数据库驱动名 mysql|pgsql|sqlite */
    private string $driver = 'mysql';
    private string $table  = '';
    private string $where  = '';
    private array  $params = [];
    private string $order  = '';
    private string $limit  = '';
    private string $fields = '*';

    /** @var int 缓存时间（秒），0 表示不缓存 */
    private int $cacheTime = 0;

    /** @var bool 是否强制刷新缓存 */
    private bool $cacheForce = false;

    /** @var array|null 缓存驱动配置 */
    private ?array $cacheConfig = null;

    /** @var bool 是否自动维护时间戳（created_at / updated_at） */
    private bool $timestamps = false;

    /** @var bool 是否启用软删除（自动过滤 deleted_at IS NOT NULL 的记录） */
    private bool $softDeletes = false;

    /** @var bool 是否包含已软删除记录 */
    private bool $withTrashed = false;

    /** @var bool 是否只查已软删除记录 */
    private bool $onlyTrashed = false;

    public function __construct(array $config)
    {
        $this->pdo = new \PDO(
            $config['dsn'],
            $config['user'] ?? null,
            $config['password'] ?? null,
            $config['options'] ?? []
        );
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->cacheConfig = $config['cache'] ?? null;

        // 从 DSN 自动检测驱动
        if (preg_match('/^(mysql|pgsql|sqlite)/i', $config['dsn'], $m)) {
            $this->driver = strtolower($m[1]);
        }
    }

    /**
     * 引用标识符（表名、列名）
     * MySQL/MariaDB 用反引号 `name`，PostgreSQL/SQLite 用双引号 "name"
     */
    private function qi(string $name): string
    {
        if ($this->driver === 'mysql') {
            return '`' . $name . '`';
        }
        return '"' . $name . '"';
    }

    // -------------------------------------------------------------------------
    // 链式查询接口
    // -------------------------------------------------------------------------

    /**
     * 指定表名，返回新的 DB 实例（支持链式且不污染当前状态）
     */
    public function table(string $table): self
    {
        $clone = clone $this;
        $clone->table     = $table;
        $clone->where     = '';
        $clone->params    = [];
        $clone->order     = '';
        $clone->limit     = '';
        $clone->fields    = '*';
        $clone->cacheTime  = 0;
        $clone->cacheForce = false;
        return $clone;
    }

    /**
     * 启用查询缓存
     *
     * @param int  $ttl   缓存秒数，0 等同于不缓存
     * @param bool $force true = 强制刷新：忽略旧缓存，重新查库并覆盖
     *
     * 用法：->cache(300)        // 有缓存就用，没有则查库后缓存
     *       ->cache(300, true)  // 强制刷新，常用于写操作后主动更新热点数据
     */
    public function cache(int $ttl = 3600, bool $force = false): self
    {
        $this->cacheTime  = $ttl;
        $this->cacheForce = $force;
        return $this;
    }

    /**
     * 指定查询字段
     * 用法：->fields('id, name, email')
     */
    public function fields(string $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * 开启自动时间戳
     *
     * 开启后：
     *   - insert() 自动填充 created_at 和 updated_at（如未传入）
     *   - update() 自动写入 updated_at（如未传入）
     *
     * 用法：$this->db->table('posts')->timestamps()->insert([...]);
     */
    public function timestamps(bool $auto = true): self
    {
        $clone = clone $this;
        $clone->timestamps = $auto;
        return $clone;
    }

    /**
     * WHERE 条件（支持占位符）
     * 用法：->where('id = ? AND status = ?', [1, 1])
     */
    public function where(string $condition, array $params = []): self
    {
        $this->where  = $condition;
        $this->params = $params;
        return $this;
    }

    /**
     * ORDER BY
     * 用法：->order('created_at DESC')
     */
    public function order(string $order): self
    {
        $this->order = $order;
        return $this;
    }

    /**
     * LIMIT / OFFSET
     * 用法：->limit(10) 或 ->limit(10, 20)（取10条，从第20条开始）
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit = $offset > 0 ? "$limit OFFSET $offset" : "$limit";
        return $this;
    }

    /**
     * 获取多条记录
     */
    public function fetchAll(): array
    {
        $sql = $this->buildSelect();

        if ($this->cacheTime > 0 && $this->cacheConfig) {
            $key   = md5($sql . serialize($this->params));
            $cache = Cache::instance($this->cacheConfig);
            // force=false 时先读缓存，命中直接返回
            if (!$this->cacheForce) {
                $hit = $cache->get($key);
                if ($hit !== null) return $hit;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->params);
            $data = $stmt->fetchAll();
            $cache->set($key, $data, $this->cacheTime);  // 写入（覆盖）缓存
            return $data;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll();
    }

    /**
     * 获取单条记录
     */
    public function fetch()
    {
        $this->limit = '1';
        $sql = $this->buildSelect();

        if ($this->cacheTime > 0 && $this->cacheConfig) {
            $key   = md5($sql . serialize($this->params));
            $cache = Cache::instance($this->cacheConfig);
            if (!$this->cacheForce) {
                $hit = $cache->get($key);
                if ($hit !== null) return $hit;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->params);
            $data = $stmt->fetch();
            if ($data !== false) {
                $cache->set($key, $data, $this->cacheTime);
            }
            return $data;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetch();
    }

    /**
     * 获取单个字段值
     * 用法：->table('config')->where('key=?', ['site_name'])->value()
     */
    public function value()
    {
        $row = $this->fetch();
        return $row ? reset($row) : null;
    }

    /**
     * 统计行数
     */
    public function count(): int
    {
        $t    = $this->qi($this->table);
        $sql  = "SELECT COUNT(*) FROM {$t}"
              . ($this->where ? " WHERE {$this->where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 插入一条记录，返回自增 ID
     */
    public function insert(array $data)
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $data['created_at'] ?? $now;
            $data['updated_at'] = $data['updated_at'] ?? $now;
        }
        $cols   = implode(', ', array_map(fn($k) => $this->qi($k), array_keys($data)));
        $marks  = implode(', ', array_fill(0, count($data), '?'));
        $t      = $this->qi($this->table);
        $sql    = "INSERT INTO {$t} ({$cols}) VALUES ({$marks})";
        if ($this->driver === 'pgsql') {
            $sql .= ' RETURNING id';
        }
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        if ($this->driver === 'pgsql') {
            return $stmt->fetchColumn();
        }
        return $this->pdo->lastInsertId();
    }

    /**
     * 更新记录，返回受影响行数
     */
    public function update(array $data): int
    {
        if ($this->timestamps) {
            $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        }
        $sets   = implode(', ', array_map(fn($k) => $this->qi($k) . ' = ?', array_keys($data)));
        $vals   = array_merge(array_values($data), $this->params);
        $t      = $this->qi($this->table);
        $sql    = "UPDATE {$t} SET {$sets}"
                . ($this->where ? " WHERE {$this->where}" : '');
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute($vals);
        return $stmt->rowCount();
    }

    /**
     * 删除记录，返回受影响行数
     */
    public function delete(): int
    {
        $t    = $this->qi($this->table);
        $sql  = "DELETE FROM {$t}"
              . ($this->where ? " WHERE {$this->where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // 软删除
    // -------------------------------------------------------------------------

    /**
     * 启用软删除模式
     *
     * 启用后：
     *   - 查询自动过滤 deleted_at IS NOT NULL 的记录
     *   - delete() 变为 softDelete()（设置 deleted_at）
     *   - 可用 withTrashed()  查询全部（含已删）
     *   - 可用 onlyTrashed() 只查已删除的
     *   - 可用 restore()     恢复已删记录
     *
     * 用法：\$this->db->table('posts')->softDeletes()->where('id=?',[$id])->delete();
     */
    public function softDeletes(): self
    {
        $clone = clone $this;
        $clone->softDeletes = true;
        return $clone;
    }

    /** 查询时包含已软删除的记录 */
    public function withTrashed(): self
    {
        $clone = clone $this;
        $clone->withTrashed = true;
        return $clone;
    }

    /** 只查询已软删除的记录 */
    public function onlyTrashed(): self
    {
        $clone = clone $this;
        $clone->onlyTrashed = true;
        return $clone;
    }

    /**
     * 软删除（设置 deleted_at）
     */
    public function softDelete(): int
    {
        return $this->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 恢复已软删除的记录
     */
    public function restore(): int
    {
        $sets = $this->qi('deleted_at') . ' = NULL';
        $t    = $this->qi($this->table);
        $sql  = "UPDATE {$t} SET {$sets}"
              . ($this->where ? " WHERE {$this->where}" : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // 直接 SQL 执行
    // -------------------------------------------------------------------------

    /**
     * 执行原生查询，返回结果集
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 执行原生语句（INSERT/UPDATE/DELETE），返回受影响行数
     */
    public function exec(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 获取原始 PDO 对象（用于事务等高级操作）
     */
    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    // -------------------------------------------------------------------------
    // 事务
    // -------------------------------------------------------------------------

    /** 开始事务 */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /** 提交事务 */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /** 回滚事务 */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * 事务闭包（自动 commit/rollback）
     *
     * 用法：
     *   \$this->db->transaction(function(\$db) {
     *       \$db->table('orders')->insert([...]);
     *       \$db->table('stock')->where('id=?',[1])->update(['qty' => 99]);
     *   });
     *
     * 闭包内抛异常自动回滚并重新抛出。
     *
     * @param callable $callback 接收 DB 实例参数
     * @return mixed 闭包返回值
     */
    public function transaction(callable $callback)
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // 关联关系辅助（hasMany / belongsTo）
    // -------------------------------------------------------------------------

    /**
     * 一对多：获取子表记录（返回可链式的 DB 实例）
     *
     * @param string $relTable  关联表名
     * @param string $fk        外键字段名（在关联表中）
     * @param mixed  $pkValue   当前记录的主键值
     *
     * 用法：
     *   // 获取 user_id=5 的所有文章，可继续链式过滤
     *   $posts = $this->db->hasMany('posts', 'user_id', 5)
     *       ->order('id DESC')->limit(10)->fetchAll();
     */
    public function hasMany(string $relTable, string $fk, $pkValue): self
    {
        return $this->table($relTable)->where("{$fk}=?", [$pkValue]);
    }

    /**
     * 多对一：获取父表记录（返回可链式的 DB 实例，通常 ->fetch()）
     *
     * @param string $relTable  父表名
     * @param string $pk        父表主键字段名
     * @param mixed  $fkValue   当前记录的外键值
     *
     * 用法：
     *   // 获取 post['user_id'] 对应的用户
     *   $user = $this->db->belongsTo('users', 'id', $post['user_id'])->fetch();
     */
    public function belongsTo(string $relTable, string $pk, $fkValue): self
    {
        return $this->table($relTable)->where("{$pk}=?", [$fkValue]);
    }

    /**
     * 多对多：通过中间表获取关联记录（返回可链式的 DB 实例）
     *
     * @param string $relTable   关联表名（目标表）
     * @param string $pivotTable 中间表名
     * @param string $localFk    中间表中指向当前记录的外键
     * @param string $relFk      中间表中指向关联记录的外键
     * @param mixed  $pkValue    当前记录的主键值
     *
     * 用法：
     *   // 获取文章的所有标签（通过 post_tag 中间表）
     *   $tags = $this->db->belongsToMany('tags', 'post_tag', 'post_id', 'tag_id', $postId)
     *       ->order('tags.name')->fetchAll();
     */
    public function belongsToMany(
        string $relTable,
        string $pivotTable,
        string $localFk,
        string $relFk,
        $pkValue
    ): self {
        $clone        = clone $this;
        $clone->table = $relTable;
        $pt = $this->qi($pivotTable);
        $rt = $this->qi($relTable);
        $lf = $this->qi($localFk);
        $rf = $this->qi($relFk);
        $clone->where  = "{$pt}.{$lf}=?";
        $clone->params = [$pkValue];
        $clone->order  = '';
        $clone->limit  = '';
        // 将 INNER JOIN 嵌入 fields 作为自定义前缀（buildSelect 会原样包含）
        $clone->fields = "{$rt}.* FROM {$pt} INNER JOIN {$rt} ON {$pt}.{$rf}={$rt}." . $this->qi('id');
        // 标记 table 为空使 buildSelect 跳过 FROM 部分
        $clone->__btm = true;
        return $clone;
    }

    // -------------------------------------------------------------------------
    // 内部辅助
    // -------------------------------------------------------------------------

    private function buildSelect(): string
    {
        if (!empty($this->__btm)) {
            $sql = "SELECT {$this->fields}";
            if ($this->where) $sql .= " WHERE {$this->where}";
            if ($this->order) $sql .= " ORDER BY {$this->order}";
            if ($this->limit) $sql .= " LIMIT {$this->limit}";
            return $sql;
        }

        $t   = $this->qi($this->table);
        $sql = "SELECT {$this->fields} FROM {$t}";

        // 构建 WHERE（含软删除过滤）
        $where = $this->where;
        if ($this->softDeletes && !$this->withTrashed) {
            $da = $this->qi('deleted_at');
            $sd = $this->onlyTrashed
                ? "{$da} IS NOT NULL"
                : "{$da} IS NULL";
            $where = $where ? "({$where}) AND {$sd}" : $sd;
        }

        if ($where) $sql .= " WHERE {$where}";
        if ($this->order) $sql .= " ORDER BY {$this->order}";
        if ($this->limit) $sql .= " LIMIT {$this->limit}";
        return $sql;
    }
}

// ------------------------------------------------------------
// Validator (229 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Validator — 表单验证器
 *
 * 用法：
 *   $v = new \Lib\Validator($_POST, [
 *       'name'  => 'required|max_len:50',
 *       'email' => 'required|email',
 *       'age'   => 'required|integer|min:1|max:150',
 *   ]);
 *
 *   if ($v->fails()) {
 *       echo $v->firstError();   // 第一条错误
 *       print_r($v->errors());   // 所有错误
 *   }
 */
class Validator
{
    /** @var array 原始数据 */
    private array $data;

    /** @var array [字段 => [错误消息, ...]] */
    private array $errors = [];

    /** @var \Lib\DB|null 用于 unique 规则 */
    private ?DB $db;

    /** @var array 自定义字段标签（用于错误提示） */
    private array $labels;

    /**
     * @param array       $data   待验证数据（通常是 $_POST）
     * @param array       $rules  [字段 => 'rule1|rule2:param|...']
     * @param array       $labels [字段 => '显示名称']（可选）
     * @param \Lib\DB|null $db    传入 DB 实例以支持 unique 规则
     */
    public function __construct(array $data, array $rules, array $labels = [], ?DB $db = null)
    {
        $this->data   = $data;
        $this->labels = $labels;
        $this->db     = $db;
        $this->validate($rules);
    }

    // -------------------------------------------------------------------------
    // 公开结果方法
    // -------------------------------------------------------------------------

    /** 是否有验证错误 */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /** 是否全部通过 */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * 获取所有错误（[字段 => [错误1, 错误2, ...]]）
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * 获取指定字段的第一条错误，不存在则返回 null
     */
    public function error(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * 获取全局第一条错误消息
     */
    public function firstError(): ?string
    {
        foreach ($this->errors as $msgs) {
            return $msgs[0] ?? null;
        }
        return null;
    }

    /**
     * 所有错误合并为一个数组（扁平化）
     */
    public function allErrors(): array
    {
        $flat = [];
        foreach ($this->errors as $msgs) {
            foreach ($msgs as $msg) {
                $flat[] = $msg;
            }
        }
        return $flat;
    }

    // -------------------------------------------------------------------------
    // 内部验证逻辑
    // -------------------------------------------------------------------------

    private function validate(array $rules): void
    {
        foreach ($rules as $field => $ruleStr) {
            $value = $this->data[$field] ?? null;
            $label = $this->labels[$field] ?? $field;

            foreach (explode('|', $ruleStr) as $ruleExpr) {
                [$rule, $param] = array_pad(explode(':', $ruleExpr, 2), 2, null);
                $rule = trim($rule);

                $error = $this->applyRule($rule, $field, $value, $param, $label);
                if ($error !== null) {
                    $this->errors[$field][] = $error;
                    // required 失败后跳过此字段的后续规则
                    if ($rule === 'required') {
                        break;
                    }
                }
            }
        }
    }

    private function applyRule(string $rule, string $field, $value, ?string $param, string $label): ?string
    {
        // 非 required 时，空值跳过其他规则
        if ($rule !== 'required' && ($value === null || $value === '')) {
            return null;
        }

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    return "{$label} 不能为空";
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "{$label} 邮箱格式不正确";
                }
                break;

            case 'integer':
                if (!ctype_digit(ltrim((string)$value, '-')) || (string)(int)$value !== (string)$value) {
                    return "{$label} 必须是整数";
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    return "{$label} 必须是数字";
                }
                break;

            case 'min':
                if (is_numeric($value) && (float)$value < (float)$param) {
                    return "{$label} 不能小于 {$param}";
                }
                break;

            case 'max':
                if (is_numeric($value) && (float)$value > (float)$param) {
                    return "{$label} 不能大于 {$param}";
                }
                break;

            case 'min_len':
                if (mb_strlen((string)$value) < (int)$param) {
                    return "{$label} 长度不能少于 {$param} 个字符";
                }
                break;

            case 'max_len':
                if (mb_strlen((string)$value) > (int)$param) {
                    return "{$label} 长度不能超过 {$param} 个字符";
                }
                break;

            case 'in':
                $allowed = explode(',', $param ?? '');
                if (!in_array($value, $allowed, true)) {
                    return "{$label} 值不合法（允许：{$param}）";
                }
                break;

            case 'regex':
                if (!preg_match($param, (string)$value)) {
                    return "{$label} 格式不正确";
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return "{$label} URL 格式不正确";
                }
                break;

            case 'confirmed':
                // 校验 {field}_confirmation 与 {field} 相同
                $confirm = $this->data["{$field}_confirmation"] ?? null;
                if ($value !== $confirm) {
                    return "{$label} 两次输入不一致";
                }
                break;

            case 'unique':
                // unique:table,column
                [$table, $col] = array_pad(explode(',', $param ?? '', 2), 2, $field);
                if ($this->db && $this->db->table($table)->where("{$col}=?", [$value])->count() > 0) {
                    return "{$label} 已被占用";
                }
                break;

            default:
                // 未知规则，忽略
                break;
        }

        return null;
    }
}

// ------------------------------------------------------------
// Upload (168 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Upload — 文件上传辅助
 *
 * 用法（控制器中）：
 *   $file = $this->upload('avatar', 'static/uploads/avatars');
 *
 *   if ($file->fails()) {
 *       $this->flash('error', $file->error());
 *       $this->redirect('/user/profile');
 *   }
 *
 *   $path = $file->path();  // 存储的相对路径，可存入数据库
 *
 * 链式配置：
 *   $file = $this->upload('photo', 'static/uploads')
 *       ->maxSize(5 * 1024 * 1024)     // 最大 5 MB
 *       ->allowTypes(['jpg', 'png', 'webp'])
 *       ->rename('uuid');              // uuid | timestamp | original
 */
class Upload
{
    private string $field;
    private string $destDir;
    private int    $maxBytes    = 5 * 1024 * 1024;   // 5 MB
    private array  $allowExts   = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip'];
    private string $renameMode  = 'uuid';

    private ?string $storedPath = null;
    private ?string $errorMsg   = null;
    private bool    $saved      = false;

    public function __construct(string $field, string $destDir)
    {
        $this->field   = $field;
        $this->destDir = rtrim($destDir, '/');
    }

    // ── 链式配置 ─────────────────────────────────────────────────

    /** 最大文件大小（字节） */
    public function maxSize(int $bytes): self { $this->maxBytes  = $bytes; return $this; }

    /** 允许的扩展名列表（小写，不含点） */
    public function allowTypes(array $exts): self { $this->allowExts = array_map('strtolower', $exts); return $this; }

    /** 重命名策略：'uuid'（默认）| 'timestamp' | 'original' */
    public function rename(string $mode): self { $this->renameMode = $mode; return $this; }

    // ── 执行保存 ─────────────────────────────────────────────────

    /**
     * 验证并保存文件
     * @return $this
     */
    public function save(): self
    {
        if ($this->saved) return $this;
        $this->saved = true;

        $files = $_FILES[$this->field] ?? null;

        if (!$files || empty($files['tmp_name']) || $files['error'] !== UPLOAD_ERR_OK) {
            $this->errorMsg = $this->uploadErrorMessage($files['error'] ?? UPLOAD_ERR_NO_FILE);
            return $this;
        }

        // 大小校验
        if ($files['size'] > $this->maxBytes) {
            $this->errorMsg = sprintf('文件大小超过限制（最大 %s）', $this->formatBytes($this->maxBytes));
            return $this;
        }

        // 扩展名校验（不依赖 MIME，简单实用）
        $ext = strtolower(pathinfo($files['name'], PATHINFO_EXTENSION));
        if ($this->allowExts && !in_array($ext, $this->allowExts, true)) {
            $this->errorMsg = sprintf('不支持的文件类型：.%s（允许：%s）', $ext, implode(', ', $this->allowExts));
            return $this;
        }

        // 生成目标路径
        $dir = defined('ROOT') ? ROOT . '/' . $this->destDir : $this->destDir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $filename = $this->generateName($files['name'], $ext);
        $dest     = $dir . '/' . $filename;

        if (!move_uploaded_file($files['tmp_name'], $dest)) {
            $this->errorMsg = '文件保存失败，请检查目录权限';
            return $this;
        }

        $this->storedPath = $this->destDir . '/' . $filename;
        return $this;
    }

    // ── 结果读取 ─────────────────────────────────────────────────

    /** 是否上传失败 */
    public function fails(): bool
    {
        $this->save();
        return $this->errorMsg !== null;
    }

    /** 错误信息 */
    public function error(): string
    {
        return $this->errorMsg ?? '';
    }

    /** 存储路径（相对 ROOT，可直接存入数据库） */
    public function path(): ?string
    {
        return $this->storedPath;
    }

    /** URL（相对路径，可直接用于 <img src=> 等） */
    public function url(): ?string
    {
        return $this->storedPath ? '/' . $this->storedPath : null;
    }

    // ── 私有辅助 ─────────────────────────────────────────────────

    private function generateName(string $original, string $ext): string
    {
        if ($this->renameMode === 'timestamp') {
            return time() . '_' . random_int(1000, 9999) . '.' . $ext;
        }
        if ($this->renameMode === 'original') {
            return preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($original, PATHINFO_FILENAME)) . '.' . $ext;
        }
        // default: uuid
        return sprintf('%s.%s', bin2hex(random_bytes(16)), $ext);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    private function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return '文件超过服务器允许的上传大小';
            case UPLOAD_ERR_PARTIAL:
                return '文件只上传了一部分';
            case UPLOAD_ERR_NO_FILE:
                return '没有选择文件';
            case UPLOAD_ERR_NO_TMP_DIR:
                return '临时目录不存在';
            case UPLOAD_ERR_CANT_WRITE:
                return '磁盘写入失败';
            default:
                return '上传失败';
        }
    }
}

// ------------------------------------------------------------
// Mail (212 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Mail — 零依赖 SMTP 邮件发送
 *
 * 通过 socket 直接与 SMTP 服务器通信，不需要 PHPMailer 等第三方库。
 * 支持 SSL/TLS、HTML 正文、CC/BCC、附件（Base64）。
 *
 * 用法（控制器中）：
 *   $this->mail('user@example.com', '注册成功', '<h1>欢迎</h1>');
 *
 * 链式（高级）：
 *   $mail = new \Lib\Mail($this->config['mail']);
 *   $mail->to('a@b.com')->cc('c@d.com')->subject('标题')->html('<p>内容</p>')->send();
 */
class Mail
{
    private array  $config;
    private array  $to      = [];
    private array  $cc      = [];
    private array  $bcc     = [];
    private string $subject = '';
    private string $body    = '';
    private bool   $isHtml  = false;
    private ?string $error  = null;

    public function __construct(array $config)
    {
        $this->config = array_merge([
            'host'     => 'smtp.qq.com',
            'port'     => 465,
            'user'     => '',
            'password' => '',
            'from'     => '',
            'name'     => '',
            'ssl'      => true,
            'timeout'  => 10,
        ], $config);

        if (empty($this->config['from'])) {
            $this->config['from'] = $this->config['user'];
        }
    }

    // ── 链式配置 ─────────────────────────────────────────────────

    public function to(string ...$addrs): self   { $this->to  = array_merge($this->to, $addrs);  return $this; }
    public function cc(string ...$addrs): self   { $this->cc  = array_merge($this->cc, $addrs);  return $this; }
    public function bcc(string ...$addrs): self  { $this->bcc = array_merge($this->bcc, $addrs); return $this; }
    public function subject(string $s): self     { $this->subject = $s; return $this; }
    public function text(string $body): self     { $this->body = $body; $this->isHtml = false; return $this; }
    public function html(string $body): self     { $this->body = $body; $this->isHtml = true;  return $this; }

    // ── 发送 ─────────────────────────────────────────────────────

    /**
     * 发送邮件
     * @return bool 成功返回 true，失败返回 false（错误信息通过 error() 获取）
     */
    public function send(): bool
    {
        $this->error = null;

        if (empty($this->to)) {
            $this->error = '收件人不能为空';
            return false;
        }

        $cfg  = $this->config;
        $host = ($cfg['ssl'] ? 'ssl://' : '') . $cfg['host'];

        // 连接 SMTP 服务器
        $fp = @stream_socket_client(
            "{$host}:{$cfg['port']}",
            $errno, $errstr,
            $cfg['timeout']
        );

        if (!$fp) {
            $this->error = "连接 SMTP 失败：{$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($fp, $cfg['timeout']);

        try {
            $this->expect($fp, 220);
            $this->cmd($fp, "EHLO " . gethostname(), 250);

            // 认证
            $this->cmd($fp, "AUTH LOGIN", 334);
            $this->cmd($fp, base64_encode($cfg['user']), 334);
            $this->cmd($fp, base64_encode($cfg['password']), 235);

            // 发件人
            $this->cmd($fp, "MAIL FROM:<{$cfg['from']}>", 250);

            // 收件人（TO + CC + BCC 都要 RCPT TO）
            $allRecipients = array_merge($this->to, $this->cc, $this->bcc);
            foreach ($allRecipients as $addr) {
                $this->cmd($fp, "RCPT TO:<{$addr}>", 250);
            }

            // 邮件内容
            $this->cmd($fp, "DATA", 354);

            $headers = $this->buildHeaders();
            $message = $headers . "\r\n" . $this->body . "\r\n.";
            $this->cmd($fp, $message, 250);

            $this->cmd($fp, "QUIT", 221);

            return true;
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            return false;
        } finally {
            @fclose($fp);
        }
    }

    /** 获取错误信息 */
    public function error(): string
    {
        return $this->error ?? '';
    }

    // ── 内部辅助 ─────────────────────────────────────────────────

    private function buildHeaders(): string
    {
        $cfg = $this->config;
        $name = $cfg['name'] ? "=?UTF-8?B?" . base64_encode($cfg['name']) . "?=" : $cfg['from'];

        $h   = [];
        $h[] = "From: {$name} <{$cfg['from']}>";
        $h[] = "To: " . implode(', ', $this->to);
        if ($this->cc)  $h[] = "Cc: "  . implode(', ', $this->cc);
        // BCC 不写入头部（符合协议）
        $h[] = "Subject: =?UTF-8?B?" . base64_encode($this->subject) . "?=";
        $h[] = "MIME-Version: 1.0";
        $h[] = "Date: " . date('r');
        $h[] = "Message-ID: <" . uniqid('h2php_', true) . "@" . gethostname() . ">";

        if ($this->isHtml) {
            $h[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $h[] = "Content-Type: text/plain; charset=UTF-8";
        }
        $h[] = "Content-Transfer-Encoding: base64";

        // 对正文做 Base64 编码（防止中文乱码和行长度问题）
        $this->body = chunk_split(base64_encode($this->body));

        return implode("\r\n", $h);
    }

    /**
     * 发送命令并检查响应码
     */
    private function cmd($fp, string $cmd, int $expectCode): string
    {
        fwrite($fp, $cmd . "\r\n");
        return $this->expect($fp, $expectCode);
    }

    /**
     * 读取 SMTP 响应并校验状态码
     */
    private function expect($fp, int $code): string
    {
        $response = '';
        while ($line = fgets($fp, 512)) {
            $response .= $line;
            // SMTP 多行响应以 "xxx-" 格式，最后一行以 "xxx " 格式
            if (isset($line[3]) && $line[3] === ' ') break;
            if (strlen($line) < 4) break;
        }

        $actual = (int)substr($response, 0, 3);
        if ($actual !== $code) {
            throw new \RuntimeException(
                "SMTP 错误：期望 {$code}，收到 {$actual}。响应：" . trim($response)
            );
        }

        return $response;
    }

    // ── 快捷静态方法 ─────────────────────────────────────────────

    /**
     * 快速发送（一行代码）
     *
     * Mail::quick($config, 'user@example.com', '标题', '<p>HTML 内容</p>');
     */
    public static function quick(array $config, string $to, string $subject, string $body): bool
    {
        $mail = new self($config);
        $mail->to($to)->subject($subject);

        if (strip_tags($body) !== $body) {
            $mail->html($body);
        } else {
            $mail->text($body);
        }

        return $mail->send();
    }
}

// ------------------------------------------------------------
// Event (68 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Event — 请求内事件总线（发布/订阅）
 *
 * 事件在当前请求内有效，不跨请求持久化。
 * 需要跨请求的异步处理，请使用 Queue。
 *
 * 用法：
 *   // 监听（通常在 index.php 或控制器 before() 里注册）
 *   Event::on('user.registered', function($user) {
 *       // 发送欢迎邮件、记录日志等
 *   });
 *
 *   // 触发（控制器里）
 *   Event::fire('user.registered', $user);
 *
 *   // 或直接用 Core 快捷方法（在控制器内）：
 *   $this->on('user.registered', fn($u) => ...);
 *   $this->fire('user.registered', $user);
 */
class Event
{
    /** @var array<string, callable[]> */
    private static array $listeners = [];

    /**
     * 注册事件监听器
     *
     * @param string   $event    事件名，建议用点号分隔，如 'user.registered'
     * @param callable $listener 回调，接收 fire() 传入的 $payload
     */
    public static function on(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    /**
     * 触发事件，依次调用所有监听器
     *
     * @param string $event   事件名
     * @param mixed  $payload 传给监听器的数据（任意类型）
     */
    public static function fire(string $event, $payload = null): void
    {
        foreach (self::$listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }

    /**
     * 移除指定事件的所有监听器
     */
    public static function forget(string $event): void
    {
        unset(self::$listeners[$event]);
    }

    /**
     * 清空所有监听器
     */
    public static function flushAll(): void
    {
        self::$listeners = [];
    }
}

// ------------------------------------------------------------
// Queue (300 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Queue — 持久化任务队列
 *
 * 支持驱动：database（默认，零额外依赖）| redis（高性能，推荐生产使用）
 *
 * config/config.php 中配置：
 *   'queue' => [
 *       'driver'      => 'database',  // database | redis
 *       'host'        => '127.0.0.1', // redis 用
 *       'port'        => 6379,
 *       'password'    => '',
 *       'key'         => 'h2_jobs',   // redis list key
 *       'max_attempts'=> 3,           // 最大重试次数
 *   ],
 *
 * Job 文件放在 app/jobs/ 目录，命名与类名一致：
 *   app/jobs/SendWelcomeEmail.php → class SendWelcomeEmail { public function handle(array $payload):void {} }
 *
 * 用法：
 *   // 控制器内入队（使用 Core 快捷方法）
 *   $this->queue('SendWelcomeEmail', ['user_id' => 5]);
 *
 *   // 或直接调用
 *   Queue::push('SendWelcomeEmail', ['user_id' => 5], $config);
 *
 *   // 延迟入队（3600 秒后执行）
 *   $this->queue('SendReminder', ['user_id' => 5], delay: 3600);
 *
 *   // Worker（后台持续运行）
 *   php h2 queue:work
 *
 *   // Cron 模式（每分钟执行一次）
 *   php h2 queue:work --once
 */
class Queue
{
    // -------------------------------------------------------------------------
    // 入队
    // -------------------------------------------------------------------------

    /**
     * 将任务推入队列
     *
     * @param string $jobName  Job 类名
     * @param array  $payload  传给 handle() 的数据
     * @param array  $config   框架配置
     * @param int    $delay    延迟秒数（0 = 立即可用）
     */
    public static function push(string $jobName, array $payload, array $config, int $delay = 0): void
    {
        $qCfg  = $config['queue'] ?? [];
        $driver = $qCfg['driver'] ?? 'database';

        if ($driver === 'redis') {
            self::redisPush($jobName, $payload, $qCfg, $delay);
        } else {
            self::dbPush($jobName, $payload, $config, $delay);
        }
    }

    // -------------------------------------------------------------------------
    // Worker 相关（由 h2 CLI 调用）
    // -------------------------------------------------------------------------

    /**
     * 处理一个待处理任务（database 驱动）
     * 返回 true=有任务处理，false=队列为空
     */
    public static function processOne(array $config): bool
    {
        $qCfg  = $config['queue'] ?? [];
        $driver = $qCfg['driver'] ?? 'database';

        if ($driver === 'redis') {
            return self::redisProcess($config, $qCfg);
        } else {
            return self::dbProcess($config, $qCfg);
        }
    }

    /**
     * 获取队列状态（仅 database 驱动）
     */
    public static function status(array $config): array
    {
        $pdo = self::pdo($config);
        self::ensureTable($pdo);

        $rows = $pdo->query(
            "SELECT status, COUNT(*) as cnt FROM `_jobs` GROUP BY status"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        return [
            'pending'    => (int)($rows['pending']    ?? 0),
            'processing' => (int)($rows['processing'] ?? 0),
            'done'       => (int)($rows['done']       ?? 0),
            'failed'     => (int)($rows['failed']     ?? 0),
        ];
    }

    /**
     * 清除指定状态的任务（默认清除 done + failed）
     */
    public static function clear(array $config, array $statuses = ['done', 'failed']): int
    {
        $pdo = self::pdo($config);
        self::ensureTable($pdo);

        $in   = implode(',', array_fill(0, count($statuses), '?'));
        $stmt = $pdo->prepare("DELETE FROM `_jobs` WHERE status IN ({$in})");
        $stmt->execute($statuses);
        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // Database 驱动
    // -------------------------------------------------------------------------

    private static function dbPush(string $jobName, array $payload, array $config, int $delay = 0): void
    {
        $pdo = self::pdo($config);
        self::ensureTable($pdo);

        $availableAt = $delay > 0 ? date('Y-m-d H:i:s', time() + $delay) : date('Y-m-d H:i:s');

        $pdo->prepare(
            "INSERT INTO `_jobs` (name, payload, max_attempts, available_at) VALUES (?, ?, ?, ?)"
        )->execute([
            $jobName,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $config['queue']['max_attempts'] ?? 3,
            $availableAt,
        ]);
    }

    private static function dbProcess(array $config, array $qCfg): bool
    {
        $pdo = self::pdo($config);
        self::ensureTable($pdo);

        // 取一条 pending 且已到期的任务并锁定
        $pdo->beginTransaction();
        $job = $pdo->query(
            "SELECT * FROM `_jobs` WHERE status='pending' AND available_at <= NOW() ORDER BY id LIMIT 1 FOR UPDATE"
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$job) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare(
            "UPDATE `_jobs` SET status='processing', attempts=attempts+1 WHERE id=?"
        )->execute([$job['id']]);
        $pdo->commit();

        try {
            self::runJob($job['name'], json_decode($job['payload'], true));
            $pdo->prepare("UPDATE `_jobs` SET status='done', ran_at=NOW() WHERE id=?")->execute([$job['id']]);
        } catch (\Throwable $e) {
            $max = (int)($job['max_attempts'] ?? $qCfg['max_attempts'] ?? 3);
            $newStatus = ($job['attempts'] + 1) >= $max ? 'failed' : 'pending';
            $pdo->prepare(
                "UPDATE `_jobs` SET status=?, error=?, ran_at=NOW() WHERE id=?"
            )->execute([$newStatus, substr($e->getMessage(), 0, 500), $job['id']]);
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Redis 驱动
    // -------------------------------------------------------------------------

    private static function redisConn(array $qCfg): \Redis
    {
        $r = new \Redis();
        $r->connect($qCfg['host'] ?? '127.0.0.1', (int)($qCfg['port'] ?? 6379));
        if (!empty($qCfg['password'])) {
            $r->auth($qCfg['password']);
        }
        return $r;
    }

    private static function redisPush(string $jobName, array $payload, array $qCfg, int $delay = 0): void
    {
        $r   = self::redisConn($qCfg);
        $key = $qCfg['key'] ?? 'h2_jobs';

        $item = json_encode([
            'name'         => $jobName,
            'payload'      => $payload,
            'available_at' => time() + $delay,
        ], JSON_UNESCAPED_UNICODE);

        if ($delay > 0) {
            // 延迟任务存入 sorted set，score = 可执行时间戳
            $r->zAdd("{$key}:delayed", time() + $delay, $item);
        } else {
            $r->rPush($key, $item);
        }
    }

    private static function redisProcess(array $config, array $qCfg): bool
    {
        $r   = self::redisConn($qCfg);
        $key = $qCfg['key'] ?? 'h2_jobs';

        // BRPOP 阻塞最多 2 秒等待任务
        $item = $r->bRPop([$key], 2);
        if (!$item) {
            return false;
        }

        $data = json_decode($item[1], true);
        try {
            self::runJob($data['name'], $data['payload'] ?? []);
        } catch (\Throwable $e) {
            // Redis 驱动：失败的任务推入 {key}:failed
            $r->rPush("{$key}:failed", json_encode([
                'job'     => $data,
                'error'   => $e->getMessage(),
                'failed_at' => date('Y-m-d H:i:s'),
            ]));
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // 执行 Job
    // -------------------------------------------------------------------------

    private static function runJob(string $name, array $payload): void
    {
        $file = defined('APP') ? APP . "/jobs/{$name}.php" : __DIR__ . "/../app/jobs/{$name}.php";

        if (!is_file($file)) {
            throw new \RuntimeException("Job 文件不存在：app/jobs/{$name}.php");
        }

        require_once $file;

        if (!class_exists($name)) {
            throw new \RuntimeException("Job 类不存在：{$name}");
        }

        $job = new $name();
        if (!method_exists($job, 'handle')) {
            throw new \RuntimeException("Job 缺少 handle() 方法：{$name}");
        }

        $job->handle($payload);
    }

    // -------------------------------------------------------------------------
    // 辅助
    // -------------------------------------------------------------------------

    private static ?array $pdoCache = null;
    private static ?\PDO   $pdoInstance = null;

    private static function pdo(array $config): \PDO
    {
        if (self::$pdoInstance === null) {
            $db = $config['db'];
            self::$pdoInstance = new \PDO(
                $db['dsn'], $db['user'], $db['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                 \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
            );
        }
        return self::$pdoInstance;
    }

    private static bool $tableEnsured = false;

    private static function ensureTable(\PDO $pdo): void
    {
        if (self::$tableEnsured) return;
        $pdo->exec("CREATE TABLE IF NOT EXISTS `_jobs` (
            `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name`         VARCHAR(100) NOT NULL,
            `payload`      TEXT NOT NULL,
            `status`       ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
            `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
            `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `error`        TEXT NULL,
            `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ran_at`       DATETIME NULL,
            INDEX idx_status_avail (status, available_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$tableEnsured = true;
    }
}

// ------------------------------------------------------------
// Scheduler (197 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Scheduler — 任务调度器（类似 Laravel Schedule）
 *
 * 使用方式：
 * 1. 在 app/schedules.php 中定义任务
 * 2. 系统 cron 中添加一条（每分钟执行一次）：
 *    * * * * * php /path/to/h2 schedule:run
 *
 * Task 文件放在 app/tasks/ 目录，类名与文件名一致，实现 handle(): void
 */
class Scheduler
{
    /** @var ScheduledTask[] */
    private array $tasks = [];

    // -------------------------------------------------------------------------
    // 注册方式
    // -------------------------------------------------------------------------

    /**
     * 注册一个 Task 类（app/tasks/{Name}.php）
     *
     * @param string $taskName Task 类名
     */
    public function call(string $taskName): ScheduledTask
    {
        $task = new ScheduledTask('task', $taskName);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * 注册一个 CLI 命令（透传给 php h2）
     *
     * @param string $command 例如 'queue:clear'
     */
    public function command(string $command): ScheduledTask
    {
        $task = new ScheduledTask('command', $command);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * 注册一个 PHP 闭包任务
     */
    public function job(callable $fn, string $name = 'closure'): ScheduledTask
    {
        $task = new ScheduledTask('closure', $name, $fn);
        $this->tasks[] = $task;
        return $task;
    }

    // -------------------------------------------------------------------------
    // 执行
    // -------------------------------------------------------------------------

    /**
     * 运行所有到期的任务（由 php h2 schedule:run 调用）
     */
    public function runDue(): void
    {
        $now = new \DateTimeImmutable();
        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                $this->runTask($task);
            }
        }
    }

    /**
     * 列出所有任务及其计划（由 php h2 schedule:list 调用）
     */
    public function listAll(): array
    {
        return $this->tasks;
    }

    private function runTask(ScheduledTask $task): void
    {
        try {
            switch ($task->type) {
                case 'task':
                    $file = defined('APP') ? APP . "/tasks/{$task->name}.php" : __DIR__ . "/../app/tasks/{$task->name}.php";
                    if (!is_file($file)) {
                        throw new \RuntimeException("Task 文件不存在：app/tasks/{$task->name}.php");
                    }
                    require_once $file;
                    if (!class_exists($task->name)) {
                        throw new \RuntimeException("Task 类不存在：{$task->name}");
                    }
                    (new $task->name())->handle();
                    break;

                case 'command':
                    $h2 = defined('ROOT') ? ROOT . '/h2' : __DIR__ . '/../h2';
                    passthru("php " . escapeshellarg($h2) . ' ' . escapeshellarg($task->name));
                    break;

                case 'closure':
                    ($task->closure)();
                    break;
            }
            echo "\033[32m✓ {$task->name}\033[0m\n";
        } catch (\Throwable $e) {
            echo "\033[31m✗ {$task->name}: {$e->getMessage()}\033[0m\n";
        }
    }
}

// =============================================================================
// ScheduledTask — 单个任务的配置（链式方法设置频率）
// =============================================================================

class ScheduledTask
{
    public string   $type;
    public string   $name;
    public ?string  $cronExpression;
    public          $closure;
    public string   $description = '';

    public function __construct(string $type, string $name, ?callable $closure = null)
    {
        $this->type    = $type;
        $this->name    = $name;
        $this->closure = $closure;
        // 默认每分钟（最高频率，is_due 由实际 expression 控制）
        $this->cronExpression = null;
    }

    // ── 频率设置 ──────────────────────────────────────────────────────────────

    /** 自定义 cron 表达式，如 '0 2 * * 0'（每周日凌晨 2 点） */
    public function cron(string $expression): self { $this->cronExpression = $expression; return $this; }

    /** 每分钟 */
    public function everyMinute(): self  { return $this->cron('* * * * *'); }

    /** 每 N 分钟 */
    public function everyMinutes(int $n): self { return $this->cron("*/{$n} * * * *"); }

    /** 每小时整点 */
    public function hourly(): self       { return $this->cron('0 * * * *'); }

    /** 每小时 N 分 */
    public function hourlyAt(int $min): self { return $this->cron("{$min} * * * *"); }

    /** 每天凌晨 0 点 */
    public function daily(): self        { return $this->cron('0 0 * * *'); }

    /** 每天指定时间，格式 'HH:MM' */
    public function dailyAt(string $time): self
    {
        [$h, $m] = explode(':', $time);
        return $this->cron(ltrim($m, '0') ?: '0') ->cron("{$m} {$h} * * *");
    }

    /** 每周一凌晨 0 点 */
    public function weekly(): self       { return $this->cron('0 0 * * 1'); }

    /** 每月 1 日凌晨 0 点 */
    public function monthly(): self      { return $this->cron('0 0 1 * *'); }

    // ── 判断是否到期 ──────────────────────────────────────────────────────────

    public function isDue(\DateTimeImmutable $now): bool
    {
        if (!$this->cronExpression) return false;

        [$min, $hour, $day, $month, $weekday] = explode(' ', $this->cronExpression);

        return self::matchField($now->format('i'), $min)
            && self::matchField($now->format('G'), $hour)
            && self::matchField($now->format('j'), $day)
            && self::matchField($now->format('n'), $month)
            && self::matchField($now->format('w'), $weekday);
    }

    private static function matchField(string $value, string $pattern): bool
    {
        if ($pattern === '*') return true;
        if (str_starts_with($pattern, '*/')) {
            $step = (int)substr($pattern, 2);
            return $step > 0 && ((int)$value % $step) === 0;
        }
        return (int)$value === (int)$pattern;
    }

    public function description(string $desc): self { $this->description = $desc; return $this; }

    public function getExpression(): string { return $this->cronExpression ?? '-'; }
}

// ------------------------------------------------------------
// Http (238 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Http — 轻量级 HTTP 客户端（基于 cURL）
 *
 * 用于调用第三方 API（微信、支付宝、短信平台等）。
 * 支持 GET/POST/PUT/PATCH/DELETE，自动处理 JSON。
 *
 * 用法：
 *   $http = new \Lib\Http();
 *   $res  = $http->get('https://api.example.com/users');
 *   $res  = $http->post('https://api.example.com/users', ['name' => 'Tom']);
 *
 *   // 链式配置
 *   $res = $http->timeout(10)->withHeaders(['Authorization' => 'Bearer xxx'])
 *              ->post($url, $data);
 *
 *   // 响应
 *   $res->status();    // 200
 *   $res->json();      // 解析后的数组
 *   $res->body();      // 原始响应体
 *   $res->headers();   // 响应头数组
 *   $res->ok();        // status >= 200 && < 300
 */
class Http
{
    private array  $headers = [];
    private int    $timeoutSec = 30;
    private bool   $verifySsl = true;
    private ?string $baseUrl = null;

    /**
     * 设置 Base URL（后续请求可只传路径）
     */
    public function baseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    /**
     * 设置超时时间（秒）
     */
    public function timeout(int $seconds): self
    {
        $this->timeoutSec = $seconds;
        return $this;
    }

    /**
     * 添加请求头
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * 携带 Bearer Token
     */
    public function withToken(string $token): self
    {
        $this->headers['Authorization'] = "Bearer {$token}";
        return $this;
    }

    /**
     * 是否跳过 SSL 验证（开发环境用）
     */
    public function withoutVerifying(): self
    {
        $this->verifySsl = false;
        return $this;
    }

    // ── 请求方法 ─────────────────────────────────────────────────────────────

    public function get(string $url, array $query = []): HttpResponse
    {
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url);
    }

    public function post(string $url, array $data = []): HttpResponse
    {
        return $this->request('POST', $url, $data);
    }

    public function put(string $url, array $data = []): HttpResponse
    {
        return $this->request('PUT', $url, $data);
    }

    public function patch(string $url, array $data = []): HttpResponse
    {
        return $this->request('PATCH', $url, $data);
    }

    public function delete(string $url, array $data = []): HttpResponse
    {
        return $this->request('DELETE', $url, $data);
    }

    /**
     * 上传文件
     *
     * 用法：$http->upload($url, '/path/to/file.jpg', 'avatar', ['user_id' => 1]);
     */
    public function upload(string $url, string $filePath, string $fieldName = 'file', array $data = []): HttpResponse
    {
        $data[$fieldName] = new \CURLFile($filePath);
        return $this->request('POST', $url, $data, false);  // false = 不编码为 JSON
    }

    // ── 核心 ─────────────────────────────────────────────────────────────────

    private function request(string $method, string $url, array $data = [], bool $json = true): HttpResponse
    {
        if ($this->baseUrl && strpos($url, '://') === false) {
            $url = $this->baseUrl . '/' . ltrim($url, '/');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSec,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_HEADER         => true,
        ]);

        if ($data && $method !== 'GET') {
            if ($json) {
                $body = json_encode($data, JSON_UNESCAPED_UNICODE);
                $this->headers['Content-Type'] = 'application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }

        if ($this->headers) {
            $formatted = [];
            foreach ($this->headers as $k => $v) {
                $formatted[] = "{$k}: {$v}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formatted);
        }

        $response   = curl_exec($ch);
        $error      = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false) {
            return new HttpResponse(0, '', [], $error);
        }

        $headerStr = substr($response, 0, $headerSize);
        $body      = substr($response, $headerSize);
        $headers   = $this->parseHeaders($headerStr);

        // 重置链式状态
        $this->headers    = [];
        $this->timeoutSec = 30;
        $this->verifySsl  = true;

        return new HttpResponse($statusCode, $body, $headers);
    }

    private function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (explode("\r\n", trim($raw)) as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $val] = explode(':', $line, 2);
                $headers[trim($key)] = trim($val);
            }
        }
        return $headers;
    }
}

/**
 * HttpResponse — HTTP 响应对象
 */
class HttpResponse
{
    private int    $statusCode;
    private string $body;
    private array  $headers;
    private string $error;

    public function __construct(int $status, string $body, array $headers, string $error = '')
    {
        $this->statusCode = $status;
        $this->body       = $body;
        $this->headers    = $headers;
        $this->error      = $error;
    }

    /** HTTP 状态码 */
    public function status(): int     { return $this->statusCode; }

    /** 原始响应体 */
    public function body(): string    { return $this->body; }

    /** 响应头数组 */
    public function headers(): array  { return $this->headers; }

    /** 获取单个响应头 */
    public function header(string $key): ?string { return $this->headers[$key] ?? null; }

    /** cURL 错误信息 */
    public function error(): string   { return $this->error; }

    /** 是否成功（2xx） */
    public function ok(): bool        { return $this->statusCode >= 200 && $this->statusCode < 300; }

    /** 是否失败 */
    public function failed(): bool    { return !$this->ok(); }

    /** JSON 解析为数组 */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : null;
    }
}

// ------------------------------------------------------------
// Redis (510 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Redis — 轻量级 Redis 封装
 *
 * 基于 phpredis 扩展，提供常用数据结构操作的便捷接口。
 * 支持：字符串、哈希、列表、集合、有序集合、分布式锁、发布/订阅、自增计数器等。
 *
 * 用法：
 *   // 方式一：在控制器中通过 $this->redis 懒加载访问
 *   $this->redis->set('key', 'value', 3600);
 *
 *   // 方式二：手动创建
 *   $redis = new \Lib\Redis($config['redis']);
 *   $redis->set('key', 'value');
 *
 * config/config.php 配置：
 *   'redis' => [
 *       'host'     => '127.0.0.1',
 *       'port'     => 6379,
 *       'password' => '',        // 无密码留空
 *       'database' => 0,         // 默认实例 0
 *       'prefix'   => 'h2_',     // key 前缀
 *       'timeout'  => 2.0,       // 连接超时（秒）
 *   ],
 */
class Redis
{
    private \Redis $conn;
    private string $prefix;

    public function __construct(array $config = [])
    {
        $host     = $config['host']     ?? '127.0.0.1';
        $port     = (int)($config['port'] ?? 6379);
        $password = $config['password'] ?? '';
        $database = (int)($config['database'] ?? 0);
        $timeout  = (float)($config['timeout'] ?? 2.0);
        $this->prefix = $config['prefix'] ?? 'h2_';

        $this->conn = new \Redis();
        $this->conn->connect($host, $port, $timeout);

        if ($password !== '') {
            $this->conn->auth($password);
        }
        if ($database > 0) {
            $this->conn->select($database);
        }
    }

    // =========================================================================
    // 字符串（String）
    // =========================================================================

    /**
     * 设置值
     *
     * @param string $key
     * @param mixed  $value  自动序列化非标量值
     * @param int    $ttl    过期秒数，0=永不过期
     */
    public function set(string $key, $value, int $ttl = 0): bool
    {
        $val = is_scalar($value) ? $value : serialize($value);
        if ($ttl > 0) {
            return $this->conn->setex($this->prefix . $key, $ttl, $val);
        }
        return $this->conn->set($this->prefix . $key, $val);
    }

    /**
     * 获取值
     *
     * @return mixed 未命中返回 null
     */
    public function get(string $key)
    {
        $val = $this->conn->get($this->prefix . $key);
        if ($val === false) return null;
        $unserialized = @unserialize($val);
        return $unserialized !== false ? $unserialized : $val;
    }

    /**
     * 删除一个或多个 key
     */
    public function del(string ...$keys): int
    {
        $keys = array_map(fn($k) => $this->prefix . $k, $keys);
        return $this->conn->del($keys);
    }

    /**
     * 判断 key 是否存在
     */
    public function exists(string $key): bool
    {
        return (bool)$this->conn->exists($this->prefix . $key);
    }

    /**
     * 设置过期时间
     */
    public function expire(string $key, int $seconds): bool
    {
        return $this->conn->expire($this->prefix . $key, $seconds);
    }

    /**
     * 获取剩余 TTL（秒），-1 永不过期，-2 key 不存在
     */
    public function ttl(string $key): int
    {
        return $this->conn->ttl($this->prefix . $key);
    }

    // =========================================================================
    // 计数器（Increment / Decrement）
    // =========================================================================

    /**
     * 自增（默认步长 1）
     */
    public function incr(string $key, int $step = 1): int
    {
        return $this->conn->incrBy($this->prefix . $key, $step);
    }

    /**
     * 自减
     */
    public function decr(string $key, int $step = 1): int
    {
        return $this->conn->decrBy($this->prefix . $key, $step);
    }

    // =========================================================================
    // 哈希（Hash）
    // =========================================================================

    /**
     * 设置哈希字段
     *
     * 用法：$redis->hSet('user:1', 'name', 'Tom');
     */
    public function hSet(string $key, string $field, $value): bool
    {
        return (bool)$this->conn->hSet($this->prefix . $key, $field, is_scalar($value) ? $value : serialize($value));
    }

    /**
     * 获取哈希字段
     */
    public function hGet(string $key, string $field)
    {
        $val = $this->conn->hGet($this->prefix . $key, $field);
        return $val === false ? null : $val;
    }

    /**
     * 批量设置哈希字段
     *
     * 用法：$redis->hMSet('user:1', ['name' => 'Tom', 'age' => 25]);
     */
    public function hMSet(string $key, array $data): bool
    {
        return $this->conn->hMSet($this->prefix . $key, $data);
    }

    /**
     * 获取哈希所有字段
     */
    public function hGetAll(string $key): array
    {
        return $this->conn->hGetAll($this->prefix . $key) ?: [];
    }

    /**
     * 删除哈希字段
     */
    public function hDel(string $key, string ...$fields): int
    {
        return $this->conn->hDel($this->prefix . $key, ...$fields);
    }

    /**
     * 哈希字段是否存在
     */
    public function hExists(string $key, string $field): bool
    {
        return $this->conn->hExists($this->prefix . $key, $field);
    }

    /**
     * 哈希字段自增
     */
    public function hIncr(string $key, string $field, int $step = 1): int
    {
        return $this->conn->hIncrBy($this->prefix . $key, $field, $step);
    }

    // =========================================================================
    // 列表（List）
    // =========================================================================

    /**
     * 从左端推入
     */
    public function lPush(string $key, ...$values): int
    {
        return $this->conn->lPush($this->prefix . $key, ...$values);
    }

    /**
     * 从右端推入
     */
    public function rPush(string $key, ...$values): int
    {
        return $this->conn->rPush($this->prefix . $key, ...$values);
    }

    /**
     * 从左端弹出
     */
    public function lPop(string $key)
    {
        $val = $this->conn->lPop($this->prefix . $key);
        return $val === false ? null : $val;
    }

    /**
     * 从右端弹出
     */
    public function rPop(string $key)
    {
        $val = $this->conn->rPop($this->prefix . $key);
        return $val === false ? null : $val;
    }

    /**
     * 获取列表长度
     */
    public function lLen(string $key): int
    {
        return $this->conn->lLen($this->prefix . $key);
    }

    /**
     * 获取列表范围
     *
     * 用法：$redis->lRange('queue', 0, -1); // 全部
     */
    public function lRange(string $key, int $start = 0, int $end = -1): array
    {
        return $this->conn->lRange($this->prefix . $key, $start, $end);
    }

    // =========================================================================
    // 集合（Set）
    // =========================================================================

    /**
     * 添加成员
     */
    public function sAdd(string $key, ...$members): int
    {
        return $this->conn->sAdd($this->prefix . $key, ...$members);
    }

    /**
     * 获取全部成员
     */
    public function sMembers(string $key): array
    {
        return $this->conn->sMembers($this->prefix . $key);
    }

    /**
     * 是否是成员
     */
    public function sIsMember(string $key, $member): bool
    {
        return $this->conn->sIsMember($this->prefix . $key, $member);
    }

    /**
     * 移除成员
     */
    public function sRem(string $key, ...$members): int
    {
        return $this->conn->sRem($this->prefix . $key, ...$members);
    }

    /**
     * 集合大小
     */
    public function sCard(string $key): int
    {
        return $this->conn->sCard($this->prefix . $key);
    }

    // =========================================================================
    // 有序集合（Sorted Set）
    // =========================================================================

    /**
     * 添加成员（带分值）
     *
     * 用法：$redis->zAdd('leaderboard', 100, 'player1');
     */
    public function zAdd(string $key, float $score, $member): int
    {
        return $this->conn->zAdd($this->prefix . $key, $score, $member);
    }

    /**
     * 按分值范围获取（从低到高）
     */
    public function zRange(string $key, int $start = 0, int $end = -1, bool $withScores = false): array
    {
        return $this->conn->zRange($this->prefix . $key, $start, $end, $withScores);
    }

    /**
     * 按分值范围获取（从高到低）
     */
    public function zRevRange(string $key, int $start = 0, int $end = -1, bool $withScores = false): array
    {
        return $this->conn->zRevRange($this->prefix . $key, $start, $end, $withScores);
    }

    /**
     * 获取成员排名（从低到高，0 起始）
     */
    public function zRank(string $key, $member): ?int
    {
        $rank = $this->conn->zRank($this->prefix . $key, $member);
        return $rank === false ? null : $rank;
    }

    /**
     * 获取成员分值
     */
    public function zScore(string $key, $member): ?float
    {
        $score = $this->conn->zScore($this->prefix . $key, $member);
        return $score === false ? null : $score;
    }

    /**
     * 移除成员
     */
    public function zRem(string $key, ...$members): int
    {
        return $this->conn->zRem($this->prefix . $key, ...$members);
    }

    /**
     * 有序集合大小
     */
    public function zCard(string $key): int
    {
        return $this->conn->zCard($this->prefix . $key);
    }

    /**
     * 成员分值自增
     *
     * 用法：$redis->zIncrBy('leaderboard', 10, 'player1');
     */
    public function zIncrBy(string $key, float $increment, $member): float
    {
        return $this->conn->zIncrBy($this->prefix . $key, $increment, $member);
    }

    // =========================================================================
    // 分布式锁
    // =========================================================================

    /**
     * 获取锁
     *
     * @param string $name    锁名称
     * @param int    $ttl     自动释放时间（秒），防止死锁
     * @param string $token   锁令牌（留空自动生成），释放时需要
     * @return string|false   成功返回 token，失败返回 false
     *
     * 用法：
     *   $token = $redis->lock('order:create', 10);
     *   if ($token) {
     *       // 执行互斥操作...
     *       $redis->unlock('order:create', $token);
     *   }
     */
    public function lock(string $name, int $ttl = 10, string $token = ''): string
    {
        $token = $token ?: bin2hex(random_bytes(16));
        $key   = $this->prefix . 'lock:' . $name;
        $ok    = $this->conn->set($key, $token, ['NX', 'EX' => $ttl]);
        return $ok ? $token : false;
    }

    /**
     * 释放锁（安全：仅持有者可释放）
     */
    public function unlock(string $name, string $token): bool
    {
        $key = $this->prefix . 'lock:' . $name;
        // Lua 脚本保证原子性
        $script = <<<'LUA'
if redis.call('get', KEYS[1]) == ARGV[1] then
    return redis.call('del', KEYS[1])
else
    return 0
end
LUA;
        return (bool)$this->conn->eval($script, [$key, $token], 1);
    }

    // =========================================================================
    // 发布/订阅
    // =========================================================================

    /**
     * 发布消息
     *
     * 用法：$redis->publish('chat', json_encode($msg));
     */
    public function publish(string $channel, string $message): int
    {
        return $this->conn->publish($this->prefix . $channel, $message);
    }

    /**
     * 订阅频道（阻塞）
     *
     * 用法：
     *   $redis->subscribe(['chat'], function($redis, $channel, $msg) {
     *       echo "收到: {$msg}\n";
     *   });
     */
    public function subscribe(array $channels, callable $callback): void
    {
        $channels = array_map(fn($c) => $this->prefix . $c, $channels);
        $this->conn->subscribe($channels, $callback);
    }

    // =========================================================================
    // 管道（Pipeline）
    // =========================================================================

    /**
     * 管道批量执行（减少网络往返）
     *
     * 用法：
     *   $results = $redis->pipeline(function($pipe) {
     *       $pipe->set('a', '1');
     *       $pipe->set('b', '2');
     *       $pipe->get('a');
     *   });
     */
    public function pipeline(callable $callback): array
    {
        $pipe = $this->conn->multi(\Redis::PIPELINE);
        $callback($pipe);
        return $pipe->exec();
    }

    // =========================================================================
    // 辅助
    // =========================================================================

    /**
     * 按前缀模式查找 key
     *
     * 用法：$keys = $redis->keys('user:*');
     * 注意：生产环境大数据量慎用，推荐 SCAN
     */
    public function keys(string $pattern = '*'): array
    {
        return $this->conn->keys($this->prefix . $pattern);
    }

    /**
     * 清空当前数据库
     */
    public function flushDb(): bool
    {
        return $this->conn->flushDB();
    }

    /**
     * 获取底层 \Redis 对象（高级操作）
     */
    public function connection(): \Redis
    {
        return $this->conn;
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        $this->conn->close();
    }
}

// ------------------------------------------------------------
// Pagination (142 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Pagination — 独立分页器
 *
 * 用法：
 *   $pager = new Pagination($totalRows, $currentPage, $perPage);
 *   $pager->offset();      // SQL OFFSET 值
 *   $pager->totalPages();  // 总页数
 *   $pager->links('/posts?page='); // 生成 HTML 分页链接
 *
 *   // 或直接从查询构建
 *   $pager = Pagination::fromQuery($this->db->table('posts')->where('status=?',['published']), $page, 10);
 *   $posts = $pager->items();      // 当前页数据
 */
class Pagination
{
    private int $total;
    private int $page;
    private int $perPage;
    private int $totalPages;
    private array $items = [];

    public function __construct(int $total, int $page = 1, int $perPage = 10)
    {
        $this->total      = max(0, $total);
        $this->perPage    = max(1, $perPage);
        $this->totalPages = max(1, (int)ceil($this->total / $this->perPage));
        $this->page       = max(1, min($page, $this->totalPages));
    }

    /**
     * 从 DB 查询自动构建分页
     */
    public static function fromQuery(DB $query, int $page = 1, int $perPage = 10): self
    {
        $total = $query->count();
        $pager = new self($total, $page, $perPage);
        $pager->items = $query
            ->limit($perPage, $pager->offset())
            ->fetchAll();
        return $pager;
    }

    /** 当前页码 */
    public function currentPage(): int  { return $this->page; }

    /** 每页条数 */
    public function perPage(): int      { return $this->perPage; }

    /** 总记录数 */
    public function total(): int        { return $this->total; }

    /** 总页数 */
    public function totalPages(): int   { return $this->totalPages; }

    /** SQL OFFSET */
    public function offset(): int       { return ($this->page - 1) * $this->perPage; }

    /** 是否有上一页 */
    public function hasPrev(): bool     { return $this->page > 1; }

    /** 是否有下一页 */
    public function hasNext(): bool     { return $this->page < $this->totalPages; }

    /** 当前页数据（需通过 fromQuery 或 setItems 设置） */
    public function items(): array      { return $this->items; }

    /** 设置当前页数据 */
    public function setItems(array $items): self { $this->items = $items; return $this; }

    /**
     * 生成 HTML 分页链接
     *
     * @param string $urlPattern URL 模式，{page} 会被替换为页码
     *                           例如：'/posts?page={page}' 或 '/posts/page/{page}'
     */
    public function links(string $urlPattern = '?page={page}'): string
    {
        if ($this->totalPages <= 1) return '';

        $html = '<nav class="pagination">';

        // 上一页
        if ($this->hasPrev()) {
            $url = str_replace('{page}', $this->page - 1, $urlPattern);
            $html .= "<a href=\"{$url}\" class=\"page-prev\">&laquo; 上一页</a>";
        }

        // 页码（显示当前页前后各 2 页）
        $start = max(1, $this->page - 2);
        $end   = min($this->totalPages, $this->page + 2);

        if ($start > 1) {
            $url = str_replace('{page}', '1', $urlPattern);
            $html .= "<a href=\"{$url}\" class=\"page-num\">1</a>";
            if ($start > 2) $html .= '<span class="page-dots">...</span>';
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $this->page) {
                $html .= "<span class=\"page-num active\">{$i}</span>";
            } else {
                $url = str_replace('{page}', $i, $urlPattern);
                $html .= "<a href=\"{$url}\" class=\"page-num\">{$i}</a>";
            }
        }

        if ($end < $this->totalPages) {
            if ($end < $this->totalPages - 1) $html .= '<span class="page-dots">...</span>';
            $url = str_replace('{page}', $this->totalPages, $urlPattern);
            $html .= "<a href=\"{$url}\" class=\"page-num\">{$this->totalPages}</a>";
        }

        // 下一页
        if ($this->hasNext()) {
            $url = str_replace('{page}', $this->page + 1, $urlPattern);
            $html .= "<a href=\"{$url}\" class=\"page-next\">下一页 &raquo;</a>";
        }

        $html .= '</nav>';
        return $html;
    }

    /**
     * 转数组（用于 API 响应）
     */
    public function toArray(): array
    {
        return [
            'data'         => $this->items,
            'current_page' => $this->page,
            'per_page'     => $this->perPage,
            'total'        => $this->total,
            'total_pages'  => $this->totalPages,
            'has_prev'     => $this->hasPrev(),
            'has_next'     => $this->hasNext(),
        ];
    }
}

// ------------------------------------------------------------
// Router (202 lines)
// ------------------------------------------------------------

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
        // 优先级：
        //   1. Apache .htaccess → $_GET['_route']
        //   2. ?path/to/page 风格 → QUERY_STRING（无 = 号）
        //   3. /path/to/page 风格 → REQUEST_URI（PHP 内置服务器 pathinfo）
        $uri = $_GET['_route'] ?? '';
        unset($_GET['_route']);

        if ($uri === '') {
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            // QUERY_STRING 不含 = 号时视作路由路径（?user/login 风格）
            if ($qs !== '' && strpos($qs, '=') === false) {
                $uri = $qs;
            }
        }

        if ($uri === '') {
            // 从 REQUEST_URI 提取路径（/user/login 风格）
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            // 子目录部署时，剥离 base_path 前缀
            $basePath = $config['base_path'] ?? '';
            if ($basePath !== '' && strpos($path, $basePath) === 0) {
                $path = substr($path, strlen($basePath));
            }
            if ($path !== '/' && $path !== '' && $path !== false) {
                $uri = ltrim($path, '/');
            }
        }

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

// ------------------------------------------------------------
// Core (624 lines)
// ------------------------------------------------------------

namespace Lib;

/**
 * Core — 基类控制器
 * 所有控制器中的 main 类都继承此类
 */
class Core
{
    /** @var array 要传递给模板的变量 */
    private array $vars = [];

    /** @var \Lib\DB|null 数据库实例（懒加载） */
    private ?DB $dbInstance = null;

    /** @var \Lib\Request|null 请求实例（懒加载） */
    private ?Request $requestInstance = null;

    /** @var array 框架配置 */
    protected array $config = [];

    /** @var string 当前控制器路径（a/b），用于自动推断模板 */
    public string $_path = '';

    /**
     * 跳过 before() 的方法列表
     *
     * 在子类中设置，列出的方法不会调用 before() 钩子。
     *
     * 示例：公开 index/list 页面，其他方法需要登录
     *   protected array \$skipBefore = ['index', 'list'];
     *
     * @var string[]
     */
    protected array $skipBefore = [];

    /**
     * 控制器级中间件列表
     *
     * 在子类中设置，仅当前控制器的请求经过这些中间件。
     * 中间件文件放在 app/middleware/ 目录，类名与文件名一致。
     *
     * 示例：仅此控制器需要鉴权中间件
     *   protected array $middleware = ['AuthCheck'];
     *
     * @var string[]
     */
    protected array $middleware = [];

    /** 获取控制器级中间件列表（供 Router 调用） */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    // -------------------------------------------------------------------------
    // 模板变量传递
    // -------------------------------------------------------------------------

    /**
     * 向模板传递变量
     *
     * @param string $key   变量名
     * @param mixed  $value 变量值
     */
    public function set(string $key, $value): void
    {
        $this->vars[$key] = $value;
    }

    /**
     * 批量向模板传递变量
     *
     * @param array $data 关联数组
     */
    public function setMulti(array $data): void
    {
        foreach ($data as $k => $v) {
            $this->vars[$k] = $v;
        }
    }

    // -------------------------------------------------------------------------
    // 布局与局部模板
    // -------------------------------------------------------------------------

    /** @var string|null 布局文件路径（不含扩展名），null = 不使用布局 */
    private ?string $layout = null;

    /**
     * 设置布局文件
     *
     * 布局文件放在 views/_layouts/ 目录下，
     * 通过 $content 变量获取页面主体内容。
     *
     * 用法：$this->layout('main');  → views/_layouts/main.html
     *       $this->layout(null);    → 不使用布局
     */
    public function layout(?string $name): void
    {
        $this->layout = $name;
    }

    /**
     * 引入局部模板（header、footer、sidebar 等）
     *
     * 局部模板放在 views/_partials/ 目录下。
     * 用法：$this->partial('header', ['title' => '首页']);
     *       → views/_partials/header.html，$title 可直接访问
     */
    public function partial(string $name, array $vars = []): void
    {
        $file = $this->config['path']['views'] . "/_partials/{$name}.html";
        if (!is_file($file)) {
            echo "<!-- partial not found: _partials/{$name}.html -->";
            return;
        }
        extract(array_merge($this->vars, $vars), EXTR_SKIP);
        include $file;
    }

    // -------------------------------------------------------------------------
    // 渲染
    // -------------------------------------------------------------------------

    /**
     * 生成带 base_path 前缀的 URL
     *
     * @param string $path  路径（如 '/user/login'）
     * @return string       完整路径（如 '/h2php/user/login'）
     */
    public function url(string $path = '/'): string
    {
        $base = rtrim($this->config['base_path'] ?? '', '/');
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * 渲染模板
     *
     * 自动查找顺序（$tpl 为 null 时）：
     *   1. views/a/b/c.html  （精确到方法）
     *   2. views/a/b.html    （控制器级，fallback）
     *
     * 如果通过 layout() 设置了布局，页面内容会注入布局的 $content 变量。
     *
     * @param string|null $tpl  手动指定模板路径（不含扩展名），指定后不走 fallback
     * @param string      $ext  模板文件扩展名，默认 .html
     */
    public function render(?string $tpl = null, string $ext = '.html'): void
    {
        $viewsBase = $this->config['path']['views'];

        if ($tpl !== null) {
            $viewFile = $viewsBase . '/' . $tpl . $ext;
        } else {
            $viewFile = $viewsBase . '/' . $this->_path . $ext;

            if (!is_file($viewFile)) {
                $parts    = explode('/', $this->_path);
                $fallback = $parts[0] . '/' . ($parts[1] ?? '');
                $viewFile = $viewsBase . '/' . $fallback . $ext;
            }
        }

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo "模板文件不存在：{$viewFile}";
            exit;
        }

        // 自动注入 basePath 变量，视图中用 $basePath.'/user/login'
        if (!isset($this->vars['basePath'])) {
            $this->vars['basePath'] = rtrim($this->config['base_path'] ?? '', '/');
        }

        extract($this->vars, EXTR_SKIP);

        if ($this->layout !== null) {
            // 有布局：用输出缓冲捕获页面内容，再注入布局 $content
            ob_start();
            include $viewFile;
            $content = ob_get_clean();

            $layoutFile = $viewsBase . '/_layouts/' . $this->layout . $ext;
            if (!is_file($layoutFile)) {
                http_response_code(500);
                echo "布局文件不存在：_layouts/{$this->layout}{$ext}";
                exit;
            }
            include $layoutFile;
        } else {
            include $viewFile;
        }
    }

    /**
     * 以 JSON 格式输出数据（API 接口用）
     */
    public function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // -------------------------------------------------------------------------
    // 跳转
    // -------------------------------------------------------------------------

    /**
     * 跳转到指定 URL
     *
     * 框架内路径（以 / 开头）会自动拼接 base_path 前缀。
     * 外部 URL（以 http 开头）直接跳转，不拼前缀。
     *
     * @param string $url  目标 URL（如 '/user/login' 或 'https://...'）
     * @param int    $code HTTP 状态码，默认 302
     */
    public function redirect(string $url, int $code = 302): void
    {
        // 框架内路径自动拼接 base_path
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = $this->url($url);
        }
        http_response_code($code);
        header("Location: {$url}");
        exit;
    }

    // -------------------------------------------------------------------------
    // Flash 消息（跨请求一次性提示）
    // -------------------------------------------------------------------------

    /**
     * 设置 Flash 消息（存入 Session）
     *
     * 用法：$this->flash('success', '操作成功');
     *       $this->flash('error',   '删除失败');
     */
    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * 读取并清除 Flash 消息（只能消费一次）
     *
     * 用法：$msg = $this->getFlash('success');   // '操作成功' 或 null
     *
     * 模板中也可以直接通过 $this->set() 传递全部 flash:
     *   $this->set('flash', $this->getAllFlash());
     */
    public function getFlash(string $type): ?string
    {
        $msg = $_SESSION['_flash'][$type] ?? null;
        unset($_SESSION['_flash'][$type]);
        return $msg;
    }

    /**
     * 读取并清除所有 Flash 消息
     * 返回关联数组，如 ['success' => '...', 'error' => '...']
     */
    public function getAllFlash(): array
    {
        $all = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $all;
    }

    // -------------------------------------------------------------------------
    // 分页辅助
    // -------------------------------------------------------------------------

    /**
     * 生成分页数据
     *
     * @param int    $total    总记录数
     * @param int    $page     当前页码（从 1 开始）
     * @param int    $pagesize 每页条数
     * @param string $baseUrl  基础 URL，不含页码段，如 '/article/list/show'
     *
     * @return array [
     *   'page'    => 当前页,
     *   'pages'   => 总页数,
     *   'total'   => 总条数,
     *   'limit'   => 每页条数,
     *   'offset'  => SQL OFFSET,
     *   'hasPrev' => bool,
     *   'hasNext' => bool,
     *   'prevUrl' => 上一页 URL 或 null,
     *   'nextUrl' => 下一页 URL 或 null,
     *   'links'   => [['page'=>N, 'url'=>'...', 'active'=>bool], ...]
     * ]
     */
    public function paginate(int $total, int $page, int $pagesize, string $baseUrl = ''): array
    {
        $page     = max(1, $page);
        $pagesize = max(1, $pagesize);
        $pages    = $total > 0 ? (int)ceil($total / $pagesize) : 1;
        $page     = min($page, $pages);
        $offset   = ($page - 1) * $pagesize;

        $url = function(int $p) use ($baseUrl, $pagesize): string {
            return $baseUrl . '/' . $p . '/' . $pagesize;
        };

        // 生成页码链接（最多显 7 个页码按鈕）
        $links = [];
        $start = max(1, $page - 3);
        $end   = min($pages, $page + 3);
        for ($i = $start; $i <= $end; $i++) {
            $links[] = ['page' => $i, 'url' => $url($i), 'active' => $i === $page];
        }

        return [
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'limit'   => $pagesize,
            'offset'  => $offset,
            'hasPrev' => $page > 1,
            'hasNext' => $page < $pages,
            'prevUrl' => $page > 1      ? $url($page - 1) : null,
            'nextUrl' => $page < $pages ? $url($page + 1) : null,
            'links'   => $links,
        ];
    }

    // -------------------------------------------------------------------------
    // 表单验证
    // -------------------------------------------------------------------------

    /**
     * 创建验证器
     *
     * @param array $data    待验证数据（通常是 $_POST）
     * @param array $rules   [字段 => 'rule1|rule2:param|...']
     * @param array $labels  [字段 => '显示名称']（可选，用于错误提示）
     *
     * @return \Lib\Validator
     *
     * 示例：
     *   $v = $this->validate($_POST, [
     *       'name'  => 'required|max_len:50',
     *       'email' => 'required|email|unique:users,email',
     *       'age'   => 'required|integer|min:1|max:150',
     *   ], ['name' => '姓名', 'email' => '邮箱', 'age' => '年龄']);
     *
     *   if ($v->fails()) {
     *       $this->flash('error', $v->firstError());
     *       $this->redirect('/user/register');
     *   }
     */
    public function validate(array $data, array $rules, array $labels = []): \Lib\Validator
    {
        return new \Lib\Validator($data, $rules, $labels, $this->dbInstance);
    }

    // -------------------------------------------------------------------------
    // 事件
    // -------------------------------------------------------------------------

    /**
     * 注册事件监听器（当前请求内有效）
     *
     * 用法：$this->on('user.registered', function($user) { ... });
     */
    public function on(string $event, callable $listener): void
    {
        \Lib\Event::on($event, $listener);
    }

    /**
     * 触发事件
     *
     * 用法：$this->fire('user.registered', $user);
     */
    public function fire(string $event, $payload = null): void
    {
        \Lib\Event::fire($event, $payload);
    }

    // -------------------------------------------------------------------------
    // HTTP 响应辅助
    // -------------------------------------------------------------------------

    /**
     * 终止并输出错误页面（支持自定义错误模板）
     *
     * 用法：$this->abort(403, '无权访问');
     */
    public function abort(int $code, string $message = ''): void
    {
        \Lib\Router::abort($code, $message);
    }

    /**
     * 写入日志
     *
     * 用法：
     *   $this->log('info', '用户登录', ['user_id' => $id]);
     *   $this->log('error', '支付失败', ['reason' => $msg]);
     *
     * @param string $level   info | warning | error | debug
     * @param string $message 日志消息
     * @param array  $context 附加数据
     */
    public function log(string $level, string $message, array $context = []): void
    {
        \Lib\Logger::write($level, $message, $context);
    }

    /**
     * 发送邮件（快捷方式）
     *
     * 用法：
     *   $this->mail('user@example.com', '注册成功', '<h1>欢迎</h1>');
     *
     * 高级用法（链式）：
     *   $mail = new \Lib\Mail($this->config['mail']);
     *   $mail->to('a@b.com')->cc('c@d.com')->subject('标题')->html('<p>内容</p>')->send();
     *
     * @param string $to      收件人
     * @param string $subject 主题
     * @param string $body    正文（含 HTML 标签自动识别为 HTML 邮件）
     * @return bool 发送成功返回 true
     */
    public function mail(string $to, string $subject, string $body): bool
    {
        return \Lib\Mail::quick($this->config['mail'] ?? [], $to, $subject, $body);
    }

    /**
     * 文件上传辅助（返回可链式配置的 Upload 实例）
     *
     * 用法：
     *   $file = $this->upload('avatar', 'static/uploads/avatars');
     *   if ($file->fails()) {
     *       $this->flash('error', $file->error());
     *       $this->redirect('/user/profile');
     *   }
     *   $path = $file->path();  // 存入数据库的相对路径
     *
     * 链式配置（可选）：
     *   $file = $this->upload('photo', 'static/uploads')
     *       ->maxSize(3 * 1024 * 1024)         // 最大 3 MB
     *       ->allowTypes(['jpg', 'png', 'webp']) // 允许类型
     *       ->rename('timestamp');               // 命名策略
     *
     * @param string $field   表单 file 字段名
     * @param string $destDir 存储目录（相对 ROOT）
     */
    public function upload(string $field, string $destDir): \Lib\Upload
    {
        return new \Lib\Upload($field, $destDir);
    }

    /**
     * JSON 成功响应
     *
     * 用法：$this->success($data);
     *        $this->success($data, '操作成功');
     *
     * @param mixed  $data 响应数据
     * @param string $msg  提示信息
     * @param int    $code 业务状态码（默认 0）
     */
    public function success($data = null, string $msg = 'ok', int $code = 0): void
    {
        $this->json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }

    /**
     * JSON 失败响应
     *
     * 用法：$this->fail('参数错误');
     *        $this->fail('资源不存在', 404);
     *
     * @param string $msg  错误描述
     * @param int    $code 业务错误码（默认 -1）
     */
    public function fail(string $msg, int $code = -1): void
    {
        $this->json(['code' => $code, 'msg' => $msg, 'data' => null]);
    }

    // -------------------------------------------------------------------------
    // 队列
    // -------------------------------------------------------------------------

    /**
     * 将任务推入队列（异步执行）
     *
     * Job 文件放在 app/jobs/ 目录，类名与文件名一致，实现 handle(array $payload) 方法。
     *
     * @param int $delay 延迟秒数（0=立即，3600=1小时后）
     *
     * 用法：
     *   $this->queue('SendWelcomeEmail', ['user_id' => 5]);          // 立即
     *   $this->queue('SendReminder',    ['user_id' => 5], delay: 3600); // 1小时后
     *
     * Worker 启动：php h2 queue:work
     */
    public function queue(string $jobName, array $payload = [], int $delay = 0): void
    {
        \Lib\Queue::push($jobName, $payload, $this->config, $delay);
    }

    // -------------------------------------------------------------------------
    // CSRF 保护
    // -------------------------------------------------------------------------



    /**
     * 获取（或生成）当前 Session 的 CSRF Token
     */
    public function csrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * 输出 CSRF 隐藏字段（在表单内调用）
     *
     * 用法：{csrfField()} 在表单模板中输出
     */
    public function csrfField(): string
    {
        $token = htmlspecialchars($this->csrfToken());
        return "<input type=\"hidden\" name=\"_csrf\" value=\"{$token}\">";
    }

    /**
     * 校验 POST 请求中的 CSRF Token
     * 校验失败时直接返回 403，终止执行。
     *
     * 在处理表单提交的方法中调用：$this->csrfVerify();
     */
    public function csrfVerify(): void
    {
        $submitted = $_POST['_csrf'] ?? '';
        $expected  = $_SESSION['_csrf_token'] ?? '';

        if (!$expected || !hash_equals($expected, $submitted)) {
            \Lib\Router::abort(403, 'CSRF token 验证失败，请刷新页面后重试。');
        }
    }

    // -------------------------------------------------------------------------
    // 懒加载属性访问（db / request）
    // -------------------------------------------------------------------------

    public function __get(string $name)
    {
        if ($name === 'db') {
            if (!$this->dbInstance) {
                $this->dbInstance = new DB($this->config['db']);
            }
            return $this->dbInstance;
        }

        if ($name === 'request') {
            if (!$this->requestInstance) {
                $this->requestInstance = new Request();
            }
            return $this->requestInstance;
        }

        if ($name === 'redis') {
            if (!$this->redisInstance) {
                $this->redisInstance = new Redis($this->config['redis'] ?? []);
            }
            return $this->redisInstance;
        }

        if ($name === 'response') {
            return new Response();
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // 可在子类覆盖的钩子
    // -------------------------------------------------------------------------

    /**
     * 在实际方法执行前调用
     *
     * 子类实现鉴权时，通过 $skipBefore 跳过特定方法：
     *   protected array $skipBefore = ['index', 'list'];
     *
     * 注意：Router 会将当前方法名写入 $this->_method，不需自行获取。
     */
    public function before(): void {}

    /**
     * 在实际方法执行后调用
     */
    public function after(): void {}

    // -------------------------------------------------------------------------
    // 内部：操作 skipBefore
    // -------------------------------------------------------------------------

    /** 当前调用的方法名（由 Router 写入） */
    public string $_method = '';

    /**
     * 判断当前方法是否应跳过 before()
     */
    final public function shouldRunBefore(): bool
    {
        return !in_array($this->_method, $this->skipBefore, true);
    }
}

// ------------------------------------------------------------
// Bootstrap (79 lines)
// ------------------------------------------------------------

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

        // ── 1. 启动 Session ──────────────────────────────────────
        if (session_status() === PHP_SESSION_NONE) {
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

        // ── 4. 自动加载 lib/ 下其他扩展库 ────────────────────────
        $skip = ['Request.php', 'DB.php', 'Core.php', 'Router.php',
                 'StaticFile.php', 'Bootstrap.php'];
        foreach (glob(LIB . '/*.php') as $file) {
            if (!in_array(basename($file), $skip)) {
                require_once $file;
            }
        }

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

