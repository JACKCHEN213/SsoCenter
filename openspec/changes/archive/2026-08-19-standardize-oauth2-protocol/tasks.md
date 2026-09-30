# 实施清单：标准化 OAuth 2.0 协议并支持四种授权模式

## 阶段一：数据库迁移

- [x] **T1.1** 编写迁移脚本：`sc_site` 表新增 `client_id`、`client_secret`、`allowed_grant_types`、`scope`、`access_token_ttl`、`refresh_token_ttl`、`code_ttl` 字段
- [x] **T1.2** 编写迁移脚本：创建 `oauth_authorization_codes` 表
- [x] **T1.3** 编写迁移脚本：创建 `oauth_access_tokens` 表
- [x] **T1.4** 编写迁移脚本：创建 `oauth_refresh_tokens` 表
- [x] **T1.5** 编写数据迁移脚本：为现有 `sc_site` 记录批量生成 `client_id`（随机 32 位）和 `client_secret`（随机 64 位）
- [x] **T1.6** 执行迁移脚本并验证数据库结构

## 阶段二：基础服务层

- [x] **T2.1** 创建 `app/service/SignatureService.php` — 实现 SM3 签名计算与验证逻辑
- [x] **T2.2** 创建 `app/service/TokenService.php` — 实现 Access Token 生成（JWT RS256）、Refresh Token 生成、Token 存储/验证/撤销/刷新
- [x] **T2.3** 创建 `app/service/IDTokenService.php` — 实现 ID Token 生成（JWT HS256，按规范文档定义 claims）
- [x] **T2.4** 创建 `app/service/AuthorizationCodeService.php` — 实现授权码生成、消费（一次性）、过期清理

## 阶段三：中间件

- [x] **T3.1** 创建 `app/http/middleware/VerifySignature.php` — SM3 签名验证中间件，调用 `SignatureService`
- [x] **T3.2** 创建 `app/http/middleware/OAuthClientAuth.php` — OAuth 客户端认证中间件（解析 Basic Auth，校验 client_id/client_secret）
- [x] **T3.3** 在路由中注册中间件

## 阶段四：OAuth 2.0 核心端点

- [x] **T4.1** 创建 `app/controller/OAuth.php` 控制器骨架
- [x] **T4.2** 实现 `OAuth::authorize()` — 授权端点
  - [x] T4.2.1 校验 `client_id`、`redirect_uri`、`scope`、`state`、`response_type` 参数
  - [x] T4.2.2 检测用户登录状态，未登录重定向登录页
  - [x] T4.2.3 `response_type=code`：生成授权码，302 回调（携带 code + state）
  - [x] T4.2.4 `response_type=token`：生成 access_token，302 回调（fragment 中携带 token）
- [x] **T4.3** 实现 `OAuth::token()` — Token 端点
  - [x] T4.3.1 解析 Basic Auth Header，验证 client_id/client_secret
  - [x] T4.3.2 `grant_type=authorization_code`：消费授权码，生成 access_token + refresh_token
  - [x] T4.3.3 `grant_type=refresh_token`：验证 refresh_token，生成新 token 对，旧 token 失效
  - [x] T4.3.4 `grant_type=password`：验证用户名密码，生成 access_token + refresh_token
  - [x] T4.3.5 `grant_type=client_credentials`：仅验证客户端，生成 access_token（无 refresh_token）
  - [x] T4.3.6 不支持的 grant_type 返回错误
- [x] **T4.4** 实现 `OAuth::userinfo()` — 获取用户信息
  - [x] T4.4.1 验证 access_token 有效性
  - [x] T4.4.2 生成 ID Token（HS256，密钥为 client_secret）
  - [x] T4.4.3 按规范文档格式返回 `{ message, status, data: { id_token } }`
- [x] **T4.5** 实现 `OAuth::logout()` — 应用通知登出
  - [x] T4.5.1 验证 Basic Auth + access_token
  - [x] T4.5.2 撤销该 access_token 及关联的 refresh_token
  - [x] T4.5.3 通知关联应用登出（遍历用户会话关联的应用，发送登出请求）

## 阶段五：SCIM 用户同步端点

- [x] **T5.1** 创建 `app/controller/Scim.php` 控制器骨架
- [x] **T5.2** 实现 `Scim::createAccount()` — POST `/api/scim/v2/accounts`
  - [x] T5.2.1 SM3 签名验证（通过中间件）
  - [x] T5.2.2 不存在则创建用户，存在则更新用户
  - [x] T5.2.3 按规范文档格式返回响应（含 externalId、accountId）
- [x] **T5.3** 实现 `Scim::updateAccount()` — PUT `/api/scim/v2/accounts/{externalId}`
  - [x] T5.3.1 SM3 签名验证
  - [x] T5.3.2 更新用户信息
- [x] **T5.4** 实现 `Scim::deleteAccount()` — DELETE `/api/scim/v2/accounts/{externalId}`
  - [x] T5.4.1 SM3 签名验证
  - [x] T5.4.2 软删除用户

## 阶段六：OAuth 回调与登出通知端点

- [x] **T6.1** 创建 `app/controller/OAuthCallback.php` 控制器骨架
- [x] **T6.2** 实现 `OAuthCallback::logout()` — POST `/oauth/logout`
  - [x] T6.2.1 验证 Bearer Token
  - [x] T6.2.2 撤销 token 并注销用户会话
- [x] **T6.3** 实现 `OAuthCallback::callback()` — GET `/oauth/callback`
  - [x] T6.3.1 接收授权码和 state
  - [x] T6.3.2 处理回调逻辑（重定向）

## 阶段七：Application 控制器适配

- [x] **T7.1** 修改 `Application::add()` — 注册应用时自动生成 `client_id`/`client_secret`，返回值中包含这两个字段
- [x] **T7.2** 修改 `Application::update()` — 支持更新 `allowed_grant_types`、`scope`、`access_token_ttl`、`refresh_token_ttl` 等配置
- [x] **T7.3** 修改 `Application::delete()` — 删除应用时级联清理关联的 OAuth 记录（授权码、token）
- [x] **T7.4** 更新 `app/validate/Application.php` 验证器，适配新字段

## 阶段八：路由定义

- [x] **T8.1** 创建 `route/oauth.php` — OAuth 2.0 端点路由
- [x] **T8.2** 创建 `route/scim.php` — SCIM 用户同步端点路由（含 VerifySignature 中间件）
- [x] **T8.3** 创建 `route/oauth_callback.php` — OAuth 回调/登出通知端点路由
- [x] **T8.4** 在 `app/common/ResponseCode.php` 新增 OAuth 相关错误码（30000-39999）
- [x] **T8.5** 在 `app/common/ResponseMessage.php` 新增对应的错误消息

## 阶段九：验证与测试

- [x] **T9.1** 测试授权码模式完整流程：authorize → code → token → userinfo → logout
- [x] **T9.2** 测试隐式模式完整流程：authorize?response_type=token → 获取 token
- [x] **T9.3** 测试密码模式完整流程：token(grant_type=password) → userinfo
- [x] **T9.4** 测试客户端凭证模式完整流程：token(grant_type=client_credentials)
- [x] **T9.5** 测试 Token 刷新流程：token(grant_type=refresh_token)
- [x] **T9.6** 测试 SM3 签名验证：正确签名通过、错误签名拒绝、重放攻击拒绝
- [x] **T9.7** 测试 SCIM 接口：创建/修改/删除用户，签名验证
- [x] **T9.8** 测试边界情况：过期授权码、已使用授权码、过期 token、已撤销 token、无效 client_id
- [x] **T9.9** 测试向后兼容：现有管理后台登录、应用 CRUD 操作不受影响

## 阶段十：文档更新

- [x] **T10.1** 更新 `openspec/specs/oauth/spec.md`（新建）— OAuth 2.0 完整规范
- [x] **T10.2** 更新 `openspec/specs/application/spec.md` — 补充 client_id/client_secret 相关规范
- [x] **T10.3** 新建 `openspec/specs/scim/spec.md` — SCIM 用户同步接口规范
