# 贡献指南

感谢你对 H2PHP 的关注！无论是修一个 typo 还是添加新功能，我们都非常欢迎。

## 如何参与

### 🐛 报告 Bug

1. 先在 [Issues](https://github.com/tang30000/h2php/issues) 中搜索是否已有相关问题
2. 如果没有，创建一个新 Issue，包含：
   - PHP 版本和操作系统
   - 最小可复现代码
   - 期望行为和实际行为
   - 错误截图或日志（如有）

### 💡 功能建议

在 [Issues](https://github.com/tang30000/h2php/issues) 中创建一个标注 `feature` 标签的 Issue，描述：
- 你想解决的问题
- 建议的实现方式
- 是否愿意自己提交 PR

### 📝 文档改进

发现 typo、表述不清或缺失内容？直接提交 PR 即可，不需要事先创建 Issue。

---

## 提交 Pull Request

### 1. Fork & Clone

```bash
git clone https://github.com/你的用户名/h2php.git
cd h2php
composer install    # 安装开发依赖（PHPUnit）
```

### 2. 创建分支

```bash
git checkout -b fix/typo-in-readme       # 修复类
git checkout -b feat/add-session-class   # 新功能
git checkout -b docs/update-tutorial     # 文档类
```

分支命名规范：

| 前缀 | 用途 | 示例 |
|------|------|------|
| `fix/` | Bug 修复 | `fix/db-null-handling` |
| `feat/` | 新功能 | `feat/add-rate-limiter` |
| `docs/` | 文档修改 | `docs/fix-readme-typo` |
| `refactor/` | 重构 | `refactor/simplify-router` |
| `test/` | 测试 | `test/add-cache-tests` |

### 3. 编码规范

- **PHP 7.2 兼容** — 不使用 7.4+ 的语法（如属性类型声明、箭头函数在复杂场景中）
- **命名空间** — lib 目录下文件使用 `namespace Lib;`
- **缩进** — 4 个空格
- **类名** — PascalCase（如 `RateLimiter`）
- **方法名** — camelCase（如 `fetchAll`）
- **单文件原则** — 每个组件一个文件，尽量保持在 200 行以内
- **零依赖** — lib 目录下的组件不得引入外部 Composer 依赖

### 4. 运行测试

```bash
# 运行全部测试
php h2 test

# 或直接用 PHPUnit
vendor/bin/phpunit

# 只运行你修改相关的测试
php h2 test --filter testYourFeature

# PHP 语法检查
php -l lib/YourFile.php
```

确保所有测试通过后再提交。

### 5. 提交信息

遵循 [Conventional Commits](https://www.conventionalcommits.org/) 格式：

```
fix: correct null handling in DB::fetch()
feat: add Pagination standalone class
docs: fix typo in README
test: add unit tests for Str class
refactor: simplify Router dispatch logic
```

### 6. 发起 PR

- 标题简洁描述改动
- 在描述中说明：改了什么、为什么改、怎么测试的
- 关联相关 Issue（如有）：`Closes #12`

---

## 项目结构

提交代码前，了解项目结构有助于找到正确的位置：

```
lib/          → 框架核心组件（修改需谨慎，要有测试覆盖）
app/          → 控制器示例（可添加示例）
views/        → 视图模板示例
config/       → 配置文件
tests/Unit/   → 单元测试
migrations/   → 数据库迁移示例
```

### 添加新 lib 组件的规范

1. 在 `lib/` 下创建单文件，使用 `namespace Lib;`
2. 类名与文件名一致（PascalCase）
3. 添加完整的 PHPDoc 注释，包含用法示例
4. 在 `tests/Unit/` 下添加对应测试
5. 更新 README.md 和 README.en.md

---

## 行为准则

- 尊重每一位参与者
- 保持友善和建设性
- 专注于技术讨论
- 接受建设性批评

---

## 许可协议

提交代码即表示你同意将代码以 [MIT 许可证](LICENSE) 发布。

---

## 联系

如有疑问，可以在 [Issues](https://github.com/tang30000/h2php/issues) 或 [Discussions](https://github.com/tang30000/h2php/discussions) 中讨论。

再次感谢你的贡献！🎉
