# 规范：会话管理

## Requirements

### REQ-SESSION-001: 本地会话追踪

**描述**：用户登录本系统时，系统必须在 `sc_user_sessions` 表中创建会话记录；用户登出或会话被管理员强制退出时，更新该记录状态。

**字段规范**：

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `user_id` | INT | 是 | 用户 ID |
| `username` | VARCHAR(64) | 是 | 用户名 |
| `token_jti` | VARCHAR(128) | 是 | JWT 的 jti，唯一标识本次会话 |
| `ip` | VARCHAR(64) | 否 | 登录 IP |
| `user_agent` | VARCHAR(512) | 否 | 浏览器 UA |
| `login_time` | DATETIME | 是 | 登录时间 |
| `last_active` | DATETIME | 是 | 最后活跃时间 |
| `expires_at` | DATETIME | 是 | 会话过期时间（与 JWT exp 对齐） |
| `is_active` | TINYINT(1) | 是 | 是否活跃，默认 1 |

**场景：用户登录创建会话**

```
GIVEN 用户 "admin" 从 IP "192.168.1.100" 登录本系统
AND JWT payload 中 jti = "abc-123"
WHEN 登录成功
THEN sc_user_sessions 表插入一条记录
AND user_id = 用户 admin 的 ID
AND token_jti = "abc-123"
AND ip = "192.168.1.100"
AND is_active = 1
```

**场景：用户登出更新会话**

```
GIVEN 用户 "admin" 的当前 JWT 的 jti = "abc-123"
WHEN 用户主动登出
THEN sc_user_sessions 中 token_jti="abc-123" 的记录 is_active 设为 0
```

---

### REQ-SESSION-002: 会话活跃状态校验

**描述**：CheckLogin 中间件在 JWT 验签通过后，须额外校验 `sc_user_sessions` 中该 `jti` 的 `is_active` 状态。若管理员已强制退出该会话，即使 JWT 未过期也应拒绝访问。

**场景：会话被强制退出后请求被拒绝**

```
GIVEN 用户 "admin" 的会话 jti="abc-123" 已被管理员强制退出（is_active=0）
WHEN 用户携带该 JWT 发起受保护的 API 请求
THEN 响应 code = 401
AND 响应 message = "会话已被终止"
```

**场景：旧会话无记录时放行（向后兼容）**

```
GIVEN 用户 "admin" 的 JWT 合法但 sc_user_sessions 中无对应 jti 记录
WHEN 用户携带该 JWT 发起受保护的 API 请求
THEN 请求正常通过（向后兼容）
```

---

### REQ-SESSION-003: 会话列表查询

**描述**：管理员可通过会话管理页面分页查询所有用户的会话概况，以用户维度聚合展示本系统登录状态和各应用授权统计。

**接口**：`POST /session/list`

**场景：查询会话列表**

```
GIVEN 系统中存在 50 个有会话记录的用户
WHEN 管理员请求 POST /session/list?page=1&limit=10
THEN 返回 code=0, success=true
AND result.total = 50
AND result.list 包含 10 条用户记录
AND 每条记录包含 user_id, username, local_sessions（active_count, last_login_ip, last_login_time）, app_authorizations（数组）
```

---

### REQ-SESSION-004: 会话详情查询

**描述**：管理员可查看某用户的完整会话详情，包括本系统所有登录会话（含 IP、登录时间、UA、活跃状态）和各第三方应用的授权码、Access Token、Refresh Token 列表。

**接口**：`POST /session/detail`

**场景：查看用户会话详情**

```
GIVEN 用户 "zhangsan" 在本系统有 2 个登录会话，在应用 A 有 1 个有效 Access Token
WHEN 管理员请求 POST /session/detail?user_id=2
THEN 返回 code=0, success=true
AND result.local_sessions 包含 2 条会话记录（含 id, ip, user_agent, login_time, expires_at, is_active）
AND result.app_authorizations 包含 1 个应用（site_id, site_name, client_id, access_tokens 数组）
AND Token 值仅显示前 10 位（token_prefix）
```

---

### REQ-SESSION-005: 强制退出本系统会话

**描述**：管理员可强制退出某用户在本系统的指定会话。

**接口**：`POST /session/logout`，参数 `scope=local`

**场景：强制退出单个会话**

```
GIVEN 用户 "admin" 有会话 jti="abc-123"（is_active=1）
WHEN 管理员请求 POST /session/logout {user_id: 1, scope: "local", session_id: 5}
THEN sc_user_sessions 中 id=5 的记录 is_active 设为 0
AND 返回 local_sessions_revoked = 1
AND 操作日志记录本次操作
```

---

### REQ-SESSION-006: 强制退出指定应用的授权

**描述**：管理员可强制退出某用户在指定第三方应用的所有授权（撤销 access_token、refresh_token，删除授权码）。

**接口**：`POST /session/logout`，参数 `scope=app`

**场景：退出指定应用**

```
GIVEN 用户 "zhangsan" 在应用 A（site_id=1）有 2 个有效 access_token、2 个有效 refresh_token、1 个未过期授权码
WHEN 管理员请求 POST /session/logout {user_id: 2, scope: "app", site_id: 1}
THEN oauth_access_tokens 中该用户在该应用的记录 revoked 设为 1
AND oauth_refresh_tokens 中该用户在该应用的记录 revoked 设为 1
AND oauth_authorization_codes 中该用户在该应用的记录被删除
AND 返回 access_tokens_revoked=2, refresh_tokens_revoked=2, auth_codes_deleted=1
AND 操作日志记录本次操作
```

---

### REQ-SESSION-007: 强制全部退出

**描述**：管理员可强制退出某用户的所有会话和所有应用授权。

**接口**：`POST /session/logout`，参数 `scope=all`

**场景：全部退出**

```
GIVEN 用户 "zhangsan" 在本系统有 2 个活跃会话，在应用 A 和应用 B 有授权
WHEN 管理员请求 POST /session/logout {user_id: 2, scope: "all"}
THEN 本系统所有活跃会话 is_active 设为 0
AND 所有应用的有效 Token 被撤销
AND 所有应用的授权码被删除
AND 如果应用配置了 logout_callback_url，发送登出通知
AND 返回完整撤销统计
```

---

### REQ-SESSION-008: 第三方系统回调登出

**描述**：提供 API 供第三方系统在用户退出时调用，本系统收到后自动撤销该用户在该应用的所有 Token。

**接口**：`POST /oauth/logout_callback`

**认证方式**：HTTP Basic Auth（`client_id:client_secret`）

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `user_id` | INT | 否 | 用户 ID |
| `username` | STRING | 否 | 用户名 |
| `external_id` | STRING | 否 | 外部 ID |

> `user_id`/`username`/`external_id` 至少提供一个。

**场景：第三方系统回调登出成功**

```
GIVEN 应用 A（client_id="app1"）通过 Basic Auth 认证
AND 用户 "zhangsan" 在应用 A 有 2 个有效 access_token
WHEN 应用 A 调用 POST /oauth/logout_callback {user_id: 2}
THEN 验证 client_id 和 client_secret 通过
THEN oauth_access_tokens 中 user_id=2 且 client_id="app1" 的记录 revoked 设为 1
AND oauth_refresh_tokens 中对应记录 revoked 设为 1
AND oauth_authorization_codes 中对应记录被删除
AND 返回 access_tokens_revoked=2
```

**场景：认证失败**

```
GIVEN 请求的 Basic Auth 中的 client_id 不存在或 client_secret 不匹配
WHEN 调用 POST /oauth/logout_callback
THEN 响应 code = 401
AND 响应 message = "客户端认证失败"
```

**场景：用户不存在**

```
GIVEN 请求认证通过，但 user_id 不存在
WHEN 调用 POST /oauth/logout_callback {user_id: 99999}
THEN 响应 code = 404
AND 响应 message = "用户不存在"
```

---

### REQ-SESSION-009: 登出回调地址管理

**描述**：应用管理页面新增"登出回调地址"配置项，用于存储第三方系统的登出回调 URL。管理员可在创建/编辑应用时配置此地址。

**字段**：`sc_site.logout_callback_url` VARCHAR(512) NULL

**场景：配置登出回调地址**

```
GIVEN 管理员创建/编辑应用
WHEN 填写"登出回调地址"为 "https://app.example.com/api/logout_callback"
THEN sc_site.logout_callback_url 保存该 URL
AND 该 URL 须通过 URL 格式校验
```

---

### REQ-SESSION-010: 跨系统登出通知

**描述**：当管理员在本系统强制退出某用户的应用授权时，如果该应用配置了 `logout_callback_url`，系统须向该 URL 发送登出通知。

**场景：发送登出通知**

```
GIVEN 管理员强制退出用户 "zhangsan" 在应用 A 的授权
AND 应用 A 配置了 logout_callback_url = "https://app-a.com/logout"
WHEN 退出操作完成
THEN 系统向 "https://app-a.com/logout" 发送 POST 请求
AND 请求体包含 {user_id: 2, username: "zhangsan", action: "logout"}
AND 使用 Guzzle 发送，超时 5 秒
AND 发送失败时记录错误日志，不影响退出操作本身
```

---

### REQ-SESSION-011: 会话管理页面导航

**描述**：左侧导航栏"用户管理"下方新增"会话管理"菜单项，点击进入会话管理页面。

**场景：导航入口**

```
GIVEN 管理员已登录本系统
WHEN 管理员查看左侧导航栏
THEN 在"用户管理"下方可见"会话管理"菜单项
AND 点击后跳转至 /session 页面
```
