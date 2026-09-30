## Why

OAuth 2.0 refresh token 刷新流程虽然已在后端实现（`POST /oauth/token`, `grant_type=refresh_token`），但缺乏正式文档和使用指南，第三方应用无法正确调用此接口。需要验证现有实现是否正常工作，文档化完整流程，并在发现 bug 时修复。

## What Changes

- **文档化 refresh token 刷新流程**：创建完整的 API 文档，包括请求参数、响应格式、错误处理、调用示例
- **验证现有实现**：测试 `POST /oauth/token` 的 `grant_type=refresh_token` 模式是否正常工作
- **修复潜在问题**：如发现 bug（如 token 未正确撤销、新 token 未正确生成等），进行修复
- **提供调用示例**：为第三方应用提供 curl 命令示例和代码片段

## Capabilities

### New Capabilities
- `oauth-refresh-token`: OAuth 2.0 refresh token 刷新 access token 的完整流程规范，包括接口定义、参数校验、token 轮换逻辑、错误处理

### Modified Capabilities

（无需修改现有规范，refresh token 流程已在 `archive/2026-08-19-standardize-oauth2-protocol/specs/oauth/spec.md` 中定义，本次仅验证和文档化）

## Impact

- **API 端点**：`POST /oauth/token`（已存在，需验证）
- **服务层**：`TokenService::refreshTokens()`（已存在，需验证）
- **数据库**：`sc_oauth_access_tokens`、`sc_oauth_refresh_tokens` 表
- **第三方应用**：需要按照文档正确调用刷新接口
- **文档**：新增 API 使用指南和调用示例
