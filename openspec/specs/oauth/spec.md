# OAuth 2.0 模块 — 标准协议端点

> 状态: IMPLEMENTED
> 最后更新: 2026-08-19

## Purpose

OAuth 2.0 模块实现标准协议的授权端点、Token 端点、用户信息端点与登出端点，支持四种授权模式（authorization_code、implicit、password、client_credentials）及 refresh_token 刷新。业务逻辑封装在服务层（`TokenService`、`AuthorizationCodeService`、`IDTokenService`），控制器不直接拼接 SQL。客户端认证通过 `OAuthClientAuth` 中间件解析 HTTP Basic Auth。Token 端点错误响应遵循 RFC 6749 的 `{ error, error_description }` 格式。

**实现位置**：
- 控制器：`app/controller/OAuth.php`、`app/controller/OAuthCallback.php`
- 服务层：`app/service/TokenService.php`、`app/service/AuthorizationCodeService.php`、`app/service/IDTokenService.php`
- 中间件：`app/http/middleware/OAuthClientAuth.php`
- 路由：`route/oauth.php`、`route/oauth_callback.php`

---
## Requirements
### Requirement: REQ-OAUTH-001 授权端点（authorization_code / implicit）

授权码模式与隐式模式的入口。用户未登录时重定向到登录页，已登录则完成授权并 302 回调 `redirect_uri`。

**实现位置**: `app/controller/OAuth.php` → `authorize()`
**路由**: `GET /oauth/authorize`

**请求参数（URL）**：

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `client_id` | String | 是 | 应用的 OAuth 客户端标识 |
| `response_type` | String | 是 | `code`（授权码）或 `token`（隐式） |
| `redirect_uri` | String | 是 | 回调地址，须与应用注册的 `redirect_url` 完全一致 |
| `scope` | String | 否 | 授权范围，默认 `trust` |
| `state` | String | 否 | 客户端状态值，防 CSRF，回调时原样带回 |

#### Scenario: 授权码模式成功
- Given 应用 `client_id` 有效（`is_del=0`、`is_use=1`）且 `allowed_grant_types` 包含 `authorization_code`
- And 用户已登录（Authorization 头或 `authorization` Cookie 携带合法登录 JWT）
- When 请求 `authorize`，`response_type=code`
- Then 生成授权码（有效期取应用 `code_ttl`，默认 600 秒）并写入 `oauth_authorization_codes`
- And 302 重定向到 `redirect_uri?code=<授权码>&state=<state>`

#### Scenario: 隐式模式成功
- Given 应用 `allowed_grant_types` 包含 `implicit`，用户已登录
- When 请求 `authorize`，`response_type=token`
- Then 生成 Access Token（JWT RS256）并写入 `oauth_access_tokens`（`grant_type=implicit`）
- And 302 重定向到 `redirect_uri#access_token=<token>&token_type=Bearer&expires_in=<秒>&state=<state>`

#### Scenario: 用户未登录
- Given 请求未携带合法登录态
- When 请求 `authorize`
- Then 302 重定向到 `/login/?oauth_redirect=<当前 authorize 完整 URL>`，登录成功后回到授权流程

#### Scenario: 参数缺失或非法
- When 缺少 `client_id` / `redirect_uri`，或 `response_type` 不是 `code`/`token`
- Then 返回 400 `{ error: "invalid_request", error_description: "..." }`

#### Scenario: client_id 无效
- Given `client_id` 不存在或应用已删除/停用
- Then 返回 401 `{ error: "invalid_client" }`

#### Scenario: redirect_uri 与注册值不一致
- Then 返回 400 `{ error: "invalid_redirect_uri" }`（不重定向，防止开放重定向攻击）

#### Scenario: 应用未启用对应授权模式
- Given `response_type=code` 但应用未启用 `authorization_code`（或 `token` 但未启用 `implicit`）
- Then 返回 403 `{ error: "access_denied" }`

**规则**：
- 授权码一次性使用，过期/重复消费返回 `invalid_grant`
- 登录态识别：优先 `Authorization` 请求头，其次 `authorization` Cookie；JWT 以系统 RSA 公钥验签，取 payload 中的 `id`

---

### Requirement: REQ-OAUTH-002 Token 端点 — 客户端认证与 grant_type 分发

获取/刷新 Token 的统一端点，通过 `grant_type` 分发到四种授权模式。

**实现位置**: `app/controller/OAuth.php` → `token()`
**路由**: `POST /oauth/token`（挂载 `OAuthClientAuth` 中间件）
**Content-Type**: `application/x-www-form-urlencoded`

**认证方式**：HTTP Basic Auth（`Authorization: Basic base64(client_id:client_secret)`），由 `OAuthClientAuth` 中间件校验：
1. 解析 Basic Auth Header
2. 查询 `site` 表（`client_id` 匹配、`is_del=0`、`is_use=1`）
3. 比对 `client_secret`
4. 通过后将应用信息注入 `request()->oauth_site`

#### Scenario: 客户端认证失败
- Given Basic Auth 缺失、格式错误、client_id 不存在或 client_secret 不匹配
- Then 返回 401 `{ error: "invalid_client" }`

#### Scenario: 不支持的 grant_type
- Given `grant_type` 不在 `authorization_code` / `refresh_token` / `password` / `client_credentials` 之内
- Then 返回 400 `{ error: "unsupported_grant_type" }`

#### Scenario: 应用未启用该授权模式
- Given `grant_type` 非 `refresh_token` 且不在应用 `allowed_grant_types` 内
- Then 返回 403 `{ error: "access_denied" }`
- 说明：`refresh_token` 为派生能力（由 authorization_code/password 颁发），不作为独立启用项，持有合法 refresh_token 即可刷新

---

### Requirement: REQ-OAUTH-003 授权码模式（grant_type=authorization_code）

消费授权码，换取 access_token + refresh_token。

**请求参数（Body）**：

| 参数 | 必选 | 说明 |
|------|------|------|
| `grant_type=authorization_code` | 是 | 固定值 |
| `code` | 是 | authorize 阶段获取的授权码 |
| `redirect_uri` | 是 | 须与 authorize 时一致 |

**响应**：
```json
{
  "access_token": "<JWT>",
  "refresh_token": "<128位随机串>",
  "token_type": "Bearer",
  "expires_in": 28800,
  "scope": "trust"
}
```

#### Scenario: 换取成功
- Given 授权码有效（未过期、未消费、client_id 与 redirect_uri 匹配）
- Then 消费授权码（一次性），生成 access_token（JWT RS256）与 refresh_token，分别写入数据库
- And 返回 token 响应（`expires_in` 取应用 `access_token_ttl`，默认 28800）

#### Scenario: 授权码无效/已过期/已使用/参数不匹配
- Then 返回 400 `{ error: "invalid_grant" }`

---

### Requirement: REQ-OAUTH-004 刷新令牌（grant_type=refresh_token）

使用 refresh_token 换取新的 token 对，旧 token 同时失效（rotation）。

**请求参数（Body）**：`grant_type=refresh_token`、`refresh_token`

#### Scenario: 刷新成功
- Given refresh_token 有效（未过期、未撤销、client_id 匹配）
- Then 生成新的 access_token + refresh_token，旧 refresh_token 与其关联的 access_token 同时失效
- And 返回与授权码模式相同结构的 token 响应

#### Scenario: refresh_token 无效/已过期/已撤销
- Then 返回 400 `{ error: "invalid_grant" }`

---

### Requirement: REQ-OAUTH-005 密码模式（grant_type=password）

以用户名密码直接换取 token，仅限显式启用 `password` 模式的应用。

**请求参数（Body）**：`grant_type=password`、`username`（必填）、`password`（必填）、`scope`（可选，默认 `trust`）

#### Scenario: 验证成功
- Given 用户存在（`is_del=0`）且密码校验通过
- Then 生成 access_token（payload `sub` 为用户 ID）+ refresh_token，写库并返回 token 响应

#### Scenario: 用户不存在或密码错误
- Then 返回 400 `{ error: "invalid_grant" }`（统一错误文案，不区分"用户不存在"与"密码错误"）

**密码校验规则**（与登录逻辑一致）：
- 已迁移用户（存在 `jwt_private_path`）：用户私钥解密密码密文后 `password_verify(md5(password), hash)`
- 未迁移用户：兼容旧 `md5(password)` 比对

---

### Requirement: REQ-OAUTH-006 客户端凭证模式（grant_type=client_credentials）

仅验证客户端自身身份，颁发应用级 access_token，无用户上下文、无 refresh_token。

**请求参数（Body）**：`grant_type=client_credentials`、`scope`（可选，默认 `trust`）

**响应**：`{ access_token, token_type: "Bearer", expires_in, scope }`（无 `refresh_token`）

#### Scenario: 颁发成功
- Then 生成 access_token（payload `sub` 为 client_id），`oauth_access_tokens` 记录 `user_id=NULL`、`grant_type=client_credentials`

---

### Requirement: REQ-OAUTH-007 获取用户信息（userinfo）

验证 access_token，返回封装用户信息的 ID Token。

**实现位置**: `app/controller/OAuth.php` → `userinfo()`
**路由**: `POST /oauth/userinfo`

**请求参数（Body）**：`access_token`（支持 form-urlencoded 与 JSON）

**响应**：
```json
{
  "message": "succes",
  "status": 200,
  "data": { "id_token": "<JWT字符串>" }
}
```

> 注：`message` 值 `succes` 与响应结构为对接规范文档的既定格式，保持不变。

**ID Token Claims**（HS256 签名，密钥为该应用的 `client_secret`）：

| Claim | 类型 | 必选 | 说明 |
|-------|------|------|------|
| `iss` | String | 是 | 固定值 `https://www.qianxin.com/` |
| `sub` | String | 是 | 用户唯一 UUID |
| `aud` | String[] | 是 | 应用的 client_id |
| `iat` | Int64 | 是 | 签发时间戳（**毫秒**） |
| `exp` | Int64 | 是 | 过期时间戳（**毫秒**） |
| `auth_time` | Int64 | 是 | 认证时间戳（**毫秒**） |
| `jti` | String | 是 | Token 唯一 ID |
| `azp` | String | 否 | client_id |
| `name` | String | 否 | 用户名 |
| `email` | String | 否 | 邮箱 |
| `phone_number` | String | 否 | 手机号 |
| `active` | Boolean | 否 | 是否启用 |
| `typ` | String | 否 | 固定 `"ID"` |
| `acr` | String | 否 | 固定 `"0"` |
| `business_account` | Object | 是 | 当前登录账号信息 `{ code, id, name }` |

> 注：ID Token 的 `iat`/`exp`/`auth_time` 采用毫秒时间戳，系对接规范文档的明确要求（Access Token 仍为秒级）。应用端解析时须按毫秒处理。

#### Scenario: 获取成功
- Given access_token JWT 验签通过，且数据库中记录有效（未撤销、未过期）
- Then 以 client_secret 生成 ID Token 并返回上述响应

#### Scenario: Token 缺失/无效/过期/已撤销
- Then 返回 401 `{ error: "invalid_token" }`

---

### Requirement: REQ-OAUTH-008 应用通知登出（oauth2/logout）

应用携带 access_token 通知认证中心注销，撤销 token 并级联处理关联会话。

**实现位置**: `app/controller/OAuth.php` → `logout()`
**路由**: `POST /oauth/logout`（挂载 `OAuthClientAuth` 中间件）

**请求**：Basic Auth + Body 中 `access_token`

**响应**：`{ message: "succes", status: 200 }`

#### Scenario: 登出成功
- Given 客户端认证通过且 access_token 有效
- Then 撤销该 access_token（`oauth_access_tokens.revoked=1`）
- And 撤销该用户在该应用下的所有 refresh_token（`oauth_refresh_tokens.revoked=1`）
- And 遍历该用户其余有效 access_token 关联的应用，发送登出通知（当前为占位实现，待接入异步队列）

#### Scenario: 客户端认证失败 / Token 无效
- Then 分别返回 401 `invalid_client` / 401 `invalid_token`

---

### Requirement: REQ-OAUTH-009 回调与登出通知端点（/oauth）

认证中心自身的回调接收与 Bearer 登出通知端点。

**实现位置**: `app/controller/OAuthCallback.php` → `callback()`、`logout()`
**路由**: `route/oauth_callback.php`

#### Scenario: GET /oauth/callback 接收授权回调
- Given 回调携带 `code` 与 `state`
- Then 302 重定向到 `/login/?code=<code>&state=<state>` 交由前端继续处理
- Given 回调携带 `error` 参数
- Then 返回 400 `{ error: "<原样透传>", error_description: "..." }`

#### Scenario: POST /oauth/logout Bearer 登出
- Given `Authorization: Bearer <access_token>` 且 token 有效
- Then 撤销该 access_token，并按 access_token 哈希撤销关联的 refresh_token（`access_token_id` 字段）
- And 返回 `{ message: "succes", status: 200 }`
- Given Authorization 头非 Bearer 或 token 无效
- Then 返回 401 `{ error: "invalid_token" }`

---

### Requirement: REQ-OAUTH-010 Token 格式与生命周期

| Token | 格式 | 签名/生成 | 默认有效期 | 存储 |
|-------|------|-----------|-----------|------|
| Access Token | JWT | RS256，系统 RSA 私钥（`app/common/keys/jwt.key`） | 28800 秒（8 小时），按应用 `access_token_ttl` 覆盖 | 数据库存其 SHA-256 摘要（`oauth_access_tokens.access_token`） |
| Refresh Token | 128 位随机字符串 | 随机生成 | 86400 秒（24 小时），按应用 `refresh_token_ttl` 覆盖 | 完整值存库（`oauth_refresh_tokens`） |
| ID Token | JWT | HS256，密钥为应用 `client_secret` | 短时效，仅供应用端解析 | 不落库 |

**Access Token payload**：`iss`（请求 host）、`sub`（用户 ID；client_credentials 时为 client_id）、`aud`（[client_id]）、`iat`/`exp`（秒）、`jti`、`scope`、`client_id`、`token_type: "access"`

**验证规则**：JWT 验签 + `exp` 检查（`TokenService::verifyAccessToken`）；数据库有效性检查 `revoked=0` 且 `expires_at` 未过期（`TokenService::isAccessTokenValid`）。任一失败即视为无效。

#### Scenario: Token 存储与验证符合规范
- Given 任一授权模式颁发 Access Token
- When 存储与验证该 Token
- Then 数据库仅存其 SHA-256 摘要，验证时 JWT 验签与数据库记录（未撤销、未过期）检查均须通过

#### Scenario: Refresh Token 轮换
- Given 使用 refresh_token 刷新成功
- When 颁发新 token 对
- Then 旧 refresh_token 与关联 access_token 同时失效

---

### Requirement: REQ-OAUTH-011 错误响应格式

所有 OAuth 端点错误统一为（HTTP 状态码同步设置）：

```json
{ "error": "invalid_client", "error_description": "Client authentication failed" }
```

| error | HTTP 状态码 | 说明 |
|-------|-------------|------|
| `invalid_client` | 401 | client_id/client_secret 无效 |
| `invalid_grant` | 400 | 授权码/refresh_token 无效、已过期、已使用；用户名或密码错误 |
| `invalid_request` | 400 | 请求参数缺失或格式错误 |
| `unsupported_grant_type` | 400 | 不支持的 grant_type |
| `access_denied` | 403 | 用户拒绝授权 / 应用未启用该授权模式 |
| `invalid_token` | 401 | Token 无效/过期/已撤销 |
| `invalid_redirect_uri` | 400 | redirect_uri 与注册值不一致 |
| `server_error` | 500 | 服务端异常 |

**内部错误码**（`app/common/ResponseCode.php`，30000-39999 段）：

| 常量 | 值 | 说明 |
|------|------|------|
| `OAUTH_INVALID_CLIENT` | 30001 | 无效客户端 |
| `OAUTH_INVALID_GRANT` | 30002 | 无效授权 |
| `OAUTH_INVALID_REQUEST` | 30003 | 无效请求 |
| `OAUTH_UNSUPPORTED_GRANT_TYPE` | 30004 | 不支持的授权类型 |
| `OAUTH_ACCESS_DENIED` | 30005 | 访问被拒绝 |
| `OAUTH_TOKEN_EXPIRED` | 30006 | Token 过期 |
| `OAUTH_TOKEN_REVOKED` | 30007 | Token 已撤销 |
| `OAUTH_INVALID_SCOPE` | 30008 | 无效 scope |
| `OAUTH_INVALID_REDIRECT_URI` | 30009 | 无效回调地址 |
| `OAUTH_SIGNATURE_INVALID` | 30010 | 签名验证失败 |
| `OAUTH_USER_NOT_FOUND` | 30011 | 用户不存在 |
| `OAUTH_GRANT_TYPE_NOT_ALLOWED` | 30012 | 未启用此授权模式 |

#### Scenario: 错误响应符合统一格式
- Given 任一 OAuth 端点发生错误
- When 返回错误响应
- Then 响应体为 `{ error, error_description }`，HTTP 状态码与错误表一致，内部业务错误码落在 30001-30012 范围

---

### Requirement: 授权端点支持授权码与隐式模式

系统 SHALL 提供 `GET /oauth/authorize` 授权端点，作为授权码模式和隐式模式的入口。用户未登录时 302 到登录页，已登录并完成授权后 302 回调 `redirect_uri`。

**请求参数（URL）**：

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `client_id` | String | 是 | 应用的 AppID |
| `response_type` | String | 是 | `code`（授权码）或 `token`（隐式） |
| `redirect_uri` | String | 是 | 回调地址，须与应用注册的一致 |
| `scope` | String | 否 | 授权范围（默认 `trust`） |
| `state` | String | 否 | 客户端状态值，防 CSRF，回调时原样带回 |

**授权码模式响应**：302 重定向到 `redirect_uri?code=<授权码>&state=<state>`
**隐式模式响应**：302 重定向到 `redirect_uri#access_token=<token>&token_type=Bearer&expires_in=<秒>&state=<state>`

**规则**：
- 授权码一次性使用，10 分钟过期（按应用 `code_ttl` 可配置）
- `redirect_uri` 必须与注册值完全一致
- `client_id` 必须存在且 `is_del=0`、`is_use=1`

#### Scenario: 授权码模式授权成功
- GIVEN 用户已登录，应用有效且启用 `authorization_code` 模式
- WHEN 请求 authorize，`response_type=code`
- THEN 生成一次性授权码并 302 回调 `redirect_uri?code=<授权码>&state=<state>`

#### Scenario: 隐式模式授权成功
- GIVEN 用户已登录，应用启用 `implicit` 模式
- WHEN 请求 authorize，`response_type=token`
- THEN 生成 access_token 并 302 回调，token 在 URL fragment 中

#### Scenario: 用户未登录
- GIVEN 请求未携带合法登录态
- WHEN 请求 authorize
- THEN 302 重定向到登录页，登录后回到授权流程

#### Scenario: 无效的 client_id 或不一致的 redirect_uri
- GIVEN client_id 不存在，或 redirect_uri 与注册值不一致
- WHEN 请求 authorize
- THEN 返回 OAuth 错误响应（`invalid_client` 401 / `invalid_redirect_uri` 400），不重定向

### Requirement: Token 端点支持四种授权模式

系统 SHALL 提供 `POST /oauth/token` 统一 Token 端点，通过 HTTP Basic Auth 认证客户端，按 `grant_type` 分发到四种授权模式。

**认证方式**：HTTP Basic Auth（`Authorization: Basic base64(client_id:client_secret)`）

**Content-Type**：`application/x-www-form-urlencoded`

**通用请求参数（Body）**：

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `grant_type` | String | 是 | `authorization_code` / `refresh_token` / `password` / `client_credentials` |

##### grant_type=authorization_code

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `code` | String | 是 | 上一步获取的授权码 |
| `redirect_uri` | String | 是 | 须与 authorize 时一致 |

**响应**：`{ access_token, refresh_token, token_type: "Bearer", expires_in, scope }`

##### grant_type=password

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `username` | String | 是 | 用户名 |
| `password` | String | 是 | 密码 |
| `scope` | String | 否 | 授权范围 |

**响应**：`{ access_token, refresh_token, token_type: "Bearer", expires_in, scope }`
**规则**：应用必须启用 `password` 授权模式

##### grant_type=client_credentials

**响应**：`{ access_token, token_type: "Bearer", expires_in, scope }`（无 refresh_token）
**规则**：应用必须启用 `client_credentials` 授权模式

#### Scenario: 授权码换取 Token
- GIVEN 持有有效授权码且客户端认证通过
- WHEN `grant_type=authorization_code` 提交 code 与 redirect_uri
- THEN 消费授权码（一次性），返回 access_token + refresh_token

#### Scenario: 密码模式换取 Token
- GIVEN 应用启用 password 模式，用户名密码正确
- WHEN `grant_type=password` 提交 username/password
- THEN 返回 access_token + refresh_token

#### Scenario: 客户端凭证模式换取 Token
- GIVEN 应用启用 client_credentials 模式
- WHEN `grant_type=client_credentials`
- THEN 仅返回 access_token（无 refresh_token，无用户上下文）

#### Scenario: 客户端认证失败或不支持的 grant_type
- GIVEN Basic Auth 无效，或 grant_type 不在四种之内，或应用未启用该模式
- WHEN 请求 token 端点
- THEN 分别返回 `invalid_client`(401) / `unsupported_grant_type`(400) / `access_denied`(403)

### Requirement: Token 端点支持刷新令牌

系统 SHALL 支持 `grant_type=refresh_token` 刷新 Token，旧 refresh_token 和关联的 access_token 同时失效（rotation）。

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `refresh_token` | String | 是 | 刷新令牌 |

**响应**：`{ access_token, refresh_token, token_type: "Bearer", expires_in, scope }`

#### Scenario: 刷新成功
- GIVEN 持有未过期、未撤销的 refresh_token
- WHEN `grant_type=refresh_token`
- THEN 返回新的 token 对，旧 refresh_token 与关联 access_token 失效

#### Scenario: refresh_token 无效
- GIVEN refresh_token 已过期、已撤销或不属于该客户端
- WHEN `grant_type=refresh_token`
- THEN 返回 `invalid_grant`(400)

### Requirement: 用户信息端点返回 ID Token

系统 SHALL 提供 `POST /oauth/userinfo` 端点，验证 access_token 并返回包含用户信息的 ID Token。

**请求参数（Body）**：

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `access_token` | String | 是 | 访问令牌 |

**响应**：
```json
{
  "message": "succes",
  "status": 200,
  "data": {
    "id_token": "<JWT字符串>"
  }
}
```

**ID Token Claims**（HS256 签名，密钥为 client_secret）：

| Claim | 类型 | 必选 | 说明 |
|-------|------|------|------|
| `iss` | String | 是 | 固定值 `https://www.qianxin.com/` |
| `sub` | String | 是 | 用户唯一 UUID |
| `aud` | String[] | 是 | 应用的 client_id |
| `iat` | Int64 | 是 | 签发时间戳（毫秒） |
| `exp` | Int64 | 是 | 过期时间戳（毫秒） |
| `auth_time` | Int64 | 是 | 认证时间戳（毫秒） |
| `jti` | String | 是 | Token 唯一 ID |
| `azp` | String | 否 | client_id |
| `name` | String | 否 | 用户名 |
| `email` | String | 否 | 邮箱 |
| `phone_number` | String | 否 | 手机号 |
| `active` | Boolean | 否 | 是否启用 |
| `typ` | String | 否 | 固定 `"ID"` |
| `acr` | String | 否 | 固定 `"0"` |
| `business_account` | Object | 是 | 当前登录账号信息 `{ code, id, name }` |

#### Scenario: 获取用户信息成功
- GIVEN access_token 有效（JWT 验签通过且数据库记录未撤销、未过期）
- WHEN 请求 userinfo
- THEN 返回 `{ message, status, data: { id_token } }`，ID Token 以 client_secret HS256 签名

#### Scenario: Token 无效
- GIVEN access_token 缺失、无效、过期或已撤销
- WHEN 请求 userinfo
- THEN 返回 `invalid_token`(401)

### Requirement: 应用通知登出

系统 SHALL 提供 `POST /oauth/logout` 端点，应用携带 access_token 通知认证中心注销用户会话，并级联通知其他关联应用登出。

**认证方式**：HTTP Basic Auth + Body 中 `access_token`

**响应**：`{ message: "succes", status: 200 }`

#### Scenario: 登出成功
- GIVEN 客户端认证通过且 access_token 有效
- WHEN 请求 logout
- THEN 撤销该 access_token 及关联 refresh_token，通知关联应用登出

### Requirement: Token 格式规范

系统 SHALL 按以下格式生成和管理三类 Token。

#### Access Token

- 格式：JWT（RS256 签名）
- 签名密钥：系统 RSA 私钥（`app/common/keys/jwt.key`）
- 有效期：默认 8 小时（可配置）
- Token 值：完整 JWT 字符串

#### Refresh Token

- 格式：128 位随机字符串（数字+字母+特殊字符）
- 有效期：默认 24 小时（可配置）
- 存储：完整值存入数据库

#### ID Token

- 格式：JWT（HS256 签名）
- 签名密钥：应用的 `client_secret`
- 用途：封装用户身份信息，供应用端解析

#### Scenario: Token 格式符合规范
- GIVEN 任一授权模式颁发 Token
- WHEN 解析 Token 结构
- THEN access_token 为 RS256 JWT、refresh_token 为 128 位随机串、ID Token 为 HS256 JWT

### Requirement: OAuth 错误响应格式

所有 OAuth 端点的错误响应 SHALL 采用 `{ error, error_description }` 格式并携带对应 HTTP 状态码。

```json
{
  "error": "invalid_client",
  "error_description": "Client authentication failed"
}
```

| error | HTTP 状态码 | 说明 |
|-------|-------------|------|
| `invalid_client` | 401 | client_id/client_secret 无效 |
| `invalid_grant` | 400 | 授权码无效/已过期/已使用 |
| `invalid_request` | 400 | 请求参数缺失或格式错误 |
| `unsupported_grant_type` | 400 | 不支持的 grant_type |
| `access_denied` | 403 | 用户拒绝授权 |
| `invalid_token` | 401 | Token 无效/过期/已撤销 |
| `invalid_scope` | 400 | 无效的 scope |

#### Scenario: 错误响应符合 RFC 6749 格式
- GIVEN 任一 OAuth 端点发生错误
- WHEN 返回错误响应
- THEN 响应体为 `{ error, error_description }`，HTTP 状态码与上表一致

## 数据库表

（前缀 `sc_`，迁移脚本：`extend/migrations/oauth2_standardize.sql`）

### sc_oauth_authorization_codes — 授权码表

| 字段 | 类型 | 说明 |
|------|------|------|
| `code` | VARCHAR(128) UNIQUE | 授权码 |
| `client_id` | VARCHAR(64) | 关联应用 client_id |
| `user_id` | INT | 授权用户 ID |
| `redirect_uri` | VARCHAR(512) | 回调地址 |
| `scope` | VARCHAR(255) | 授权范围 |
| `state` | VARCHAR(128) | 客户端 state 参数 |
| `expires_at` | DATETIME | 过期时间 |

### sc_oauth_access_tokens — Access Token 表

| 字段 | 类型 | 说明 |
|------|------|------|
| `access_token` | VARCHAR(255) UNIQUE | Access Token 的 SHA-256 摘要 |
| `client_id` | VARCHAR(64) | 关联应用 client_id |
| `user_id` | INT NULL | 关联用户 ID（client_credentials 为 NULL） |
| `grant_type` | VARCHAR(32) | 授权模式 |
| `scope` | VARCHAR(255) | 授权范围 |
| `expires_at` | DATETIME | 过期时间 |
| `revoked` | TINYINT(1) | 是否已撤销 |

### sc_oauth_refresh_tokens — Refresh Token 表

| 字段 | 类型 | 说明 |
|------|------|------|
| `refresh_token` | VARCHAR(128) UNIQUE | Refresh Token 完整值 |
| `client_id` | VARCHAR(64) | 关联应用 client_id |
| `user_id` | INT | 关联用户 ID |
| `scope` | VARCHAR(255) | 授权范围 |
| `expires_at` | DATETIME | 过期时间 |
| `revoked` | TINYINT(1) | 是否已撤销 |
| `access_token_id` | VARCHAR(255) | 关联的 access_token 摘要 |

`sc_site` 表的 OAuth 凭证与配置字段见 `application` 模块规范。

---

## 相关路由

| 路由 | 方法 | 控制器/方法 | 中间件 | 说明 |
|------|------|-------------|--------|------|
| `/oauth/authorize` | GET | `OAuth/authorize` | — | 授权端点（code/token） |
| `/oauth/token` | POST | `OAuth/token` | `OAuthClientAuth` | Token 端点（四种 grant） |
| `/oauth/userinfo` | POST | `OAuth/userinfo` | — | 获取用户信息（ID Token） |
| `/oauth/logout` | POST | `OAuth/logout` | `OAuthClientAuth` | 应用通知登出 |
| `/oauth/callback` | GET | `OAuthCallback/callback` | — | 授权回调接收 |
| `/oauth/logout` | POST | `OAuthCallback/logout` | — | Bearer 登出通知 |
| `/oauth/authorize` | GET | `OAuth/authorizePage` | — | SSO 授权登录页（面向用户的浏览器） |
| `/oauth/authorize/confirm` | POST | `OAuth/confirmAuthorize` | — | 用户确认授权（AJAX，JWT 鉴权） |
| `/oauth/authorize/deny` | POST | `OAuth/denyAuthorize` | — | 用户拒绝授权（AJAX） |

---

## SSO 授权登录页（adapt-frontend-oauth2）

### Requirement: REQ-OAUTH-012 SSO 授权登录页面

面向用户的浏览器页面，用于在第三方应用跳转过来时引导用户完成登录 + 授权确认。

**实现位置**: `app/controller/OAuth.php` → `authorizePage()`
**路由**: `GET /oauth/authorize`
**模板**: `app/view/oauth_authorize.html`（继承 `template/login`）

**请求参数（URL）**：

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `client_id` | String(UUID4) | 是 | 应用的 OAuth 客户端标识 |
| `response_type` | String | 是 | `code`（授权码）或 `token`（隐式） |
| `redirect_uri` | String | 是 | 回调地址，须与应用注册的 `redirect_url` 完全一致 |
| `scope` | String | 否 | 授权范围，默认 `trust` |
| `state` | String | 否 | 客户端状态值，回调时原样带回 |

**页面两个状态视图**：
- **状态 A（登录表单）**：用户未登录时显示，提交 `ssoLogin(event)` AJAX 登录
- **状态 B（授权确认）**：用户已登录后显示，展示应用信息与权限列表，按钮"允许"/"拒绝"

**初始渲染逻辑**：服务端通过 `getLoggedInUserId()` 检测登录态（兼容 Authorization Cookie 的 raw JWT 和 base64 包裹两种格式），若已登录则直接渲染状态 B。

**参数错误处理**：
- client_id 格式无效 / 应用不存在 → 错误卡片"未知的应用"
- response_type 不是 `code`/`token` → 错误卡片"不支持的授权类型"
- redirect_uri 与注册值不一致 → 错误卡片"无效的回调地址"
- 缺少必要参数 → 错误卡片"参数错误"

#### Scenario: 用户未登录访问 SSO 页面
- Given 第三方应用跳转到 `/oauth/authorize?client_id=...&response_type=code&redirect_uri=...`
- And 用户未登录（无有效 Cookie）
- Then 渲染状态 A（登录表单），用户提交 `ssoLogin(event)` AJAX 登录
- And 登录成功后切换到状态 B（授权确认视图）

#### Scenario: 用户已登录访问 SSO 页面
- Given 用户已登录（Cookie 携带合法 JWT）
- When 访问 `/oauth/authorize`
- Then 直接渲染状态 B，跳过登录步骤

#### Scenario: 用户点击"允许"
- Given 用户已登录且处于状态 B
- When 点击"允许"按钮
- Then 前端 AJAX POST `/oauth/authorize/confirm`（携带 JWT Authorization 头）
- And 后端生成授权码（`response_type=code`）或 access_token（`response_type=token`）
- And 返回 `{ redirect_url: "<完整回调 URL>" }`
- And 前端 `window.location.href = redirect_url` 跳转回调

#### Scenario: 用户点击"拒绝"
- Given 用户处于状态 B
- When 点击"拒绝"按钮
- Then 前端 `window.location.href = redirect_uri?error=access_denied&error_description=...&state=...`

#### Scenario: 错误参数
- Given client_id / response_type / redirect_uri 中任一无效
- When 访问 `/oauth/authorize`
- Then 渲染错误卡片，显示对应的中文错误说明

---

### Requirement: REQ-OAUTH-013 SSO 确认授权端点

`POST /oauth/authorize/confirm` 接收用户授权确认，生成授权码或 access_token 并返回回调 URL。

**实现位置**: `app/controller/OAuth.php` → `confirmAuthorize()`
**路由**: `POST /oauth/authorize/confirm`
**鉴权**: 请求体中携带 `Authorization` Header（JWT）

**请求参数（JSON）**：`client_id`、`redirect_uri`、`response_type`、`scope`（可选）、`state`（可选）

**响应**：`{ code: 0, success: true, message: "成功", result: { redirect_url: "<完整回调 URL>" } }`

**规则**：
- 未登录 → 返回 10007 JWT_ERROR
- client_id / redirect_uri / response_type 无效 → 返回 10001 VALIDATE_ERROR
- redirect_uri 与注册值不一致 → 拒绝响应（防止开放重定向攻击）
- `response_type=code` → 生成授权码，回调 URL 格式：`redirect_uri?code=<code>&state=<state>`
- `response_type=token` → 生成 JWT access_token，回调 URL 格式：`redirect_uri#access_token=<token>&token_type=Bearer&expires_in=<秒>&state=<state>`

#### Scenario: 确认授权成功
- Given 用户已登录且参数有效
- When POST `/oauth/authorize/confirm`
- Then 返回回调 URL，前端据此跳转

#### Scenario: 未登录
- Given Authorization Header 缺失或 JWT 无效
- Then 返回 10007 JWT_ERROR

---

### Requirement: REQ-OAUTH-014 SSO 拒绝授权端点

`POST /oauth/authorize/deny` 前端拒绝授权时调用，返回带 `error=access_denied` 的回调 URL。

**实现位置**: `app/controller/OAuth.php` → `denyAuthorize()`
**路由**: `POST /oauth/authorize/deny`

**响应**：`{ code: 0, success: true, message: "成功", result: { redirect_url: "<带 error 参数的回调 URL>" } }`

#### Scenario: 拒绝授权
- Given 参数有效
- When POST `/oauth/authorize/deny`
- Then 返回回调 URL，携带 `error=access_denied`、`error_description`、`state`
