## Purpose

OAuth 2.0 refresh token 机制，允许第三方应用在 access token 过期后使用 refresh token 获取新的 access token，无需用户重新授权。

## ADDED Requirements

### Requirement: Refresh Token 端点

系统 SHALL 提供 `POST /oauth/token` 端点支持 `grant_type=refresh_token`，用于刷新 access token。

**请求参数**：
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `grant_type` | String | 是 | 固定值 `refresh_token` |
| `refresh_token` | String | 是 | 刷新令牌 |

**认证方式**：HTTP Basic Auth（`client_id:client_secret`）

**响应格式**：
```json
{
  "access_token": "新的 access token",
  "refresh_token": "新的 refresh token",
  "token_type": "Bearer",
  "expires_in": 28800,
  "scope": "trust"
}
```

#### Scenario: 刷新成功
- **WHEN** 第三方应用使用有效的 refresh_token 调用 `POST /oauth/token`，`grant_type=refresh_token`
- **THEN** 系统返回新的 access_token 和 refresh_token
- **AND** 旧 refresh_token 被撤销
- **AND** 旧 access_token 被撤销

#### Scenario: refresh_token 无效
- **WHEN** 第三方应用使用已过期、已撤销或不存在的 refresh_token 调用接口
- **THEN** 系统返回 HTTP 400，`error=invalid_grant`，`error_description=refresh_token 无效或已过期`

#### Scenario: 客户端认证失败
- **WHEN** 第三方应用未提供 Basic Auth 或 client_id/client_secret 不匹配
- **THEN** 系统返回 HTTP 401，`error=invalid_client`

#### Scenario: refresh_token 不属于该客户端
- **WHEN** 第三方应用使用的 refresh_token 属于其他客户端
- **THEN** 系统返回 HTTP 400，`error=invalid_grant`

### Requirement: Token 轮换（Rotation）

系统 SHALL 在刷新时执行 token 轮换：旧的 refresh_token 和关联的 access_token 同时失效，返回新的 token 对。

#### Scenario: Token 轮换生效
- **WHEN** 刷新成功后，尝试使用旧 refresh_token 再次刷新
- **THEN** 系统返回 `error=invalid_grant`，拒绝刷新

#### Scenario: 旧 access_token 失效
- **WHEN** 刷新成功后，尝试使用旧 access_token 调用 `/oauth/userinfo`
- **THEN** 系统返回 `error=invalid_token`，拒绝访问

### Requirement: 刷新权限校验

系统 SHALL 验证 refresh_token 与客户端的归属关系，确保只能刷新自己颁发的 token。

#### Scenario: 客户端匹配
- **WHEN** 客户端 A 使用自己获得的 refresh_token 刷新
- **THEN** 刷新成功

#### Scenario: 客户端不匹配
- **WHEN** 客户端 A 使用客户端 B 获得的 refresh_token 刷新
- **THEN** 系统返回 `error=invalid_grant`

### Requirement: Scope 保持不变

系统 SHALL 在刷新时保持原有的 scope，不允许通过刷新扩大权限范围。

#### Scenario: Scope 继承
- **WHEN** 原始 token 的 scope 为 `trust`
- **THEN** 刷新后的新 token scope 仍为 `trust`
