<?php
/**
 * 示例 Job：发送欢迎邮件
 *
 * 放置路径：app/jobs/SendWelcomeEmail.php
 * 调用：$this->queue('SendWelcomeEmail', ['user_id' => 5, 'email' => 'a@b.com']);
 */
class SendWelcomeEmail
{
    public function handle(array $payload): void
    {
        $userId = $payload['user_id'] ?? null;
        $email  = $payload['email']   ?? null;

        // TODO: 实现发送邮件逻辑，例如：
        // mail($email, '欢迎注册', '感谢您注册！');

        error_log("SendWelcomeEmail: user_id={$userId}, email={$email}");
    }
}
