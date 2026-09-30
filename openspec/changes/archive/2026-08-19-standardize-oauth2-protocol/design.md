# 设计方案：标准化 OAuth 2.0 协议并支持四种授权模式

## 一、架构总览

```
                          ┌─────────────────────┐
                          │    第三方应用系统     │
                          └──────────┬──────────┘
                                     │
              ┌──────────────────────┼──────────────────────┐
              │                      │                      │
    OAuth 2.0 端点            SCIM 用户同步           SSO 回调/登出
  /oauth/*     /api/scim/v2/accounts    /oauth/*
              │                      │                      │
              └──────────────────────┼──────────────────────┘
                                     │
              ┌──────────────────────┼──────────────────────┐
              │                      │                      │
        OAuthController       ScimController        OAuthCallbackController
              │                      │                      │
        ┌─────┴─────┐         ┌─────┴─────┐          ┌─────┴─────┐
        │           │         │           │          │           │
   TokenService  IDTokenService  SignatureMiddleware  SessionService
        │                       │
   ┌────┴────┐            ┌────┴────┐
   │         │            │         │
  DB表:     DB表:        DB表:     DB表:
  oauth_*   sc_site      sc_user   sc_login_history
```

---

## 二、数据库设计

### 2.1 sc_site 表扩展（ALTER）

在现有 `sc_site` 表基础上新增以下字段：

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `client_id` | VARCHAR(64) | NULL | OAuth 2.0 客户端标识（AppID），唯一，注册时自动生成 |
| `client_secret` | VARCHAR(128) | NULL | OAuth 2.0 客户端密钥（AppSecret），注册时自动生成 |
| `allowed_grant_types` | VARCHAR(255) | `'authorization_code'` | 允许的授权模式，逗号分隔（authorization_code, implicit, password, client_credentials） |
| `scope` | VARCHAR(255) | `'trust'` | 默认授权范围 |
| `access_token_ttl` | INT | 28800 | Access Token 有效期（秒），默认 8 小时 |
| `refresh_token_ttl` | INT | 86400 | Refresh Token 有效期（秒），默认 24 小时 |
| `code_ttl` | INT | 600 | 授权码有效期（秒），默认 10 分钟 |

**索引**：`UNIQUE KEY idx_client_id (client_id)`

### 2.2 oauth_authorization_codes 表（NEW）

存储授权码，用于授权码模式。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `id` | INT | AUTO_INCREMENT | 主键 |
| `code` | VARCHAR(128) | NOT NULL | 授权码，唯一 |
| `client_id` | VARCHAR(64) | NOT NULL | 关联应用 |
| `user_id` | INT | NULL | 授权用户（client_credentials 模式为 NULL） |
| `redirect_uri` | VARCHAR(512) | NULL | 回调地址 |
| `scope` | VARCHAR(255) | NULL | 授权范围 |
| `state` | VARCHAR(128) | NULL | 客户端 state 参数 |
| `expires_at` | DATETIME | NOT NULL | 过期时间 |
| `created_at` | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

**索引**：`UNIQUE KEY idx_code (code)`，`KEY idx_client_id (client_id)`

### 2.3 oauth_access_tokens 表（NEW）

存储已颁发的 Access Token（含已撤销的，用于验证）。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `id` | INT | AUTO_INCREMENT | 主键 |
| `access_token` | VARCHAR(255) | NOT NULL | Access Token 值（JWT 字符串的 SHA256 摘要，用于索引） |
| `client_id` | VARCHAR(64) | NOT NULL | 关联应用 |
| `user_id` | INT | NULL | 关联用户（client_credentials 模式为 NULL） |
| `grant_type` | VARCHAR(32) | NOT NULL | 授权模式 |
| `scope` | VARCHAR(255) | NULL | 授权范围 |
| `expires_at` | DATETIME | NOT NULL | 过期时间 |
| `revoked` | TINYINT(1) | 0 | 是否已撤销 |
| `created_at` | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

**索引**：`UNIQUE KEY idx_access_token (access_token)`，`KEY idx_user_id (user_id)`，`KEY idx_client_id (client_id)`

### 2.4 oauth_refresh_tokens 表（NEW）

存储 Refresh Token。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `id` | INT | AUTO_INCREMENT | 主键 |
| `refresh_token` | VARCHAR(128) | NOT NULL | Refresh Token 值，唯一 |
| `client_id` | VARCHAR(64) | NOT NULL | 关联应用 |
| `user_id` | INT | NOT NULL | 关联用户 |
| `scope` | VARCHAR(255) | NULL | 授权范围 |
| `expires_at` | DATETIME | NOT NULL | 过期时间 |
| `revoked` | TINYINT(1) | 0 | 是否已撤销 |
| `access_token_id` | VARCHAR(255) | NULL | 关联的 access_token 标识 |
| `created_at` | DATETIME | CURRENT_TIMESTAMP | 创建时间 |

**索引**：`UNIQUE KEY idx_refresh_token (refresh_token)`，`KEY idx_user_id (user_id)`

---

## 三、OAuth 2.0 四种授权模式设计

### 3.1 授权码模式（Authorization Code）

**流程**：`用户 → authorize?response_type=code → 登录/授权 → 回调带 code → 应用用 code 换 token`

| 步骤 | 端点 | 说明 |
|------|------|------|
| 1 | `GET /oauth/authorize` | 校验 client_id/redirect_uri/scope/state，未登录重定向登录页，已登录生成授权码并 302 回调 |
| 2 | `POST /oauth/token` | 应用携带 code + Basic Auth(client_id:client_secret) 换取 access_token + refresh_token |

**授权码规则**：
- 一次性使用，10 分钟过期
- 随机生成 64 位字符串
- 绑定 client_id、user_id、redirect_uri、scope

### 3.2 隐式模式（Implicit）

**流程**：`用户 → authorize?response_type=token → 登录/授权 → 回调 URL 片段中带 token`

| 步骤 | 端点 | 说明 |
|------|------|------|
| 1 | `GET /oauth/authorize` | `response_type=token`，授权后 302 回调，token 在 URL fragment 中 |

**隐式模式规则**：
- 直接返回 access_token（不返回 refresh_token）
- Token 通过 URL fragment（`#access_token=xxx&token_type=Bearer&expires_in=xxx`）传递
- 安全性低于授权码模式，仅用于纯客户端应用

### 3.3 密码模式（Resource Owner Password Credentials）

**流程**：`应用直接携带 username + password 换 token`

| 步骤 | 端点 | 说明 |
|------|------|------|
| 1 | `POST /oauth/token` | `grant_type=password`，Body 携带 username + password，Header 携带 Basic Auth |

**密码模式规则**：
- 必须在应用的 `allowed_grant_types` 中启用
- 验证用户密码（复用 `Login::verifyPassword()` 逻辑）
- 返回 access_token + refresh_token

### 3.4 客户端凭证模式（Client Credentials）

**流程**：`应用以自身身份换取 token（无用户参与）`

| 步骤 | 端点 | 说明 |
|------|------|------|
| 1 | `POST /oauth/token` | `grant_type=client_credentials`，Header 携带 Basic Auth |

**客户端凭证模式规则**：
- 必须在应用的 `allowed_grant_types` 中启用
- 验证 client_id + client_secret（通过 Basic Auth）
- 仅返回 access_token（无 refresh_token，无 user_id）

---

## 四、Token 设计

### 4.1 Access Token

- **格式**：JWT（RS256 签名，复用现有 RSA 密钥对）
- **Payload 结构**：

```json
{
  "iss": "<host>",
  "sub": "<user_uuid>",
  "aud": ["<client_id>"],
  "iat": 1585538255,
  "exp": 1585567055,
  "jti": "<uuid>",
  "scope": "trust",
  "client_id": "<client_id>",
  "token_type": "access"
}
```

- **有效期**：默认 8 小时（可配置 `sc_site.access_token_ttl`）
- **存储**：JWT 字符串直接返回给客户端，数据库中存储其 SHA256 摘要作为索引

### 4.2 Refresh Token

- **格式**：随机字符串（128 位，由数字+字母+特殊字符组成）
- **有效期**：默认 24 小时（可配置 `sc_site.refresh_token_ttl`）
- **存储**：完整值存入 `oauth_refresh_tokens` 表
- **刷新规则**：刷新后旧 Refresh Token 和旧 Access Token 同时失效

### 4.3 ID Token

- **格式**：JWT（HS256 签名，密钥为应用的 `client_secret`）
- **Payload 结构**（严格遵循规范文档）：

```json
{
  "iss": "https://www.qianxin.com/",
  "sub": "<user_uuid>",
  "aud": ["<client_id>"],
  "iat": 1585538255547,
  "exp": 1585540054930,
  "auth_time": 1585538254930,
  "jti": "<uuid>",
  "azp": "<client_id>",
  "name": "admin",
  "email": "admin@example.com",
  "phone_number": "17761419630",
  "active": true,
  "typ": "ID",
  "acr": "0",
  "business_account": {
    "code": "admin",
    "id": "<user_id>",
    "name": "admin"
  }
}
```

- **签名密钥**：应用的 `client_secret`（每个应用不同）

---

## 五、SM3 签名验证中间件

SCIM 接口和部分 OAuth 接口使用 SM3（国密）签名验证，而非 OAuth 2.0 标准的 Bearer Token。

### 验证流程

1. 从 Header 提取：`X-trust-signature-version`、`X-trust-appid`、`X-trust-timestamp`、`X-trust-nonce`、`Content-MD5-HEX`
2. 计算请求 Body 的 MD5（GET/DELETE 方法为 URL path+query），与 `Content-MD5-HEX` 比对
3. 构造 V 值：7 个字段按 key 排序后 `&` 连接
4. 使用应用的 `client_secret` 作为 K，计算 SM3(K, V)
5. 比对计算结果与 `X-trust-signature` Header

### 中间件类

`app/http/middleware/VerifySignature.php`

```php
// 使用场景：SCIM 路由组
Route::group('api/scim/v2', function () {
    // ...SCIM routes
})->middleware(VerifySignature::class);
```

---

## 六、控制器设计

### 6.1 OAuthController（新建）

**文件**：`app/controller/OAuth.php`

| 方法 | 路由 | 说明 |
|------|------|------|
| `authorize()` | `GET /oauth/authorize` | 授权端点（授权码+隐式） |
| `token()` | `POST /oauth/token` | Token 端点（四种 grant_type） |
| `userinfo()` | `POST /oauth/userinfo` | 获取用户信息（返回 ID Token） |
| `logout()` | `POST /oauth/logout` | 应用通知登出 |

**认证方式**：
- `authorize()`：基于 Cookie Session（检测用户是否已登录）
- `token()`：HTTP Basic Auth（`client_id:client_secret`）
- `userinfo()`：Body 中传递 `access_token`
- `logout()`：HTTP Basic Auth + `access_token`

### 6.2 ScimController（新建）

**文件**：`app/controller/Scim.php`

| 方法 | 路由 | 说明 |
|------|------|------|
| `createAccount()` | `POST /api/scim/v2/accounts` | 创建用户（不存在创建，存在更新） |
| `updateAccount()` | `PUT /api/scim/v2/accounts/{externalId}` | 修改用户 |
| `deleteAccount()` | `DELETE /api/scim/v2/accounts/{externalId}` | 删除用户 |

**认证方式**：SM3 签名验证（通过 `VerifySignature` 中间件）

### 6.3 OAuthCallbackController（新建）

**文件**：`app/controller/OAuthCallback.php`

| 方法 | 路由 | 说明 |
|------|------|------|
| `logout()` | `POST /oauth/logout` | 认证平台通知应用登出 |
| `callback()` | `GET /oauth/callback` | 认证回调（重定向） |

---

## 七、服务层设计

### 7.1 TokenService（新建）

**文件**：`app/service/TokenService.php`

| 方法 | 说明 |
|------|------|
| `generateAccessToken(array $payload): string` | 生成 JWT Access Token（RS256） |
| `generateRefreshToken(): string` | 生成随机 Refresh Token |
| `verifyAccessToken(string $token): object\|false` | 验证并解析 Access Token |
| `storeAccessToken(array $data): void` | 存储 Access Token 记录到数据库 |
| `storeRefreshToken(array $data): string` | 存储 Refresh Token 记录到数据库 |
| `revokeToken(string $tokenId): void` | 撤销 Token |
| `refreshTokens(string $refreshToken, string $clientId): array\|false` | 刷新 Token（返回新 token 对） |

### 7.2 IDTokenService（新建）

**文件**：`app/service/IDTokenService.php`

| 方法 | 说明 |
|------|------|
| `generate(array $user, string $clientId, string $clientSecret): string` | 生成 ID Token（HS256，密钥为 client_secret） |

### 7.3 AuthorizationCodeService（新建）

**文件**：`app/service/AuthorizationCodeService.php`

| 方法 | 说明 |
|------|------|
| `generate(string $clientId, int $userId, string $redirectUri, string $scope, string $state): string` | 生成授权码 |
| `consume(string $code, string $clientId, string $redirectUri): array\|false` | 消费授权码（验证+删除，一次性使用） |
| `cleanup(): int` | 清理过期授权码 |

### 7.4 SignatureService（新建）

**文件**：`app/service/SignatureService.php`

| 方法 | 说明 |
|------|------|
| `verify(Request $request): bool` | 验证请求签名（SM3） |
| `computeSignature(string $data, string $key): string` | 计算 SM3 签名 |
| `computeContentMd5(string $content): string` | 计算 Body MD5 |

---

## 八、路由设计

### 8.1 新增路由文件

| 文件 | 路由前缀 | 说明 |
|------|----------|------|
| `route/oauth.php` | `/oauth` | OAuth 2.0 端点 |
| `route/scim.php` | `/api/scim/v2` | SCIM 用户同步端点 |
| `route/oauth_callback.php` | `/oauth` | OAuth 回调/登出通知端点 |

### 8.2 路由定义

```php
// route/oauth.php
Route::group('oauth', function () {
    Route::get('authorize', 'authorize');        // 授权码/隐式
    Route::post('token', 'token');                // 获取/刷新 Token
    Route::post('userinfo', 'userinfo');          // 获取用户信息
    Route::post('logout', 'logout');              // 应用通知登出
})->prefix('oauth/');

// route/scim.php
Route::group('api/scim/v2', function () {
    Route::post('accounts', 'createAccount');
    Route::put('accounts/:externalId', 'updateAccount');
    Route::delete('accounts/:externalId', 'deleteAccount');
})->prefix('scim/')
  ->middleware(\app\http\middleware\VerifySignature::class);

// route/oauth_callback.php
Route::group('oauth', function () {
    Route::post('logout', 'logout');
    Route::get('callback', 'callback');
})->prefix('oauth_callback/');
```

---

## 九、错误码设计

在 `app/common/ResponseCode.php` 中新增 OAuth 相关错误码（30000-39999 范围）：

| 常量 | 值 | 说明 |
|------|------|------|
| `OAUTH_INVALID_CLIENT` | 30001 | 无效的 client_id 或 client_secret |
| `OAUTH_INVALID_GRANT` | 30002 | 无效的授权码或凭证 |
| `OAUTH_INVALID_REQUEST` | 30003 | 请求参数缺失或格式错误 |
| `OAUTH_UNSUPPORTED_GRANT_TYPE` | 30004 | 不支持的 grant_type |
| `OAUTH_ACCESS_DENIED` | 30005 | 用户拒绝授权 |
| `OAUTH_TOKEN_EXPIRED` | 30006 | Token 已过期 |
| `OAUTH_TOKEN_REVOKED` | 30007 | Token 已撤销 |
| `OAUTH_INVALID_SCOPE` | 30008 | 无效的 scope |
| `OAUTH_INVALID_REDIRECT_URI` | 30009 | 无效的 redirect_uri |
| `OAUTH_SIGNATURE_INVALID` | 30010 | SM3 签名验证失败 |
| `OAUTH_USER_NOT_FOUND` | 30011 | 用户不存在（密码模式） |
| `OAUTH_GRANT_TYPE_NOT_ALLOWED` | 30012 | 该应用未启用此授权模式 |

---

## 十、向后兼容策略

1. **`sc_site` 表扩展**：新增字段均有默认值，现有记录不受影响；对现有数据批量生成 `client_id`/`client_secret`（迁移脚本处理）
2. **Application 控制器**：注册应用时自动生成 `client_id`/`client_secret`，返回给前端展示
3. **管理后台**：`Login` 控制器的 JWT 认证机制保持不变，与 OAuth 2.0 Token 独立
4. **路由**：现有 `/app/*` 路由不受影响，新增路由使用 `/api/` 前缀隔离
