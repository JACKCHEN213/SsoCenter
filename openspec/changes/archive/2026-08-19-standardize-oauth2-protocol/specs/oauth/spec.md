# OAuth 2.0 模块规范（增量变更）

## ADDED Requirements

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
