<?php
/**
 * 示例控制器：用户模块 — 登录
 * 对应路由：/user/login/[method]/[...d]
 */

class main extends \Lib\Core
{
    // ── 鉴权示例（可在 before 方法中实现）─────────────────────
    public function before(): void
    {
        // 此处可加 session 检查、权限验证等
        // 如需拦截：$this->redirect('/user/login'); exit;
    }

    // 访问：/user/login 或 /user/login/index
    public function index(): void
    {
        $this->set('title', '用户登录');
        $this->set('error', $this->request->get('error', ''));
        $this->set('csrfField', $this->csrfField()); // 传 CSRF 隐藏字段给模板
        $this->render();
    }

    // 处理登录表单提交
    // 访问：POST /user/login/submit
    public function submit(): void
    {
        $this->csrfVerify(); // 先验证 CSRF，失败直接 403

        if (!$this->request->isPost()) {
            $this->redirect('/user/login');
        }

        $username = $this->request->post('username', '');
        $password = $this->request->post('password', '');

        // 示例：查询数据库（实际使用时取消注释并调整表名）
        // $user = $this->db->table('users')
        //     ->where('username = ? AND password = ?', [$username, md5($password)])
        //     ->fetch();

        // 模拟验证
        if ($username === 'admin' && $password === '123456') {
            $_SESSION['user'] = $username;
            $this->redirect('/home/index');
        } else {
            $this->redirect('/user/login?error=用户名或密码错误');
        }
    }

    // 查看用户 — 演示 d 参数作为方法参数
    // 访问：/user/login/show/42   → show(42)
    // 访问：/user/login/show/42/2 → show(42, 2)（第2页）
    public function show(int $id, int $page = 1): void
    {
        // $user = $this->db->table('users')->where('id = ?', [$id])->fetch();
        $user = ['id' => $id, 'name' => '示例用户', 'email' => 'user@example.com'];

        $this->set('title', "用户 #{$id} 详情");
        $this->set('user', $user);
        $this->set('page', $page);
        $this->render();
    }

    // 用户列表 — 演示翻页
    // 访问：/user/login/list        → list(1, 20)（默认）
    // 访问：/user/login/list/2/10   → list(2, 10)（第2页，每页10条）
    public function list(int $page = 1, int $pagesize = 20): void
    {
        $offset = ($page - 1) * $pagesize;

        // $rows  = $this->db->table('users')->order('id DESC')->limit($pagesize, $offset)->fetchAll();
        // $total = $this->db->table('users')->count();
        $rows  = [['id' => 1, 'name' => '示例A'], ['id' => 2, 'name' => '示例B']];
        $total = 2;

        $this->set('title', '用户列表');
        $this->set('users', $rows);
        $this->set('page', $page);
        $this->set('pagesize', $pagesize);
        $this->set('total', $total);
        $this->set('pages', (int)ceil($total / $pagesize));
        $this->render();
    }

    // JSON 接口示例（AJAX 调用）
    // 访问：/user/login/info/42
    public function info(int $id): void
    {
        // $user = $this->db->table('users')->where('id=?', [$id])->fetch();
        $user = ['id' => $id, 'name' => '示例用户'];
        $this->json(['code' => 0, 'msg' => 'ok', 'data' => $user]);
    }
}
