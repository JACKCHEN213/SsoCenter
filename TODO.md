# TODO - 项目改进建议与待办事项

> 本文档汇总了 `standardize-oauth2-protocol` 开发过程中提出的改进建议和未来扩展方向。

---

## 一、安全增强

### 1.1 PKCE 支持（RFC 7636）
**优先级**: 高  
**说明**: 为授权码模式添加 PKCE（Proof Key for Code Exchange）支持，防止授权码拦截攻击，特别适用于移动应用和单页应用等公共客户端。

**实施要点**:
- `authorize` 端点接受 `code_challenge` 和 `code_challenge_method`（S256/Plain）
- `oauth_authorization_codes` 表新增 `code_challenge` 和 `code_challenge_method` 字段
- `token` 端点（`grant_type=authorization_code`）验证 `code_verifier`
- 公共客户端可免 `client_secret`，仅凭 PKCE 换取 token

**参考**: [RFC 7636 - Proof Key for Code Exchange](https://tools.ietf.org/html/rfc7636)

---

### 1.2 Token 绑定 / 发送方约束令牌
**优先级**: 中  
**说明**: 将 Access Token 与特定客户端或设备绑定，防止令牌被盗用后在其他环境使用。

**实施方案**:
- **方案 A**: DPoP（Demonstration of Proof-of-Possession，RFC 9449）— 客户端在请求中携带 DPoP 证明
- **方案 B**: 在 JWT payload 中嵌入 `client_ip` 或 `device_fingerprint`，服务端验证时比对
- **方案 C**: 证书绑定（mTLS）— 适用于高安全场景

---

### 1.3 速率限制
**优先级**: 高  
**说明**: 对敏感端点实施速率限制，防止暴力破解和滥用。

**实施要点**:
- Token 端点（`grant_type=password`）：限制失败次数（如 5 次/分钟/IP）
- 授权码生成：限制单用户短时间内多次授权
- 签名验证失败：限制连续失败次数后临时封禁
- 使用 Redis 或数据库计数器实现滑动窗口算法

---

### 1.4 审计日志增强
**优先级**: 中  
**说明**: 当前审计日志覆盖不足，建议增强关键操作的日志记录。

**需要记录的操作**:
- 所有 Token 颁发事件（包含 client_id、user_id、grant_type、IP、UA）
- Token 撤销事件
- 授权码生成和消费
- 签名验证失败（用于安全监控）
- 异常登录模式（如短时间内多次失败）

**日志字段建议**:
```json
{
  "event": "token_issued",
  "client_id": "...",
  "user_id": 1,
  "grant_type": "authorization_code",
  "ip": "192.168.1.1",
  "user_agent": "...",
  "timestamp": "2026-08-20T10:30:00Z"
}
```

---

### 1.5 Refresh Token 轮换状态追踪
**优先级**: 中  
**说明**: 当前实现中，刷新 Token 时旧 refresh_token 失效，但未追踪其使用历史。建议增加轮换状态检测，防止重放攻击。

**实施要点**:
- `oauth_refresh_tokens` 表新增 `replaced_by` 字段，记录轮换链
- 检测到已使用的 refresh_token 再次提交时，撤销该用户的所有 token（安全策略）
- 记录轮换历史用于安全审计

---

### 1.6 Device Binding / Session Management
**优先级**: 低  
**说明**: 支持设备级会话管理，允许用户查看和撤销特定设备的登录状态。

**实施要点**:
- 登录时记录设备信息（IP、UA、设备指纹）
- 提供 `/sessions` 端点查看活跃会话
- 支持单设备登出（不影响其他设备）

---

## 二、功能扩展

### 2.1 Token 撤销端点（RFC 7009）
**优先级**: 高  
**说明**: 实现标准的 Token 撤销端点，允许客户端主动撤销 access_token 或 refresh_token。

**端点**: `POST /oauth/revoke`

**请求参数**:
| 参数 | 必选 | 说明 |
|------|------|------|
| `token` | 是 | 要撤销的 token（access_token 或 refresh_token） |
| `token_type_hint` | 否 | `access_token` 或 `refresh_token`（提示服务端优先查找） |

**响应**: 200 OK（无论 token 是否存在，均返回成功，防止信息泄露）

**认证**: HTTP Basic Auth（client_id:client_secret）

**参考**: [RFC 7009 - OAuth 2.0 Token Revocation](https://tools.ietf.org/html/rfc7009)

---

### 2.2 Token 内省端点（RFC 7662）
**优先级**: 中  
**说明**: 实现 Token 内省端点，允许资源服务器验证 token 的有效性并获取其元数据。

**端点**: `POST /oauth/introspect`

**请求参数**:
| 参数 | 必选 | 说明 |
|------|------|------|
| `token` | 是 | 要内省的 token |
| `token_type_hint` | 否 | `access_token` 或 `refresh_token` |

**响应**:
```json
{
  "active": true,
  "scope": "trust",
  "client_id": "...",
  "username": "admin",
  "token_type": "Bearer",
  "exp": 1585567055,
  "iat": 1585538255,
  "sub": "1",
  "aud": ["..."],
  "iss": "https://sso.example.com"
}
```

**认证**: HTTP Basic Auth

**参考**: [RFC 7662 - OAuth 2.0 Token Introspection](https://tools.ietf.org/html/rfc7662)

---

### 2.3 动态客户端注册（RFC 7591）
**优先级**: 低  
**说明**: 允许第三方应用通过 API 自动注册，无需管理员手动创建。

**端点**: `POST /oauth/register`

**请求参数**（JSON）:
```json
{
  "redirect_uris": ["https://app.example.com/callback"],
  "client_name": "Example App",
  "grant_types": ["authorization_code"],
  "response_types": ["code"],
  "token_endpoint_auth_method": "client_secret_basic"
}
```

**响应**: 返回 `client_id`、`client_secret`、`client_id_issued_at`、`client_secret_expires_at`

**安全措施**:
- 需要管理员预授权（registration token）
- 限制 redirect_uri 域名白名单
- 记录注册事件供审计

**参考**: [RFC 7591 - OAuth 2.0 Dynamic Client Registration Protocol](https://tools.ietf.org/html/rfc7591)

---

### 2.4 OpenID Connect 扩展
**优先级**: 中  
**说明**: 在 OAuth 2.0 基础上实现 OpenID Connect（OIDC）核心功能，支持身份认证。

**新增端点**:
- `GET /.well-known/openid-configuration` — 发现端点
- `GET /jwks` — JSON Web Key Set 端点（暴露公钥）
- `GET /userinfo` — 标准 OIDC 用户信息端点（返回 JSON，非 ID Token）

**新增 Claims**:
- `preferred_username`、`profile`、`picture`、`website`、`gender`、`birthdate`、`zoneinfo`、`locale`

**参考**: [OpenID Connect Core 1.0](https://openid.net/specs/openid-connect-core-1_0.html)

---

### 2.5 单点登出（Single Logout）
**优先级**: 中  
**说明**: 当前实现中，用户在一个应用登出后，其他应用的会话仍保持。建议实现全局单点登出。

**实施方案**:
- 用户登出时，遍历该用户所有活跃会话，向关联应用发送登出通知
- 应用端实现 `/logout/callback` 接收登出通知
- 前端通过 WebSocket 或轮询检测登出事件

**当前状态**: `OAuth::logout()` 已有部分实现（遍历关联应用发送通知），但为同步阻塞调用，建议改为异步队列。

---

### 2.6 同意管理 UI
**优先级**: 低  
**说明**: 提供用户可管理的授权同意列表，允许用户查看和撤销已授权的应用。

**功能**:
- 用户可查看已授权的应用列表
- 显示每个应用的授权时间、最后使用时间、权限范围
- 支持撤销单个应用的授权（撤销该应用下所有 token）

**实现要点**:
- 新增 `oauth_user_consent` 表记录用户同意历史
- 提供 `/user/consents` 端点管理同意
- 前端 UI 在用户中心展示

---

## 三、运维与监控

### 3.1 过期 Token 清理任务
**优先级**: 高  
**说明**: 当前数据库中过期的 token 和授权码未被自动清理，会导致表膨胀。

**实施要点**:
- 编写 cron 脚本（每日执行）：
  ```sql
  DELETE FROM oauth_authorization_codes WHERE expires_at < NOW();
  DELETE FROM oauth_access_tokens WHERE expires_at < NOW() AND revoked = 1;
  DELETE FROM oauth_refresh_tokens WHERE expires_at < NOW() AND revoked = 1;
  ```
- 或使用事件调度器（MySQL Event Scheduler）
- 保留最近 30 天的已撤销记录用于审计

---

### 3.2 监控与告警
**优先级**: 中  
**说明**: 添加关键指标的监控和告警，及时发现异常。

**监控指标**:
- Token 颁发速率（按 client_id、grant_type 分组）
- 授权码消费成功率
- 签名验证失败率
- Token 刷新频率
- 异常登录模式（如短时间内多次失败）

**告警阈值示例**:
- 同一 client_id 1 分钟内失败超过 100 次
- 同一 IP 5 分钟内授权码生成超过 50 次
- Token 刷新频率异常（如 1 秒内多次刷新）

**实施建议**:
- 将审计日志接入 ELK 或 Prometheus + Grafana
- 使用 `recordLog()` 统一记录，便于日志采集

---

### 3.3 数据库索引优化
**优先级**: 低  
**说明**: 随着 token 数量增长，查询性能可能下降。建议定期分析慢查询并优化索引。

**当前索引**:
- `oauth_access_tokens.access_token`（UNIQUE）
- `oauth_refresh_tokens.refresh_token`（UNIQUE）
- `oauth_authorization_codes.code`（UNIQUE）

**建议新增**:
- `oauth_access_tokens` 复合索引：`(client_id, expires_at)` — 用于批量查询某应用的活跃 token
- `oauth_refresh_tokens` 复合索引：`(user_id, client_id)` — 用于查询用户在某应用的所有 refresh_token
- `oauth_access_tokens` 索引：`(revoked, expires_at)` — 用于清理任务

---

## 四、代码质量

### 4.1 单元测试
**优先级**: 高  
**说明**: 当前缺少自动化测试，建议为核心服务层添加单元测试。

**测试覆盖目标**:
- `TokenService` — Token 生成、验证、撤销、刷新
- `AuthorizationCodeService` — 授权码生成、消费、过期
- `SignatureService` — SM3 签名计算与验证
- `IDTokenService` — ID Token 生成与 claims 验证

**测试框架建议**:
- PHPUnit（PHP 标准测试框架）
- 使用 mock 隔离数据库依赖
- 覆盖率目标：80%+

**测试文件位置**: `tests/Unit/Service/`

---

### 4.2 集成测试
**优先级**: 中  
**说明**: 为完整的 OAuth 流程编写集成测试，确保端到端功能正确。

**测试场景**:
- 授权码模式完整流程：authorize → code → token → userinfo → logout
- 隐式模式流程：authorize → token
- 密码模式流程：token(password) → userinfo
- Token 刷新流程：refresh_token
- 错误场景：过期 token、无效签名、重复使用授权码

**测试框架**:
- PHPUnit + HTTP 客户端（Guzzle）
- 使用测试数据库（独立于生产）
- 每个测试用例独立事务，测试后回滚

---

### 4.3 业务逻辑提取
**优先级**: 低  
**说明**: 部分控制器中仍包含业务逻辑（如 `OAuth::authorize()` 中的登录态检测），建议进一步提取到服务层。

**待提取逻辑**:
- `OAuth::getLoggedInUserId()` → `AuthService::getCurrentUser()`
- `OAuth::authorizePage()` 中的参数校验 → `OAuthValidationService`
- `Scim::createAccount()` 中的用户创建逻辑 → `UserService::createOrUpdate()`

**好处**:
- 控制器职责单一（仅负责 HTTP 请求/响应）
- 业务逻辑可复用、可测试
- 便于后续扩展（如添加新的认证方式）

---

## 五、文档与规范

### 5.1 API 文档（OpenAPI/Swagger）
**优先级**: 中  
**说明**: 为所有 OAuth 和 SCIM 端点生成标准 API 文档，便于第三方应用对接。

**实施要点**:
- 使用 OpenAPI 3.0 规范编写 `openapi.yaml`
- 描述每个端点的请求参数、响应格式、错误码
- 提供示例请求和响应
- 可托管为静态 HTML 或使用 Swagger UI

**示例端点描述**:
```yaml
/oauth/token:
  post:
    summary: 获取/刷新 Access Token
    description: 支持授权码、隐式、密码、客户端凭证四种授权模式
    security:
      - basicAuth: []
    requestBody:
      content:
        application/x-www-form-urlencoded:
          schema:
            type: object
            required:
              - grant_type
            properties:
              grant_type:
                type: string
                enum: [authorization_code, refresh_token, password, client_credentials]
    responses:
      '200':
        description: Token 颁发成功
```

---

### 5.2 第三方应用对接指南
**优先级**: 中  
**说明**: 编写面向第三方开发者的对接文档，说明如何接入 SSO 认证。

**文档内容**:
1. 快速开始（5 分钟完成接入）
2. 授权码模式对接流程（含代码示例）
3. 隐式模式对接流程
4. Token 刷新机制
5. 用户信息获取
6. 错误码说明
7. 常见问题（FAQ）

**格式**: Markdown 或 HTML，托管在 `/docs` 路径

---

### 5.3 安全最佳实践
**优先级**: 低  
**说明**: 为第三方应用开发者提供安全建议，帮助他们正确使用 OAuth 2.0。

**建议内容**:
- 如何安全存储 `client_secret`
- 为什么推荐使用授权码模式而非隐式模式
- PKCE 的使用场景
- 如何防范 CSRF（使用 `state` 参数）
- 如何处理 Token 过期
- 如何安全调用 `/userinfo` 端点

---

## 六、性能优化

### 6.1 JWT 验证缓存
**优先级**: 中  
**说明**: 频繁调用 `/userinfo` 端点时，每次都需验证 JWT 签名并查询数据库。建议增加缓存层。

**实施方案**:
- 使用 Redis 缓存已验证的 token 元数据（key: token_hash, value: payload）
- 缓存过期时间：token 有效期 / 2
- 撤销 token 时同步删除缓存

**性能提升**:
- 减少 JWT 签名验证次数（CPU 密集型操作）
- 减少数据库查询（检查 token 是否撤销）

---

### 6.2 数据库连接池
**优先级**: 低  
**说明**: 高并发场景下，频繁创建/销毁数据库连接会成为瓶颈。

**实施方案**:
- 使用 Swoole 或 Workerman 实现常驻内存的 PHP 进程
- 配置数据库连接池（如 Swoole\Database\PDOProxy）
- 或部署 PHP-FPM + 持久连接（`PDO::ATTR_PERSISTENT => true`）

**注意事项**:
- 需评估是否值得引入常驻进程架构（复杂度增加）
- 当前 ThinkPHP 架构为每次请求独立进程，改造成本较高

---

### 6.3 异步通知
**优先级**: 低  
**说明**: 当前 `OAuth::logout()` 中遍历关联应用发送登出通知为同步阻塞调用，可能影响响应时间。

**实施方案**:
- 使用消息队列（Redis/Beanstalkd/RabbitMQ）异步发送登出通知
- 控制器仅负责将任务入队，立即返回
- 后台 worker 消费队列，执行 HTTP 请求

**好处**:
- 减少用户请求延迟
- 提高系统吞吐量
- 失败重试机制

---

## 七、已知问题与待修复

### 7.1 `client_secret` 明文存储
**优先级**: 高  
**说明**: 当前 `sc_site.client_secret` 以明文存储，`OAuthClientAuth` 中间件使用 `!==` 直接比对。

**风险**:
- 数据库泄露时，所有应用的 secret 暴露
- 不符合安全最佳实践

**修复方案**:
- 存储 `client_secret` 的哈希值（如 bcrypt 或 SHA-256）
- `OAuthClientAuth` 中间件改为哈希比对
- **注意**: 需要数据迁移脚本，且会影响现有应用的 secret（需重新生成）

**替代方案**:
- 使用加密存储（AES），服务端解密后比对
- 保持明文，但加强数据库访问控制

---

### 7.2 `redirect_uri` 严格匹配
**优先级**: 中  
**说明**: 当前实现要求 `redirect_uri` 与注册值完全一致，不支持子路径或通配符。

**问题**:
- 第三方应用可能需要多个回调地址
- 环境切换（开发/测试/生产）时需修改注册信息

**改进方案**:
- 支持注册多个 `redirect_uri`（逗号分隔或 JSON 数组）
- 或使用通配符匹配（如 `https://*.example.com/callback`）

**安全注意**:
- 通配符需严格限制，防止开放重定向攻击
- 建议使用白名单机制

---

### 7.3 密码模式安全性
**优先级**: 低  
**说明**: 密码模式（`grant_type=password`）要求用户将密码直接提供给第三方应用，存在安全风险。

**问题**:
- 第三方应用可能滥用用户密码
- 不符合 OAuth 2.1 草案（已废弃密码模式）

**建议**:
- 默认禁用密码模式，需管理员显式启用
- 文档中明确说明密码模式的安全风险
- 推荐优先使用授权码模式

---

## 八、合规性

### 8.1 GDPR / 个人信息保护
**优先级**: 中  
**说明**: 如果系统面向欧盟用户，需符合 GDPR 要求；如果面向中国用户，需符合《个人信息保护法》。

**实施要点**:
- 用户有权查看和导出个人数据
- 用户有权删除账号（"被遗忘权"）
- 提供隐私政策和使用条款模板
- 记录数据处理的法律依据

**实施建议**:
- 提供 `/user/export` 端点导出个人数据
- 提供 `/user/delete` 端点永久删除账号
- 在授权页面展示隐私政策链接

---

### 8.2 OAuth 2.1 合规
**优先级**: 低  
**说明**: OAuth 2.1 草案（尚未正式发布）对 OAuth 2.0 进行了安全增强。

**主要变化**:
- 强制要求 PKCE（即使是机密客户端）
- 废弃隐式模式和密码模式
- 要求 refresh_token 轮换
- 要求 access_token 绑定（sender-constrained）

**建议**:
- 关注 OAuth 2.1 最终版本的发布
- 逐步迁移到更安全的授权模式
- 为隐式模式和密码模式添加废弃警告

---

## 九、总结

### 高优先级（建议 3 个月内完成）
1. ✅ PKCE 支持（1.1）
2. ✅ 速率限制（1.3）
3. ✅ Token 撤销端点（2.1）
4. ✅ 过期 Token 清理任务（3.1）
5. ✅ 单元测试（4.1）
6. ✅ `client_secret` 哈希存储（7.1）

### 中优先级（建议 6 个月内完成）
1. ✅ Token 内省端点（2.2）
2. ✅ OpenID Connect 扩展（2.4）
3. ✅ 单点登出（2.5）
4. ✅ 审计日志增强（1.4）
5. ✅ 监控与告警（3.2）
6. ✅ API 文档（5.1）
7. ✅ 第三方对接指南（5.2）
8. ✅ JWT 验证缓存（6.1）

### 低优先级（长期规划）
1. ✅ 动态客户端注册（2.3）
2. ✅ 同意管理 UI（2.6）
3. ✅ Device Binding（1.6）
4. ✅ 数据库索引优化（3.3）
5. ✅ 集成测试（4.2）
6. ✅ 业务逻辑提取（4.3）
7. ✅ 安全最佳实践文档（5.3）
8. ✅ 异步通知（6.3）
9. ✅ OAuth 2.1 合规（8.2）

---

> **最后更新**: 2026-08-20  
> **维护者**: AI Assistant  
> **状态**: 待评审
