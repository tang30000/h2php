<?php
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
        // 安全：限制下载路径在 ROOT 目录内
        if (defined('ROOT')) {
            $real = realpath($filePath);
            if ($real === false || strpos($real, realpath(ROOT)) !== 0) {
                $this->status(403)->text('Forbidden');
                return;
            }
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
