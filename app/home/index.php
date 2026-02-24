<?php
/**
 * 示例控制器：首页
 * 对应路由：/ 或 /home/index
 */

class main extends \Lib\Core
{
    // 访问：http://localhost/h2php/
    public function index(): void
    {
        $this->set('title', 'H2PHP 框架 — 欢迎');
        $this->set('version', '1.0.0');
        $this->set('routes', [
            '/'                         => '首页',
            '/home/index'               => '首页（显式）',
            '/user/login'               => '用户登录',
            '/user/login/show/42'       => '查看用户（id=42）',
            '/article/list/show/1/20'   => '文章列表（第1页，每页20条）',
        ]);
        $this->render(); // → views/home/index.html
    }
}
