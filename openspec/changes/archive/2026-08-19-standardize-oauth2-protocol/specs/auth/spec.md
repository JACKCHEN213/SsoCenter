# 认证与签名模块规范（增量变更）

## ADDED Requirements

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
