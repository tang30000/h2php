# H2PHP

> 轻量、原生、无侵入的 PHP MVC 框架

H2PHP 是一个极简的单入口 PHP 框架。路由即目录结构，模板与逻辑分离，没有复杂配置，没有 Composer 依赖，保留 PHP 原生开发的舒适度。

---

## 特性

- **单入口路由** — 所有请求经由根目录 `index.php` 分发
- **目录即路由** — URL 结构与文件目录一一对应，一眼看懂
- **数字位置参数** — URL 中的数字段自动注入为方法参数（分页、ID 等）
- **模板分离** — 控制器与 HTML 模板完全隔离，模板中可使用 PHP 语法
- **极简依赖** — 仅需 PHP 7.2+，无需 Composer，无需任何第三方库
- **链式 DB** — 内置 PDO 封装，支持链式查询，也支持原生 SQL

---

## 目录结构

```
h2php/
├── index.php              # 单入口引导（无需修改）
├── .htaccess              # Apache URL 重写
├── nginx.conf.example     # Nginx 配置参考
│
├── config/
│   └── config.php         # 数据库、路由默认值、调试开关
│
├── lib/                   # 框架核心（无需修改）
│   ├── Router.php         # 路由解析与分发
│   ├── Core.php           # 基类控制器
│   ├── DB.php             # PDO 数据库封装
│   └── Request.php        # 请求封装
│
├── app/                   # 你的控制器代码
│   └── {模块}/{功能}.php
│
├── views/                 # HTML 模板（支持两级，见下方说明）
│   ├── {模块}/{功能}/{方法}.html   # 精确到方法（优先）
│   └── {模块}/{功能}.html         # 控制器级（fallback）
│
└── static/                # 静态资源（CSS / JS / 图片）
```

---

## 路由规则

```
URL:  /{a}/{b}/{c}/{d1}/{d2}
       │   │   │   └─── 数字位置参数（注入为方法参数）
       │   │   └─────── 方法名（main 类中的 public 方法）
       │   └─────────── 文件名 → app/{a}/{b}.php
       └─────────────── 目录   → app/{a}/
```

| URL | 文件 | 调用 |
|-----|------|------|
| `/` | `app/home/index.php` | `main::index()` |
| `/user/login` | `app/user/login.php` | `main::index()` |
| `/user/login/submit` | `app/user/login.php` | `main::submit()` |
| `/article/list/show/3` | `app/article/list.php` | `main::show(3)` |
| `/article/list/show/3/2` | `app/article/list.php` | `main::show(3, 2)` |

`?key=val` 格式的额外参数通过 `$_GET` / `$_POST` 正常获取。

---

## 快速开始

### 1. 部署

**Apache**：将项目放入 Web 根目录，`.htaccess` 已包含重写规则，开箱即用。

**Nginx**：参考 `nginx.conf.example` 配置 URL 重写。

**PHP 内置服务器**（开发调试）：
```bash
php -S localhost:8080 index.php
```

### 2. 修改配置

编辑 `config/config.php`，填入数据库连接信息：

```php
'db' => [
    'dsn'      => 'mysql:host=localhost;dbname=your_db;charset=utf8mb4',
    'user'     => 'root',
    'password' => 'your_password',
],
```

### 3. 新建一个页面

**创建控制器** `app/goods/detail.php`：

```php
<?php
class main extends \Lib\Core
{
    // 访问 /goods/detail/view/100 → view(100)
    public function view(int $id): void
    {
        $goods = $this->db->table('goods')->where('id=?', [$id])->fetch();
        $this->set('goods', $goods);
        $this->render();
        // render() 自动查找模板，优先级：
        //   1. views/goods/detail/view.html  （精确到方法）
        //   2. views/goods/detail.html        （fallback）
    }
}
```

**创建模板** `views/goods/detail/view.html`（或 `views/goods/detail.html`）：

```html
<!DOCTYPE html>
<html>
<body>
    <h1><?= htmlspecialchars($goods['name']) ?></h1>
    <p>价格：¥<?= $goods['price'] ?></p>
</body>
</html>
```

访问 `/goods/detail/view/100`，无需任何额外配置。

> **模板查找规则**：`render()` 先找 `views/a/b/c.html`，不存在则自动降级到 `views/a/b.html`。两种目录组织方式可混用。

---

## Core 基类 API

| 方法 | 说明 |
|------|------|
| `$this->set($key, $val)` | 向模板传递变量 |
| `$this->setMulti($array)` | 批量传递变量 |
| `$this->render($tpl)` | 渲染模板（默认同名模板） |
| `$this->json($data)` | 输出 JSON（API 接口用） |
| `$this->redirect($url)` | 跳转 |
| `$this->db` | DB 实例（懒加载） |
| `$this->request` | Request 实例（懒加载） |
| `before()` | 钩子：方法执行前（可在子类覆盖，用于鉴权） |
| `after()` | 钩子：方法执行后 |

---

## DB 链式查询

```php
// 查询多行
$users = $this->db->table('users')
    ->where('status=?', [1])
    ->order('id DESC')
    ->limit(20, ($page - 1) * 20)
    ->fetchAll();

// 查询单行
$user = $this->db->table('users')->where('id=?', [$id])->fetch();

// 统计
$total = $this->db->table('users')->count();

// 插入（返回自增 ID）
$id = $this->db->table('users')->insert(['name' => 'Tom', 'email' => 'tom@example.com']);

// 更新
$this->db->table('users')->where('id=?', [$id])->update(['name' => 'Jerry']);

// 删除
$this->db->table('users')->where('id=?', [$id])->delete();

// 原生 SQL
$rows = $this->db->query('SELECT * FROM users WHERE age > ?', [18]);
```

---

## 鉴权示例（before 钩子）

```php
class main extends \Lib\Core
{
    public function before(): void
    {
        if (empty($_SESSION['user'])) {
            $this->redirect('/user/login');
        }
    }

    public function dashboard(): void
    {
        // before() 验证通过后才会执行
        $this->render();
    }
}
```

---

## Request 封装

```php
$this->request->get('keyword', '');     // $_GET，带默认值
$this->request->post('username', '');   // $_POST，带默认值
$this->request->input('key', '');       // GET + POST 合并（POST 优先）
$this->request->isPost();               // 是否 POST 请求
$this->request->isAjax();              // 是否 AJAX 请求
$this->request->ip();                   // 客户端 IP
```

---

## 环境要求

- PHP 7.2+
- Apache（mod_rewrite）或 Nginx
- PDO + PDO_MySQL 扩展（使用数据库时）

---

## License

MIT
