# 设计方案：应用管理页面适配 OAuth 2.0 & SSO 登录页

## 一、架构总览

```
┌──────────────────────────────────────────────────────────┐
│                   应用管理页面（index.html）                │
├──────────────────┬───────────────────────────────────────┤
│                  │                                       │
│  添加应用弹窗     │  应用列表表格                           │
│  ┌─────────────┐ │  ┌─────────────────────────────────┐  │
│  │ 基本信息     │ │  │ APP_ID | 名称 | 授权模式 | 操作 │  │
│  │ 应用名称     │ │  │ UUID4  | xxx  | 标签    | 详情  │  │
│  │ 请求路径     │ │  │        |      |         | 编辑  │  │
│  │ 回调路径     │ │  │        |      |         | 删除  │  │
│  │ 应用图标     │ │  │        |      |         | 下载   │  │
│  ├─────────────┤ │  └─────────────────────────────────┘  │
│  │ OAuth 配置   │ │                                       │
│  │ 授权模式 []  │ │  应用详情弹窗（点击"详情"打开）         │
│  │ Scope []     │ │  ┌─────────────────────────────────┐  │
│  │ Token 有效期 │ │  │ APP_ID: [xxxx] [复制]            │  │
│  │ Refresh 有效期│ │  │ APP_KEY: [****] [查看][复制]     │  │
│  └─────────────┘ │  │          [重置][下载]              │  │
│                  │  │ 授权模式: 授权码 ☑ 隐式 ☑ ...      │  │
│  编辑应用弹窗     │  │ Scope: trust                       │  │
│  （同添加+APP_ID展示）│ │ Token有效期: 8h / Refresh: 24h    │  │
│                  │  └─────────────────────────────────┘  │
└──────────────────┴───────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│              SSO 授权登录页（oauth_authorize.html）         │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  未登录状态：                                              │
│  ┌────────────────────────────────────┐                  │
│  │  [Logo]                            │                  │
│  │  "第三方应用 [App名称] 请求登录"     │                  │
│  │  用户名: [________]                 │                  │
│  │  密码:   [________]                 │                  │
│  │  [登录]                             │                  │
│  └────────────────────────────────────┘                  │
│                                                          │
│  已登录状态（授权确认）：                                    │
│  ┌────────────────────────────────────┐                  │
│  │  [Logo]                            │                  │
│  │  "[App名称] 请求访问您的账户"        │                  │
│  │  该应用将获取以下权限：              │                  │
│  │  • 获取您的基本信息                  │                  │
│  │  [拒绝]          [允许]             │                  │
│  └────────────────────────────────────┘                  │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

---

## 二、APP_ID 格式规范

### 2.1 生成规则

- **格式**：UUID Version 4（随机），如 `550e8400-e29b-41d4-a716-446655440000`
- **生成方式**：后端使用 PHP 的 `random_bytes()` 生成，遵循 RFC 4122
- **存储**：`sc_site.client_id` 字段，VARCHAR(36)
- **唯一性**：数据库 `UNIQUE KEY` 约束

### 2.2 生成函数

```php
// app/common/Uuid.php
class Uuid
{
    public static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
```

### 2.3 与 standardize-oauth2-protocol 的关系

此变更**覆盖** `standardize-oauth2-protocol` 中 `client_id` 的生成方式：
- 原方案：随机 32 位字符串
- 本方案：UUID4 格式（36 位含连字符）
- 影响：迁移脚本中数据填充逻辑需调整，`sc_site.client_id` 字段类型改为 VARCHAR(36)

---

## 三、应用管理页面改造设计

### 3.1 添加应用弹窗（改造 `index.html` 中的 Modal）

弹窗分两个区域：**基本信息** + **OAuth 配置**

#### 基本信息区

| 字段 | HTML 元素 | 说明 |
|------|-----------|------|
| 应用名称 | `<input type="text">` | 必填，对应 `name` |
| 请求路径 | `<input type="url">` | 必填，对应 `request_url` |
| 回调路径 | `<input type="url">` | 必填，对应 `redirect_url` |
| 应用图标 | `<input type="file">` | 选填，对应 `image` |

#### OAuth 配置区

| 字段 | HTML 元素 | 说明 |
|------|-----------|------|
| 授权模式 | 4 个 `<input type="checkbox">` | `authorization_code`（默认勾选）、`implicit`、`password`、`client_credentials` |
| 授权范围 | `<input type="text">` | 默认 `trust`，可选填 |
| Access Token 有效期 | `<input type="number">` + 单位下拉 | 默认 8 小时，支持 分钟/小时/天 |
| Refresh Token 有效期 | `<input type="number">` + 单位下拉 | 默认 24 小时，支持 分钟/小时/天 |
| 授权码有效期 | `<input type="number">` + 单位下拉 | 默认 10 分钟，支持 秒/分钟 |

#### 添加接口返回值调整

```json
{
  "code": 0,
  "success": true,
  "result": {
    "site_id": 1,
    "client_id": "550e8400-e29b-41d4-a716-446655440000",
    "client_secret": "xY9kL..."
  }
}
```

添加成功后，前端弹窗自动展示 APP_ID 和 APP_KEY，提示用户保存。

### 3.2 应用列表表格（改造）

**表头调整**：

| 列 | 宽度 | 内容 |
|----|------|------|
| 图标 | 8% | 应用缩略图 |
| APP_ID | 22% | UUID4 格式，可点击复制 |
| 应用名称 | 15% | 应用名称 |
| 授权模式 | 20% | 彩色标签展示已启用的模式 |
| 回调路径 | 15% | redirect_url |
| 操作 | 20% | 详情 / 编辑 / 删除 / 下载 APP_KEY |

**授权模式标签颜色**：
- 授权码：蓝色
- 隐式：绿色
- 密码：橙色
- 客户端凭证：紫色

### 3.3 应用详情弹窗（新增）

点击列表中"详情"按钮打开，展示完整的 OAuth 配置信息：

| 展示项 | 说明 |
|--------|------|
| APP_ID | UUID4，只读 + 复制按钮（点击复制到剪贴板） |
| APP_KEY | 默认隐藏（显示 `••••••••`），点击"查看"显示明文，点击"复制"复制，点击"重置"重新生成 |
| 授权模式 | 显示已勾选的模式（只读） |
| 授权范围 | 显示 scope 值 |
| Access Token 有效期 | 显示配置值 |
| Refresh Token 有效期 | 显示配置值 |
| 回调路径 | 显示 redirect_url |
| 请求路径 | 显示 request_url |

**APP_KEY 安全机制**：
- 查看明文前需二次确认弹窗："确认查看 APP_KEY？请确保周围环境安全"
- 重置前需二次确认："重置后旧的 APP_KEY 将立即失效，确认继续？"
- 重置接口：`POST /app/reset_secret`，返回新的 client_secret

### 3.4 编辑弹窗改造

与添加弹窗基本相同，额外变化：
- APP_ID 以只读方式展示（不可修改）
- 已有配置值预填充
- 不展示 APP_KEY（需通过详情弹窗查看）

### 3.5 下载 APP_KEY

**按钮**："下载 APP_KEY"
**行为**：生成文本文件下载，文件内容格式：

```
# OAuth 2.0 应用凭证
# 应用名称: xxx
# 生成时间: 2026-08-20 10:00:00
# 重要：请妥善保管此文件，不要泄露 APP_KEY

APP_ID=550e8400-e29b-41d4-a716-446655440000
APP_KEY=xY9kL2mN3pQ4rS5tU6vW7xY8zA9bC0dE
```

**文件名**：`{应用名称}_oauth_credentials.txt`

**后端接口**：`GET /app/download_secret?id={site_id}`

---

## 四、SSO 授权登录页设计

### 4.1 页面结构

**文件**：`app/view/oauth_authorize.html`
**模板继承**：`{extend name="template/login" /}`（复用登录页布局）

**两种状态**：

#### 状态 A：用户未登录

- 显示 Logo + 第三方应用名称
- 提示语："**[应用名称]** 请求使用您的账户登录"
- 用户名/密码输入框
- 登录按钮
- 登录成功后不跳转首页，而是自动进入状态 B（授权确认）

#### 状态 B：用户已登录（授权确认）

- 显示 Logo + 第三方应用名称和图标
- 提示语："**[应用名称]** 请求访问您的账户"
- 权限列表：根据 `scope` 展示请求的权限
- 两个按钮：**拒绝**（返回 error 到 redirect_uri）、**允许**（生成授权码/token，302 回调）

### 4.2 页面参数

页面由后端渲染，通过模板变量传递：

| 变量 | 来源 | 说明 |
|------|------|------|
| `$app_name` | `sc_site.name` | 第三方应用名称 |
| `$app_image` | `sc_site.image` | 第三方应用图标 |
| `$client_id` | URL 参数 | 应用标识 |
| `$redirect_uri` | URL 参数 | 回调地址 |
| `$scope` | URL 参数 | 请求的权限范围 |
| `$state` | URL 参数 | 客户端状态值 |
| `$response_type` | URL 参数 | `code` 或 `token` |
| `$is_logged_in` | Session 检测 | 用户是否已登录 |

### 4.3 交互流程

```
第三方系统                         SSO Center                     用户浏览器
    │                                 │                              │
    │  GET /authorize?client_id=...   │                              │
    ├────────────────────────────────>│                              │
    │                                 │   302 → /oauth/authorize页面  │
    │                                 ├─────────────────────────────>│
    │                                 │                              │
    │                                 │   用户输入账号密码登录          │
    │                                 │<─────────────────────────────│
    │                                 │                              │
    │                                 │   渲染授权确认页               │
    │                                 ├─────────────────────────────>│
    │                                 │                              │
    │                                 │   用户点击"允许"               │
    │                                 │<─────────────────────────────│
    │                                 │                              │
    │   302 redirect_uri?code=xxx     │                              │
    │<────────────────────────────────┤                              │
    │                                 │                              │
```

### 4.4 登录成功后的处理

- **AJAX 登录**：登录成功后将 token 存入 localStorage（同管理后台逻辑）
- **自动进入授权确认**：登录成功后页面不跳转，直接渲染状态 B 的内容
- **前端处理**：JavaScript 监听登录表单 submit 的 AJAX 响应，成功后替换页面内容

### 4.5 用户点击"允许"

- **授权码模式**：`POST /oauth/authorize/confirm`，后端生成 code，302 到 `redirect_uri?code=xxx&state=xxx`
- **隐式模式**：后端生成 access_token，302 到 `redirect_uri#access_token=xxx&token_type=Bearer&expires_in=xxx&state=xxx`

### 4.6 用户点击"拒绝"

- 302 到 `redirect_uri?error=access_denied&error_description=User+denied+the+request&state=xxx`

---

## 五、后端接口适配

### 5.1 Application 控制器改造

| 方法 | 变更 |
|------|------|
| `add()` | 接收 `allowed_grant_types`、`scope`、`access_token_ttl`、`refresh_token_ttl`、`code_ttl` 参数；使用 UUID4 生成 client_id；返回包含 client_id/client_secret |
| `update()` | 支持更新 OAuth 配置字段 |
| `detail()` | **新增**，返回单个应用完整信息（含 client_id，不含 client_secret 明文） |
| `resetSecret()` | **新增**，重置 client_secret，返回新值 |
| `downloadSecret()` | **新增**，生成凭证文件下载 |

### 5.2 OAuth 控制器新增页面路由

| 方法 | 路由 | 说明 |
|------|------|------|
| `authorizePage()` | `GET /oauth/authorize` | 渲染 SSO 授权登录页（页面路由，非 API） |

**注意**：此路由与 API 路由 `GET /oauth/authorize` 不同。API 路由处理授权逻辑（检测登录状态后 302），页面路由负责渲染登录/授权确认 UI。

### 5.3 新增路由

```php
// route/oauth.php 中新增
Route::get('oauth/authorize', 'authorizePage');        // 页面路由
Route::post('oauth/authorize/confirm', 'confirmAuthorize'); // 确认授权
```

---

## 六、前端 JS 逻辑设计

### 6.1 index.html 脚本改造

| 函数 | 变更 |
|------|------|
| `modifyApp()` | 新增 OAuth 配置字段的收集和提交 |
| `deleteApp()` | 无变化 |
| `initModal()` | 编辑模式下预填充 OAuth 配置字段 |
| `showAppDetail()` | **新增**，打开详情弹窗，加载 APP_ID 和授权配置 |
| `copyToClipboard()` | **新增**，复制文本到剪贴板 |
| `toggleSecretVisibility()` | **新增**，切换 APP_KEY 显示/隐藏 |
| `resetAppSecret()` | **新增**，调用重置接口 |
| `downloadAppSecret()` | **新增**，调用下载接口 |
| `renderGrantTypeBadges()` | **新增**，渲染授权模式标签 |

### 6.2 oauth_authorize.js（新增）

| 函数 | 说明 |
|------|------|
| `ssoLogin(event)` | SSO 登录（AJAX），成功后切换到授权确认视图 |
| `confirmAuthorize()` | 用户点击"允许"，POST 确认授权 |
| `denyAuthorize()` | 用户点击"拒绝"，跳转到 redirect_uri 带 error 参数 |

---

## 七、样式设计

### 7.1 授权模式标签

```css
.grant-type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    margin: 2px;
}
.grant-type-badge.authorization_code { background: #e3f2fd; color: #1565c0; }
.grant-type-badge.implicit { background: #e8f5e9; color: #2e7d32; }
.grant-type-badge.password { background: #fff3e0; color: #e65100; }
.grant-type-badge.client_credentials { background: #f3e5f5; color: #6a1b9a; }
```

### 7.2 APP_ID 展示区域

```css
.app-id-display {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 6px;
    font-family: monospace;
}
.app-id-display .copy-btn {
    cursor: pointer;
    opacity: 0.6;
}
.app-id-display .copy-btn:hover {
    opacity: 1;
}
```

### 7.3 SSO 授权页

```css
.sso-authorize-card {
    max-width: 420px;
    margin: 80px auto;
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.sso-authorize-card .app-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}
.sso-authorize-card .app-info img {
    width: 48px;
    height: 48px;
    border-radius: 8px;
}
.sso-authorize-card .permission-list {
    list-style: none;
    padding: 0;
    margin: 16px 0;
}
.sso-authorize-card .permission-list li::before {
    content: "✓";
    color: #2e7d32;
    margin-right: 8px;
}
```

---

## 八、向后兼容

1. **现有应用**：编辑弹窗中 OAuth 配置字段有默认值（授权码模式、scope=trust、8h/24h）
2. **访问应用按钮**：保留现有功能，同时支持通过 OAuth 2.0 流程访问
3. **下载公钥按钮**：保留，与"下载 APP_KEY"按钮并存
4. **列表展示**：旧应用如未配置 OAuth 字段，授权模式列显示"未配置"提示
