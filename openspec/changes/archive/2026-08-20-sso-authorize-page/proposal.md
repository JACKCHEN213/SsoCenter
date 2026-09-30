# 提案：SSO 认证页面（简化参数版）

## 变更 ID

`sso-authorize-page`

## 背景与动机

现有 `adapt-frontend-oauth2` 已实现了 OAuth 2.0 标准的授权登录页（`oauth_authorize.html`），使用标准 OAuth 2.0 参数名（`client_id`、`redirect_uri`、`response_type`、`scope`、`state`）。

但实际对接中，第三方系统只需要最简单的三个参数即可完成认证跳转：

| 用户要求的参数 | 含义 | 对应现有参数 |
|--------------|------|-------------|
| `APP_ID` | 应用标识（UUID4） | `client_id` |
| `state` | 防 CSRF 随机值 | `state` |
| `callback` | 认证成功后的回调地址 | `redirect_uri` |

### 现状问题

1. **参数过多**：现有实现要求 5 个参数，第三方系统对接成本高
2. **参数名不直观**：`client_id`、`redirect_uri` 不如 `APP_ID`、`callback` 直观
3. **`response_type` 不必要**：目前只支持授权码模式，`response_type` 参数多余
4. **`scope` 不必要**：当前 scope 固定为 `trust`，无需传递

### 改造目标

- 认证页面只接收 `APP_ID`、`state`、`callback` 三个参数
- 未登录 → 展示登录表单 → 登录成功后自动进入授权确认
- 已登录 → 直接展示授权确认 → 用户点击"允许"后生成授权码 → 回调 `callback` 地址携带 `code` 和 `state`
- 保持现有 `oauth_authorize.html` 页面和后端逻辑兼容

## 变更目标

1. **简化请求参数**：认证页面只需 `APP_ID`、`state`、`callback`
2. **固定授权码模式**：只支持授权码模式，不再传递 `response_type` 和 `scope`
3. **回调格式**：`callback?code={授权码}&state={state}`

## 涉及范围

| 范围 | 文件 |
|------|------|
| **修改控制器** | `app/controller/OAuth.php` — `authorizePage()`、`confirmAuthorize()` 适配新参数名 |
| **修改页面** | `app/view/oauth_authorize.html` — 适配新参数名 |
| **修改路由** | `route/oauth.php` — 路由参数调整（如有必要） |

## 影响分析

- **向后兼容**：现有使用 `client_id`/`redirect_uri`/`state` 的调用方可同时兼容新旧参数名
- **现有功能**：OAuth 2.0 API 端点（`/oauth/authorize`）不受影响，仅页面认证流程简化
