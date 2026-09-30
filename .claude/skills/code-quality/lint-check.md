```
---
name: lint-check
category: code-quality
description: 代码风格检查与修复（PHP/JS/CSS）
triggers: 检查代码风格, 格式化代码, 修复格式, 跑lint, 检查规范
version: 1.0.0
---
```



# Skill: 代码风格检查与修复

## 触发条件
用户说："检查代码风格"、"帮我格式化代码"、"修复 PHP 格式"、"跑一下 lint"、"检查 JS 规范"、"检查 CSS 规范"等

## 前置条件
- PHP_CodeSniffer 已安装（`composer require --dev squizlabs/php_codesniffer`）
- ESLint 已安装（项目根目录有 `eslint.config.js`）
- Stylelint 已安装（项目根目录有 `stylelint.config.js`）

## 执行步骤

### 1. PHP 代码检查（PSR-12）

**检查单个文件**：
```bash
/usr/local/php8.0/bin/php /claude_code/php_codesniffer/bin/phpcs --standard=vinchin --extensions=php,html,htm ${文件名}
```

**检查整个目录**：

```bash
/usr/local/php8.0/bin/php /claude_code/php_codesniffer/bin/phpcs --standard=vinchin --extensions=php,html,htm app/controller/
```

**自动修复**：
```bash
/usr/local/php8.0/bin/php /claude_code/php_codesniffer/bin/phpcbf --standard=vinchin --extensions=php,html,htm ${文件名}
```

### 2. JavaScript 代码检查

**检查单个文件**：
```bash
npx eslint --ext .js --global /claude_code/SsoCenter-top8.1/globals.d.ts -c /claude_code/eslint/eslint.config.js ${文件名}
```

**检查整个目录**：
```bash
npx eslint --ext .js --global /claude_code/SsoCenter-top8.1/globals.d.ts -c /claude_code/eslint/eslint.config.js app/controller/
```

**自动修复**：
```bash
npx eslint --ext .js --global /claude_code/SsoCenter-top8.1/globals.d.ts -c /claude_code/eslint/eslint.config.js --fix ${文件名}
```

**注意**：配置文件使用 `eslint.config.js`（ESM 格式），已包含全局变量定义。

### 3. CSS/SCSS 代码检查

**检查单个文件**：
```bash
npx stylelint --config /claude_code/eslint/stylelint.config.js ${文件名}
```

**检查整个目录**：
```bash
npx stylelint --config /claude_code/eslint/stylelint.config.js "public/assets/**/*.css"
```

**自动修复**：
```bash
npx stylelint --config /claude_code/eslint/stylelint.config.js --fix ${文件名}
```

### 4. 批量检查（推荐）

**一次性检查所有类型**：
```bash
# PHP
/usr/local/php8.0/bin/php /claude_code/php_codesniffer/bin/phpcs --standard=vinchin --extensions=php,html,htm app/

# JavaScript
npx eslint --ext .js --global /claude_code/SsoCenter-top8.1/globals.d.ts -c /claude_code/eslint/eslint.config.js "app/view/**/*.js"

# CSS
npx stylelint --config /claude_code/eslint/stylelint.config.js "public/assets/**/*.css"
```

## 修复失败处理

如果自动修复后仍有问题：

1. **分析错误信息**，尝试手动修复（最多 3 次）
2. 如果 3 次后仍失败，**放弃修复**并在任务输出中注明：
   ```
   ⚠️ 以下文件自动修复失败，请人工介入：
   - app/controller/Login.php
     错误：Missing function doc comment
     建议：添加 `@param` 和 `@return` 注释
   - public/assets/js/app.js
     错误：Unexpected var
     建议：将 `var` 替换为 `const` 或 `let`
   ```

## 常见问题处理

| 问题 | 原因 | 解决方案 |
|------|------|----------|
| `phpcs: command not found` | PHP_CodeSniffer 未安装 | `composer require --dev squizlabs/php_codesniffer` |
| `ESLint: command not found` | ESLint 未安装 | `npm install -D eslint` |
| `Stylelint: command not found` | Stylelint 未安装 | `npm install -D stylelint stylelint-config-standard stylelint-order stylelint-scss` |
| `Cannot find module` | 依赖缺失 | `npm install` |
| 修复后仍有大量错误 | 配置文件与项目不匹配 | 检查 `eslint.config.js` 和 `stylelint.config.js` 是否存在 |

## 工作流建议

1. **开发完成后**：自动运行检查
2. **提交前**：运行所有检查并修复
3. **CI/CD**：配置自动 lint 检查

