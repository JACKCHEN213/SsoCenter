# 实施清单：按控制器拆分路由文件

## 准备阶段

- [x] **T1.1** 阅读当前 `route/app.php` 完整内容，确认所有路由组归属
- [x] **T1.2** 确认 ThinkPHP 8.1 路由多文件加载机制可用（`route/` 目录自动扫描）

## 拆分阶段

按控制器逐个创建路由文件，每完成一个文件即校验语法：

- [x] **T2.1** 创建 `route/application.php`，从 `app.php` 迁移 `Route::group('app', ...)` 路由组
- [x] **T2.2** 创建 `route/login.php`，从 `app.php` 迁移 `Route::group('login', ...)` 路由组
- [x] **T2.3** 创建 `route/reset_password.php`，从 `app.php` 迁移 `Route::group('reset_password', ...)` 路由组
- [x] **T2.4** 创建 `route/signup.php`，从 `app.php` 迁移 `Route::group('signup', ...)` 路由组
- [x] **T2.5** 创建 `route/system_manage.php`，从 `app.php` 迁移 `Route::group('system_manage', ...)` 路由组
- [x] **T2.6** 创建 `route/user.php`，从 `app.php` 迁移 `Route::group('user', ...)` 路由组

## 清理阶段

- [x] **T3.1** 精简 `route/app.php`：移除已迁移的路由组，仅保留 Index 相关路由
- [x] **T3.2** 为所有新文件添加 `declare(strict_types=1);` 和文件注释

## 验证阶段

- [x] **T4.1** 执行 `php -l` 语法检查所有 `route/*.php` 文件
- [x] **T4.2** 执行 `php think route:list` 确认路由列表与拆分前一致
- [x] **T4.3** 逐条比对拆分前后的路由表（路径、方法、控制器、动作），确保零差异
- [x] **T4.4** 测试关键端点可正常访问（如 `/login/`、`/user/`、`/app/add`）
