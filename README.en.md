# H2PHP — Lightweight PHP MVC Framework

> **Minimalist · Zero-dependency · Directory-based routing · Performance-first**
>
> Covers 96% of mainstream framework features with only 0.5% of Laravel's codebase.

[中文文档](README.md)

H2PHP is a minimalist single-entry PHP framework. Routes map directly to the directory structure, templates are separated from logic, zero complex configuration, zero Composer dependencies — retaining the natural comfort of plain PHP development.

### 🤔 Why Not Laravel?

PHP is PHP because it's **simple, direct, and refreshes instantly**. Yet Laravel turned PHP into a "pseudo-Java": service containers, dependency injection, facade patterns, service providers, pipeline middleware... A single empty request loads hundreds of files, eats 15MB of cold-start memory, and takes 10ms+ to respond. Open a controller and you need to trace through 5-6 layers just to find the actual logic — is this still PHP?

Want rigorous architecture and extreme performance? Use **Java / Go / Rust** — they have **compilation optimizations, type systems, and JIT**. Using PHP to mimic Java means you get PHP's slowness while losing PHP's lightness — the worst of both worlds.

**H2PHP's choice: embrace the nature of PHP.** Feature coverage is on par with mainstream frameworks (26/27 common features), but the codebase is only **0.5%** of Laravel's. A request goes from `index.php` → `Router.php` → controller, three steps — anyone can **understand the code at a glance**.

| Metric | H2PHP | Laravel |
|--------|------:|--------:|
| Feature coverage | 26/27 (**96%**) | 27/27 |
| Framework code | **~2,000 lines** | ~400,000 lines |
| Composer deps | **0** | ~70 |
| Cold-start memory | **~1 MB** | ~15 MB |
| Empty request | **< 1 ms** | ~10 ms |
| Time to understand | **3 seconds** | 3 hours |

---

## Features

- **Single entry routing** — All requests dispatched through `index.php`, URL = directory structure
- **Positional params** — Support strings/integers, auto-cast by method type hints
- **Templates** — Layouts, Partials, native PHP syntax
- **Middleware** — Onion model pipeline, global + per-controller, zero overhead when empty
- **Chainable DB** — Query / Relations / Cache / Transactions / Soft Delete / Auto Timestamps
- **Form validation** — 13 built-in rules + database uniqueness check
- **File upload** — Chainable config, auto-validate size/type/rename
- **Queue** — Database/Redis dual driver, delayed dispatch
- **Task scheduler** — Cron-style scheduler, 8 frequency methods
- **Event system** — Request-scoped pub/sub
- **Logging** — Leveled (info/warning/error/debug), auto daily rotation
- **SMTP Mail** — Zero-dependency socket-based email sending
- **CSRF / Flash / Pagination** — Out of the box
- **Database migrations** — Batch execute/rollback
- **CLI tools** — make:controller / view / job / task / migrate / queue / schedule / test
- **Local config** — `config.local.php` override, not committed to Git
- **Composer ecosystem** — Auto-loads `vendor/autoload.php` when present
- **PHPUnit** — Built-in Unit/Feature test suites
- **Zero dependencies** — PHP 7.2+ only, zero runtime Composer dependencies

---

### 📖 Table of Contents

| Category | Sections |
|----------|----------|
| Getting Started | [Directory Structure](#directory-structure) · [Routing Rules](#routing-rules) · [CLI Tools](#cli-tools) · [Quick Start](#quick-start) |
| Core | [Core API](#core-base-class-api) · [DB Queries](#db-cheat-sheet) · [ORM Relations](#orm-relations) · [Transactions](#db-transactions) · [Soft Delete](#db-soft-delete) · [Auto Timestamps](#db-auto-timestamps) |
| Request Handling | [Middleware](#middleware) · [Auth (before + skipBefore)](#authentication-before--skipbefore) · [Validation](#form-validation) · [CSRF](#csrf-protection) · [File Upload](#file-upload) |
| Templates | [Layouts & Partials](#layouts--partials) · [Flash Messages](#flash-messages) · [Pagination](#pagination) · [Error Pages](#custom-error-pages) |
| Async & Scheduling | [Queue](#queue) · [Events](#events) · [Task Scheduler](#task-scheduler) |
| Infrastructure | [Cache](#query-caching) · [Logging](#logging) · [Mail](#mail) · [Composer Packages](#composer-third-party-packages) · [Migrations](#database-migrations) · [Local Config](#local-config-override) · [Testing](#testing) |

---

### 📊 Framework Comparison

#### Feature Coverage

| Feature | H2PHP | Laravel | ThinkPHP | CodeIgniter | Slim | Symfony |
|---------|:-----:|:-------:|:--------:|:-----------:|:----:|:-------:|
| Routing | ✅ Directory-based | ✅ Annotation/File | ✅ Config/Annotation | ✅ Config file | ✅ Callback | ✅ Annotation/YAML |
| MVC layers | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Middleware | ✅ Onion model | ✅ | ✅ | ✅ | ✅ | ✅ |
| ORM / Query builder | ✅ Lightweight | ✅ Eloquent | ✅ Built-in | ✅ | ❌ | ✅ Doctrine |
| ORM relations (1:N / N:N) | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| DB transactions | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Soft delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| DB migrations | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Auto timestamps | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Template engine | ✅ Native PHP | ✅ Blade | ✅ Built-in | ✅ | ❌ | ✅ Twig |
| Layouts / Partials | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Form validation | ✅ 13 rules | ✅ 60+ rules | ✅ | ✅ | ❌ | ✅ |
| CSRF protection | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| File upload | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Cache (multi-driver) | ✅ 4 drivers | ✅ | ✅ | ✅ | ❌ | ✅ |
| Query cache | ✅ | ❌ Manual | ✅ | ❌ | ❌ | ❌ |
| Queue (multi-driver) | ✅ | ✅ | ✅ | ❌ Third-party | ❌ | ✅ Messenger |
| Delayed queue | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| Task scheduler | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Event system | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Logging | ✅ | ✅ Monolog | ✅ | ✅ | ❌ | ✅ Monolog |
| Multi-env config | ✅ .local | ✅ .env | ✅ .env | ✅ | ❌ | ✅ .env |
| CLI tools | ✅ 12 commands | ✅ Artisan | ✅ | ✅ | ❌ | ✅ Console |
| Unit testing | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| API response helpers | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Mail | ✅ SMTP | ✅ | ✅ | ✅ | ❌ | ✅ |

#### Engineering Metrics

| Metric | H2PHP | Laravel | ThinkPHP | CodeIgniter | Slim |
|--------|------:|--------:|---------:|------------:|-----:|
| Framework code | **~2,000 lines** | ~400,000 lines | ~150,000 lines | ~80,000 lines | ~5,000 lines |
| Composer deps | **0** | ~70 | ~30 | ~5 | ~7 |
| Min PHP version | **7.2** | 8.2 | 8.0 | 7.4 | 8.1 |
| `composer install` time | **0 sec** | ~30 sec | ~15 sec | ~5 sec | ~3 sec |
| Cold-start memory | **~1 MB** | ~15 MB | ~8 MB | ~3 MB | ~2 MB |
| Empty request latency | **< 1 ms** | ~10 ms | ~5 ms | ~3 ms | ~2 ms |
| Learning curve | ⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ | ⭐⭐ |

#### Use Cases

| Scenario | Recommended |
|----------|------------|
| Small-to-mid Web apps, admin panels | **H2PHP** ✅ · CodeIgniter |
| API services (lightweight, high-perf) | **H2PHP** ✅ · Slim |
| Rapid prototyping, teaching | **H2PHP** ✅ |
| Large enterprise, microservices | Java (Spring) · Go |
| Need third-party Composer ecosystem (payment/SMS/OSS) | **H2PHP** ✅ · Laravel · ThinkPHP |
| Legacy projects (PHP 7.x) | **H2PHP** ✅ · CodeIgniter |

> **H2PHP's positioning**: Feature coverage approaching mainstream full-featured frameworks (26/27 common features), yet the codebase is only **0.5%** of Laravel's — zero dependencies, zero configuration, zero learning curve. Built for developers who prioritize **extreme lightness**, **performance first**, **full control**, and prefer to ditch the heavy "pseudo-Java" architecture in favor of PHP's natural simplicity.

---

## Directory Structure

```
h2php/
├── index.php              # Single entry point
├── .htaccess              # Apache URL rewrite
├── nginx.conf.example     # Nginx config reference
├── h2                     # CLI tool (php h2 ...)
│
├── config/
│   ├── config.php         # Main config (DB, routes, cache, queue, mail)
│   └── config.local.php   # Local override (gitignored)
│
├── lib/                   # Framework core
│   ├── Router.php         # Route parsing & dispatching
│   ├── Core.php           # Base controller
│   ├── DB.php             # PDO database wrapper
│   ├── Request.php        # HTTP request helper
│   ├── Validator.php      # Form validation
│   ├── Cache.php          # Multi-driver cache
│   ├── Event.php          # Event system
│   ├── Queue.php          # Job queue
│   ├── Scheduler.php      # Task scheduler
│   ├── Logger.php         # Logging
│   ├── Mail.php           # SMTP mail
│   └── Upload.php         # File upload helper
│
├── app/                   # Your controllers
│   ├── {module}/{feature}.php
│   ├── middleware/         # Middleware classes
│   ├── jobs/              # Queue job classes
│   ├── tasks/             # Scheduled task classes
│   └── schedules.php      # Schedule definitions
│
├── views/                 # Templates
│   ├── _layouts/          # Layout files
│   ├── _partials/         # Reusable partials
│   ├── _errors/           # Custom error pages (403.html, 404.html, etc.)
│   └── {module}/{feature}/{method}.html
│
├── migrations/            # Database migrations
├── logs/                  # Log files (auto-created, gitignored)
├── cache/                 # File cache (gitignored)
└── static/                # Static assets (CSS/JS/images)
```

---

## Routing Rules

```
URL:  /{a}/{b}/{c}/{d1}/{d2}
       │   │   │   └─── Positional params (string/integer, auto-cast by type hints)
       │   │   └─────── Method name (public method in the main class)
       │   └─────────── File name → app/{a}/{b}.php
       └─────────────── Directory → app/{a}/
```

| URL | File | Call |
|-----|------|------|
| `/` | `app/home/index.php` | `main::index()` |
| `/user/login` | `app/user/login.php` | `main::index()` |
| `/user/login/submit` | `app/user/login.php` | `main::submit()` |
| `/article/list/show/3` | `app/article/list.php` | `main::show(3)` |
| `/article/show/view/abc123` | `app/article/show.php` | `main::view('abc123')` |
| `/post/detail/view/php/1` | `app/post/detail.php` | `main::view('php', 1)` |

**Auto type casting** based on method type hints:

```php
public function show(int $id): void {}          // '42' → 42 (int)
public function view(string $slug): void {}     // passed as-is
public function list(string $cat, int $page = 1): void {}  // mixed types
```

---

## CLI Tools

```bash
php h2 make:controller user/profile    # Create controller
php h2 make:view user/profile index    # Create view template
php h2 make:job SendWelcomeEmail       # Create queue job
php h2 make:task CleanExpiredTokens    # Create scheduled task
php h2 migrate                         # Run migrations
php h2 migrate:rollback                # Rollback last batch
php h2 queue:work                      # Start queue worker
php h2 schedule:run                    # Execute due tasks
php h2 schedule:list                   # List all tasks
php h2 test                            # Run PHPUnit tests
php h2 test unit                       # Run unit tests only
php h2 test feature                    # Run feature tests only
```

---

## Quick Start

### 1. Deploy

**Apache**: Drop into web root. `.htaccess` handles rewrites out of the box.

**Nginx**: Reference `nginx.conf.example` for URL rewrite config.

**PHP Built-in Server** (local dev):
```bash
php -S localhost:8080 index.php
```

### 2. Configure

Edit `config/config.php`:

```php
'db' => [
    'dsn'      => 'mysql:host=localhost;dbname=your_db;charset=utf8mb4',
    'user'     => 'root',
    'password' => 'your_password',
],
```

### 3. Create a Page

**Controller** `app/goods/detail.php`:

```php
<?php
class main extends \Lib\Core
{
    public function view(int $id): void
    {
        $goods = $this->db->table('goods')->where('id=?', [$id])->fetch();
        $this->set('goods', $goods);
        $this->render();
    }

    public function show(string $slug): void
    {
        $goods = $this->db->table('goods')->where('slug=?', [$slug])->fetch();
        $this->set('goods', $goods);
        $this->render();
    }
}
```

**Template** `views/goods/detail/view.html`:

```html
<!DOCTYPE html>
<html>
<body>
    <h1><?= htmlspecialchars($goods['name']) ?></h1>
    <p>Price: $<?= $goods['price'] ?></p>
</body>
</html>
```

Visit `/goods/detail/view/100` — no additional configuration required.

---

## Core Base Class API

| Method | Description |
|--------|-------------|
| `$this->set($key, $val)` | Pass variable to template |
| `$this->setMulti($array)` | Pass multiple variables |
| `$this->render($tpl)` | Render template (auto-detects route template) |
| `$this->layout($name)` | Set layout template |
| `$this->partial($name)` | Include a partial |
| `$this->json($data)` | Output JSON response |
| `$this->redirect($url)` | HTTP redirect |
| `$this->flash($key, $msg)` | Set flash message |
| `$this->db` | DB instance (lazy-loaded) |
| `$this->request` | Request instance (lazy-loaded) |
| `$this->validate($data, $rules)` | Create form validator |
| `$this->csrfToken()` | Get/generate CSRF token |
| `$this->csrfField()` | Return CSRF hidden field HTML |
| `$this->csrfVerify()` | Verify POST CSRF token (auto 403 on failure) |
| `$this->abort($code, $msg)` | Terminate with error page |
| `$this->success($data, $msg)` | JSON success `{"code":0,"msg":"ok","data":...}` |
| `$this->fail($msg, $code)` | JSON error `{"code":-1,"msg":"...","data":null}` |
| `$this->log($level, $msg, $ctx)` | Write to log (info/warning/error/debug) |
| `$this->mail($to, $subj, $body)` | Send email (SMTP) |
| `$this->upload($field, $dir)` | File upload helper, returns `Upload` instance |
| `$this->on($event, $fn)` | Register event listener |
| `$this->fire($event, $data)` | Fire event |
| `$this->queue($job, $payload)` | Push job to queue |
| `before()` | Hook: before action (override for auth) |
| `after()` | Hook: after action |

---

## DB Cheat Sheet

```php
// Fetch multiple rows
$users = $this->db->table('users')
    ->where('status=?', [1])
    ->order('id DESC')
    ->limit(20, ($page - 1) * 20)
    ->fetchAll();

// Fetch single row
$user = $this->db->table('users')->where('id=?', [$id])->fetch();

// Count
$total = $this->db->table('users')->count();

// Insert (returns auto-increment ID)
$id = $this->db->table('users')->insert(['name' => 'Tom', 'email' => 'tom@example.com']);

// Update
$this->db->table('users')->where('id=?', [$id])->update(['name' => 'Jerry']);

// Delete
$this->db->table('users')->where('id=?', [$id])->delete();

// Raw SQL
$rows = $this->db->query('SELECT * FROM users WHERE age > ?', [18]);
```

---

## ORM Relations

```php
// Has Many: get all orders for a user
$orders = $this->db->hasMany('orders', 'user_id', $userId)->fetchAll();

// Belongs To: get the user for an order
$user = $this->db->belongsTo('users', 'user_id', $order['user_id'])->fetch();

// Many-to-Many: get tags for a post (through pivot table)
$tags = $this->db->belongsToMany('tags', 'post_tag', 'post_id', 'tag_id', $postId)->fetchAll();
```

---

## DB Transactions

```php
// Manual transaction
$this->db->beginTransaction();
try {
    $this->db->table('orders')->insert(['user_id' => $uid, 'total' => 100]);
    $this->db->table('stock')->where('id=?', [1])->update(['qty' => 99]);
    $this->db->commit();
} catch (\Throwable $e) {
    $this->db->rollback();
    throw $e;
}

// Closure transaction (recommended, auto commit/rollback)
$this->db->transaction(function($db) use ($uid) {
    $db->table('orders')->insert(['user_id' => $uid, 'total' => 100]);
    $db->table('stock')->where('id=?', [1])->update(['qty' => 99]);
    // throws = auto rollback
});
```

---

## DB Soft Delete

Add a `deleted_at` column (DATETIME, NULL) to your table, then use `softDeletes()`:

```php
// Soft delete (sets deleted_at, no physical deletion)
$this->db->table('posts')->softDeletes()->where('id=?', [$id])->softDelete();

// Queries auto-exclude soft-deleted records
$posts = $this->db->table('posts')->softDeletes()->fetchAll();

// Include soft-deleted records
$all = $this->db->table('posts')->softDeletes()->withTrashed()->fetchAll();

// Only soft-deleted records
$trashed = $this->db->table('posts')->softDeletes()->onlyTrashed()->fetchAll();

// Restore
$this->db->table('posts')->softDeletes()->where('id=?', [$id])->restore();
```

> Without `softDeletes()`, `delete()` remains a physical delete — fully backward compatible.

---

## DB Auto Timestamps

```php
// insert() auto-fills created_at and updated_at
$id = $this->db->table('posts')->timestamps()->insert([
    'title' => 'My First Post',
    'body'  => 'Content...',
]);

// update() auto-updates updated_at
$this->db->table('posts')->timestamps()->where('id=?', [$id])->update([
    'title' => 'Updated Title',
]);

// Without timestamps(), no auto-handling — original behavior preserved
```

---

## Middleware

Middleware is a reusable processing layer executed **before** controllers, using the onion model:

```
Request → Cors → AuthCheck → [before() → action → after()] → AuthCheck → Cors → Response
```

**Middleware classes** go in `app/middleware/`, implementing `handle(callable $next)`:

```php
// app/middleware/Cors.php
class Cors
{
    public function handle(callable $next): void
    {
        header('Access-Control-Allow-Origin: *');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;  // don't call $next() = intercept
        }

        $next();  // continue pipeline → controller
    }
}
```

**Global registration** (all requests):

```php
// config/config.php
'middleware' => [
    'Cors',
    'AuthCheck',
],
```

**Per-controller registration**:

```php
class main extends \Lib\Core
{
    protected array $middleware = ['AuthCheck'];
}
```

> No middleware configured (empty array or not set) = framework skips the pipeline entirely, zero performance overhead.

---

## Authentication (before + skipBefore)

```php
class main extends \Lib\Core
{
    // Public methods skip auth
    protected array $skipBefore = ['index', 'list'];

    public function before(): void
    {
        if (empty($_SESSION['user'])) {
            $this->redirect('/user/login');
        }
    }

    public function index(): void { $this->render(); } // no login required

    public function dashboard(): void
    {
        // only reached if before() passes
        $this->render();
    }
}
```

---

## abort / success / fail

```php
// Terminate with error page (uses views/_errors/{code}.html or built-in style)
$this->abort(403, 'Access denied');
$this->abort(404, 'Resource not found');

// Standard JSON API responses
$this->success($data);                       // {"code":0,"msg":"ok","data":...}
$this->success($data, 'Operation successful');
$this->fail('Invalid parameters');           // {"code":-1,"msg":"...","data":null}
$this->fail('Resource not found', 404);
```

---

## File Upload

```php
$file = $this->upload('avatar', 'static/uploads/avatars');

if ($file->fails()) {
    $this->flash('error', $file->error());
    $this->redirect('/user/profile');
}

$path = $file->path();  // e.g. static/uploads/avatars/3f2a...b1.jpg

// Chainable config (optional)
$file = $this->upload('photo', 'static/uploads')
    ->maxSize(3 * 1024 * 1024)          // max 3 MB (default 5 MB)
    ->allowTypes(['jpg', 'png', 'webp']) // allowed types
    ->rename('timestamp');               // uuid (default) | timestamp | original
```

---

## Local Config Override (config.local.php)

For local development, create `config/config.local.php` (already in `.gitignore`):

```bash
cp config/config.local.example.php config/config.local.php
```

```php
return [
    'debug' => true,
    'db' => [
        'dsn'      => 'mysql:host=127.0.0.1;dbname=h2php_dev;charset=utf8mb4',
        'user'     => 'root',
        'password' => '',
    ],
];
```

The framework auto-detects and deep-merges `config.local.php` after loading `config.php`.

---

## Logging

```php
// In controllers
$this->log('info', 'User logged in', ['user_id' => $id]);
$this->log('error', 'Payment failed', ['order_id' => 123, 'reason' => $msg]);

// Static (anywhere)
\Lib\Logger::info('Cache cleared');
\Lib\Logger::warning('Low stock', ['sku' => 'A001']);
\Lib\Logger::error('DB connection failed', ['dsn' => $dsn]);
\Lib\Logger::debug('SQL executed', ['sql' => $sql, 'time' => $ms]);
```

Log files auto-rotate by date: `logs/2026-02-25.log`

```
[2026-02-25 14:30:15] [INFO] User logged in {"user_id":5}
[2026-02-25 14:30:16] [ERROR] Payment failed {"order_id":123,"reason":"Insufficient balance"}
```

---

## Mail

```php
// One-liner (auto-detects HTML by tags)
$this->mail('user@example.com', 'Welcome!', '<h1>Welcome</h1><p>Thanks for signing up!</p>');

// Chainable (advanced)
$mail = new \Lib\Mail($this->config['mail']);
$ok = $mail->to('user@example.com')
    ->cc('admin@example.com')
    ->subject('Order Confirmation')
    ->html('<p>Your order has been created</p>')
    ->send();

if (!$ok) {
    $this->log('error', 'Mail failed', ['error' => $mail->error()]);
}
```

SMTP config (`config.php`):

```php
'mail' => [
    'host'     => 'smtp.gmail.com',
    'port'     => 465,
    'user'     => 'noreply@example.com',
    'password' => 'app_password',
    'name'     => 'H2PHP App',
    'ssl'      => true,
],
```

> Zero dependencies — uses socket to connect directly to the SMTP server.

---

## Composer Third-Party Packages

H2PHP itself has zero dependencies, but is fully compatible with the Composer ecosystem. When `vendor/autoload.php` exists it's auto-loaded; if not, no errors:

```bash
composer require overtrue/easy-sms      # SMS
composer require yansongda/pay          # Alipay/WeChat Pay
composer require league/flysystem       # Filesystem/OSS
composer require phpmailer/phpmailer    # Mail (if you prefer over built-in)
```

Use directly in controllers:

```php
use Overtrue\EasySms\EasySms;

public function sendCode(): void {
    $sms = new EasySms($this->config['sms']);
    $sms->send('13800138000', ['content' => 'Code: 1234']);
}
```

> Composer packages are the PHP ecosystem's public resources, not Laravel-exclusive. Any non-framework-coupled package works in H2PHP.

---

## Form Validation

```php
$v = $this->validate($_POST, [
    'username' => 'required|min_len:3|max_len:20',
    'email'    => 'required|email|unique:users,email',
    'password' => 'required|min_len:6|confirmed',
    'age'      => 'required|integer|min:1|max:150',
], [
    'username' => 'Username',
    'email'    => 'Email',
    'password' => 'Password',
    'age'      => 'Age',
]);

if ($v->fails()) {
    $this->flash('error', $v->firstError());
    $this->redirect('/user/register');
}
```

| Rule | Meaning |
|------|---------|
| `required` | Must not be empty |
| `email` | Valid email format |
| `integer` | Must be an integer |
| `numeric` | Must be numeric |
| `min:n` | Value ≥ n |
| `max:n` | Value ≤ n |
| `min_len:n` | String length ≥ n |
| `max_len:n` | String length ≤ n |
| `in:a,b,c` | Value in list |
| `regex:/pattern/` | Custom regex |
| `url` | Valid URL |
| `confirmed` | Must match `{field}_confirmation` |
| `unique:table,column` | Must be unique in database |

---

## Query Caching

```php
// Cache for 300 seconds
$articles = $this->db->table('articles')
    ->where('status=?', [1])
    ->order('id DESC')
    ->cache(300)
    ->fetchAll();

// Force refresh
$articles = $this->db->table('articles')
    ->where('status=?', [1])
    ->cache(300, true)
    ->fetchAll();
```

Cache driver config in `config/config.php`:

```php
'cache' => [
    'driver' => 'file',   // file | redis | memcache | memcached
    'host'   => '127.0.0.1',
    'port'   => 6379,
    'prefix' => 'h2_',
],
```

---

## Layouts & Partials

```
views/
├── _layouts/
│   └── main.html
└── _partials/
    ├── nav.html
    └── footer.html
```

```php
public function index(): void
{
    $this->layout('main');
    $this->set('title', 'Dashboard');
    $this->render();
}
```

Layout `views/_layouts/main.html`:
```html
<!DOCTYPE html>
<html>
<head><title><?= $title ?></title></head>
<body>
    <?php $this->partial('nav') ?>
    <main><?= $content ?></main>
    <?php $this->partial('footer') ?>
</body>
</html>
```

---

## CSRF Protection

```html
<form method="POST" action="/user/login/submit">
    <?= $csrfField ?>
    ...
</form>
```

```php
public function index(): void {
    $this->set('csrfField', $this->csrfField());
    $this->render();
}

public function submit(): void {
    $this->csrfVerify();  // auto 403 on failure
    // process...
}
```

---

## Queue

```php
// Push job (immediate)
$this->queue('SendWelcomeEmail', ['user_id' => 5]);

// Push with delay (1 hour)
$this->queue('SendReminder', ['user_id' => 5], delay: 3600);
```

Start worker: `php h2 queue:work`

Queue config supports `database` (zero-dep) and `redis` (high-perf) drivers.

---

## Events

```php
// Register listener
$this->on('user.registered', function($user) {
    // send welcome email, log, etc.
});

// Fire event
$this->fire('user.registered', $user);
```

---

## Task Scheduler

Define schedules in `app/schedules.php`:

```php
$scheduler->task('CleanExpiredTokens')->daily();
$scheduler->task('GenerateReport')->dailyAt('02:00');
$scheduler->command('php h2 queue:work --once')->everyMinute();
```

Run via system cron (once per minute):
```
* * * * * cd /path/to/project && php h2 schedule:run
```

---

## Request Helper

```php
$this->request->get('keyword', '');
$this->request->post('username', '');
$this->request->input('key', '');
$this->request->isPost();
$this->request->isAjax();
$this->request->ip();
```

---

## Database Migrations

Create migration files in `migrations/`:

```bash
php h2 migrate              # Run pending migrations
php h2 migrate:rollback     # Rollback last batch
```

---

## Custom Error Pages

Place templates in `views/_errors/`:
```
views/_errors/
├── 403.html
├── 404.html
└── 500.html
```

---

## Testing

```bash
php h2 test              # Run all tests
php h2 test unit         # Unit tests only
php h2 test feature      # Feature tests only
```

---

## Requirements

- PHP 7.2+
- Apache (with `mod_rewrite`) or Nginx
- PDO + PDO_MySQL (when using database)
- Optional: Redis extension (for Redis queue/cache driver)

---

## License

MIT
