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
     * @param string $url  目标 URL（支持框架内路径 如 '/user/login'）
     * @param int    $code HTTP 状态码，默认 302
     */
    public function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header("Location: {$url}");
        exit;
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
     * 用法：<?= $this->csrfField() ?>
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

        return null;
    }

    // -------------------------------------------------------------------------
    // 可在子类覆盖的钩子
    // -------------------------------------------------------------------------

    /**
     * 在实际方法执行前调用（可在子类中实现鉴权等）
     */
    public function before(): void {}

    /**
     * 在实际方法执行后调用
     */
    public function after(): void {}
}
