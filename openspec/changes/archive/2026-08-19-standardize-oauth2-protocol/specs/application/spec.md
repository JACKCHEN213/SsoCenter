# 应用管理模块规范（增量变更）

## ADDED Requirements

### Requirement: 应用注册自动生成 OAuth 凭证

注册应用时系统 SHALL 自动生成 `client_id`（32 位随机字符串）和 `client_secret`（64 位随机字符串），并在响应中返回给前端展示。

**修改后响应**：
```json
{
  "code": 0,
  "success": true,
  "message": "成功",
  "result": {
    "id": 1,
    "client_id": "ab3sd...",
    "client_secret": "xY9kL..."
  }
}
```

#### Scenario: 注册新应用返回 OAuth 凭证
- GIVEN 提交不重复的应用信息
- WHEN 发送 `POST /app/add`
- THEN 创建应用并自动生成 client_id/client_secret，响应 result 中包含 id、client_id、client_secret

#### Scenario: client_id 唯一性
- GIVEN 生成的 client_id 与现有应用冲突
- WHEN 生成凭证
- THEN 重新生成直至唯一

### Requirement: 应用 OAuth 配置管理

系统 SHALL 支持在应用更新时配置以下 OAuth 相关参数（未传入则不修改）：

| 字段 | 说明 | 默认值 |
|------|------|--------|
| `allowed_grant_types` | 允许的授权模式（逗号分隔） | `authorization_code` |
| `scope` | 默认授权范围 | `trust` |
| `access_token_ttl` | Access Token 有效期（秒） | `28800`（8 小时） |
| `refresh_token_ttl` | Refresh Token 有效期（秒） | `86400`（24 小时） |
| `code_ttl` | 授权码有效期（秒） | `600`（10 分钟） |

#### Scenario: 更新应用 OAuth 配置
- GIVEN 应用存在
- WHEN 发送 `PUT /app/update` 携带 allowed_grant_types / access_token_ttl 等字段
- THEN 对应配置更新成功

### Requirement: 应用删除级联清理 OAuth 记录

删除应用时，除原有公钥文件清理外，系统 SHALL 额外清理：
- `oauth_authorization_codes` 中该应用的所有授权码
- `oauth_access_tokens` 中该应用的所有 Access Token 记录
- `oauth_refresh_tokens` 中该应用的所有 Refresh Token 记录

#### Scenario: 删除应用级联清理
- GIVEN 应用存在且持有授权码与 Token 记录
- WHEN 发送 `DELETE /app/delete`
- THEN 应用软删除，关联的授权码被删除、access/refresh token 被撤销

### Requirement: sc_site 表新增 OAuth 字段

`sc_site` 表 SHALL 新增以下字段（存量记录通过数据迁移脚本补齐 client_id/client_secret）：

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `client_id` | VARCHAR(64) | NULL → 自动生成 | OAuth 客户端标识（UNIQUE） |
| `client_secret` | VARCHAR(128) | NULL → 自动生成 | OAuth 客户端密钥 |
| `allowed_grant_types` | VARCHAR(255) | `'authorization_code'` | 允许的授权模式 |
| `scope` | VARCHAR(255) | `'trust'` | 默认授权范围 |
| `access_token_ttl` | INT | `28800` | Access Token 有效期 |
| `refresh_token_ttl` | INT | `86400` | Refresh Token 有效期 |
| `code_ttl` | INT | `600` | 授权码有效期 |

#### Scenario: 迁移后字段可用
- GIVEN 迁移脚本已执行
- WHEN 应用注册/更新/删除
- THEN 新字段可正常读写，存量应用已补齐 client_id/client_secret
