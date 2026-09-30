# 提案：操作日志系统

## 变更 ID

`add-operation-log`

## 背景与动机

当前系统缺少统一的操作日志记录机制：
- `sc_login_history` 表仅记录登录（username + ip + time），无登出、无操作详情
- `recordLog()` 函数写入 ThinkPHP 文件日志，无法在页面查看和检索
- 推送日志 `sc_user_push_log` 仅覆盖推送场景，其他操作无日志

需要一个统一的操作日志系统，记录所有关键业务操作的完整上下文，便于审计和问题排查。

### 需要记录的操作

| 模块 | 操作 | 说明 |
|------|------|------|
| **认证** | 登录 | 记录登录成功/失败、IP、浏览器 |
| **认证** | 登出 | 记录登出时间 |
| **应用** | 创建应用 | 记录应用名称、基本信息 |
| **应用** | 修改应用 | 记录修改了哪些字段 |
| **应用** | 删除应用 | 记录被删应用信息 |
| **应用** | 重置 APP_KEY | 记录（不记录明文 secret） |
| **应用** | 查看 APP_KEY | 记录查看行为 |
| **用户** | 新增用户 | 记录用户名等基本信息 |
| **用户** | 修改用户 | 记录修改了哪些字段 |
| **用户** | 删除用户 | 记录被删用户信息 |
| **用户** | 推送用户 | 记录推送的请求/响应完整详情 |
| **用户** | 批量推送 | 记录推送了哪些应用及结果汇总 |

### 不记录的操作

- 纯前端 UI 操作（复制 APP_ID、展开/折叠面板等）
- 只读查询（查看列表、查看详情、查看推送状态等）

### 每条日志需要记录的信息

| 信息 | 说明 |
|------|------|
| **谁** | 操作者（user_id + username） |
| **什么时间** | 操作时间（精确到秒） |
| **操作类型** | 分类：`auth` / `app` / `user` |
| **操作动作** | 具体动作：`login` / `logout` / `app_create` / `user_push` 等 |
| **操作对象** | 被操作的实体（类型 + ID + 名称） |
| **操作详情** | 请求数据（URL、方法、请求头、请求体） |
| **操作结果** | 成功/失败 + 响应数据（状态码、响应头、响应体） |
| **环境信息** | IP 地址、User-Agent |

## 变更目标

### 1. 数据库设计

新增 `sc_operation_log` 表，存储结构化操作日志。

### 2. 日志写入服务

新增 `AuditLogService.php`，提供统一的日志写入接口。各 Controller 在操作完成后调用。

### 3. 日志查询页面

新增操作日志页面（替代原 `orders.html`），支持：
- 按操作类型筛选
- 按操作者筛选
- 按时间范围筛选
- 查看日志详情（请求/响应完整数据）

### 4. 集成埋点

在以下 Controller 方法中集成日志写入：
- `Login::login()` / `Login::logout()`
- `Application::add()` / `update()` / `delete()` / `resetSecret()` / `getSecret()`
- `User::add()` / `update()` / `delete()` / `pushUser()` / `pushUserToAll()`

## 涉及范围

| 范围 | 说明 |
|------|------|
| **新增表** | `sc_operation_log` |
| **新增服务** | `app/service/AuditLogService.php` |
| **新增页面** | `app/view/operation_log.html`（操作日志列表 + 详情弹窗） |
| **新增路由** | `GET /operation_log` / `POST /operation_log/list` / `POST /operation_log/detail` |
| **新增控制器** | `app/controller/OperationLog.php` |
| **改造** | `Login.php`、`Application.php`、`User.php` — 集成日志写入 |
| **改造** | `template/index.html` — 左侧导航新增"操作日志"菜单项 |

## 影响分析

- **性能**：每次操作多一次 INSERT，对性能影响可忽略（单行写入）
- **存储**：推送日志的请求/响应体可能较大，建议 TEXT 类型 + 定期清理
- **安全**：日志中不记录明文密码、APP_KEY 等敏感信息
