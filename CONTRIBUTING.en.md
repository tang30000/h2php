# Contributing Guide

Thank you for your interest in H2PHP! Whether it's fixing a typo or adding a new feature, all contributions are welcome.

## How to Contribute

### 🐛 Report a Bug

1. Search [Issues](https://github.com/tang30000/h2php/issues) to check if it's already reported
2. If not, create a new Issue with:
   - PHP version and OS
   - Minimal reproducible code
   - Expected vs. actual behavior
   - Screenshots or error logs (if any)

### 💡 Feature Requests

Create an Issue with the `feature` label in [Issues](https://github.com/tang30000/h2php/issues), describing:
- The problem you want to solve
- Suggested implementation approach
- Whether you're willing to submit a PR

### 📝 Documentation Improvements

Found a typo, unclear wording, or missing content? Submit a PR directly — no need to create an Issue first.

---

## Submitting a Pull Request

### 1. Fork & Clone

```bash
git clone https://github.com/your-username/h2php.git
cd h2php
composer install    # Install dev dependencies (PHPUnit)
```

### 2. Create a Branch

```bash
git checkout -b fix/typo-in-readme       # Bug fixes
git checkout -b feat/add-session-class   # New features
git checkout -b docs/update-tutorial     # Documentation
```

Branch naming convention:

| Prefix | Purpose | Example |
|--------|---------|---------|
| `fix/` | Bug fix | `fix/db-null-handling` |
| `feat/` | New feature | `feat/add-rate-limiter` |
| `docs/` | Documentation | `docs/fix-readme-typo` |
| `refactor/` | Refactoring | `refactor/simplify-router` |
| `test/` | Tests | `test/add-cache-tests` |

### 3. Coding Standards

- **PHP 7.2 compatible** — Do not use 7.4+ syntax (e.g., typed properties, arrow functions in complex cases)
- **Namespace** — Files under `lib/` use `namespace Lib;`
- **Indentation** — 4 spaces
- **Class names** — PascalCase (e.g., `RateLimiter`)
- **Method names** — camelCase (e.g., `fetchAll`)
- **Single-file principle** — One file per component, aim for under 200 lines
- **Zero dependencies** — Components under `lib/` must not introduce external Composer dependencies

### 4. Run Tests

```bash
# Run all tests
php h2 test

# Or use PHPUnit directly
vendor/bin/phpunit

# Run only tests related to your changes
php h2 test --filter testYourFeature

# PHP syntax check
php -l lib/YourFile.php
```

Make sure all tests pass before submitting.

### 5. Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/) format:

```
fix: correct null handling in DB::fetch()
feat: add Pagination standalone class
docs: fix typo in README
test: add unit tests for Str class
refactor: simplify Router dispatch logic
```

### 6. Open a PR

- Use a concise title describing the change
- In the description, explain: what changed, why, and how you tested it
- Link related Issues (if any): `Closes #12`

---

## Project Structure

Understanding the project structure helps you find the right place for your changes:

```
lib/          → Framework core components (modify carefully, test coverage required)
app/          → Controller examples (add examples here)
views/        → View template examples
config/       → Configuration files
tests/Unit/   → Unit tests
migrations/   → Database migration examples
```

### Adding a New lib Component

1. Create a single file under `lib/` with `namespace Lib;`
2. Class name must match the filename (PascalCase)
3. Add complete PHPDoc comments with usage examples
4. Add corresponding tests under `tests/Unit/`
5. Update both README.md and README.en.md

---

## Code of Conduct

- Respect every participant
- Be friendly and constructive
- Focus on technical discussions
- Accept constructive criticism

---

## License

By submitting code, you agree to release it under the [MIT License](LICENSE).

---

## Contact

If you have questions, discuss in [Issues](https://github.com/tang30000/h2php/issues) or [Discussions](https://github.com/tang30000/h2php/discussions).

Thank you for contributing! 🎉
