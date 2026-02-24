<?php
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
