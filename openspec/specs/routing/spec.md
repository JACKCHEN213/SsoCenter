# routing Specification

## Purpose
TBD - created by archiving change split-routes-by-controller. Update Purpose after archive.
## Requirements
### Requirement: 路由按控制器拆分到独立文件
系统 SHALL 将各控制器的路由定义拆分到 `route/` 目录下的独立文件中。

#### Scenario: Application 控制器路由独立
- GIVEN Application 控制器在 `app/controller/Application.php`
- WHEN 系统加载路由配置
- THEN 路由定义 SHALL 从 `route/application.php` 加载
- AND 路由前缀 SHALL 为 `/app/*`

#### Scenario: Login 控制器路由独立
- GIVEN Login 控制器在 `app/controller/Login.php`
- WHEN 系统加载路由配置
- THEN 路由定义 SHALL 从 `route/login.php` 加载
- AND 路由前缀 SHALL 为 `/login/*`

#### Scenario: ResetPassword 控制器路由独立
- GIVEN ResetPassword 控制器在 `app/controller/ResetPassword.php`
- WHEN 系统加载路由配置
- THEN 路由定义 SHALL 从 `route/reset_password.php` 加载
- AND 路由前缀 SHALL 为 `/reset_password/*`

#### Scenario: Signup 控制器路由独立
- GIVEN Signup 控制器在 `app/controller/Signup.php`
- WHEN 系统加载路由配置
- THEN 路由定义 SHALL 从 `route/signup.php` 加载
- AND 路由前缀 SHALL 为 `/signup/*`

#### Scenario: SystemManage 控制器路由独立
- GIVEN SystemManage 控制器在 `app/controller/SystemManage.php`
- WHEN 系统加载路由配置
- THEN 路由定义 SHALL 从 `route/system_manage.php` 加载
- AND 路由前缀 SHALL 为 `/system_manage/*`

#### Scenario: User 控制器路由独立
- GIVEN User 控制器在 `app/controller/User.php`
- WHEN 系统加载路由配置
- THEN 路由定义 SHALL 从 `route/user.php` 加载
- AND 路由前缀 SHALL 为 `/user/*`

### Requirement: 路由文件编码规范
每个独立路由文件 SHALL 遵循统一的编码规范。

#### Scenario: 文件头部声明
- GIVEN 任何路由文件
- WHEN 文件被创建或修改
- THEN 文件 MUST 包含 `declare(strict_types=1);`
- AND 文件 MUST 包含 `use think\facade\Route;`

#### Scenario: 路由定义模式
- GIVEN 任何路由文件
- WHEN 定义路由组
- THEN 路由 SHALL 使用 `Route::group()->prefix()` 模式定义
- AND 路由组前缀 SHALL 与文件名对应（如 `user.php` → 前缀 `/user`）

### Requirement: 路由文件命名规范
路由文件 SHALL 使用控制器的蛇形命名形式。

#### Scenario: 命名转换规则
- GIVEN 控制器类名为 `SystemManage`
- WHEN 生成对应的路由文件
- THEN 文件名 SHALL 为 `system_manage.php`（蛇形命名）
- AND 文件 MUST 放置在 `route/` 目录下

### Requirement: route/app.php 路由定义
`route/app.php` SHALL 仅包含 Index 控制器的路由定义。

#### Scenario: Index 控制器路由保留
- GIVEN Index 控制器在 `app/controller/Index.php`
- WHEN 访问 `/`、`/index`、`/orders`、`/docs`、`/charts`、`/help`、`/download`、`/about`、`/404`
- THEN 路由 SHALL 由 `route/app.php` 处理

#### Scenario: 非 Index 控制器路由迁移
- GIVEN 非 Index 控制器的路由定义
- WHEN 系统加载路由配置
- THEN 路由 SHALL 从独立的路由文件加载（如 `route/user.php`）
- AND `route/app.php` SHALL NOT 包含这些路由定义

