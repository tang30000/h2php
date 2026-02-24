# H2PHP

> 轻量、原生、无侵入的 PHP MVC 框架
>
> **史上最轻便高效的 MVC 框架。**

[English Documentation](README.en.md)

H2PHP 是一个极简的单入口 PHP 框架。路由即目录结构，模板与逻辑分离，没有复杂配置，没有 Composer 依赖，保留 PHP 原生开发的舒适度。

### 🤔 为什么不用 Laravel？

PHP 之所以是 PHP，是因为它**简单、直接、改完刷新就生效**。然而 Laravel 用 PHP 写了一个"伪 Java"：服务容器、依赖注入、门面模式、服务提供者、管道中间件……一个空请求加载几百个文件，冷启动吃掉 15MB 内存，响应时间 10ms 起步。打开一个控制器，追踪代码要跳 5-6 层才找到实际逻辑——这还是 PHP 吗？

想要严谨架构和极致性能？那应该去用 **Java / Go / Rust**，它们有**编译优化、类型系统、JIT**。用 PHP 模仿 Java，等于拿了 PHP 的慢，丢了 PHP 的轻，两头不讨好。

**H2PHP 的选择是：拥抱 PHP 的本质。** 功能不比主流框架少（覆盖 26/27 项常用功能），但代码量只有 Laravel 的 **0.5%**。一个请求从 `index.php` → `Router.php` → 控制器，三步完事——任何人打开代码都能**一眼看懂**。

| 指标 | H2PHP | Laravel |
|------|------:|--------:|
| 功能覆盖 | 26/27 (**96%**) | 27/27 |
| 框架代码量 | **~2,000 行** | ~400,000 行 |
| Composer 依赖 | **0** | ~70 个 |
| 冷启动内存 | **~1 MB** | ~15 MB |
| 空请求响应 | **< 1 ms** | ~10 ms |
| 看懂代码 | **3 秒** | 3 小时 |

---

## 特性

- **单入口路由** — 所有请求经由 `index.php` 分发，URL 即目录结构
- **位置参数** — 支持字符串/整数，按方法类型提示自动转型
- **模板** — 布局（Layout）、局部模板（Partial）、原生 PHP 语法
- **中间件** — 洋葱模型管道，全局 + 控制器级，无配置零开销
- **链式 DB** — 查询 / 关联 / 缓存 / 事务 / 软删除 / 自动时间戳
- **表单验证** — 13 种内置规则 + 数据库唯一性校验
- **文件上传** — 链式配置，自动验证大小/类型/重命名
- **队列** — 数据库/Redis 双驱动，延迟入队
- **任务调度** — Cron 式调度器，8 种频率方法
- **事件系统** — 请求内发布/订阅
- **日志** — 分级（info/warning/error/debug），按日期自动分文件
- **CSRF / Flash / 分页** — 开箱即用
- **数据库迁移** — 按批次执行/回滚
- **CLI 工具** — make:controller / view / job / task / migrate / queue / schedule / test
- **本地配置** — `config.local.php` 覆盖，不提交 Git
- **PHPUnit** — 内置 Unit/Feature 套件
- **极简依赖** — 仅需 PHP 7.2+，运行时零 Composer 依赖

---

### 📖 目录

| 分类 | 章节 |
|------|------|
| 快速上手 | [目录结构](#目录结构) · [路由规则](#路由规则) · [CLI 工具](#cli-工具) · [快速开始](#快速开始) |
| 核心功能 | [Core API](#core-基类-api) · [DB 链式查询](#db-链式查询) · [ORM 关联](#orm-关联关系) · [事务](#db-事务) · [软删除](#db-软删除) · [自动时间戳](#db-自动时间戳) |
| 请求处理 | [中间件](#中间件middleware) · [鉴权 before+skipBefore](#鉴权示例before-钩子--skipbefore) · [表单验证](#表单验证) · [CSRF](#csrf-保护) · [文件上传](#文件上传) |
| 模板系统 | [布局与局部模板](#布局与局部模板) · [Flash 消息](#flash-消息) · [分页](#分页辅助) · [自定义错误页](#自定义错误页) |
| 异步与调度 | [队列](#队列) · [事件](#事件) · [任务调度](#任务调度scheduler) |
| 基础设施 | [缓存](#查询缓存) · [日志](#日志) · [数据库迁移](#数据库迁移) · [本地配置](#本地配置覆盖configlocalphp) · [测试](#测试) |

---

### 📊 与主流框架对比

#### 功能覆盖

| 功能 | H2PHP | Laravel | ThinkPHP | CodeIgniter | Slim | Symfony |
|------|:-----:|:-------:|:--------:|:-----------:|:----:|:-------:|
| 路由 | ✅ 目录即路由 | ✅ 注解/文件 | ✅ 配置/注解 | ✅ 配置文件 | ✅ 回调路由 | ✅ 注解/YAML |
| MVC 分层 | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| 中间件 | ✅ 洋葱模型 | ✅ | ✅ | ✅ | ✅ | ✅ |
| ORM / 链式查询 | ✅ 轻量 | ✅ Eloquent | ✅ 内置 | ✅ | ❌ | ✅ Doctrine |
| ORM 关联 (1:N / N:N) | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| 数据库事务 | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| 软删除 | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| 数据库迁移 | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| 自动时间戳 | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| 模板引擎 | ✅ 原生 PHP | ✅ Blade | ✅ 内置 | ✅ | ❌ | ✅ Twig |
| 布局 / 局部模板 | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| 表单验证 | ✅ 13 规则 | ✅ 60+ 规则| ✅ | ✅ | ❌ | ✅ |
| CSRF 保护 | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| 文件上传 | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| 缓存 (多驱动) | ✅ 4 驱动 | ✅ | ✅ | ✅ | ❌ | ✅ |
| 查询缓存 | ✅ | ❌ 需手动 | ✅ | ❌ | ❌ | ❌ |
| 队列 (多驱动) | ✅ | ✅ | ✅ | ❌ 第三方 | ❌ | ✅ Messenger |
| 延迟队列 | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| 任务调度 | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| 事件系统 | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| 日志 | ✅ | ✅ Monolog | ✅ | ✅ | ❌ | ✅ Monolog |
| 多环境配置 | ✅ .local | ✅ .env | ✅ .env | ✅ | ❌ | ✅ .env |
| CLI 工具 | ✅ 12 命令 | ✅ Artisan | ✅ | ✅ | ❌ | ✅ Console |
| 单元测试 | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| API 响应辅助 | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| 邮件发送 | ✅ SMTP | ✅ | ✅ | ✅ | ❌ | ✅ |

#### 工程指标

| 指标 | H2PHP | Laravel | ThinkPHP | CodeIgniter | Slim |
|------|------:|--------:|---------:|------------:|-----:|
| 框架代码量 | **~1,800 行** | ~400,000 行 | ~150,000 行 | ~80,000 行 | ~5,000 行 |
| Composer 依赖 | **0** | ~70 个 | ~30 个 | ~5 个 | ~7 个 |
| 最低 PHP 版本 | **7.2** | 8.2 | 8.0 | 7.4 | 8.1 |
| `composer install` 耗时 | **0 秒** | ~30 秒 | ~15 秒 | ~5 秒 | ~3 秒 |
| 冷启动内存 | **~1 MB** | ~15 MB | ~8 MB | ~3 MB | ~2 MB |
| 空请求响应时间 | **< 1 ms** | ~10 ms | ~5 ms | ~3 ms | ~2 ms |
| 学习曲线 | ⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ | ⭐⭐ |

#### 适用场景

| 场景 | 推荐框架 |
|------|---------|
| 中小型 Web 应用、后台管理系统 | **H2PHP** ✅ · CodeIgniter |
| API 服务（轻量、高性能） | **H2PHP** ✅ · Slim |
| 快速原型、教学演示 | **H2PHP** ✅ |
| 大型企业级应用、微服务 | Java (Spring) · Go |
| 需要第三方 Composer 生态（支付/短信/OSS 等） | **H2PHP** ✅ · Laravel · ThinkPHP |
| 旧项目维护（PHP 7.x 环境） | **H2PHP** ✅ · CodeIgniter |

> **H2PHP 的定位**：在功能覆盖上已接近主流全功能框架（覆盖 26/27 项常用功能），但代码量仅为 Laravel 的 **0.5%**，零依赖、零配置、零学习成本。适合追求**极致轻量**、**性能优先**、**完全可控**，以及不喜欢"伪 Java"式重型架构、希望回归 PHP 代码轻松感的开发者。

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
| `$this->abort($code, $msg)` | 终止并输出错误页（支持自定义 `_errors/` 模板） |
| `$this->success($data, $msg)` | JSON 成功响应 `{"code":0,"msg":"ok","data":...}` |
| `$this->fail($msg, $code)` | JSON 失败响应 `{"code":-1,"msg":"...","data":null}` |
| `$this->log($level, $msg, $ctx)` | 写入日志（info/warning/error/debug） |
| `$this->mail($to, $subj, $body)` | 发送邮件（SMTP） |
| `$this->upload($field, $dir)` | 文件上传辅助，返回 `Upload` 实例 |
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

## 鉴权示例（before 钩子 + skipBefore）

```php
class main extends \Lib\Core
{
    // 公开方法跳过鉴权
    protected array $skipBefore = ['index', 'list'];

    public function before(): void
    {
        if (empty($_SESSION['user'])) {
            $this->redirect('/user/login');
        }
    }

    public function index(): void { $this->render(); } // 无需登录

    public function dashboard(): void
    {
        // before() 验证通过后才会执行
        $this->render();
    }
}
```

---

## 中间件（Middleware）

中间件是在控制器**之前**执行的可复用处理层，采用洋葱模型：

```
请求 → Cors → AuthCheck → [before() → action → after()] → AuthCheck → Cors → 响应
```

**中间件类**放在 `app/middleware/`，实现 `handle(callable $next)` 方法：

```php
// app/middleware/Cors.php
class Cors
{
    public function handle(callable $next): void
    {
        header('Access-Control-Allow-Origin: *');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;  // 不调用 $next() = 拦截请求
        }

        $next();  // 继续后续中间件 → 控制器
    }
}
```

**全局注册**（所有请求都经过）：

```php
// config/config.php
'middleware' => [
    'Cors',       // 跨域支持
    'AuthCheck',  // 全局登录检查
],
```

**控制器级注册**（仅指定控制器）：

```php
class main extends \Lib\Core
{
    protected array $middleware = ['AuthCheck'];  // 仅当前控制器经过
}
```

> 不配置任何中间件时（空数组或不设置），框架完全跳过管道逻辑，零性能开销。

---

## abort / success / fail

```php
// 终止并输出错误页（使用 views/_errors/{code}.html 或内置样式）
$this->abort(403, '无权访问');
$this->abort(404, '资源不存在');

// JSON API 标准响应
$this->success($data);                      // {"code":0,"msg":"ok","data":...}
$this->success($data, '操作成功');
$this->fail('参数错误');                     // {"code":-1,"msg":"参数错误","data":null}
$this->fail('资源不存在', 404);
```

---

## 文件上传

```php
$file = $this->upload('avatar', 'static/uploads/avatars');

if ($file->fails()) {
    $this->flash('error', $file->error());
    $this->redirect('/user/profile');
}

// 存入数据库的相对路径，可直接用于 <img src>
$path = $file->path();  // 例：static/uploads/avatars/3f2a...b1.jpg

// 链式配置（可选）
$file = $this->upload('photo', 'static/uploads')
    ->maxSize(3 * 1024 * 1024)          // 最大 3 MB（默认 5 MB）
    ->allowTypes(['jpg', 'png', 'webp']) // 允许类型（默认含常见图片/PDF/zip）
    ->rename('timestamp');               // uuid（默认）| timestamp | original
```

---

## 本地配置覆盖（config.local.php）

本地开发时，无需修改 `config.php`，创建 `config/config.local.php`（已加入 `.gitignore`）：

```bash
cp config/config.local.example.php config/config.local.php
```

```php
// config/config.local.php
return [
    'debug' => true,
    'db' => [
        'dsn'      => 'mysql:host=127.0.0.1;dbname=h2php_dev;charset=utf8mb4',
        'user'     => 'root',
        'password' => '',
    ],
];
```

框架在加载 `config.php` 后自动检测并深度合并 `config.local.php`，任意配置项均可覆盖。

---

## DB 自动时间戳

```php
// insert() 自动填充 created_at 和 updated_at
$id = $this->db->table('posts')->timestamps()->insert([
    'title' => '我的第一篇文章',
    'body'  => '内容...',
]);

// update() 自动更新 updated_at
$this->db->table('posts')->timestamps()->where('id=?', [$id])->update([
    'title' => '修改后的标题',
]);

// 不传 timestamps() 则不自动处理，保持原有行为
```

---

## DB 事务

```php
// 手动事务
$this->db->beginTransaction();
try {
    $this->db->table('orders')->insert(['user_id' => $uid, 'total' => 100]);
    $this->db->table('stock')->where('id=?', [1])->update(['qty' => 99]);
    $this->db->commit();
} catch (\Throwable $e) {
    $this->db->rollback();
    throw $e;
}

// 闭包事务（推荐，自动 commit/rollback）
$this->db->transaction(function($db) use ($uid) {
    $db->table('orders')->insert(['user_id' => $uid, 'total' => 100]);
    $db->table('stock')->where('id=?', [1])->update(['qty' => 99]);
    // 抛异常自动回滚
});
```

---

## DB 软删除

为表添加 `deleted_at` 字段（DATETIME, NULL），然后使用 `softDeletes()` 开启：

```php
// 软删除（设置 deleted_at，不物理删除）
$this->db->table('posts')->softDeletes()->where('id=?', [$id])->softDelete();

// 查询自动排除已软删除的记录
$posts = $this->db->table('posts')->softDeletes()->fetchAll();

// 包含已删除记录
$all = $this->db->table('posts')->softDeletes()->withTrashed()->fetchAll();

// 只查已删除的
$trashed = $this->db->table('posts')->softDeletes()->onlyTrashed()->fetchAll();

// 恢复
$this->db->table('posts')->softDeletes()->where('id=?', [$id])->restore();
```

> 不调用 `softDeletes()` 时，`delete()` 仍为物理删除，完全向后兼容。

---

## 日志

```php
// 控制器中
$this->log('info', '用户登录', ['user_id' => $id]);
$this->log('error', '支付失败', ['order_id' => 123, 'reason' => $msg]);

// 静态调用（任意位置）
\Lib\Logger::info('缓存已清除');
\Lib\Logger::warning('库存不足', ['sku' => 'A001']);
\Lib\Logger::error('数据库连接失败', ['dsn' => $dsn]);
\Lib\Logger::debug('SQL 执行', ['sql' => $sql, 'time' => $ms]);
```

日志文件按日期自动分割：`logs/2026-02-25.log`

```
[2026-02-25 14:30:15] [INFO] 用户登录 {"user_id":5}
[2026-02-25 14:30:16] [ERROR] 支付失败 {"order_id":123,"reason":"余额不足"}
```

---

## 邮件发送

```php
// 一行快捷发送（含 HTML 标签自动识别为 HTML 邮件）
$this->mail('user@example.com', '注册成功', '<h1>欢迎</h1><p>感谢注册！</p>');

// 链式（高级）
$mail = new \Lib\Mail($this->config['mail']);
$ok = $mail->to('user@example.com')
    ->cc('admin@example.com')
    ->subject('订单确认')
    ->html('<p>您的订单已创建</p>')
    ->send();

if (!$ok) {
    $this->log('error', '邮件发送失败', ['error' => $mail->error()]);
}
```

SMTP 配置（`config.php`）：

```php
'mail' => [
    'host'     => 'smtp.qq.com',     // QQ / Gmail / 阿里企业邮等
    'port'     => 465,
    'user'     => 'noreply@example.com',
    'password' => '授权码',           // 非登录密码
    'name'     => 'H2PHP App',       // 发件人显示名
    'ssl'      => true,
],
```

> 零依赖，内部通过 socket 直连 SMTP 服务器，不需要 PHPMailer 等第三方库。

---

## Composer 第三方包

H2PHP 框架本身零依赖，但完全兼容 Composer 生态。安装了 `vendor/autoload.php` 时自动加载，不安装也不报错：

```bash
# 安装任意 Composer 包
composer require overtrue/easy-sms      # 短信
composer require yansongda/pay          # 支付宝/微信支付
composer require league/flysystem       # 文件系统/OSS
composer require phpmailer/phpmailer    # 邮件（如果不想用内置 Mail）
```

控制器中直接使用：

```php
use Overtrue\EasySms\EasySms;

public function sendCode(): void {
    $sms = new EasySms($this->config['sms']);
    $sms->send('13800138000', ['content' => '验证码：1234']);
}
```

> Composer 包是 PHP 生态的公共资源，不是 Laravel 的专属。任何非框架耦合的包都能在 H2PHP 中直接使用。

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
