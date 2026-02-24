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
        $this->set('title', 'H2PHP');
        $this->set('version', '1.0.0');
        $this->set('routes', [
            '/' => [
                'zh' => '首页',
                'en' => 'Home',
            ],
            '/home/index' => [
                'zh' => '首页（显式路由）',
                'en' => 'Home (explicit route)',
            ],
            '/user/login' => [
                'zh' => '用户登录',
                'en' => 'User login',
            ],
            '/user/login/show/42' => [
                'zh' => '查看用户（id=42）',
                'en' => 'View user (id=42)',
            ],
            '/article/list/show/1/20' => [
                'zh' => '文章列表（第1页，每页20条）',
                'en' => 'Article list (page 1, 20 per page)',
            ],
        ]);
        $this->render();
    }
}
