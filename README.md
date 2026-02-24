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
- **数字位置参数** — URL 第 4 段起全部作为位置参数，支持字符串（slug/hash）和整数，按方法类型提示自动转型
- **模板** — 布局（Layout）、局部模板（Partial）、变量传递，保留原生 PHP 语法
- **极简依赖** — 仅需 PHP 7.2+，运行时零 Composer 依赖
- **链式 DB** — PDO 封装，支持链式查询 / 关联关系 / 查询缓存
- **表单验证** — 13 种内置规则，支持自定义标签和数据库唯一性校验
- **CSRF / Flash / 分页** — 开箱即用
- **队列 + 事件** — 数据库/Redis 双驱动，`php h2 queue:work` 启动 Worker
- **数据库迁移** — `migrations/` 目录，按批次执行/回滚
- **CLI 工具** — `php h2 make:controller` / `make:view` / `migrate` / `queue:work` / `test`
- **PHPUnit 测试** — 内置 Unit/Feature 套件，示例测试覆盖 Validator 与 DB

---

## 目录结构

```
h2php/
├── h2                     # CLI 工具（php h2 <命令>）
├── index.php              # 单入口引导（无需修改）
├── .htaccess              # Apache URL 重写
├── nginx.conf.example     # Nginx 配置参考
├── composer.json          # 仅含开发依赖（phpunit）
│
├── config/
│   └── config.php         # 数据库、缓存、队列配置
│
├── lib/                   # 框架核心（无需修改）
│   ├── Router.php         # 路由解析与分发
│   ├── Core.php           # 基类控制器（所有特性入口）
│   ├── DB.php             # PDO 链式查询 + 缓存 + 关联
│   ├── Request.php        # 请求封装
│   ├── Validator.php      # 表单验证器（13 种规则）
│   ├── Cache.php          # 查询缓存（file/redis/memcache）
│   ├── Event.php          # 请求内事件总线
│   └── Queue.php          # 持久化队列（database/redis）
│
├── app/                   # 你的控制器代码
│   ├── {模块}/{功能}.php
│   └── jobs/              # 队列 Job 文件
│       └── SendWelcomeEmail.php
│
├── migrations/            # 数据库迁移文件
│   └── 001_create_users_table.php
│
├── tests/                 # 测试文件（PHPUnit）
│   ├── bootstrap.php
│   ├── config.php         # 测试数据库配置
│   └── Unit/
│       ├── ValidatorTest.php
│       └── DBTest.php
│
├── cache/                 # file 驱动缓存目录（.gitkeep）
│
├── views/                 # HTML 模板
│   ├── _layouts/          # 布局文件
│   ├── _partials/         # 局部模板
│   ├── _errors/           # 自定义错误页
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
       │   │   │   └─── 位置参数（字符串/整数，按方法类型提示转型）
       │   │   └───── 方法名（main 类中的 public 方法）
       │   └───────── 文件名 → app/{a}/{b}.php
       └───────────── 目录   → app/{a}/
```

| URL | 文件 | 调用 |
|-----|------|------|
| `/` | `app/home/index.php` | `main::index()` |
| `/user/login` | `app/user/login.php` | `main::index()` |
| `/user/login/submit` | `app/user/login.php` | `main::submit()` |
| `/article/list/show/3` | `app/article/list.php` | `main::show(3)` |
| `/article/list/show/3/2` | `app/article/list.php` | `main::show(3, 2)` |
| `/article/show/view/abc123` | `app/article/show.php` | `main::view('abc123')` |
| `/post/detail/view/php/1` | `app/post/detail.php` | `main::view('php', 1)` |

**位置参数按方法类型提示自动转型：**

```php
// int 类型提示 → URL 中 '42' 自动转成 42
public function show(int $id): void { ... }

// string 类型提示 → 原样传入（slug、hash 等）
public function view(string $slug): void { ... }

// 多参数，混调也没问题
public function show(string $category, int $page = 1): void { ... }
// 访问 /article/list/show/php/2 → show('php', 2)
```

`?key=val` 格式的额外参数通过 `$_GET` / `$_POST` 正常获取。

---

## CLI 工具

所有命令通过根目录的 `h2` 脚本执行（`php h2 <命令>`）：

### 代码生成

```bash
# 生成控制器（自动创建 app/user/login.php）
php h2 make:controller user/login

# 生成视图模板（自动创建 views/user/login/index.html）
php h2 make:view user/login/index

# 生成 Job 模板（自动创建 app/jobs/SendWelcomeEmail.php）
php h2 make:job SendWelcomeEmail

# 生成定时任务模板（自动创建 app/tasks/CleanExpiredTokens.php）
php h2 make:task CleanExpiredTokens
```

### 数据库迁移

```bash
php h2 migrate             # 运行所有未执行的迁移
php h2 migrate:rollback    # 回滚上一批迁移
php h2 migrate:status      # 查看所有迁移的执行状态
```

### 队列 Worker

```bash
php h2 queue:work          # 启动 Worker，持续轮询（Ctrl+C 停止）
php h2 queue:work --once   # 处理一个任务就退出，适合 cron 调度
php h2 queue:status        # 查看各状态任务数量（pending/done/failed）
php h2 queue:clear         # 清除已完成、失败的任务记录
```

### 定时任务（Scheduler）

```bash
php h2 schedule:run        # 执行所有到期的定时任务
php h2 schedule:list       # 列出所有已注册的定时任务
```

### 测试

```bash
php h2 test                         # 运行全部测试
php h2 test --filter testEmail      # 只运行匹配名称的测试
php h2 test --testsuite Unit        # 只运行 Unit 套件
```

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
    // GET /goods/detail/view/100     → view(100)      int
    // GET /goods/detail/view/abc123  → view('abc123')  string
    public function view(int $id): void
    {
        $goods = $this->db->table('goods')->where('id=?', [$id])->fetch();
        $this->set('goods', $goods);
        $this->render();
    }

    // 字符串参数示例：GET /goods/detail/slug/iphone-16-pro
    public function slug(string $slug): void
    {
        $goods = $this->db->table('goods')->where('slug=?', [$slug])->fetch();
        $this->set('goods', $goods);
        $this->render();
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

**模板与响应：**

| 方法 | 说明 |
|------|------|
| `$this->set($key, $val)` | 向模板传递变量 |
| `$this->setMulti($array)` | 批量传递变量 |
| `$this->render($tpl)` | 渲染模板（默认同名模板） |
| `$this->json($data)` | 输出 JSON |
| `$this->redirect($url)` | 跳转（默认 302） |
| `$this->layout($name)` | 设置布局文件 (`_layouts/`) |
| `$this->partial($name)` | 引入局部模板 (`_partials/`) |

**功能辅助：**

| 方法 | 说明 |
|------|------|
| `$this->flash($type, $msg)` | 设置跨请求 Flash 消息 |
| `$this->getFlash($type)` | 读取并清除指定 Flash |
| `$this->getAllFlash()` | 读取并清除所有 Flash |
| `$this->paginate($total, $page, $size, $url)` | 生成分页数组 |
| `$this->validate($data, $rules, $labels)` | 创建验证器，返回 `Validator` 实例 |
| `$this->csrfToken()` | 获取/生成 CSRF Token |
| `$this->csrfField()` | 返回 CSRF 隐藏字段 HTML |
| `$this->csrfVerify()` | 校验 POST 中的 CSRF token，失败返回 403 |
| `$this->on($event, $fn)` | 注册事件监听器（请求内有效） |
| `$this->fire($event, $data)` | 触发事件 |
| `$this->queue($jobName, $payload)` | 将任务推入队列（异步执行） |

**属性与钩子：**

| | 说明 |
|-|------|
| `$this->db` | DB 实例（懒加载） |
| `$this->request` | Request 实例（懒加载） |
| `before()` | 钩子：方法执行前（子类覆盖，用于鉴权） |
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

## 数据库迁移

在 `migrations/` 目录下创建迁移文件：

```php
// migrations/001_create_articles_table.php
return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("CREATE TABLE `articles` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title`      VARCHAR(200) NOT NULL,
            `body`       TEXT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `articles`");
    }
};
```

执行后自动在数据库建立 `_migrations` 追踪表，记录每次迁移的批次，支持按批次回滚。

---

## ORM 关联关系

`hasMany` 和 `belongsTo` 都返回可链式的 DB 实例，接下来可继续 `.order()` `.limit()` `.cache()` 等操作：

```php
// 一对多：获取该用户的所有文章（返回数组）
$posts = $this->db->hasMany('posts', 'user_id', $user['id'])
    ->order('id DESC')
    ->limit(10)
    ->fetchAll();

// 多对一：获取文章对应的用户（返回单条记录）
$author = $this->db->belongsTo('users', 'id', $post['user_id'])->fetch();

// 组合使用示例
public function show(int $id): void {
    $post   = $this->db->table('posts')->where('id=?', [$id])->fetch();
    $author = $this->db->belongsTo('users', 'id', $post['user_id'])->fetch();
    $tags   = $this->db->hasMany('post_tags', 'post_id', $id)->fetchAll();

    $this->setMulti(compact('post', 'author', 'tags'));
    $this->render();
}

// 多对多：获取文章的所有标签（通过 post_tag 中间表）
$tags = $this->db->belongsToMany('tags', 'post_tag', 'post_id', 'tag_id', $postId)
    ->order('tags.name')
    ->fetchAll();
```

| 方法 | 说明 |
|------|------|
| `hasMany($table, $fk, $id)` | 一对多，返回可链式 DB |
| `belongsTo($table, $pk, $fk)` | 多对一，通常接 `->fetch()` |
| `belongsToMany($table, $pivot, $localFk, $relFk, $id)` | 多对多，INNER JOIN 中间表 |

---

## 事件

事件在当前请求内有效（发布/订阅），适合解耦同步流程：

```php
// 注册监听（通常在 before() 或 index.php 里）
$this->on('user.registered', function(array $user) {
    // 发送欢迎邮件、写日志等
});

// 触发（控制器里）
$this->fire('user.registered', ['id' => $id, 'email' => $email]);

// 直接使用静态类
\Lib\Event::on('order.paid', fn($o) => /* ... */);
\Lib\Event::fire('order.paid', $order);
```

---

## 队列

队列任务异步执行，跨请求持久化。Job 文件放在 `app/jobs/`：

```php
// app/jobs/SendWelcomeEmail.php
class SendWelcomeEmail {
    public function handle(array $payload): void {
        // 发送邮件...
        mail($payload['email'], '欢迎注册', '感谢您注册！');
    }
}
```

```php
// 控制器里入队（立即返回，任务后台执行）
$this->queue('SendWelcomeEmail', ['user_id' => $id, 'email' => $email]);

// 延迟入队（1 小时后才会被 Worker 执行）
$this->queue('SendReminder', ['user_id' => $id], delay: 3600);
```

```bash
# 启动 Worker（持续运行）
php h2 queue:work

# Cron 模式（每分钟执行一次）
* * * * * php /path/to/h2 queue:work --once
```

**`config/config.php` 配置：**

```php
'queue' => [
    'driver'       => 'database',  // database（默认，零依赖）| redis（高性能）
    'host'         => '127.0.0.1',
    'port'         => 6379,
    'password'     => '',
    'key'          => 'h2_jobs',
    'max_attempts' => 3,           // 失败后最多重试次数
],
```

> **database 驱动**：任务存入 `_jobs` 表（自动创建），使用 `FOR UPDATE` 防并发冲突，支持失败重试。  
> **redis 驱动**：`BRPOP` 实时阻塞，近乎实时响应，适合高频场景。

---

## 任务调度（Scheduler）

只需一条系统 cron，框架内部判断哪些任务到期执行：

```bash
# 系统 cron 只需这一条（每分钟由操作系统触发一次）
* * * * * php /path/to/h2 schedule:run
```

在 `app/schedules.php` 定义所有任务：

```php
return function(\Lib\Scheduler $s) {

    // 每天凌晨 2 点清理过期 Token
    $s->call('CleanExpiredTokens')->daily()->description('清理过期 Token');

    // 每天早上 8 点发送日报
    $s->call('SendDailyReport')->dailyAt('08:00');

    // 每 15 分钟同步库存
    $s->call('SyncInventory')->everyMinutes(15);

    // 每周一凌晨清理队列
    $s->command('queue:clear')->weekly();

    // 自定义 cron 表达式
    $s->call('BackupDatabase')->cron('0 3 * * 0');

    // 闭包任务
    $s->job(function() {
        // 简单逻辑直接写
    }, 'CleanOldCache')->daily();
};
```

| 频率方法 | 说明 |
|---------|------|
| `->everyMinute()` | 每分钟 |
| `->everyMinutes(15)` | 每 15 分钟 |
| `->hourly()` | 每小时整点 |
| `->daily()` | 每天凌晨 0 点 |
| `->dailyAt('08:30')` | 每天指定时间 |
| `->weekly()` | 每周一凌晨 |
| `->monthly()` | 每月 1 日凌晨 |
| `->cron('0 2 * * 0')` | 自定义表达式 |

Task 文件放在 `app/tasks/`，实现 `handle(): void` 方法：

```bash
php h2 make:task CleanExpiredTokens   # 生成模板
php h2 schedule:list                  # 列出所有已注册任务
```

---

## 测试

框架内置 PHPUnit 测试套件，运行时零 Composer 依赖，测试工具作为 dev 依赖单独安装：

```bash
# 首次安装 PHPUnit（仅开发用）
composer install

# 运行测试
php h2 test
php h2 test --filter testEmail        # 过滤
php h2 test --testsuite Unit          # 指定套件
```

**示例：** `tests/Unit/ValidatorTest.php`

```php
use PHPUnit\Framework\TestCase;
use Lib\Validator;

class ValidatorTest extends TestCase {
    public function testEmailFails(): void {
        $v = new Validator(['email' => 'not'], ['email' => 'email']);
        $this->assertTrue($v->fails());
    }
}
```

> `DBTest.php` 使用 SQLite 内存库，无需配置 MySQL 即可运行，覆盖全部链式查询 API。

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
    'driver'   => 'file',          // file | redis | memcache | memcached
    'host'     => '127.0.0.1',
    'port'     => 6379,            // Redis 默认 6379，Memcache 默认 11211
    'prefix'   => 'h2_',           // key 前缀，防止多项目 key 冲突
    'password' => '',              // Redis 密码（无密码留空或删除此行）
    // 'dir'   => '/tmp/h2cache',  // file 驱动缓存目录（默认 ROOT/cache）
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

| 项目 | 要求 |
|------|------|
| PHP | 7.2+（运行时） / 7.4+（PhpUnit 需要） |
| Web 服务器 | Apache（mod_rewrite）或 Nginx |
| PHP 扩展（运行） | PDO + PDO_MySQL（使用数据库时） |
| PHP 扩展（可选） | Redis 扩展（queue/cache redis 驱动）/ Memcache 扩展 |
| PHP 扩展（测试） | pdo_sqlite（DBTest 使用 SQLite 内存库） |
| Composer | 仅开发时需要（安装 PHPUnit）|

---

## License

MIT
