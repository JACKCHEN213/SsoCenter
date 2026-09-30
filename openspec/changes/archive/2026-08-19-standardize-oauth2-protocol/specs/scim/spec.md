# SCIM 用户同步模块规范（增量变更）

## ADDED Requirements

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
