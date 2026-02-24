# H2PHP Framework

> The lightest, most efficient PHP MVC framework — directory-based routing, zero configuration

[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.2-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

## ✨ Philosophy

- **Directory = Route** — URLs map directly to the file system, no routing tables
- **Ultra-light** — ~3500 lines of core code, zero external dependencies
- **Batteries included** — 23 built-in components covering all web development scenarios
- **Native PHP templates** — No custom syntax; PHP itself is the best template engine
- **Composer compatible** — Freely integrate any third-party package

## 🚀 Quick Start

```bash
git clone https://github.com/tang30000/h2php.git
cd h2php
composer install
php -S localhost:8080 -t . index.php
```

Visit http://localhost:8080.

## 📁 Project Structure

```
h2php/
├── app/                    # Controllers
│   ├── home/index.php      # → /home/index
│   ├── user/login.php      # → /user/login
│   ├── middleware/          # Middleware
│   └── jobs/               # Queue jobs
├── views/                  # View templates
│   ├── _layouts/           # Layout templates
│   └── _partials/          # Shared partials
├── lib/                    # Framework core (23 components)
├── config/                 # Configuration
├── static/                 # Static assets
├── migrations/             # Database migrations
├── tests/                  # Unit tests
└── index.php               # Entry point
```

## 🔗 Routing

URLs map directly to files under `app/`:

| URL | File | Method |
|-----|------|--------|
| `/` | `app/home/index.php` | `index()` |
| `/user/login` | `app/user/login.php` | `index()` |
| `/post/index/view/5` | `app/post/index.php` | `view($id)` |

## 📦 Built-in Components (23)

### Core
| Component | Description |
|-----------|-------------|
| **Core** | Base controller with view rendering, JSON, redirects |
| **Router** | Directory-based routing engine |
| **Bootstrap** | Framework initialization |
| **Request** | Request wrapper (GET/POST/IP/Method/Ajax) |
| **Response** | Response wrapper (JSON/download/redirect/status) |

### Data
| Component | Description |
|-----------|-------------|
| **DB** | PDO wrapper for MySQL / PostgreSQL / SQLite |
| **Redis** | Full Redis wrapper (strings/hashes/lists/sets/sorted sets/locks/pub-sub/pipelines) |
| **Cache** | Multi-driver cache (file / redis / memcache / memcached) |

### Security
| Component | Description |
|-----------|-------------|
| **Auth** | Password hashing (bcrypt) + Session + JWT |
| **Encryption** | AES-256-CBC encryption + HMAC integrity |
| **Cookie** | Secure cookies (HttpOnly / Secure / SameSite / encrypted) |
| **Validator** | Form validation (15+ rules + custom rules) |

### Utilities
| Component | Description |
|-----------|-------------|
| **Str** | String utilities (slug / random / uuid / camel / mask — 18 methods) |
| **Http** | HTTP client (GET/POST/PUT/DELETE / Bearer / uploads) |
| **Env** | .env environment variable loader |
| **Pagination** | Standalone paginator (auto-paginate / HTML links / API output) |
| **RateLimiter** | Rate limiting (Redis sliding window / file counter) |

### Advanced
| Component | Description |
|-----------|-------------|
| **Event** | Event system (action/filter pattern) |
| **Scheduler** | Task scheduler (cron alternative) |
| **Queue** | Async queue (database / redis drivers) |
| **Mail** | SMTP email sender |
| **Logger** | File-based logging (by date / by level) |

### File
| Component | Description |
|-----------|-------------|
| **Upload** | File upload (type/size validation + auto-rename) |
| **StaticFile** | Static file server (for dev server) |

## 🛠 CLI Tool

```bash
php h2 make:controller user/profile
php h2 make:model User
php h2 migrate
php h2 queue:work
php h2 schedule:run
php h2 serve
php h2 key:generate
php h2 route:list
```

## 📝 Example

```php
<?php
class main extends \Lib\Core
{
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

    public function create(): void
    {
        $this->validate([
            'title' => 'required|min:2',
            'body'  => 'required',
        ]);

        $id = $this->db->table('posts')->timestamps()->insert([
            'title' => $this->request->post('title'),
            'body'  => $this->request->post('body'),
        ]);

        $this->response->status(201)->json(['id' => $id]);
    }
}
```

## ⚙️ Configuration

### config/config.php + .env

```php
return [
    'db' => [
        'dsn'      => 'mysql:host=' . Env::get('DB_HOST') . ';dbname=' . Env::get('DB_NAME'),
        'user'     => Env::get('DB_USER', 'root'),
        'password' => Env::get('DB_PASS', ''),
    ],
    'app_key'   => Env::get('APP_KEY'),
    'debug'     => Env::get('APP_DEBUG', false),
    'middleware' => ['Cors'],
];
```

## 📚 Tutorials

30 lessons available in the [H2CMS](https://github.com/tang30000/h2cms) demo project.

## 📄 License

[MIT](LICENSE)
