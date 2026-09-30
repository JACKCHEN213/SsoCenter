# 设计方案：会话管理

## 一、数据库设计

### 1.1 sc_user_sessions 表（NEW）

追踪本地系统的活跃登录会话。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `id` | INT | AUTO_INCREMENT | 主键 |
| `user_id` | INT | NOT NULL | 用户 ID |
| `username` | VARCHAR(64) | NOT NULL | 用户名（冗余） |
| `token_jti` | VARCHAR(128) | NOT NULL | JWT 的 jti（唯一标识本次会话） |
| `ip` | VARCHAR(64) | NULL | 登录 IP |
| `user_agent` | VARCHAR(512) | NULL | 浏览器 UA |
| `login_time` | DATETIME | NOT NULL | 登录时间 |
| `last_active` | DATETIME | NOT NULL | 最后活跃时间（可选，定期更新） |
| `expires_at` | DATETIME | NOT NULL | 会话过期时间（与 JWT exp 对齐） |
| `is_active` | TINYINT(1) | 1 | 是否活跃（管理员强制退出时设为 0） |

**索引**：
- `UNIQUE KEY idx_token_jti (token_jti)`
- `KEY idx_user_id (user_id)`
- `KEY idx_is_active (is_active)`

**生命周期**：
- 登录时 INSERT
- 正常登出时 DELETE（或 `is_active=0`）
- JWT 过期后由定时任务清理
- 管理员强制退出时 `is_active=0`

### 1.2 sc_site 表扩展（MODIFIED）

新增 `logout_callback_url` 字段，存储第三方系统的登出回调地址。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `logout_callback_url` | VARCHAR(512) | NULL | 第三方系统登出回调 URL |

---

## 二、会话管理页面

### 2.1 页面入口

左侧导航栏"用户管理"下方新增"会话管理"菜单项。

### 2.2 会话列表页

```
┌──────────────────────────────────────────────────────────────────────────┐
│  会话管理                                                                │
├──────────────────────────────────────────────────────────────────────────┤
│  筛选：[用户名 ____] [状态 ▼]                                            │
│  [查询]  [重置]                                                          │
├──────────────────────────────────────────────────────────────────────────┤
│  # │ 用户名 │ 登录IP │ 登录时间 │ 本系统会话 │ 应用授权 │ 操作           │
│  1 │ admin │ ...   │ 08-22 10:00│ 1 个活跃  │ 3 个应用 │ [详情] [强制退出]│
│  2 │ zhangsan│ ... │ 08-22 09:30│ 已过期    │ 1 个应用 │ [详情]          │
├──────────────────────────────────────────────────────────────────────────┤
│  < 1 2 3 ... >                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

### 2.3 会话详情弹窗

点击"详情"打开弹窗，按用户维度展示完整的会话信息：

```
┌──────────────────────────────────────────────────────────────────────────┐
│  会话详情 — zhangsan                                                      │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ── 本系统登录 ──                                                         │
│  ┌────────────────────────────────────────────────────────────────┐      │
│  │ IP: 192.168.1.100 │ 登录时间: 2026-08-22 10:00 │ 状态: ● 活跃 │      │
│  │ UA: Mozilla/5.0 ...                                            │      │
│  │                                                   [退出此会话] │      │
│  ├────────────────────────────────────────────────────────────────┤      │
│  │ IP: 192.168.1.101 │ 登录时间: 2026-08-22 09:00 │ 状态: ○ 过期 │      │
│  └────────────────────────────────────────────────────────────────┘      │
│                                                                          │
│  ── 第三方应用授权 ──                                                     │
│  ┌────────────────────────────────────────────────────────────────┐      │
│  │ 应用名称    │ 授权码 │ Access Token │ Refresh Token │ 操作      │      │
│  ├────────────────────────────────────────────────────────────────┤      │
│  │ 应用A       │ 0     │ 2 个有效     │ 2 个有效      │ [退出此应用]│      │
│  │ 应用B       │ 1 个  │ 1 个有效     │ 1 个有效      │ [退出此应用]│      │
│  │ 应用C       │ 0     │ 0            │ 0             │ —          │      │
│  └────────────────────────────────────────────────────────────────┘      │
│                                                                          │
│  [ 全部退出（本系统 + 所有应用） ]                          [ 关闭 ]       │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

### 2.4 强制退出操作

| 操作 | 按钮位置 | 效果 |
|------|----------|------|
| 退出此会话 | 本系统登录行 | `is_active=0` 该 session 记录 |
| 退出此应用 | 应用授权行 | 撤销该用户在该应用的所有 access_token + refresh_token + 删除授权码 |
| 全部退出 | 弹窗底部 | 退出本系统所有会话 + 退出所有应用 |

**退出应用时**：
1. 撤销 `oauth_access_tokens`（`revoked=1`）
2. 撤销 `oauth_refresh_tokens`（`revoked=1`）
3. 删除 `oauth_authorization_codes`
4. 如果应用配置了 `logout_callback_url`，发送登出通知（异步）

---

## 三、后端接口

### 3.1 Session 控制器

| 路由 | 方法 | 说明 |
|------|------|------|
| `GET /session` | GET | 渲染会话管理页面 |
| `POST /session/list` | POST | 分页查询会话列表（按用户聚合） |
| `POST /session/detail` | POST | 获取某用户的完整会话详情 |
| `POST /session/logout` | POST | 强制退出（支持指定范围） |

### 3.2 session/list 接口

按用户维度聚合，返回每个用户的会话概况：

```json
{
  "code": 0, "success": true,
  "result": {
    "total": 50,
    "list": [
      {
        "user_id": 1,
        "username": "admin",
        "local_sessions": {
          "active_count": 1,
          "last_login_ip": "192.168.1.100",
          "last_login_time": "2026-08-22 10:00:00"
        },
        "app_authorizations": [
          {
            "site_id": 1,
            "site_name": "应用A",
            "auth_codes": 0,
            "access_tokens": 2,
            "refresh_tokens": 2
          }
        ]
      }
    ]
  }
}
```

### 3.3 session/detail 接口

返回某用户的完整会话详情：

```json
{
  "code": 0, "success": true,
  "result": {
    "user_id": 1,
    "username": "admin",
    "local_sessions": [
      {
        "id": 1,
        "ip": "192.168.1.100",
        "user_agent": "Mozilla/5.0 ...",
        "login_time": "2026-08-22 10:00:00",
        "expires_at": "2026-08-22 12:00:00",
        "is_active": true
      }
    ],
    "app_authorizations": [
      {
        "site_id": 1,
        "site_name": "应用A",
        "client_id": "xxx",
        "auth_codes": [
          { "code": "***", "scope": "trust", "expires_at": "...", "created_at": "..." }
        ],
        "access_tokens": [
          { "token_prefix": "eyJ...", "grant_type": "authorization_code", "scope": "trust", "expires_at": "...", "revoked": false, "created_at": "..." }
        ],
        "refresh_tokens": [
          { "token_prefix": "abc...", "scope": "trust", "expires_at": "...", "revoked": false, "created_at": "..." }
        ]
      }
    ]
  }
}
```

> Token 值仅显示前 10 位（`token_prefix`），不暴露完整 Token。

### 3.4 session/logout 接口

```json
// 请求
{
  "user_id": 1,
  "scope": "app",       // "local" | "app" | "all"
  "site_id": 1          // scope=app 时必填
}

// 响应
{
  "code": 0, "success": true,
  "result": {
    "local_sessions_revoked": 1,
    "access_tokens_revoked": 2,
    "refresh_tokens_revoked": 2,
    "auth_codes_deleted": 0,
    "apps_notified": ["应用A"]
  }
}
```

### 3.5 第三方回调登出接口

`POST /oauth/logout_callback`

第三方系统在用户退出时调用此接口，通知本系统撤销该用户在该应用的所有 Token。

```json
// 请求（HTTP Basic Auth 认证）
POST /oauth/logout_callback
Authorization: Basic base64(client_id:client_secret)
Content-Type: application/json

{
  "user_id": 1,
  "username": "zhangsan",
  "external_id": "ext_001"   // 可选，用于按 externalId 匹配
}

// 响应
{
  "code": 0, "success": true,
  "result": {
    "access_tokens_revoked": 2,
    "refresh_tokens_revoked": 2
  }
}
```

**认证方式**：HTTP Basic Auth（与 `/oauth/token` 相同），验证 `client_id` + `client_secret`。

**处理逻辑**：
1. 验证客户端身份（Basic Auth）
2. 根据 `user_id` 或 `external_id` 匹配用户
3. 撤销该用户在该应用（`client_id`）的所有 access_token 和 refresh_token
4. 删除该用户在该应用的授权码
5. 返回撤销结果

---

## 四、Login 控制器改造

### 4.1 login() — 创建 session

```php
// 登录成功后
$jwtPayload = [...];
$jti = $jwtPayload['jti'] ?? UUID::v4();
Db::name('user_sessions')->insert([
    'user_id' => $user['id'],
    'username' => $username,
    'token_jti' => $jti,
    'ip' => $ip,
    'user_agent' => request()->header('user-agent'),
    'login_time' => $login_time,
    'last_active' => $login_time,
    'expires_at' => date('Y-m-d H:i:s', time() + 7200), // 与 JWT exp 对齐
    'is_active' => 1,
]);
```

### 4.2 logout() — 删除 session

```php
// 登出时
$jti = $payload->jti ?? null;
if ($jti) {
    Db::name('user_sessions')->where('token_jti', $jti)->update(['is_active' => 0]);
}
```

### 4.3 CheckLogin 中间件 — 校验 session 活跃状态

在 JWT 验签通过后，额外检查 `sc_user_sessions` 中该 `jti` 是否 `is_active=1`。如果管理员已强制退出，即使 JWT 未过期也应拒绝。

---

## 五、跨系统登出通知

### 5.1 notifyRelatedAppsLogout() 改造

从 TODO 状态改为实际发送：

```php
private function notifyRelatedAppsLogout(int $userId, ?array $siteIds = null): void
{
    $query = Db::name('oauth_access_tokens')
        ->where('user_id', $userId)
        ->where('revoked', 0);
    if ($siteIds) {
        $clientIds = Db::name('site')->whereIn('id', $siteIds)->column('client_id');
        $query->whereIn('client_id', $clientIds);
    }
    $tokens = $query->select();

    $notifiedClients = [];
    foreach ($tokens as $token) {
        $clientId = $token['client_id'];
        if (in_array($clientId, $notifiedClients)) continue;
        $notifiedClients[] = $clientId;

        $site = Db::name('site')->where('client_id', $clientId)->find();
        if ($site && !empty($site['logout_callback_url'])) {
            // 使用 Guzzle 异步发送登出通知
            try {
                $client = new \GuzzleHttp\Client();
                $client->postAsync($site['logout_callback_url'], [
                    'json' => ['user_id' => $userId, 'action' => 'logout'],
                    'timeout' => 5,
                ]);
            } catch (Exception $e) {
                recordLog("登出通知失败: site={$site['name']}, error={$e->getMessage()}", 'error');
            }
        }
    }
}
```

---

## 六、向后兼容

1. **现有 `sc_login_history`**：保留不动，`sc_user_sessions` 是补充而非替代
2. **现有 OAuth 流程**：不改变 Token 颁发/刷新/撤销逻辑
3. **CheckLogin 中间件**：新增 session 活跃校验，如果 session 表无记录（旧 session）则放行（向后兼容）
4. **第三方回调**：新增接口，不影响现有 OAuth 端点
