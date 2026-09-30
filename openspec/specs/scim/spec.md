# SCIM 模块 — 用户同步接口

> 状态: IMPLEMENTED
> 最后更新: 2026-08-19

## Purpose

SCIM 模块提供标准化的用户同步接口（创建/修改/删除账号），供对接系统以 SM3 签名认证的方式向认证中心推送用户数据。所有请求须通过 `VerifySignature` 中间件的签名验证（含时间戳与 nonce 防重放）；业务逻辑封装在 `UserSyncService`，控制器不直接操作数据库。删除为软删除（`is_del=1`）。

**实现位置**：
- 控制器：`app/controller/Scim.php`
- 服务层：`app/service/UserSyncService.php`（用户 upsert/更新/软删除）、`app/service/SignatureService.php`（SM3 签名计算与验证）
- 中间件：`app/http/middleware/VerifySignature.php`
- 验证器：`app/validate/Scim.php`
- 路由：`route/scim.php`

---
## Requirements
### Requirement: REQ-SCIM-001 SM3 签名验证

所有 SCIM 请求先经 `VerifySignature` 中间件验证签名，失败返回 HTTP 403 `{ error: "invalid_signature", error_description: "..." }`。

**请求 Header**：

| Header | 说明 |
|--------|------|
| `Content-MD5-HEX` | 内容 MD5（32 位十六进制小写） |
| `Content-Type` | `application/json;charset=UTF-8` |
| `X-trust-appid` | 对接系统 client_id |
| `X-trust-nonce` | 挑战随机数 |
| `X-trust-signature-version` | 固定 `2.0` |
| `X-trust-timestamp` | 时间戳（秒） |
| `X-trust-signature` | SM3 签名值 |
| `X-api-version` | 固定 `1.0-rev0` |

**验证流程**（`SignatureService::verify`）：

1. `X-trust-appid` 对应的应用存在（`is_del=0`），取其 `client_secret` 作为密钥 K
2. `X-trust-signature-version` 必须为 `2.0`
3. `X-trust-timestamp` 与服务器时间差不超过 300 秒（防重放）
4. `X-trust-nonce` 未曾使用（写入 `scim_nonce` 表，唯一键防重放）
5. 校验 `Content-MD5-HEX`：POST/PUT 为请求 Body 的 MD5；GET/DELETE 为 URL path+query 的 MD5
6. 提取 7 个字段：`content-type`、`x-trust-signature-version`、`x-trust-appid`、`x-trust-timestamp`、`x-trust-nonce`、`content-md5-hex`、`method`；字段名转小写、值去前后空格，格式化为 `key=value`，按 key 字母排序后用 `&` 连接得到 V
7. 计算 `HMAC-SM3(K, V)`（openssl 支持时用 openssl，否则用内置实现），与 `X-trust-signature` 比对
8. 全部通过后将 `client_id` 注入请求上下文（`request()->oauth_client_id`）供控制器记录审计日志

#### Scenario: 签名正确
- Given 请求携带正确的签名、合法时间戳与未使用的 nonce
- When 请求任一 SCIM 端点
- Then 签名验证通过，请求进入控制器正常处理

#### Scenario: 签名值错误 / appid 不存在 / version 不为 2.0 / Content-MD5-HEX 不匹配
- Given 上述任一校验项不合法
- When 请求任一 SCIM 端点
- Then 返回 403 `invalid_signature`

#### Scenario: 时间戳超过 ±300 秒
- Given 请求时间戳与服务器时间差超过 300 秒
- When 请求任一 SCIM 端点
- Then 返回 403（过期请求）

#### Scenario: nonce 重复使用（重放攻击）
- Given 请求 nonce 已存在于 `scim_nonce` 表
- When 请求任一 SCIM 端点
- Then 返回 403

---

### Requirement: REQ-SCIM-002 创建账号（upsert 语义）

创建或更新用户：不存在则创建，存在则更新。

**实现位置**: `app/controller/Scim.php` → `createAccount()`
**路由**: `POST /api/scim/v2/accounts`（中间件：`VerifySignature` + `ValidateParams`）
**验证器场景**: `Scim::sceneCreateAccount()` — `code`、`name`、`externalId`、`status` 必填

**请求参数（Body）**：

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `code` | String | 是 | 账号标识，唯一（如 `zhangsan`） |
| `name` | String | 是 | 账号名称 |
| `externalId` | String | 是 | 外部 ID |
| `mobile` | String | 否 | 手机号 |
| `email` | String | 否 | 邮箱 |
| `status` | Boolean/String | 是 | 用户状态（启用/禁用；接受布尔值或 `1`/`true`/`active`/`enabled`） |

#### Scenario: 创建新用户
- Given 不存在 `external_id` 或 `code` 匹配的未删除用户
- Then 插入 `user` 表：`username=code`、`code`、`external_id`、`mobile`、`email`、`status`、`type=2`、`is_del=0`，密码写入随机 bcrypt 散列（SCIM 同步用户不可密码登录）
- And 记录审计日志（client_id、code、external_id、is_new）
- And 返回成功响应，`message: "创建账号成功"`

#### Scenario: 用户已存在（更新）
- Given 按 `external_id` 优先、其次按 `code` 匹配到未删除用户
- Then 更新该用户的 `status`、`external_id`、`mobile`、`email`
- And 返回成功响应，`message: "账号已存在，更新成功"`

**成功响应格式**：
```json
{
  "success": true,
  "code": 200,
  "message": "创建账号成功",
  "title": "创建账号",
  "externalId": "SCIM-EXT-001",
  "data": { "accountId": "12dcab39-69db-449a-82ea-ad8cfce27309" }
}
```

> `accountId` 为基于 `external_id`（缺失时用 `code`）与用户 ID 的确定性 UUID（MD5 派生），同一用户多次调用返回相同值。

---

### Requirement: REQ-SCIM-003 修改账号

按 URL 中的 externalId 修改用户信息。

**实现位置**: `app/controller/Scim.php` → `updateAccount()`
**路由**: `PUT /api/scim/v2/accounts/:externalId`（中间件：`VerifySignature` + `ValidateParams`；路由变量正则放宽为 `[^/]+` 以兼容含连字符的 externalId）
**验证器场景**: `Scim::sceneUpdateAccount()` — 与创建相同

**请求参数**：与创建接口相同。

#### Scenario: 修改成功
- Given `external_id` 对应的未删除用户存在，且 Body 中 `externalId` 与 URL 一致
- Then 更新用户信息，返回成功响应，`message: "修改账号成功"`、`title: "修改账号"`

#### Scenario: Body 与 URL 的 externalId 不一致
- Then 返回 400 `{ success: false, code: 10001, message: "Body 中的 externalId 与 URL 不一致", title: "修改账号" }`

#### Scenario: 账号不存在
- Then 返回 404 `{ success: false, code: 30011, message: "账号不存在", title: "修改账号" }`

---

### Requirement: REQ-SCIM-004 删除账号（软删除）

按 URL 中的 externalId 软删除用户。

**实现位置**: `app/controller/Scim.php` → `deleteAccount()`
**路由**: `DELETE /api/scim/v2/accounts/:externalId`（中间件：仅 `VerifySignature`）

**请求参数**：仅 URL 中的 `externalId`，无 Body 参数。DELETE 路由不挂载 `ValidateParams`（ThinkPHP `only([])` 不等于"不校验"，空场景无法表达，故在路由层跳过参数校验）。

#### Scenario: 删除成功
- Given `external_id` 对应的未删除用户存在
- Then 将 `user.is_del` 置为 1（软删除），记录审计日志
- And 返回成功响应，`message: "删除账号成功"`、`title: "删除账号"`

#### Scenario: 账号不存在（含重复删除）
- Then 返回 404 `{ success: false, code: 30011, message: "账号不存在", title: "删除账号" }`

---

### Requirement: REQ-SCIM-005 错误响应格式

SCIM 端点错误响应与成功响应同构（区别于 OAuth 端点的 `{ error }` 格式）：

```json
{
  "success": false,
  "code": 30011,
  "message": "账号不存在",
  "title": "删除账号",
  "externalId": "",
  "data": null
}
```

签名验证失败由中间件返回 OAuth 风格错误：HTTP 403 `{ error: "invalid_signature", error_description: "..." }`。

#### Scenario: 业务错误与签名错误响应格式区分
- Given SCIM 控制器内发生业务错误（如账号不存在）
- When 返回错误响应
- Then 响应为 `{ success, code, message, title, externalId, data }` 结构并携带对应 HTTP 状态码
- Given 签名验证失败
- When 中间件拦截请求
- Then 响应为 HTTP 403 `{ error: "invalid_signature", error_description: "..." }`

---

### Requirement: 创建用户账号（upsert 语义）

系统 SHALL 提供 `POST /api/scim/v2/accounts` 创建用户。不存在则创建，存在则更新（upsert 语义）。认证方式为 SM3 签名验证（Header 中 `X-trust-signature`）。

**请求参数（Body）**：

| 参数 | 类型 | 必选 | 说明 |
|------|------|------|------|
| `code` | String | 是 | 账号标识，唯一（如 `zhangsan`） |
| `name` | String | 是 | 账号名称 |
| `externalId` | String | 是 | 外部 ID |
| `mobile` | String | 否 | 手机号 |
| `email` | String | 否 | 邮箱 |
| `status` | Boolean | 是 | 用户状态（启用/禁用） |

**请求 Header**：

| Header | 说明 |
|--------|------|
| `Content-MD5-HEX` | Body 内容 MD5 |
| `Content-Type` | `application/json;charset=UTF-8` |
| `X-trust-appid` | 对接系统 client_id |
| `X-trust-nonce` | 挑战随机数 |
| `X-trust-signature-version` | 固定 `2.0` |
| `X-trust-timestamp` | 时间戳 |
| `X-trust-signature` | SM3 签名值 |
| `X-api-version` | 固定 `1.0-rev0` |

**响应**：
```json
{
  "success": true,
  "code": 200,
  "message": "创建账号成功",
  "title": "创建账号",
  "externalId": "SuperAdmin",
  "data": {
    "accountId": "12dcab39-69db-449a-82ea-ad8cfce27309"
  }
}
```

#### Scenario: 创建新用户
- GIVEN externalId 与 code 对应的用户均不存在
- WHEN 提交合法签名的创建请求
- THEN 创建用户，返回 `创建账号成功` 与 accountId

#### Scenario: 用户已存在（upsert 更新）
- GIVEN externalId 或 code 对应的用户已存在
- WHEN 提交合法签名的创建请求
- THEN 更新该用户，返回成功与 accountId

### Requirement: 修改用户账号

系统 SHALL 提供 `PUT /api/scim/v2/accounts/{externalId}` 修改指定 externalId 的用户信息。请求参数与创建接口相同，Body 中 `externalId` 须与 URL 中一致。

**响应**：
```json
{
  "success": true,
  "code": 200,
  "message": "修改账号成功",
  "title": "修改账号",
  "externalId": "SuperAdmin",
  "data": {
    "accountId": "12dcab39-69db-449a-82ea-ad8cfce27309"
  }
}
```

#### Scenario: 修改成功
- GIVEN externalId 对应的用户存在，Body externalId 与 URL 一致
- WHEN 提交合法签名的修改请求
- THEN 更新用户，返回 `修改账号成功`

#### Scenario: externalId 不一致
- GIVEN Body 中 externalId 与 URL 不一致
- WHEN 提交修改请求
- THEN 返回 400 错误

#### Scenario: 用户不存在
- GIVEN externalId 对应的用户不存在
- WHEN 提交修改请求
- THEN 返回 404 `账号不存在`

### Requirement: 删除用户账号（软删除）

系统 SHALL 提供 `DELETE /api/scim/v2/accounts/{externalId}` 软删除指定 externalId 的用户。仅 URL 中的 `{externalId}`，无 Body 参数。

**响应**：
```json
{
  "success": true,
  "code": 200,
  "message": "删除账号成功",
  "title": "删除账号",
  "externalId": "SuperAdmin",
  "data": {
    "accountId": "12dcab39-69db-449a-82ea-ad8cfce27309"
  }
}
```

#### Scenario: 删除成功
- GIVEN externalId 对应的用户存在
- WHEN 提交合法签名的删除请求
- THEN 软删除用户（is_del=1），返回 `删除账号成功`

#### Scenario: 用户不存在
- GIVEN externalId 对应的用户不存在或已被删除
- WHEN 提交删除请求
- THEN 返回 404 `账号不存在`

### Requirement: SM3 签名验证

所有 SCIM 请求 SHALL 通过签名验证，验证流程：

1. 提取 Header 中的 7 个字段：`content-type`、`x-trust-signature-version`、`x-trust-appid`、`x-trust-timestamp`、`x-trust-nonce`、`content-md5-hex`、`method`
2. 字段名转小写，字段值去前后空格，格式化为 `key=value`
3. 按 key 字母排序，用 `&` 连接得到 V
4. 使用应用的 `client_secret` 作为 K，计算 `SM3(K, V)`
5. 比对结果与 `X-trust-signature` Header 值
6. 验证 `Content-MD5-HEX`：POST/PUT 为 Body 的 MD5（32 位小写），GET/DELETE 为 URL path+query 的 MD5

**签名错误响应**：HTTP 403，返回 `{ error: "invalid_signature", error_description: "..." }`

#### Scenario: 正确签名通过
- GIVEN 请求携带正确的 SM3 签名与时间戳、未使用的 nonce
- WHEN 请求任一 SCIM 端点
- THEN 签名验证通过，进入业务逻辑

#### Scenario: 错误签名拒绝
- GIVEN 请求签名值错误
- WHEN 请求任一 SCIM 端点
- THEN 返回 403 `invalid_signature`

#### Scenario: 重放攻击拒绝
- GIVEN 请求 nonce 已被使用，或时间戳超出允许窗口
- WHEN 请求任一 SCIM 端点
- THEN 返回 403 `invalid_signature`

### Requirement: sc_user 表扩展字段

SCIM 接口操作 `sc_user` 表，系统 SHALL 确保以下字段存在：

| 字段 | 类型 | 说明 |
|------|------|------|
| `code` | VARCHAR(64) | 账号标识，唯一 |
| `external_id` | VARCHAR(128) | 外部 ID |
| `mobile` | VARCHAR(32) | 手机号 |
| `email` | VARCHAR(128) | 邮箱 |
| `status` | TINYINT(1) | 启用/禁用 |

如现有 `sc_user` 表缺少这些字段，需通过迁移脚本补充。

#### Scenario: 扩展字段可用
- GIVEN 迁移脚本已执行
- WHEN SCIM 接口读写用户
- THEN code、external_id、mobile 等字段可正常读写

## 数据库表

（前缀 `sc_`，迁移脚本：`extend/migrations/oauth2_standardize.sql`）

### sc_user 表扩展字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `code` | VARCHAR(64) | 账号标识，唯一 |
| `external_id` | VARCHAR(128) | 外部 ID |
| `mobile` | VARCHAR(32) | 手机号 |

（`email`、`status` 为原有字段。）

### sc_scim_nonce — 防重放 Nonce 表

| 字段 | 类型 | 说明 |
|------|------|------|
| `nonce` | VARCHAR(128) UNIQUE | 已使用的随机数 |
| `created_at` | DATETIME | 写入时间 |

---

## 相关路由

| 路由 | 方法 | 控制器/方法 | 中间件 | 说明 |
|------|------|-------------|--------|------|
| `/api/scim/v2/accounts` | POST | `Scim/createAccount` | `VerifySignature` + `ValidateParams` | 创建账号（upsert） |
| `/api/scim/v2/accounts/:externalId` | PUT | `Scim/updateAccount` | `VerifySignature` + `ValidateParams` | 修改账号 |
| `/api/scim/v2/accounts/:externalId` | DELETE | `Scim/deleteAccount` | `VerifySignature` | 删除账号（软删除） |
