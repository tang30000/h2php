# H2PHP

> A lightweight, native, non-intrusive PHP MVC framework
>
> **The most lightweight and efficient MVC framework ever.**

H2PHP is a minimalist single-entry PHP framework. Routes map directly to the directory structure, templates are separated from logic, zero complex configuration, zero Composer dependencies — retaining the natural comfort of plain PHP development.

[中文文档](README.md)

---

## Features

- **Single entry point** — All requests are dispatched through `index.php`
- **Directory = Route** — URL structure mirrors the file directory; no guesswork
- **Numeric positional parameters** — Numeric URL segments are automatically injected as method arguments (great for IDs, pagination, etc.)
- **Template separation** — Controllers and HTML templates are fully decoupled; templates support native PHP syntax
- **Zero dependencies** — PHP 7.2+ only, no Composer, no third-party libraries required
- **Chainable DB** — Built-in PDO wrapper with a fluent query interface and raw SQL support

---

## Directory Structure

```
h2php/
├── index.php              # Single entry point (no need to modify)
├── .htaccess              # Apache URL rewrite rules
├── nginx.conf.example     # Nginx configuration reference
│
├── config/
│   └── config.php         # Database, routing defaults, debug toggle
│
├── lib/                   # Framework core (no need to modify)
│   ├── Router.php         # Route parsing and dispatching
│   ├── Core.php           # Base controller
│   ├── DB.php             # PDO database wrapper
│   └── Request.php        # HTTP request wrapper
│
├── app/                   # Your controller code
│   └── {module}/{feature}.php
│
├── views/                 # HTML templates (two-level lookup, see below)
│   ├── {module}/{feature}/{method}.html   # Method-level (preferred)
│   └── {module}/{feature}.html           # Controller-level (fallback)
│
└── static/                # Static assets (CSS / JS / images)
```

---

## Routing Rules

```
URL:  /{a}/{b}/{c}/{d1}/{d2}
       │   │   │   └─── Numeric positional params (injected as method args)
       │   │   └─────── Method name (a public method in the main class)
       │   └─────────── File name → app/{a}/{b}.php
       └─────────────── Directory → app/{a}/
```

| URL | File | Call |
|-----|------|------|
| `/` | `app/home/index.php` | `main::index()` |
| `/user/login` | `app/user/login.php` | `main::index()` |
| `/user/login/submit` | `app/user/login.php` | `main::submit()` |
| `/article/list/show/3` | `app/article/list.php` | `main::show(3)` |
| `/article/list/show/3/2` | `app/article/list.php` | `main::show(3, 2)` |

Additional parameters (non-numeric) are accessed via `$_GET` / `$_POST` as usual.

---

## Quick Start

### 1. Deploy

**Apache**: Drop the project into your web root. The `.htaccess` file handles URL rewriting out of the box.

**Nginx**: Use `nginx.conf.example` as a reference for URL rewrite configuration.

**PHP Built-in Server** (local development):
```bash
php -S localhost:8080 index.php
```

> ⚠️ The PHP built-in server does not support URL rewriting. Prefix routes with `?`:
> - Production: `http://localhost/user/login/show/42`
> - Built-in server: `http://localhost:8080?user/login/show/42`

### 2. Configure

Edit `config/config.php` with your database credentials:

```php
'db' => [
    'dsn'      => 'mysql:host=localhost;dbname=your_db;charset=utf8mb4',
    'user'     => 'root',
    'password' => 'your_password',
],
```

### 3. Create a Page

**Create a controller** at `app/goods/detail.php`:

```php
<?php
class main extends \Lib\Core
{
    // Accessed via /goods/detail/view/100 → view(100)
    public function view(int $id): void
    {
        $goods = $this->db->table('goods')->where('id=?', [$id])->fetch();
        $this->set('goods', $goods);
        $this->render();
        // render() auto-locates the template in this order:
        //   1. views/goods/detail/view.html  (method-level, preferred)
        //   2. views/goods/detail.html        (fallback)
    }
}
```

**Create a template** at `views/goods/detail/view.html` (or `views/goods/detail.html`):

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

> **Template lookup rule**: `render()` first looks for `views/a/b/c.html`. If not found, it falls back to `views/a/b.html`. Both directory layouts can be mixed freely.

---

## Core Base Class API

| Method | Description |
|--------|-------------|
| `$this->set($key, $val)` | Pass a variable to the template |
| `$this->setMulti($array)` | Pass multiple variables at once |
| `$this->render($tpl)` | Render a template (defaults to current route's template) |
| `$this->json($data)` | Output JSON (for API endpoints) |
| `$this->redirect($url)` | HTTP redirect |
| `$this->db` | DB instance (lazy-loaded) |
| `$this->request` | Request instance (lazy-loaded) |
| `before()` | Hook: called before the action method (override for auth, etc.) |
| `after()` | Hook: called after the action method |

---

## DB Cheat Sheet

```php
// Fetch multiple rows (with pagination)
$users = $this->db->table('users')
    ->where('status=?', [1])
    ->order('id DESC')
    ->limit(20, ($page - 1) * 20)
    ->fetchAll();

// Fetch a single row
$user = $this->db->table('users')->where('id=?', [$id])->fetch();

// Count rows
$total = $this->db->table('users')->count();

// Insert (returns new auto-increment ID)
$id = $this->db->table('users')->insert(['name' => 'Tom', 'email' => 'tom@example.com']);

// Update
$this->db->table('users')->where('id=?', [$id])->update(['name' => 'Jerry']);

// Delete
$this->db->table('users')->where('id=?', [$id])->delete();

// Raw SQL
$rows = $this->db->query('SELECT * FROM users WHERE age > ?', [18]);
```

---

## Authentication with the `before()` Hook

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
        // Only reached if before() does not redirect
        $this->render();
    }
}
```

---

## Request Helper

```php
$this->request->get('keyword', '');     // $_GET with default value
$this->request->post('username', '');   // $_POST with default value
$this->request->input('key', '');       // GET + POST merged (POST wins)
$this->request->isPost();               // Is it a POST request?
$this->request->isAjax();              // Is it an AJAX request?
$this->request->ip();                   // Client IP address
```

---

## Requirements

- PHP 7.2+
- Apache (with `mod_rewrite`) or Nginx
- PDO + PDO_MySQL extension (when using the database)

---

## License

MIT
