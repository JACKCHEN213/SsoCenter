# 设计方案：操作日志系统

## 一、数据库设计

### sc_operation_log 表（NEW）

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `id` | INT | AUTO_INCREMENT | 主键 |
| `operator_id` | INT | NULL | 操作者 user_id（登录失败时可能为 NULL） |
| `operator_name` | VARCHAR(64) | NULL | 操作者用户名（冗余，方便展示） |
| `module` | VARCHAR(32) | NOT NULL | 操作模块：`auth` / `app` / `user` |
| `action` | VARCHAR(64) | NOT NULL | 操作动作：`login` / `logout` / `app_create` / `app_update` / `app_delete` / `app_reset_secret` / `app_view_secret` / `user_create` / `user_update` / `user_delete` / `user_push` / `user_push_all` |
| `target_type` | VARCHAR(32) | NULL | 操作对象类型：`app` / `user` / `system` |
| `target_id` | INT | NULL | 操作对象 ID |
| `target_name` | VARCHAR(255) | NULL | 操作对象名称（应用名/用户名，方便展示） |
| `result` | VARCHAR(16) | NOT NULL | 操作结果：`success` / `failure` |
| `result_message` | VARCHAR(512) | NULL | 结果描述（如"登录成功"、"密码错误"、"推送响应 code=500"） |
| `request_data` | JSON | NULL | 请求数据（JSON 对象） |
| `response_data` | JSON | NULL | 响应数据（JSON 对象） |
| `ip` | VARCHAR(64) | NULL | 操作者 IP 地址 |
| `user_agent` | VARCHAR(512) | NULL | 浏览器 User-Agent |
| `related_type` | VARCHAR(32) | NULL | 关联记录类型（如 `push_log`） |
| `related_id` | INT | NULL | 关联记录 ID（如 push_log.id） |
| `created_at` | DATETIME | CURRENT_TIMESTAMP | 操作时间 |

**索引**：
- `KEY idx_operator (operator_id)`
- `KEY idx_module_action (module, action)`
- `KEY idx_target (target_type, target_id)`
- `KEY idx_created_at (created_at)`

### request_data 结构

根据操作类型，`request_data` 包含不同内容：

#### 登录/登出

```json
{
  "username": "zhangsan",
  "password": "***"
}
```

> 密码脱敏为 `***`，不记录明文。

#### 应用操作

```json
{
  "app_name": "应用A",
  "request_url": "https://app.example.com",
  "redirect_url": "https://app.example.com/callback",
  "allowed_grant_types": "authorization_code,password"
}
```

#### 用户推送

```json
{
  "url": "https://app.example.com/api/scim/v2/accounts",
  "method": "POST",
  "headers": {
    "Content-Type": "application/json;charset=UTF-8",
    "X-trust-appid": "889381",
    "X-trust-signature": "****",
    "X-api-version": "1.0-rev0"
  },
  "body": {
    "code": "zhangsan",
    "name": "zhangsan",
    "email": "zhangsan@example.com",
    "status": true
  }
}
```

> `X-trust-signature` 脱敏为 `****`，不记录签名值。

### response_data 结构

#### 应用操作

```json
{
  "code": 0,
  "message": "成功"
}
```

#### 用户推送

```json
{
  "status_code": 200,
  "headers": {
    "Content-Type": "application/json",
    "X-Request-Id": "abc123"
  },
  "body": {
    "success": true,
    "code": 200,
    "message": "创建账号成功",
    "externalId": "zhangsan",
    "data": { "accountId": "12dcab39-..." }
  }
}
```

---

## 二、AuditLogService

**文件**：`app/service/AuditLogService.php`

### 核心方法

```php
class AuditLogService
{
    /**
     * 记录操作日志
     */
    public static function log(
        string $module,           // auth / app / user
        string $action,           // login / app_create / user_push ...
        string $result,           // success / failure
        ?string $resultMessage,   // 结果描述
        ?array $requestData,      // 请求数据
        ?array $responseData,     // 响应数据
        ?string $targetType,      // 操作对象类型
        ?int $targetId,           // 操作对象 ID
        ?string $targetName,      // 操作对象名称
        ?string $relatedType,     // 关联类型
        ?int $relatedId           // 关联 ID
    ): int {
        // 自动获取 operator_id, operator_name, ip, user_agent
        // 插入 sc_operation_log
        // 返回 log id
    }

    /**
     * 快捷方法：记录登录日志
     */
    public static function login(string $username, string $result, ?string $message = null, ?int $userId = null): int;

    /**
     * 快捷方法：记录登出日志
     */
    public static function logout(string $username, int $userId): int;

    /**
     * 快捷方法：记录应用操作日志
     */
    public static function app(string $action, string $result, ?string $message, ?array $requestData, ?array $responseData, ?int $appId, ?string $appName): int;

    /**
     * 快捷方法：记录用户操作日志
     */
    public static function user(string $action, string $result, ?string $message, ?array $requestData, ?array $responseData, ?int $userId, ?string $userName, ?string $relatedType, ?int $relatedId): int;

    /**
     * 获取当前操作者信息（从 JWT token 中解析）
     */
    private static function getOperator(): array;  // ['id' => ..., 'name' => ...]
}
```

### 操作者信息获取

从当前请求的 JWT Token 中解析 `operator_id` 和 `operator_name`。如果 Token 不存在（如登录失败场景），`operator_id` 为 NULL，但 `operator_name` 从请求参数中获取（如登录时的 username）。

### 敏感数据脱敏

| 字段 | 脱敏规则 |
|------|----------|
| 登录密码 | 替换为 `***` |
| APP_KEY（client_secret） | 不记录；重置操作仅记录 `id` 和 `name` |
| X-trust-signature | 替换为 `****` |
| appkey（推送配置） | 不记录 |

---

## 三、集成埋点

### 3.1 Login 控制器

| 方法 | 日志动作 | 记录内容 |
|------|----------|----------|
| `login()` 成功 | `auth.login` | 请求：username(脱敏密码)；结果：success |
| `login()` 失败 | `auth.login` | 请求：username(脱敏密码)；结果：failure + 失败原因 |
| `logout()` | `auth.logout` | 结果：success |

### 3.2 Application 控制器

| 方法 | 日志动作 | 记录内容 |
|------|----------|----------|
| `add()` 成功 | `app.app_create` | 请求：app_name, request_url, redirect_url, grant_types；响应：client_id |
| `update()` 成功 | `app.app_update` | 请求：修改的字段 |
| `delete()` 成功 | `app.app_delete` | 目标：应用名 |
| `resetSecret()` 成功 | `app.app_reset_secret` | 目标：应用名（不记录 secret） |
| `getSecret()` | `app.app_view_secret` | 目标：应用名 |

### 3.3 User 控制器

| 方法 | 日志动作 | 记录内容 |
|------|----------|----------|
| `add()` 成功 | `user.user_create` | 请求：username, email |
| `update()` 成功 | `user.user_update` | 请求：修改的字段 |
| `delete()` 成功 | `user.user_delete` | 目标：用户名 |
| `pushUser()` | `user.user_push` | 请求：完整 HTTP 请求（URL/headers/body）；响应：完整 HTTP 响应（status/headers/body）；关联 `push_log.id` |
| `pushUserToAll()` | `user.user_push_all` | 请求：推送了哪些应用；响应：结果汇总 |

---

## 四、操作日志页面

### 4.1 导航入口

在左侧导航栏"用户管理"下方新增"操作日志"菜单项（替代原移除的占位项）。

### 4.2 日志列表页

```
┌──────────────────────────────────────────────────────────────────┐
│  操作日志                                                         │
├──────────────────────────────────────────────────────────────────┤
│  筛选：                                                           │
│  [操作模块 ▼]  [操作动作 ▼]  [操作者 ____]  [时间范围 ___ ~ ___]    │
│  [查询]  [重置]                                                    │
├──────────────────────────────────────────────────────────────────┤
│  # │ 时间 │ 操作者 │ 模块 │ 动作 │ 操作对象 │ 结果 │ IP │ 操作     │
│  1 │ 08-22│ admin │ 认证 │ 登录 │ —       │ ✓成功│ ...│ [详情]   │
│  2 │ 08-22│ admin │ 应用 │ 创建 │ 应用A   │ ✓成功│ ...│ [详情]   │
│  3 │ 08-22│ admin │ 用户 │ 推送 │ zhangsan│ ✗失败│ ...│ [详情]   │
├──────────────────────────────────────────────────────────────────┤
│  < 1 2 3 ... >                                                   │
└──────────────────────────────────────────────────────────────────┘
```

### 4.3 日志详情弹窗

点击"详情"按钮打开弹窗，展示完整日志信息：

```
┌──────────────────────────────────────────────────────────────┐
│  日志详情                                                     │
├──────────────────────────────────────────────────────────────┤
│  操作者: admin                                                │
│  时间: 2026-08-22 10:30:45                                    │
│  IP: 192.168.1.100                                            │
│  User-Agent: Mozilla/5.0 ...                                  │
│  模块: 用户                                                   │
│  动作: 推送用户                                                │
│  操作对象: zhangsan (user_id: 5)                               │
│  结果: ✗ 失败                                                 │
│  结果描述: 响应验证失败: code: 期望 200(int), 实际 500(int)      │
│                                                              │
│  ── 请求数据 ──                                               │
│  URL: POST https://app.example.com/api/scim/v2/accounts       │
│  Headers:                                                     │
│    Content-Type: application/json;charset=UTF-8               │
│    X-trust-appid: 889381                                      │
│    X-trust-signature: ****                                    │
│  Body:                                                        │
│    {"code":"zhangsan","name":"zhangsan","status":true}        │
│                                                              │
│  ── 响应数据 ──                                               │
│  Status: 200                                                  │
│  Headers:                                                     │
│    Content-Type: application/json                             │
│  Body:                                                        │
│    {"success":true,"code":500,"message":"..."}                │
│                                                              │
│  关联: push_log #123                                          │
│                                                              │
│                                                [ 关闭 ]       │
└──────────────────────────────────────────────────────────────┘
```

---

## 五、后端接口

| 路由 | 方法 | 说明 |
|------|------|------|
| `GET /operation_log` | GET | 渲染操作日志页面 |
| `POST /operation_log/list` | POST | 分页查询日志列表（支持筛选） |
| `POST /operation_log/detail` | POST | 获取单条日志详情 |

### list 接口参数

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `module` | String | 否 | 筛选模块：auth / app / user |
| `action` | String | 否 | 筛选动作 |
| `operator_name` | String | 否 | 操作者用户名（模糊匹配） |
| `start_time` | String | 否 | 起始时间 |
| `end_time` | String | 否 | 结束时间 |
| `page` | Int | 否 | 页码，默认 1 |
| `page_size` | Int | 否 | 每页条数，默认 20 |

### list 接口响应

```json
{
  "code": 0,
  "success": true,
  "result": {
    "total": 150,
    "page": 1,
    "page_size": 20,
    "list": [
      {
        "id": 1,
        "operator_name": "admin",
        "module": "auth",
        "action": "login",
        "target_name": null,
        "result": "success",
        "result_message": "登录成功",
        "ip": "192.168.1.100",
        "created_at": "2026-08-22 10:30:45"
      }
    ]
  }
}
```

---

## 六、与 sc_user_push_log 的关系

| 维度 | sc_operation_log | sc_user_push_log |
|------|------------------|------------------|
| 定位 | 全局操作审计日志 | 推送专用状态追踪 |
| 粒度 | 每次操作一条 | 每次推送一条 |
| 内容 | 操作者 + 动作 + 请求/响应摘要 | 推送结果 + externalId |
| 用途 | 审计、安全追溯 | 推送状态面板展示 |

推送操作同时写入两张表：
- `sc_user_push_log`：供推送状态面板快速查询（is_success、external_id）
- `sc_operation_log`：供操作日志页面统一展示，通过 `related_type=push_log` + `related_id` 关联

---

## 七、向后兼容

1. **现有 `sc_login_history`**：保留不动，新增操作日志不替代它
2. **现有 `recordLog()`**：保留用于文件日志，操作日志写入数据库，两者互补
3. **现有 `sc_user_push_log`**：保留不动
4. **左侧导航栏**：新增"操作日志"菜单项（在 `remove-unimplemented-ui` 变更移除旧项后）
