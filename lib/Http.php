<?php
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

        // 重置每次请求的链式状态（baseUrl 保留）
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
