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
    // 渲染
    // -------------------------------------------------------------------------

    /**
     * 渲染模板
     *
     * @param string|null $tpl  模板相对路径（不含扩展名），
     *                          为 null 时自动使用当前控制器同名模板（即 a/b）
     * @param string      $ext  模板文件扩展名，默认 .html
     */
    public function render(?string $tpl = null, string $ext = '.html'): void
    {
        $tpl      = $tpl ?? $this->_path;
        $viewFile = $this->config['path']['views'] . '/' . $tpl . $ext;

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo "模板文件不存在：{$viewFile}";
            exit;
        }

        // 将变量解包到当前作用域，模板直接用 $varname 访问
        extract($this->vars, EXTR_SKIP);

        include $viewFile;
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
