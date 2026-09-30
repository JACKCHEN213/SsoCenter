## Context

OAuth 2.0 refresh token 流程已在后端实现：
- **路由**：`POST /oauth/token`（`route/oauth.php` line 13）
- **中间件**：`OAuthClientAuth`（HTTP Basic Auth 认证）
- **控制器**：`OAuth::token()` → `handleRefreshTokenGrant()`（`app/controller/OAuth.php` line 571-586）
- **服务层**：`TokenService::refreshTokens()`（`app/service/TokenService.php` line 197-256）

现有实现逻辑：
1. 验证 refresh_token 有效性（未过期、未撤销、属于该客户端）
2. 撤销旧 refresh_token 和关联的 access_token
3. 生成新的 access_token 和 refresh_token
4. 返回新 token 对

## Goals / Non-Goals

**Goals:**
- 验证现有 refresh token 流程是否正常工作
- 文档化完整的 API 使用指南（请求参数、响应格式、错误处理）
- 提供第三方应用调用示例（curl 命令、代码片段）
- 如发现 bug 则修复

**Non-Goals:**
- 不改变现有 refresh token 的核心逻辑（除非发现严重 bug）
- 不实现新的 grant type
- 不修改 token 存储结构

## Decisions

### 决策 1：验证优先，文档为辅

**选择**：先验证现有实现是否正常工作，再基于验证结果编写文档。

**理由**：
- 现有代码已实现，需要确认是否存在 bug
- 文档应基于实际行为，而非假设

**替代方案**：
- 直接编写文档而不验证 → 可能文档化错误行为

### 决策 2：使用现有端点，不新增接口

**选择**：继续使用 `POST /oauth/token` + `grant_type=refresh_token`，不新增独立的 `/oauth/refresh` 端点。

**理由**：
- 符合 OAuth 2.0 规范（RFC 6749 Section 6）
- 现有实现已支持
- 减少维护成本

**替代方案**：
- 新增 `/oauth/refresh` 端点 → 违反 OAuth 2.0 规范，增加复杂性

### 决策 3：Token 轮换策略

**选择**：刷新时撤销旧 token 对，返回新 token 对（Rotation）。

**理由**：
- 现有实现已采用此策略
- 提高安全性（旧 token 失效，防止重放攻击）
- 符合 OAuth 2.0 安全最佳实践

**替代方案**：
- 保留旧 refresh_token 可多次使用 → 安全风险高

## Risks / Trade-offs

**风险 1：现有实现可能存在 bug**
→ 缓解：通过系统性测试验证每个场景（成功刷新、无效 token、客户端不匹配等）

**风险 2：第三方应用未正确实现刷新逻辑**
→ 缓解：提供详细的调用示例和错误处理指南

**风险 3：Token 轮换导致并发请求失败**
→ 缓解：文档中说明刷新时应暂停其他请求，等待新 token 返回后再继续

**Trade-off：Token 轮换 vs 保留旧 token**
- Token 轮换提高安全性，但增加复杂性（需要处理并发刷新）
- 选择安全性优先，符合 OAuth 2.0 最佳实践

## 验证计划

### 测试场景

1. **成功刷新**
   - 使用有效 refresh_token 调用接口
   - 验证返回新 token 对
   - 验证旧 token 被撤销

2. **无效 refresh_token**
   - 使用过期/已撤销/不存在的 refresh_token
   - 验证返回 `error=invalid_grant`

3. **客户端认证失败**
   - 不提供 Basic Auth 或使用错误的 client_id/client_secret
   - 验证返回 `error=invalid_client`

4. **客户端不匹配**
   - 使用其他客户端的 refresh_token
   - 验证返回 `error=invalid_grant`

5. **Token 轮换验证**
   - 刷新后使用旧 refresh_token 再次刷新
   - 验证返回 `error=invalid_grant`

### 调用示例

提供以下示例：
- curl 命令
- PHP (Guzzle) 代码
- JavaScript (axios) 代码
- Python (requests) 代码
