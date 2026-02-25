<?php
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
        // 安全校验：禁止路径穿越
        if (preg_match('/\.\.|[\/\\\\]\.\./', $name)) {
            echo '<!-- partial name invalid -->';
            return;
        }
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
            echo '模板文件不存在';
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
                echo '布局文件不存在';
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

        // 验证成功后轮换 token，防止泄露后被重用
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    // -------------------------------------------------------------------------
    // 懒加载属性访问（db / request）
    // -------------------------------------------------------------------------

    public function __get(string $name)
    {
        if ($name === 'db') {
            if (!$this->dbInstance) {
                $this->dbInstance = DB::instance($this->config['db']);
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
                $this->redisInstance = Redis::instance($this->config['redis'] ?? []);
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
