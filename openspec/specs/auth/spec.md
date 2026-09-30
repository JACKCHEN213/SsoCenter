# Auth 模块 — 认证与会话管理

> 状态: IMPLEMENTED
> 最后更新: 2026-08-17

## Purpose

认证模块负责用户登录、JWT Token 签发与验证，以及路由级登录态拦截。系统使用 RS256 非对称加密的 JWT 作为认证凭据。

---
## Requirements
### Requirement: REQ-AUTH-001 用户登录（账号密码）

用户通过用户名和密码进行身份认证，认证成功后返回 JWT Token。

**实现位置**: `app/controller/Login.php` → `login()`

#### Scenario: 登录成功
- Given 数据库中存在用户名为 `admin` 的用户
- When 发送 `POST /login/login`，参数 `username=admin`，`password=<正确密码>`
- Then 系统验证密码（md5 比对）
- And 在 `login_history` 表中记录本次登录（username, ip, login_time）
- And 更新 `user` 表的 `ip` 字段为当前请求 IP
- And 返回 JWT Token（RS256 签名），payload 包含 `ip`、`login_time`、`username`、`id`、`type`
- And 响应格式: `{ code: 0, success: true, message: "成功", result: "<token>" }`

#### Scenario: 用户不存在
- Given 数据库中不存在用户名为 `unknown` 的用户
- When 发送 `POST /login/login`，参数 `username=unknown`
- Then 返回错误: `{ code: 10005, success: false, message: "用户找不到", result: "用户不存在" }`

#### Scenario: 密码错误
- Given 数据库中存在用户名为 `admin` 的用户
- When 发送 `POST /login/login`，参数 `username=admin`，`password=<错误密码>`
- Then 返回错误: `{ code: 10006, success: false, message: "密码错误", result: "登录密码错误" }`

---

### Requirement: REQ-AUTH-002 Token 验证（verify）

前端调用此接口验证 JWT Token 的有效性，并获取用户基本信息。

**实现位置**: `app/controller/Login.php` → `verify()` → `checkAuthorization()`

#### Scenario: Token 有效
- Given 用户持有一个有效的 JWT Token
- When 发送 `POST /login/verify`，参数 `token=<有效JWT>`
- Then 使用 RSA 公钥（RS256）解析 Token
- And 验证用户是否仍存在于数据库
- And 返回: `{ code: 10010, success: true, message: "身份验证成功", result: { username, id(base64编码), type } }`

#### Scenario: Token 无效或过期
- Given 用户持有一个无效/损坏的 JWT Token
- When 发送 `POST /login/verify`，参数 `token=<无效JWT>`
- Then 返回错误: `{ code: 10007, success: false, message: "身份验证错误", result: "token错误" }`

#### Scenario: Token 对应的用户已删除
- Given Token 有效但用户已从数据库中删除
- When 发送 `POST /login/verify`
- Then 返回错误: `{ code: 10007, success: false, message: "身份验证错误", result: "用户xxx不存在" }`

---

### Requirement: REQ-AUTH-003 全局登录态拦截（中间件）

所有非免登录路由的写操作请求（非 GET）必须携带有效 JWT Token。

**实现位置**: `app/http/middleware/CheckLogin.php`

#### Scenario: 免登录控制器直接放行
- Given 控制器在 `config('common.LOGON_FREE')['controller']` 白名单中
- When 请求到达该控制器
- Then 中间件直接放行，不验证 Token

#### Scenario: GET 请求直接放行
- Given 请求方法为 GET
- When 请求到达中间件
- Then 中间件直接放行（GET 请求不验证 Token）

#### Scenario: 免登录路由直接放行
- Given 路由在 `config('common.LOGON_FREE')['route']` 白名单中，且请求方法在白名单内
- When 请求到达该路由
- Then 中间件直接放行

#### Scenario: 非免登录路由无 Token 或 Token 无效
- Given 路由不在免登录白名单中，且请求方法为非 GET
- When 请求未携带 `Authorization` Header 或 Token 无效
- Then 返回错误: `{ code: 10007, success: false, message: "身份验证错误", result: "<错误信息>" }`

---

### Requirement: VerifySignature 中间件

系统 SHALL 提供 `app/http/middleware/VerifySignature.php` 中间件，用于 SCIM 接口的 SM3 签名验证。

**验证逻辑**：

1. 从 Header 提取签名相关字段
2. 校验 `X-trust-signature-version` 是否为 `2.0`
3. 校验 `X-trust-timestamp` 与当前时间差不超过 5 分钟（防重放）
4. 校验 `X-trust-nonce` 是否已使用（防重放，已用 nonce 存入 `scim_nonce` 表）
5. 计算 Body/URL 的 MD5，与 `Content-MD5-HEX` 比对
6. 构造 V 值 → 计算 SM3(K, V) → 与 `X-trust-signature` 比对
7. 全部通过后将 `appid`（client_id）注入请求上下文供控制器使用

**使用方式**：
```php
// 路由组级别
Route::group('api/scim/v2', function () { ... })
    ->middleware(VerifySignature::class);
```

#### Scenario: 签名验证通过注入客户端标识
- GIVEN 请求通过全部签名校验
- WHEN 中间件处理请求
- THEN client_id 注入请求上下文，请求进入控制器

#### Scenario: 签名验证失败拦截请求
- GIVEN 签名/时间戳/nonce/MD5 任一校验失败
- WHEN 中间件处理请求
- THEN 返回 403 `invalid_signature`，请求不进入控制器

### Requirement: OAuthClientAuth 中间件

系统 SHALL 提供 `app/http/middleware/OAuthClientAuth.php` 中间件，用于 OAuth Token 端点的客户端身份认证。

**验证逻辑**：

1. 解析 `Authorization: Basic base64(client_id:client_secret)` Header
2. 从 `site` 表查询 `client_id` 对应的应用
3. 验证应用存在且 `is_del=0`、`is_use=1`
4. 验证 `client_secret` 匹配
5. 将认证后的应用信息注入请求上下文

**使用方式**：
```php
// Token 端点与登出端点
Route::post('token', [OAuth::class, 'token'])->middleware(OAuthClientAuth::class);
```

#### Scenario: 客户端认证通过
- GIVEN Basic Auth 凭证合法
- WHEN 中间件处理请求
- THEN 应用信息注入 `request()->oauth_site`，请求进入控制器

#### Scenario: 客户端认证失败
- GIVEN Basic Auth 缺失、格式错误、client_id 不存在或 secret 不匹配
- WHEN 中间件处理请求
- THEN 返回 401 `invalid_client`

### Requirement: SignatureService 服务

系统 SHALL 提供 `app/service/SignatureService.php` 封装 SM3 签名计算逻辑，供中间件和其他服务调用。

**核心方法**：

| 方法 | 说明 |
|------|------|------|
| `verify(Request $request, string $clientSecret): bool` | 验证请求签名 |
| `computeSignature(string $data, string $key): string` | SM3 HMAC 计算，返回 32 位十六进制小写字符串 |
| `computeContentMd5(string $content): string` | 计算 MD5，返回 32 位十六进制小写字符串 |
| `buildSignatureContent(array $headers, string $method): string` | 构造 V 值（排序 + & 连接） |

#### Scenario: 签名计算与验证一致
- GIVEN 客户端与服务端使用相同密钥与算法
- WHEN 客户端计算签名、服务端验证
- THEN 验证通过；openssl 不可用时自动降级到内置 SM3 实现，结果一致

### Requirement: OAuth 错误码（30000-39999）

`app/common/ResponseCode.php` SHALL 新增以下错误码：

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

#### Scenario: 错误码落在 OAuth 段
- GIVEN OAuth/SCIM 业务错误发生
- WHEN 返回业务错误码
- THEN 错误码为 30001-30012 之一，与 `ResponseMessage` 中的消息对应

## JWT 技术规范

| 项目 | 说明 |
|------|------|
| 算法 | RS256（RSA + SHA256 非对称签名） |
| 私钥路径 | `config('common.JWT_KEY_PATH')` / `config('common.JWT_KEY_NAME').key` |
| 公钥路径 | `config('common.JWT_KEY_PATH')` / `config('common.JWT_KEY_NAME').key.pem` |
| Payload 字段 | `ip`, `login_time`, `username`, `id`, `type` |
| 实现类 | `extend/JWT.php`（`extra\JWT` 命名空间） |

---

## 数据库表

| 表名 | 用途 |
|------|------|
| `user` | 用户信息存储（含 `ip` 字段记录最后登录 IP） |
| `login_history` | 登录历史记录（`username`, `ip`, `login_time`） |

---

## 相关路由

| 路由 | 方法 | 控制器/方法 | 说明 |
|------|------|-------------|------|
| `GET /login/` | GET | `Login/index` | 渲染登录页面 |
| `POST /login/login` | POST | `Login/login` | 执行登录 |
| `POST /login/verify` | POST | `Login/verify` | 验证 Token |
| `GET /reset_password/` | GET | `ResetPassword/index` | 渲染重置密码页面（仅页面，未实现逻辑） |
