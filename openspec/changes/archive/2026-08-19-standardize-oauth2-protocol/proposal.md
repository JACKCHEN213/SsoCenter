# 提案：标准化 OAuth 2.0 协议并支持四种授权模式

## 变更 ID

`standardize-oauth2-protocol`

## 背景与动机

当前 SSO Center 的应用管理模块仅提供基础的 CRUD 操作（注册应用、生成 RSA 公钥），**不具备任何标准 OAuth 2.0 协议能力**。第三方应用无法通过标准协议完成用户认证、获取令牌、刷新令牌等操作。

### 现状问题

1. **无标准 OAuth 2.0 端点**：缺少 `/authorize`、`/token`、`/userinfo`、`/revoke` 等标准端点
2. **无 client_id/client_secret 机制**：`sc_site` 表仅有 `name`、`request_url`、`redirect_url`，缺少 OAuth 2.0 必需的客户端凭证字段
3. **无授权码/令牌存储**：缺少 `oauth_authorization_codes`、`oauth_access_tokens`、`oauth_refresh_tokens` 等数据库表
4. **仅支持单一 JWT 登录**：`Login` 控制器生成的 JWT 仅用于管理后台认证，不是标准 OAuth 2.0 Access Token
5. **无 SCIM 用户同步**：缺少应用系统接口定义中的用户创建/修改/删除/登出/回调接口
6. **无请求签名验证**：缺少基于 SM3 的 API 签名认证机制

### 规范依据

本变更严格依据以下两份文档进行：
- `extend/认证中心OAuth2.0规范.md` — OAuth 2.0 端点定义、Token 格式、ID Token 结构、签名算法
- `extend/应用系统接口定义.md` — SCIM 用户同步接口、登出通知接口、回调接口

## 变更目标

### 核心目标

1. **实现标准 OAuth 2.0 协议**：完整支持授权码、隐式、密码、客户端凭证四种授权模式
2. **标准化 Token 管理**：Access Token（JWT RS256）+ Refresh Token（数据库存储）+ ID Token（JWT HS256，含用户信息）
3. **实现 SCIM 用户同步接口**：创建/修改/删除用户、登出通知、回调，支持 SM3 签名验证
4. **保持向后兼容**：现有管理后台的登录/用户管理功能不受影响

### 具体端点清单

#### OAuth 2.0 端点（依据认证中心OAuth2.0规范.md）

| 端点 | 方法 | 用途 |
|------|------|------|
| `GET /oauth/authorize` | GET | 获取授权码（授权码/隐式模式） |
| `POST /oauth/token` | POST | 获取/刷新 Access Token（全部四种模式） |
| `POST /oauth/userinfo` | POST | 获取用户信息（返回 ID Token） |
| `POST /oauth/logout` | POST | 应用通知登出 |

#### SCIM 用户同步端点（依据应用系统接口定义.md）

| 端点 | 方法 | 用途 |
|------|------|------|
| `POST /api/scim/v2/accounts` | POST | 创建/同步用户 |
| `PUT /api/scim/v2/accounts/{externalId}` | PUT | 修改用户 |
| `DELETE /api/scim/v2/accounts/{externalId}` | DELETE | 删除用户 |
| `POST /oauth/logout` | POST | 认证平台通知应用登出 |
| `GET /oauth/callback` | GET | 认证回调 |

## 涉及范围

| 范围 | 说明 |
|------|------|
| **新增文件** | OAuth 控制器、SCIM 控制器、Token 服务、签名验证中间件、多个验证器、迁移脚本 |
| **修改文件** | `sc_site` 表结构（新增字段）、Application 控制器（适配新字段）、路由文件 |
| **删除文件** | 无 |
| **数据库变更** | 新增 `client_id`/`client_secret` 字段到 `sc_site`；新建 3 张 OAuth 表 |

## 影响分析

- **现有功能**：管理后台登录（`Login` 控制器）保持不变
- **现有 API**：`/app/*` 管理接口保持向后兼容，注册应用时自动生成 `client_id`/`client_secret`
- **URL 路径**：新增 `/oauth/*` 和 `/api/scim/v2/*` 路径前缀
- **安全要求**：所有 SCIM 接口必须通过 SM3 签名验证；OAuth 端点需 HTTPS
