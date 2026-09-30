# 设计方案：按控制器拆分路由文件

## ThinkPHP 8.1 多路由文件机制

ThinkPHP 8.1 默认扫描 `route/` 目录下所有 `.php` 文件并加载其中的路由定义。拆分后无需额外注册，框架自动识别。

## 拆分方案

### 文件映射

| 源路由组（app.php 中） | 目标文件 | 前缀 |
|------------------------|----------|------|
| `Route::group('app', ...)` | `route/application.php` | `application/` |
| `Route::group('login', ...)` | `route/login.php` | `login/` |
| `Route::group('reset_password', ...)` | `route/reset_password.php` | `reset_password/` |
| `Route::group('signup', ...)` | `route/signup.php` | `signup/` |
| `Route::group('system_manage', ...)` | `route/system_manage.php` | `system_manage/` |
| `Route::group('user', ...)` | `route/user.php` | `user/` |
| `Route::group('/', ...)` (Index) | `route/app.php`（保留） | `index/` |

### 新路由文件模板

每个拆分出的文件遵循统一结构：

```php
<?php
declare(strict_types=1);

use think\facade\Route;

// [控制器名] 路由
Route::group('[url_prefix]', function () {
    // 迁移过来的路由定义
})->prefix('[controller_prefix]/');
```

### 迁移后 app.php 内容

`route/app.php` 仅保留：
- Index 控制器的 `/` 路由组
- 文件头部的版权声明注释
- `use think\facade\Route;` 引入

## 无 ADR 需要记录

本次变更是纯重构，不涉及架构决策取舍。
