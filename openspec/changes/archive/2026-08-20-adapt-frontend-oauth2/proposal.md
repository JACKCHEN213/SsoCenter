# 提案：应用管理页面适配 OAuth 2.0 & SSO 登录页

## 变更 ID

`adapt-frontend-oauth2`

## 背景与动机

后端已实现标准 OAuth 2.0 协议（`standardize-oauth2-protocol`），但前端页面仍停留在旧的简单应用管理模式：

### 现状问题

1. **应用表单过于简单**：添加/编辑应用时只有名称、请求路径、跳转路径三个字段，不支持 OAuth 2.0 的四种授权模式配置
2. **缺少 APP_ID / APP_SECRET 展示**：页面不展示 `client_id`（APP_ID）和 `client_secret`（APP_KEY），第三方系统无法获取 OAuth 2.0 对接所需的凭证
3. **不支持下载 APP_KEY**：现有"下载公钥"按钮是 RSA 公钥文件下载，不是 OAuth 2.0 的 client_secret 下载
4. **无 SSO 登录页**：缺少标准 OAuth 2.0 授权码流程中的用户授权页面，第三方系统无法跳转到本系统进行认证
5. **访问应用方式不规范**：当前通过 URL 拼接 `sso_token` 参数直接访问应用，不符合 OAuth 2.0 授权码跳转流程
6. **APP_ID 格式不符**：当前 `client_id` 是随机 32 位字符串，不是标准 UUID4 格式

### 改造目标

- 应用管理表单支持配置 OAuth 2.0 授权模式
- 应用列表/详情展示 APP_ID（UUID4）和 APP_KEY
- 支持下载 APP_KEY（client_secret 文本文件）
- 新增 SSO 授权登录页，支持第三方系统跳转认证

## 变更目标

### 1. 应用管理页面增强

**添加应用弹窗**新增字段：
- 授权模式选择（多选 checkbox）：授权码、隐式、密码、客户端凭证
- 授权范围（scope）输入
- Access Token 有效期配置
- Refresh Token 有效期配置

**应用列表**新增展示：
- APP_ID（UUID4 格式，可复制）
- 授权模式标签展示

**应用详情/编辑弹窗**新增：
- 展示 APP_ID（只读 + 复制按钮）
- 展示/重置 APP_KEY（隐藏 + 查看/复制/重置按钮）
- 下载 APP_KEY 按钮（下载为 `.txt` 文件）

### 2. SSO 授权登录页（新增）

**新增页面**：`app/view/oauth_authorize.html`

**流程**：
1. 第三方系统重定向用户到 `/oauth/authorize?client_id=xxx&response_type=code&redirect_uri=xxx&scope=trust&state=xxx`
2. 后端检测用户未登录 → 302 到 SSO 登录页
3. 用户在 SSO 登录页输入账号密码
4. 登录成功后展示授权确认页（显示应用名称、请求的权限范围）
5. 用户确认授权 → 生成授权码 → 302 回调到第三方系统的 `redirect_uri`
6. 用户已登录 → 直接展示授权确认页（跳过登录步骤）

## 涉及范围

| 范围 | 文件 |
|------|------|
| **修改页面** | `app/view/index.html`（应用列表/添加/编辑弹窗） |
| **新增页面** | `app/view/oauth_authorize.html`（SSO 授权登录页） |
| **修改控制器** | `app/controller/Index.php`（传递新字段到模板）、`app/controller/OAuth.php`（授权页渲染） |
| **修改控制器** | `app/controller/Application.php`（返回值包含 client_id/client_secret） |
| **新增路由** | `route/oauth.php` 中新增授权页面路由 |
| **新增 JS** | `public/assets/js/oauth-authorize.js`（SSO 登录页逻辑） |
| **数据库** | `sc_site.client_id` 格式改为 UUID4（影响 `standardize-oauth2-protocol` 的迁移脚本） |

## 影响分析

- **现有功能**：管理后台的应用 CRUD 操作不受影响，仅表单字段扩展
- **依赖变更**：依赖 `standardize-oauth2-protocol` 后端改造完成
- **URL 路径**：新增 `/oauth/authorize` 页面路由
- **用户体验**：添加/编辑弹窗字段增多，需合理分区（基本信息 + OAuth 配置）
