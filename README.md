# H2PHP

> 轻量、原生、无侵入的 PHP MVC 框架
>
> **史上最轻便高效的 MVC 框架。**

[English Documentation](README.en.md)

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
│   ├── _layouts/            # 布局文件
│   ├── _partials/           # 局部模板
│   ├── _errors/             # 自定义错误页
│   │   ├── 404.html
│   │   └── 500.html
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

> ⚠️ 内置服务器不支持 URL Rewrite，路由需改用 `?` 前缀：
> - 正常部署：`http://localhost/user/login/show/42`
> - 内置服务器：`http://localhost:8080?user/login/show/42`

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
| `$this->layout($name)` | 设置布局文件 |
| `$this->partial($name)` | 引入局部模板 |
| `$this->flash($type, $msg)` | 设置 Flash 消息（跨请求一次性） |
| `$this->getFlash($type)` | 读取并清除指定 Flash |
| `$this->getAllFlash()` | 读取并清除所有 Flash |
| `$this->paginate($total,$page,$size,$url)` | 生成分页数据数组 |
| `$this->csrfField()` | 返回 CSRF 隐藏字段 HTML |
| `$this->csrfVerify()` | 校验 CSRF token |

| `$this->db` | DB 实例（懒加载） |
| `$this->request` | Request 实例（懒加载） |
| `before()` | 钩子：方法执行前（可在子类覆盖，用于鉴权） |
| `after()` | 钩子：方法执行后 |

---

## Flash 消息

```php
// 操作后写入 Flash 再跳转
public function delete(int $id): void {
    $this->db->table('users')->where('id=?', [$id])->delete();
    $this->flash('success', '删除成功');
    $this->redirect('/user/list');
}

// 接收页把 Flash 传给模板
public function index(): void {
    $this->set('flash', $this->getAllFlash());
    $this->render();
}
```

```html
<?php if (!empty($flash['success'])): ?>
<div class="alert-success"><?= htmlspecialchars($flash['success']) ?></div>
<?php endif; ?>
```

---

## 分页辅助

```php
public function list(int $page = 1, int $size = 20): void {
    $total = $this->db->table('articles')->count();
    $p     = $this->paginate($total, $page, $size, '/article/list/show');

    $articles = $this->db->table('articles')
        ->order('id DESC')
        ->limit($p['limit'], $p['offset'])
        ->fetchAll();

    $this->setMulti(['articles' => $articles, 'p' => $p]);
    $this->render();
}
```

```html
<?php if ($p['hasPrev']): ?><a href="<?= $p['prevUrl'] ?>">上一页</a><?php endif; ?>
<?php foreach ($p['links'] as $link): ?>
<a href="<?= $link['url'] ?>" <?= $link['active'] ? 'class="active"' : '' ?>><?= $link['page'] ?></a>
<?php endforeach; ?>
<?php if ($p['hasNext']): ?><a href="<?= $p['nextUrl'] ?>">下一页</a><?php endif; ?>
```

---

## 自定义错误页

在 `views/_errors/` 下创建错误页模板，框架自动使用：

```
views/_errors/
├── 404.html   ← 控制器/方法不存在
└── 500.html   ← 服务器错误
```

模板内可用三个变量：`$code`（状态码）、`$title`（标题）、`$message`（详细信息）。不存在时自动回退内置样式。
---

## 查询缓存

在链式查询的末尾加 `->cache(秒数)` 即可，缓存 key 由 `md5(SQL + 参数)` 自动生成。

```php
// 缓存 300 秒（有缓存直接返回，未命中则查库后缓存）
$articles = $this->db->table('articles')
    ->where('status=?', [1])
    ->order('id DESC')
    ->cache(300)
    ->fetchAll();

// 强制刷新（第二个参数 true）：忽略旧缓存，重新查库并覆盖
// 常用于写操作后主动刷新热点数据
$articles = $this->db->table('articles')
    ->where('status=?', [1])
    ->cache(300, true)
    ->fetchAll();
```

**典型模式** — 更新后主动刷新缓存：

```php
public function update(int $id, array $data): void {
    $this->db->table('articles')->where('id=?', [$id])->update($data);

    // 主动刷新列表和详情的缓存
    $this->db->table('articles')->order('id DESC')->limit(20)->cache(300, true)->fetchAll();
    $this->db->table('articles')->where('id=?', [$id])->cache(3600, true)->fetch();

    $this->flash('success', '更新成功');
    $this->redirect('/article/list');
}
```

驱动在 `config/config.php` 中配置（默认 file，无需任何扩展）：

```php
'cache' => [
    'driver' => 'file',   // file | redis | memcache | memcached
    'host'   => '127.0.0.1',
    'port'   => 6379,
    'prefix' => 'h2_',
],
```

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

## 表单验证

```php
$v = $this->validate($_POST, [
    'username' => 'required|min_len:3|max_len:20',
    'email'    => 'required|email|unique:users,email',
    'password' => 'required|min_len:6|confirmed',
    'age'      => 'required|integer|min:1|max:150',
], [
    'username' => '用户名',
    'email'    => '邮箱',
    'password' => '密码',
    'age'      => '年龄',
]);

if ($v->fails()) {
    $this->flash('error', $v->firstError());
    $this->redirect('/user/register');
}
```

| 规则 | 含义 |
|------|------|
| `required` | 不能为空 |
| `email` | 邮箱格式 |
| `integer` | 必须为整数 |
| `numeric` | 必须为数字 |
| `min:n` | 数值 ≥ n |
| `max:n` | 数值 ≤ n |
| `min_len:n` | 字符串长度 ≥ n |
| `max_len:n` | 字符串长度 ≤ n |
| `in:a,b,c` | 值必须在列表中 |
| `regex:/pattern/` | 正则匹配 |
| `url` | URL 格式 |
| `confirmed` | 与 `{field}_confirmation` 一致（密码确认） |
| `unique:table,column` | 数据库唯一性检查 |


## 布局与局部模板

**目录约定：**
```
views/
├── _layouts/    # 布局文件
│   └── main.html
└── _partials/   # 局部模板（header、footer 等）
    ├── nav.html
    └── footer.html
```

**控制器中使用：**
```php
public function index(): void
{
    $this->layout('main');          // 使用 views/_layouts/main.html
    $this->set('title', '用户中心');
    $this->render();
}
```

**布局文件** `views/_layouts/main.html`：
```html
<!DOCTYPE html>
<html>
<head><title><?= $title ?></title></head>
<body>
    <?php $this->partial('nav') ?>       <!-- 引入 _partials/nav.html -->
    <main><?= $content ?></main>         <!-- 页面主体内容自动注入 -->
    <?php $this->partial('footer') ?>
</body>
</html>
```

> 不调用 `layout()` 时，`render()` 行为与之前完全相同，向后兼容。

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

## CSRF 保护

在表单模板中输出隐藏字段：

```html
<form method="POST" action="/user/login/submit">
    <?= $csrfField ?>    <!-- 输出 <input type="hidden" name="_csrf" value="..."> -->
    ...
</form>
```

在控制器中传递字段 + 校验：

```php
// 登录页：传递 CSRF 字段给模板
public function index(): void {
    $this->set('csrfField', $this->csrfField());
    $this->render();
}

// 提交处理：先校验
public function submit(): void {
    $this->csrfVerify();  // 失败自动 403
    // 继续处理...
}
```

| 方法 | 说明 |
|------|------|
| `$this->csrfToken()` | 获取（或生成）Session 中的 token |
| `$this->csrfField()` | 返回隐藏表单字段 HTML 字符串 |
| `$this->csrfVerify()` | 校验 POST 请求中的 token，失败自动返回 403 |

---

## 环境要求

- PHP 7.2+
- Apache（mod_rewrite）或 Nginx
- PDO + PDO_MySQL 扩展（使用数据库时）

---

## License

MIT
