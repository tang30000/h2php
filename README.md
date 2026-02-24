# H2PHP 框架

> 最轻量、最高效的 PHP MVC 框架 — 目录即路由，零配置上手

[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.2-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

## ✨ 核心理念

- **目录即路由** — URL 直接映射到文件系统，无需配置路由表
- **极简轻量** — 核心代码 ~3500 行，零外部依赖
- **开箱即用** — 23 个内置组件覆盖 Web 开发全场景
- **原生 PHP 模板** — 不发明新语法，PHP 本身就是最好的模板引擎
- **Composer 兼容** — 可自由引入任何第三方包

## 🚀 快速开始

```bash
# 克隆
git clone https://github.com/tang30000/h2php.git
cd h2php

# 安装依赖（可选）
composer install

# 启动开发服务器
php -S localhost:8080 -t . index.php
```

打开 http://localhost:8080 即可看到欢迎页。

## 📁 目录结构

```
h2php/
├── app/                    # 业务代码（控制器）
│   ├── home/index.php      # 首页 → /home/index
│   ├── user/login.php       # 登录 → /user/login
│   ├── middleware/          # 中间件
│   └── jobs/               # 队列 Job
├── views/                  # 视图模板
│   ├── _layouts/           # 布局模板
│   └── _partials/          # 公共片段
├── lib/                    # 框架核心（23 个组件）
├── config/                 # 配置文件
├── static/                 # 静态资源
├── migrations/             # 数据库迁移
├── tests/                  # 单元测试
├── .env.example            # 环境变量模板
├── composer.json
└── index.php               # 入口文件
```

## 🔗 路由规则

URL 直接映射到 `app/` 目录下的 PHP 文件：

| URL | 映射文件 | 调用方法 |
|-----|---------|---------|
| `/` | `app/home/index.php` | `index()` |
| `/user/login` | `app/user/login.php` | `index()` |
| `/post/index/view/5` | `app/post/index.php` | `view($id)` |
| `/admin/dashboard` | `app/admin/dashboard.php` | `index()` |

## 📦 内置组件（23 个）

### 核心

| 组件 | 说明 |
|------|------|
| **Core** | 控制器基类，提供视图渲染、JSON 输出、重定向等 |
| **Router** | 目录路由引擎，URL → 文件自动映射 |
| **Bootstrap** | 框架启动引导，自动加载一切 |
| **Request** | 请求封装（GET/POST/IP/Method/Ajax 判断） |
| **Response** | 响应封装（JSON/下载/重定向/状态码） |

### 数据

| 组件 | 说明 |
|------|------|
| **DB** | PDO 数据库封装，支持 MySQL / PostgreSQL / SQLite |
| **Redis** | Redis 封装（字符串/哈希/列表/集合/有序集合/锁/发布订阅/管道） |
| **Cache** | 多驱动缓存（file / redis / memcache / memcached） |

### 安全

| 组件 | 说明 |
|------|------|
| **Auth** | 密码哈希（bcrypt）+ Session 登录 + JWT Token |
| **Encryption** | AES-256-CBC 加解密 + HMAC 防篡改 |
| **Cookie** | 安全 Cookie（HttpOnly / Secure / SameSite / 加密） |
| **Validator** | 表单验证（15+ 规则 + 自定义规则） |

### 工具

| 组件 | 说明 |
|------|------|
| **Str** | 字符串工具（slug / random / uuid / camel / mask 等 18 个方法） |
| **Http** | HTTP 客户端（GET/POST/PUT/DELETE / Bearer Token / 文件上传） |
| **Env** | .env 环境变量加载器 |
| **Pagination** | 独立分页器（自动分页 / HTML 链接 / API 输出） |
| **RateLimiter** | 接口限流器（Redis 滑动窗口 / 文件计数器） |

### 高级

| 组件 | 说明 |
|------|------|
| **Event** | 事件系统（类似 WordPress add_action / add_filter） |
| **Scheduler** | 定时任务调度器（cron 替代方案） |
| **Queue** | 异步队列（database / redis 双驱动） |
| **Mail** | SMTP 邮件发送 |
| **Logger** | 日志记录（按日期 / 按级别） |

### 文件

| 组件 | 说明 |
|------|------|
| **Upload** | 文件上传（类型/大小验证 + 自动重命名） |
| **StaticFile** | 静态文件服务（开发服务器用） |

## 🛠 CLI 工具

```bash
# 代码生成
php h2 make:controller user/profile    # 创建控制器
php h2 make:view user/profile           # 创建视图
php h2 make:model User                  # 创建模型
php h2 make:middleware Auth             # 创建中间件
php h2 make:job SendEmail               # 创建队列 Job
php h2 make:task CleanCache             # 创建定时任务
php h2 make:test UserTest               # 创建测试

# 数据库迁移
php h2 migrate                          # 执行迁移
php h2 migrate:rollback                 # 回滚迁移
php h2 migrate:status                   # 迁移状态

# 队列 & 调度
php h2 queue:work                       # 启动队列消费者
php h2 schedule:run                     # 执行定时任务

# 其他
php h2 serve                            # 启动开发服务器
php h2 key:generate                     # 生成 APP_KEY
php h2 route:list                       # 列出所有路由
php h2 cache:clear                      # 清除缓存
```

## 📝 代码示例

### 控制器

```php
<?php
class main extends \Lib\Core
{
    // GET /post/index
    public function index(): void
    {
        $posts = $this->db->table('posts')
            ->where('status=?', ['published'])
            ->order('id DESC')
            ->limit(10)
            ->fetchAll();

        $this->set('posts', $posts);
        $this->render('post/list');
    }

    // GET /post/index/view/5
    public function view(int $id): void
    {
        $post = $this->db->table('posts')
            ->where('id=?', [$id])
            ->fetch();

        if (!$post) $this->abort(404);

        $this->set('post', $post);
        $this->render('post/detail');
    }

    // POST /post/index/create
    public function create(): void
    {
        $this->validate([
            'title' => 'required|min:2|max:200',
            'body'  => 'required',
        ]);

        $id = $this->db->table('posts')->timestamps()->insert([
            'title'   => $this->request->post('title'),
            'body'    => $this->request->post('body'),
            'user_id' => Auth::id(),
        ]);

        $this->response->status(201)->json(['id' => $id]);
    }
}
```

### 视图（原生 PHP）

```html
<?php $this->layout('_layouts/main'); ?>

<h1><?= htmlspecialchars($post['title']) ?></h1>
<p><?= nl2br(htmlspecialchars($post['body'])) ?></p>

<?php if (!empty($comments)): ?>
    <?php foreach ($comments as $c): ?>
        <div class="comment"><?= $c['content'] ?></div>
    <?php endforeach; ?>
<?php endif; ?>
```

## ⚙️ 配置

### config/config.php

```php
return [
    'db' => [
        'dsn'      => 'mysql:host=' . Env::get('DB_HOST') . ';dbname=' . Env::get('DB_NAME'),
        'user'     => Env::get('DB_USER', 'root'),
        'password' => Env::get('DB_PASS', ''),
    ],
    'app_key'    => Env::get('APP_KEY'),
    'debug'      => Env::get('APP_DEBUG', false),
    'middleware'  => ['Cors'],
    'redis'      => ['host' => '127.0.0.1', 'port' => 6379, 'prefix' => 'h2_'],
    'cache'      => ['driver' => 'file'],
];
```

### .env

```
APP_KEY=your-32-character-secret-key
APP_DEBUG=true
DB_HOST=localhost
DB_NAME=myapp
DB_USER=root
DB_PASS=secret
```

## 🔌 Composer 第三方包

```bash
composer require nesbot/carbon           # 日期时间
composer require ramsey/uuid             # UUID
composer require phpmailer/phpmailer     # 邮件
composer require illuminate/support      # Laravel Collection
composer require intervention/image      # 图片处理
composer require phpoffice/phpspreadsheet # Excel
```

安装后直接 `use` 即可，零配置。

## 📚 教程

H2CMS 示例项目提供 **30 课**完整教程，从入门到精通：

| 阶段 | 课程 |
|------|------|
| **基础** (1-5) | 路由 → MVC → 数据库 → 验证 → CSRF |
| **进阶** (6-15) | 中间件 → 上传 → 软删除 → 事务 → 事件 → 日志 → 布局 → 时间戳 → 配置 |
| **核心** (16-20) | 缓存 → Request → CLI → 调度器 → 插件机制 |
| **工具** (21-28) | Redis → Http → Auth → 加解密 → 限流 → Cookie/Str → 多数据库 → Composer |
| **高级** (29-30) | Response/分页器 → Env/CORS |

## 📄 License

[MIT](LICENSE)
