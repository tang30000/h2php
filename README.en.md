# H2PHP — Lightweight PHP MVC Framework

> **Minimalist · Zero Dependencies · Directory = Route · Performance First**
>
> 1% of Laravel's codebase, covering 100% of mainstream framework features.
>
> 23 built-in components · 30 tutorials · MySQL / PostgreSQL / SQLite

[中文文档](README.md)

H2PHP is a minimalist single-entry PHP framework. Routes are directory structures, templates are separated from logic, no complex configuration, no Composer dependencies — preserving the simplicity of native PHP development.

### 🤔 Why Not Laravel?

PHP is PHP because it's **simple, direct, and changes take effect instantly on refresh**. Yet Laravel turns PHP into "pseudo-Java": service containers, dependency injection, facades, service providers, pipeline middleware... An empty request loads hundreds of files, eats 15MB of memory on cold start, and takes 10ms minimum to respond. Opening a controller requires jumping through 5-6 layers to find the actual logic — is this still PHP?

If you want rigorous architecture and extreme performance, use **Java / Go / Rust** — they have **compilation optimization, type systems, and JIT**. Using PHP to imitate Java means taking PHP's slowness while losing PHP's lightness — the worst of both worlds.

**H2PHP's choice: embrace the essence of PHP.** No fewer features than mainstream frameworks (covering 27/27 common features), yet only **1%** of Laravel's codebase. A request goes from `index.php` → `Router.php` → controller in three steps — anyone can **understand the code at a glance**.

| Metric | H2PHP | Laravel |
|--------|------:|--------:|
| Feature Coverage | 27/27 (**100%**) | 27/27 |
| Framework Code | **~3,500 lines** | ~400,000 lines |
| Composer Dependencies | **0** | ~70+ |
| Cold Start Memory | **~1 MB** | ~15 MB |
| Empty Request Response | **< 1 ms** | ~10 ms |
| Time to Understand Code | **3 seconds** | 3 hours |

---

## Features

- **Single-entry Routing** — All requests dispatched via `index.php`, URLs = directory structure
- **Positional Parameters** — String/integer support, auto-cast by method type hints
- **Templates** — Layouts, partials, native PHP syntax
- **Middleware** — Onion-model pipeline, global + controller-level, zero overhead
- **Chainable DB** — Query / relations / cache / transactions / soft deletes / timestamps / multi-database
- **Form Validation** — 13 built-in rules + database uniqueness check
- **File Upload** — Chainable config, auto-validate size/type/rename
- **Queue** — Database/Redis dual drivers, delayed dispatch
- **Task Scheduling** — Cron-style scheduler, 8 frequency methods
- **Event System** — In-request publish/subscribe
- **Logging** — Leveled (info/warning/error/debug), auto-split by date
- **CSRF / Flash / Pagination** — Works out of the box
- **Database Migrations** — Execute/rollback by batch
- **CLI Tool** — make:controller / view / job / task / migrate / queue / schedule / test
- **Local Config** — `config.local.php` + `.env` environment variables, excluded from Git
- **PHPUnit** — Built-in Unit/Feature test suites
- **Security Tools** — Auth (password/session/JWT) · Encryption (AES-256) · Cookie (secure attrs + encryption) · RateLimiter
- **Network Tools** — Http client · Redis wrapper · Response · Pagination
- **String Utilities** — Str (slug/uuid/mask/camel/snake — 18 methods)
- **Minimal Dependencies** — Only PHP 7.2+, zero runtime Composer dependencies

---

### 📖 Table of Contents

| Category | Sections |
|----------|----------|
| Getting Started | [Directory Structure](#directory-structure) · [Routing Rules](#routing-rules) · [CLI Tool](#cli-tool) · [Quick Start](#quick-start) |
| Core Features | [Core API](#core-base-api) · [DB Chainable Query](#db-chainable-query) · [ORM Relations](#orm-relations) · [Transactions](#db-transactions) · [Soft Deletes](#db-soft-deletes) · [Auto Timestamps](#db-auto-timestamps) |
| Request Handling | [Middleware](#middleware) · [Auth Hooks](#auth-example-before-hook--skipbefore) · [Form Validation](#form-validation) · [CSRF](#csrf-protection) · [File Upload](#file-upload) |
| Template System | [Layouts & Partials](#layouts--partials) · [Flash Messages](#flash-messages) · [Pagination](#pagination-helper) · [Custom Error Pages](#custom-error-pages) |
| Async & Scheduling | [Queue](#queue) · [Events](#events) · [Task Scheduling](#task-scheduling-scheduler) |
| Infrastructure | [Cache](#query-cache) · [Logging](#logging) · [Database Migrations](#database-migrations) · [Local Config](#local-config-override-configlocalphp) · [Testing](#testing) |

---

### 📊 Framework Comparison

#### Feature Coverage

| Feature | H2PHP | Laravel | ThinkPHP | CodeIgniter | Slim | Symfony |
|---------|:-----:|:-------:|:--------:|:-----------:|:----:|:-------:|
| Routing | ✅ Directory-based | ✅ Annotations/File | ✅ Config/Annotations | ✅ Config file | ✅ Callback | ✅ Annotations/YAML |
| MVC Layering | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Middleware | ✅ Onion model | ✅ | ✅ | ✅ | ✅ | ✅ |
| ORM / Query Builder | ✅ Lightweight | ✅ Eloquent | ✅ Built-in | ✅ | ❌ | ✅ Doctrine |
| ORM Relations (1:N / N:N) | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| DB Transactions | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Soft Deletes | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Database Migrations | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Auto Timestamps | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Template Engine | ✅ Native PHP | ✅ Blade | ✅ Built-in | ✅ | ❌ | ✅ Twig |
| Layouts / Partials | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Form Validation | ✅ 13 rules | ✅ 60+ rules | ✅ | ✅ | ❌ | ✅ |
| CSRF Protection | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| File Upload | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Cache (multi-driver) | ✅ 4 drivers | ✅ | ✅ | ✅ | ❌ | ✅ |
| Query Cache | ✅ | ❌ Manual | ✅ | ❌ | ❌ | ❌ |
| Queue (multi-driver) | ✅ | ✅ | ✅ | ❌ 3rd party | ❌ | ✅ Messenger |
| Delayed Queue | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| Task Scheduling | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Event System | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Logging | ✅ | ✅ Monolog | ✅ | ✅ | ❌ | ✅ Monolog |
| Multi-env Config | ✅ .local + .env | ✅ .env | ✅ .env | ✅ | ❌ | ✅ .env |
| CLI Tool | ✅ 12 commands | ✅ Artisan | ✅ | ✅ | ❌ | ✅ Console |
| Unit Testing | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| API Response Helpers | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Email Sending | ✅ SMTP | ✅ | ✅ | ✅ | ❌ | ✅ |

#### Engineering Metrics

| Metric | H2PHP | Laravel | ThinkPHP | CodeIgniter | Slim |
|--------|------:|--------:|---------:|------------:|-----:|
| Framework Code | **~3,500 lines** | ~400,000 lines | ~150,000 lines | ~80,000 lines | ~5,000 lines |
| Composer Dependencies | **0** | ~70+ | ~30 | ~5 | ~7 |
| Min PHP Version | **7.2** | 8.2 | 8.0 | 7.4 | 8.1 |
| `composer install` Time | **0 sec** | ~30 sec | ~15 sec | ~5 sec | ~3 sec |
| Cold Start Memory | **~1 MB** | ~15 MB | ~8 MB | ~3 MB | ~2 MB |
| Empty Request Time | **< 1 ms** | ~10 ms | ~5 ms | ~3 ms | ~2 ms |
| Learning Curve | ⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ | ⭐⭐ |

#### Use Cases

| Scenario | Recommended |
|----------|-------------|
| Small/Medium Web Apps, Admin Panels | **H2PHP** ✅ · CodeIgniter |
| API Services (lightweight, high-perf) | **H2PHP** ✅ · Slim |
| Rapid Prototyping, Teaching | **H2PHP** ✅ |
| Large Enterprise Apps, Microservices | Java (Spring) · Go |
| Composer Ecosystem (payments/SMS/OSS) | **H2PHP** ✅ · Laravel · ThinkPHP |
| Legacy Projects (PHP 7.x) | **H2PHP** ✅ · CodeIgniter |

> **H2PHP's positioning**: Feature coverage matches mainstream full-featured frameworks (27/27 common features), but with only **1%** of Laravel's codebase — zero dependencies, zero configuration, zero learning curve. For developers pursuing **ultra-lightweight**, **performance-first**, **fully controllable** architecture who prefer to stay away from "pseudo-Java" heavy architecture and return to PHP's original simplicity.

---

## Directory Structure

```
h2php/
├── h2                     # CLI tool (php h2 <command>)
├── index.php              # Single entry point (no modification needed)
├── .htaccess              # Apache URL rewrite
├── nginx.conf.example     # Nginx config reference
├── composer.json          # Dev dependencies only (phpunit)
│
├── config/
│   └── config.php         # Database, cache, queue config
│
├── lib/                   # Framework core — 23 components (no modification needed)
│   ├── Bootstrap.php      # Framework initialization
│   ├── Router.php         # Route parsing and dispatching
│   ├── Core.php           # Base controller (all feature entry point)
│   ├── DB.php             # PDO query builder (MySQL/PostgreSQL/SQLite)
│   ├── Request.php        # Request wrapper
│   ├── Response.php       # Response wrapper (JSON/download/redirect)
│   ├── Validator.php      # Form validator (13 rules)
│   ├── Auth.php           # Password hashing + Session + JWT
│   ├── Encryption.php     # AES-256-CBC encryption
│   ├── Cookie.php         # Secure cookies
│   ├── Redis.php          # Full Redis wrapper
│   ├── Cache.php          # Multi-driver cache (file/redis/memcache)
│   ├── Http.php           # HTTP client
│   ├── Str.php            # String utilities (18 methods)
│   ├── Env.php            # .env environment variable loader
│   ├── Pagination.php     # Standalone paginator
│   ├── RateLimiter.php    # Rate limiter
│   ├── Event.php          # Event bus
│   ├── Queue.php          # Async queue (database/redis)
│   ├── Scheduler.php      # Task scheduler
│   ├── Mail.php           # SMTP email
│   ├── Logger.php         # Logging
│   ├── Upload.php         # File upload
│   └── StaticFile.php     # Static file server
│
├── app/                   # Your controller code
│   ├── {module}/{feature}.php
│   ├── middleware/         # Middleware files
│   └── jobs/              # Queue Job files
│       └── SendWelcomeEmail.php
│
├── migrations/            # Database migration files
│   └── 001_create_users_table.php
│
├── tests/                 # Test files (PHPUnit)
│   ├── bootstrap.php
│   ├── config.php         # Test database config
│   └── Unit/
│       ├── ValidatorTest.php
│       └── DBTest.php
│
├── cache/                 # File driver cache directory (.gitkeep)
│
├── views/                 # HTML templates
│   ├── _layouts/          # Layout files
│   ├── _partials/         # Partial templates
│   ├── _errors/           # Custom error pages
│   │   ├── 404.html
│   │   └── 500.html
│   ├── {module}/{feature}/{method}.html   # Method-level (priority)
│   └── {module}/{feature}.html            # Controller-level (fallback)
│
└── static/                # Static assets (CSS / JS / images)
```

---

## Routing Rules

```
URL:  /{a}/{b}/{c}/{d1}/{d2}
       │   │   │   └─── Positional params (string/int, auto-cast by type hint)
       │   │   └───── Method name (public method in main class)
       │   └───────── Filename → app/{a}/{b}.php
       └───────────── Directory → app/{a}/
```

| URL | File | Call |
|-----|------|------|
| `/` | `app/home/index.php` | `main::index()` |
| `/user/login` | `app/user/login.php` | `main::index()` |
| `/user/login/submit` | `app/user/login.php` | `main::submit()` |
| `/article/list/show/3` | `app/article/list.php` | `main::show(3)` |
| `/article/list/show/3/2` | `app/article/list.php` | `main::show(3, 2)` |
| `/article/show/view/abc123` | `app/article/show.php` | `main::view('abc123')` |

**Positional parameters auto-cast by type hint:**

```php
// int type hint → '42' in URL auto-converts to 42
public function show(int $id): void { ... }

// string type hint → passed as-is (slugs, hashes, etc.)
public function view(string $slug): void { ... }

// Multiple params, mixed types work fine
public function show(string $category, int $page = 1): void { ... }
// Visit /article/list/show/php/2 → show('php', 2)
```

`?key=val` query parameters are accessed normally via `$_GET` / `$_POST`.

---

## CLI Tool

All commands via the `h2` script in root directory (`php h2 <command>`):

### Code Generation

```bash
# Generate controller (creates app/user/login.php)
php h2 make:controller user/login

# Generate view template (creates views/user/login/index.html)
php h2 make:view user/login/index

# Generate Job template (creates app/jobs/SendWelcomeEmail.php)
php h2 make:job SendWelcomeEmail

# Generate scheduled task template (creates app/tasks/CleanExpiredTokens.php)
php h2 make:task CleanExpiredTokens
```

### Database Migrations

```bash
php h2 migrate             # Run all pending migrations
php h2 migrate:rollback    # Rollback last batch
php h2 migrate:status      # View migration status
```

### Queue Worker

```bash
php h2 queue:work          # Start worker, continuous polling (Ctrl+C to stop)
php h2 queue:work --once   # Process one job and exit, suitable for cron
php h2 queue:status        # View job counts by status (pending/done/failed)
php h2 queue:clear         # Clear completed and failed job records
```

### Task Scheduling

```bash
php h2 schedule:run        # Execute all due scheduled tasks
php h2 schedule:list       # List all registered scheduled tasks
```

### Testing

```bash
php h2 test                         # Run all tests
php h2 test --filter testEmail      # Filter by test name
php h2 test --testsuite Unit        # Run Unit suite only
```

---

## Quick Start

### 1. Deploy

**Apache**: Place project in web root. `.htaccess` includes rewrite rules, works out of the box.

**Nginx**: Reference `nginx.conf.example` for URL rewrite config.

**PHP Built-in Server** (development):
```bash
php -S localhost:8080 index.php
```

### 2. Configure

Edit `config/config.php` with your database connection:

```php
'db' => [
    'dsn'      => 'mysql:host=localhost;dbname=your_db;charset=utf8mb4',
    'user'     => 'root',
    'password' => 'your_password',
],
```

### 3. Create a Page

**Create controller** `app/goods/detail.php`:

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

    // String param example: GET /goods/detail/slug/iphone-16-pro
    public function slug(string $slug): void
    {
        $goods = $this->db->table('goods')->where('slug=?', [$slug])->fetch();
        $this->set('goods', $goods);
        $this->render();
    }
}
```

**Create template** `views/goods/detail/view.html` (or `views/goods/detail.html`):

```html
<!DOCTYPE html>
<html>
<body>
    <h1><?= htmlspecialchars($goods['name']) ?></h1>
    <p>Price: ¥<?= $goods['price'] ?></p>
</body>
</html>
```

Visit `/goods/detail/view/100` — no additional configuration needed.

> **Template lookup rule**: `render()` first looks for `views/a/b/c.html`, falls back to `views/a/b.html`. Both directory structures can be mixed.

---

## Core Base API

**Template & Response:**

| Method | Description |
|--------|-------------|
| `$this->set($key, $val)` | Pass variable to template |
| `$this->setMulti($array)` | Pass multiple variables |
| `$this->render($tpl)` | Render template (defaults to same-name) |
| `$this->json($data)` | Output JSON |
| `$this->redirect($url)` | Redirect (default 302) |
| `$this->layout($name)` | Set layout file (`_layouts/`) |
| `$this->partial($name)` | Include partial template (`_partials/`) |

**Helpers:**

| Method | Description |
|--------|-------------|
| `$this->flash($type, $msg)` | Set cross-request Flash message |
| `$this->getFlash($type)` | Read and clear specific Flash |
| `$this->getAllFlash()` | Read and clear all Flash messages |
| `$this->paginate($total, $page, $size, $url)` | Generate pagination array |
| `$this->validate($data, $rules, $labels)` | Create validator, returns `Validator` instance |
| `$this->csrfToken()` | Get/generate CSRF Token |
| `$this->csrfField()` | Return CSRF hidden field HTML |
| `$this->csrfVerify()` | Verify POST CSRF token, fails with 403 |
| `$this->abort($code, $msg)` | Terminate with error page (supports custom `_errors/`) |
| `$this->success($data, $msg)` | JSON success `{"code":0,"msg":"ok","data":...}` |
| `$this->fail($msg, $code)` | JSON failure `{"code":-1,"msg":"...","data":null}` |
| `$this->log($level, $msg, $ctx)` | Write log (info/warning/error/debug) |
| `$this->mail($to, $subj, $body)` | Send email (SMTP) |
| `$this->upload($field, $dir)` | File upload helper, returns `Upload` instance |
| `$this->on($event, $fn)` | Register event listener (request-scoped) |
| `$this->fire($event, $data)` | Fire event |
| `$this->queue($jobName, $payload)` | Push job to queue (async) |

**Properties & Hooks:**

| | Description |
|-|-------------|
| `$this->db` | DB instance (lazy-loaded) |
| `$this->request` | Request instance (lazy-loaded) |
| `$this->response` | Response instance (lazy-loaded) |
| `$this->redis` | Redis instance (lazy-loaded) |
| `before()` | Hook: before method execution (override for auth) |
| `after()` | Hook: after method execution |

---

## Flash Messages

```php
// Set Flash after operation, then redirect
public function delete(int $id): void {
    $this->db->table('users')->where('id=?', [$id])->delete();
    $this->flash('success', 'Deleted successfully');
    $this->redirect('/user/list');
}

// Receiving page passes Flash to template
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

## Pagination Helper

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
<?php if ($p['hasPrev']): ?><a href="<?= $p['prevUrl'] ?>">Previous</a><?php endif; ?>
<?php foreach ($p['links'] as $link): ?>
<a href="<?= $link['url'] ?>" <?= $link['active'] ? 'class="active"' : '' ?>><?= $link['page'] ?></a>
<?php endforeach; ?>
<?php if ($p['hasNext']): ?><a href="<?= $p['nextUrl'] ?>">Next</a><?php endif; ?>
```

---

## Custom Error Pages

Create error page templates in `views/_errors/`, the framework uses them automatically:

```
views/_errors/
├── 404.html   ← Controller/method not found
└── 500.html   ← Server error
```

Three variables available in templates: `$code` (status code), `$title` (title), `$message` (detail). Falls back to built-in styles if not found.

---

## Database Migrations

Create migration files in `migrations/`:

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

Automatically creates `_migrations` tracking table, records batch numbers, supports rollback by batch.

---

## ORM Relations

`hasMany` and `belongsTo` both return chainable DB instances — you can continue with `.order()` `.limit()` `.cache()` etc.:

```php
// One-to-many: get all posts for a user (returns array)
$posts = $this->db->hasMany('posts', 'user_id', $user['id'])
    ->order('id DESC')
    ->limit(10)
    ->fetchAll();

// Many-to-one: get the user for a post (returns single record)
$author = $this->db->belongsTo('users', 'id', $post['user_id'])->fetch();

// Combined usage
public function show(int $id): void {
    $post   = $this->db->table('posts')->where('id=?', [$id])->fetch();
    $author = $this->db->belongsTo('users', 'id', $post['user_id'])->fetch();
    $tags   = $this->db->hasMany('post_tags', 'post_id', $id)->fetchAll();

    $this->setMulti(compact('post', 'author', 'tags'));
    $this->render();
}

// Many-to-many: get all tags for a post (via post_tag pivot table)
$tags = $this->db->belongsToMany('tags', 'post_tag', 'post_id', 'tag_id', $postId)
    ->order('tags.name')
    ->fetchAll();
```

| Method | Description |
|--------|-------------|
| `hasMany($table, $fk, $id)` | One-to-many, returns chainable DB |
| `belongsTo($table, $pk, $fk)` | Many-to-one, typically followed by `->fetch()` |
| `belongsToMany($table, $pivot, $localFk, $relFk, $id)` | Many-to-many, INNER JOIN pivot table |

---

## Events

Events are request-scoped (publish/subscribe), suitable for decoupling synchronous flows:

```php
// Register listener (usually in before() or index.php)
$this->on('user.registered', function(array $user) {
    // Send welcome email, write logs, etc.
});

// Fire (in controller)
$this->fire('user.registered', ['id' => $id, 'email' => $email]);

// Static class usage
\Lib\Event::on('order.paid', fn($o) => /* ... */);
\Lib\Event::fire('order.paid', $order);
```

---

## Queue

Queue jobs execute asynchronously, persisted across requests. Job files go in `app/jobs/`:

```php
// app/jobs/SendWelcomeEmail.php
class SendWelcomeEmail {
    public function handle(array $payload): void {
        mail($payload['email'], 'Welcome', 'Thank you for registering!');
    }
}
```

```php
// Enqueue in controller (returns immediately, job runs in background)
$this->queue('SendWelcomeEmail', ['user_id' => $id, 'email' => $email]);

// Delayed enqueue (executed 1 hour later)
$this->queue('SendReminder', ['user_id' => $id], delay: 3600);
```

```bash
# Start Worker (continuous)
php h2 queue:work

# Cron mode (once per minute)
* * * * * php /path/to/h2 queue:work --once
```

**`config/config.php` configuration:**

```php
'queue' => [
    'driver'       => 'database',  // database (default, zero deps) | redis (high perf)
    'host'         => '127.0.0.1',
    'port'         => 6379,
    'password'     => '',
    'key'          => 'h2_jobs',
    'max_attempts' => 3,           // Max retry count on failure
],
```

> **database driver**: Jobs stored in `_jobs` table (auto-created), uses `FOR UPDATE` to prevent concurrent conflicts, supports failure retry.
> **redis driver**: `BRPOP` blocking, near real-time response, suitable for high-frequency scenarios.

---

## Task Scheduling (Scheduler)

Only one system cron needed, the framework internally determines which tasks are due:

```bash
# System cron only needs this one entry (triggered by OS every minute)
* * * * * php /path/to/h2 schedule:run
```

Define all tasks in `app/schedules.php`:

```php
return function(\Lib\Scheduler $s) {

    // Clean expired tokens daily at 2 AM
    $s->call('CleanExpiredTokens')->daily()->description('Clean expired tokens');

    // Send daily report at 8 AM
    $s->call('SendDailyReport')->dailyAt('08:00');

    // Sync inventory every 15 minutes
    $s->call('SyncInventory')->everyMinutes(15);

    // Clear queue every Monday midnight
    $s->command('queue:clear')->weekly();

    // Custom cron expression
    $s->call('BackupDatabase')->cron('0 3 * * 0');

    // Closure task
    $s->job(function() {
        // Simple logic inline
    }, 'CleanOldCache')->daily();
};
```

| Frequency Method | Description |
|-----------------|-------------|
| `->everyMinute()` | Every minute |
| `->everyMinutes(15)` | Every 15 minutes |
| `->hourly()` | Every hour on the hour |
| `->daily()` | Daily at midnight |
| `->dailyAt('08:30')` | Daily at specified time |
| `->weekly()` | Every Monday midnight |
| `->monthly()` | First day of month midnight |
| `->cron('0 2 * * 0')` | Custom expression |

Task files go in `app/tasks/`, implement `handle(): void`:

```bash
php h2 make:task CleanExpiredTokens   # Generate template
php h2 schedule:list                  # List all registered tasks
```

---

## Testing

Built-in PHPUnit test suite, zero runtime Composer dependencies, testing tools installed as dev dependencies:

```bash
# First-time PHPUnit install (dev only)
composer install

# Run tests
php h2 test
php h2 test --filter testEmail        # Filter
php h2 test --testsuite Unit          # Specific suite
```

**Example:** `tests/Unit/ValidatorTest.php`

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

> `DBTest.php` uses SQLite in-memory database, no MySQL config needed, covers all chainable query APIs.

---

## Query Cache

Add `->cache(seconds)` at the end of a chainable query. Cache key auto-generated from `md5(SQL + params)`.

```php
// Cache for 300 seconds (returns from cache if hit, queries DB and caches if miss)
$articles = $this->db->table('articles')
    ->where('status=?', [1])
    ->order('id DESC')
    ->cache(300)
    ->fetchAll();

// Force refresh (2nd param true): ignore old cache, re-query and overwrite
$articles = $this->db->table('articles')
    ->where('status=?', [1])
    ->cache(300, true)
    ->fetchAll();
```

**Typical pattern** — proactively refresh cache after update:

```php
public function update(int $id, array $data): void {
    $this->db->table('articles')->where('id=?', [$id])->update($data);

    // Actively refresh list and detail cache
    $this->db->table('articles')->order('id DESC')->limit(20)->cache(300, true)->fetchAll();
    $this->db->table('articles')->where('id=?', [$id])->cache(3600, true)->fetch();

    $this->flash('success', 'Updated successfully');
    $this->redirect('/article/list');
}
```

Driver configured in `config/config.php` (default file, no extensions needed):

```php
'cache' => [
    'driver'   => 'file',          // file | redis | memcache | memcached
    'host'     => '127.0.0.1',
    'port'     => 6379,            // Redis: 6379, Memcache: 11211
    'prefix'   => 'h2_',           // Key prefix to prevent multi-project conflicts
    'password' => '',              // Redis password (leave empty if none)
    // 'dir'   => '/tmp/h2cache',  // File driver cache directory (default ROOT/cache)
],
```

---

## DB Chainable Query

```php
// Select multiple rows
$users = $this->db->table('users')
    ->where('status=?', [1])
    ->order('id DESC')
    ->limit(20, ($page - 1) * 20)
    ->fetchAll();

// Select single row
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

## Auth Example (before Hook + skipBefore)

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

    public function index(): void { $this->render(); } // No login needed

    public function dashboard(): void
    {
        // Only executes after before() passes
        $this->render();
    }
}
```

---

## Middleware

Middleware executes before controllers as reusable processing layers, using the onion model:

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
            return;  // Don't call $next() = intercept request
        }

        $next();  // Continue to next middleware → controller
    }
}
```

**Global registration** (all requests pass through):

```php
// config/config.php
'middleware' => [
    'Cors',       // CORS support
    'AuthCheck',  // Global login check
],
```

**Controller-level registration** (specific controller only):

```php
class main extends \Lib\Core
{
    protected array $middleware = ['AuthCheck'];  // This controller only
}
```

> When no middleware is configured (empty array or not set), the framework completely skips the pipeline logic — zero performance overhead.

---

## abort / success / fail

```php
// Terminate with error page (uses views/_errors/{code}.html or built-in styles)
$this->abort(403, 'Access denied');
$this->abort(404, 'Resource not found');

// JSON API standard responses
$this->success($data);                      // {"code":0,"msg":"ok","data":...}
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

// Relative path for database storage, usable in <img src>
$path = $file->path();  // e.g.: static/uploads/avatars/3f2a...b1.jpg

// Chainable config (optional)
$file = $this->upload('photo', 'static/uploads')
    ->maxSize(3 * 1024 * 1024)          // Max 3 MB (default 5 MB)
    ->allowTypes(['jpg', 'png', 'webp']) // Allowed types
    ->rename('timestamp');               // uuid (default) | timestamp | original
```

---

## Local Config Override (config.local.php)

For local development, create `config/config.local.php` (already in `.gitignore`):

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

The framework auto-detects and deep-merges `config.local.php` after loading `config.php`. Any config item can be overridden.

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

// Without timestamps(), behavior remains unchanged — fully backward compatible
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
    // Throws exception → auto rollback
});
```

---

## DB Soft Deletes

Add a `deleted_at` field (DATETIME, NULL) to the table, then use `softDeletes()`:

```php
// Soft delete (sets deleted_at, no physical deletion)
$this->db->table('posts')->softDeletes()->where('id=?', [$id])->softDelete();

// Queries auto-exclude soft-deleted records
$posts = $this->db->table('posts')->softDeletes()->fetchAll();

// Include deleted records
$all = $this->db->table('posts')->softDeletes()->withTrashed()->fetchAll();

// Only deleted records
$trashed = $this->db->table('posts')->softDeletes()->onlyTrashed()->fetchAll();

// Restore
$this->db->table('posts')->softDeletes()->where('id=?', [$id])->restore();
```

> Without `softDeletes()`, `delete()` remains physical deletion — fully backward compatible.

---

## Logging

```php
// In controller
$this->log('info', 'User logged in', ['user_id' => $id]);
$this->log('error', 'Payment failed', ['order_id' => 123, 'reason' => $msg]);

// Static call (anywhere)
\Lib\Logger::info('Cache cleared');
\Lib\Logger::warning('Low stock', ['sku' => 'A001']);
\Lib\Logger::error('DB connection failed', ['dsn' => $dsn]);
\Lib\Logger::debug('SQL execution', ['sql' => $sql, 'time' => $ms]);
```

Log files auto-split by date: `logs/2026-02-25.log`

```
[2026-02-25 14:30:15] [INFO] User logged in {"user_id":5}
[2026-02-25 14:30:16] [ERROR] Payment failed {"order_id":123,"reason":"Insufficient balance"}
```

---

## Email Sending

```php
// One-line quick send (HTML tags auto-detected as HTML email)
$this->mail('user@example.com', 'Registration Successful', '<h1>Welcome</h1><p>Thanks for registering!</p>');

// Chainable (advanced)
$mail = new \Lib\Mail($this->config['mail']);
$ok = $mail->to('user@example.com')
    ->cc('admin@example.com')
    ->subject('Order Confirmation')
    ->html('<p>Your order has been created</p>')
    ->send();

if (!$ok) {
    $this->log('error', 'Email send failed', ['error' => $mail->error()]);
}
```

SMTP config (`config.php`):

```php
'mail' => [
    'host'     => 'smtp.gmail.com',     // Gmail / QQ / Aliyun etc.
    'port'     => 465,
    'user'     => 'noreply@example.com',
    'password' => 'app_password',        // App password, not login password
    'name'     => 'H2PHP App',           // Sender display name
    'ssl'      => true,
],
```

> Zero dependencies — internally connects directly to SMTP server via socket, no PHPMailer etc. needed.

---

## Composer Third-party Packages

H2PHP itself has zero dependencies, but is fully compatible with the Composer ecosystem. When `vendor/autoload.php` is installed it auto-loads; without it, no error:

```bash
# Install any Composer package
composer require overtrue/easy-sms      # SMS
composer require yansongda/pay          # Alipay/WeChat Pay
composer require league/flysystem       # Filesystem/OSS
composer require phpmailer/phpmailer    # Email (if you prefer over built-in Mail)
```

Use directly in controllers:

```php
use Overtrue\EasySms\EasySms;

public function sendCode(): void {
    $sms = new EasySms($this->config['sms']);
    $sms->send('13800138000', ['content' => 'Verification code: 1234']);
}
```

> Composer packages are public PHP ecosystem resources, not exclusive to Laravel. Any non-framework-coupled package works directly in H2PHP.

---

## New Built-in Components (v1.1)

The following components were added in v1.1, all with zero external dependencies:

| Component | Description | Example |
|-----------|-------------|---------|
| **Response** | Unified response wrapper | `$this->response->status(201)->json($data)` |
| **Auth** | Password hashing + Session + JWT | `Auth::hashPassword()` / `Auth::login()` / `Auth::jwtEncode()` |
| **Encryption** | AES-256-CBC + HMAC tamper detection | `$enc->encrypt($data)` / `$enc->decrypt($cipher)` |
| **Cookie** | Secure cookies (HttpOnly/SameSite/encrypted) | `$cookie->setEncrypted('token', $val)` |
| **Redis** | Full wrapper (7 data structures + locks + pipelines + pub/sub) | `$this->redis->set()` / `$this->redis->lock()` |
| **Http** | cURL HTTP client | `(new Http)->withToken($t)->post($url, $data)->json()` |
| **Str** | 18 string utility methods | `Str::slug()` / `Str::uuid()` / `Str::mask()` |
| **Env** | .env environment variable loader | `Env::get('DB_HOST', 'localhost')` |
| **Pagination** | Standalone paginator | `Pagination::fromQuery($query, $page, 10)` |
| **RateLimiter** | Sliding window rate limiting (Redis/file backends) | `$limiter->tooMany('api:'.$ip, 60, 60)` |

Controller lazy-loading: `$this->db` / `$this->request` / `$this->response` / `$this->redis`

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
| `email` | Email format |
| `integer` | Must be integer |
| `numeric` | Must be numeric |
| `min:n` | Value ≥ n |
| `max:n` | Value ≤ n |
| `min_len:n` | String length ≥ n |
| `max_len:n` | String length ≤ n |
| `in:a,b,c` | Value must be in list |
| `regex:/pattern/` | Regex match |
| `url` | URL format |
| `confirmed` | Must match `{field}_confirmation` (password confirm) |
| `unique:table,column` | Database uniqueness check |

---

## Layouts & Partials

**Directory convention:**
```
views/
├── _layouts/    # Layout files
│   └── main.html
└── _partials/   # Partial templates (header, footer, etc.)
    ├── nav.html
    └── footer.html
```

**In controller:**
```php
public function index(): void
{
    $this->layout('main');          // Use views/_layouts/main.html
    $this->set('title', 'User Center');
    $this->render();
}
```

**Layout file** `views/_layouts/main.html`:
```html
<!DOCTYPE html>
<html>
<head><title><?= $title ?></title></head>
<body>
    <?php $this->partial('nav') ?>       <!-- Include _partials/nav.html -->
    <main><?= $content ?></main>         <!-- Page body auto-injected -->
    <?php $this->partial('footer') ?>
</body>
</html>
```

> Without calling `layout()`, `render()` behaves as before — fully backward compatible.

---

## Request Wrapper

```php
$this->request->get('keyword', '');     // $_GET, with default
$this->request->post('username', '');   // $_POST, with default
$this->request->input('key', '');       // GET + POST merged (POST priority)
$this->request->isPost();               // Is POST request?
$this->request->isAjax();              // Is AJAX request?
$this->request->ip();                   // Client IP
```

---

## CSRF Protection

Output hidden field in form template:

```html
<form method="POST" action="/user/login/submit">
    <?= $csrfField ?>    <!-- Outputs <input type="hidden" name="_csrf" value="..."> -->
    ...
</form>
```

In controller — pass field + verify:

```php
// Login page: pass CSRF field to template
public function index(): void {
    $this->set('csrfField', $this->csrfField());
    $this->render();
}

// Submit handler: verify first
public function submit(): void {
    $this->csrfVerify();  // Fails with auto 403
    // Continue processing...
}
```

| Method | Description |
|--------|-------------|
| `$this->csrfToken()` | Get (or generate) Session token |
| `$this->csrfField()` | Return hidden form field HTML string |
| `$this->csrfVerify()` | Verify POST request token, auto 403 on failure |

---

## System Requirements

| Item | Requirement |
|------|-------------|
| PHP | 7.2+ (runtime) / 7.4+ (PHPUnit) |
| Web Server | Apache (mod_rewrite) or Nginx |
| PHP Extensions (runtime) | PDO + PDO_MySQL (when using database) |
| PHP Extensions (optional) | Redis (queue/cache redis driver) / Memcache |
| PHP Extensions (testing) | pdo_sqlite (DBTest uses SQLite in-memory) |
| Composer | Only needed for dev (install PHPUnit) |

---

## 📚 Tutorials

[H2CMS](https://github.com/tang30000/h2cms) demo project provides **30 lessons** of complete tutorials:

| Stage | Lessons |
|-------|---------|
| **Basics** (1-5) | Routing → MVC → Database → Validation → CSRF |
| **Intermediate** (6-15) | Middleware → Upload → Soft Deletes → Transactions → Events → Logging → Layouts → Timestamps → Config |
| **Core** (16-20) | Cache → Request → CLI → Scheduler → Plugin System |
| **Tools** (21-28) | Redis → Http → Auth → Encryption → RateLimiter → Cookie/Str → Multi-DB → Composer |
| **Advanced** (29-30) | Response/Pagination → Env/CORS |

---

## License

MIT
