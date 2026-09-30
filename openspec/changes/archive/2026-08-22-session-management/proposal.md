# 提案：会话管理（在线用户与 Token 管理）

## 变更 ID

`session-management`

## 背景与动机

当前系统缺乏对"已登录会话"的统一视图和管理能力：
- 无法查看当前有哪些用户在线（登录了本系统）
- 无法查看用户向哪些第三方应用发放了授权码、Access Token、Refresh Token
- 无法从管理页面强制让用户退出登录或使 Token 失效
- 第三方系统退出登录时，无法通知本系统同步退出

### 现状

| 数据 | 存储位置 | 可查 | 可管理 |
|------|----------|------|--------|
| 本地登录记录 | `sc_login_history` | ✓（仅历史记录） | ✗ |
| 本地活跃会话 | 无（JWT 无状态） | ✗ | ✗ |
| 授权码 | `sc_oauth_authorization_codes` | ✓（数据库） | ✓（可删除） |
| Access Token | `sc_oauth_access_tokens` | ✓（revoked 标记） | ✓（可撤销） |
| Refresh Token | `sc_oauth_refresh_tokens` | ✓（revoked 标记） | ✓（可撤销） |
| 跨系统登出通知 | `notifyRelatedAppsLogout()` | — | 仅 TODO 状态 |

## 变更目标

### 1. 本地会话追踪

新增 `sc_user_sessions` 表，追踪本地系统的活跃登录会话（JWT 本身无状态，需额外表记录）。

### 2. 会话管理页面

新增"会话管理"页面，以用户为维度展示：
- 本系统登录状态（IP、登录时间、会话状态）
- 各第三方应用的授权状态（授权码数量、Access Token 数量、Refresh Token 数量）
- 强制退出操作（本系统退出 / 指定应用退出 / 全部退出）

### 3. 强制登出

管理员可从页面：
- 强制某用户退出本系统（销毁本地 session）
- 强制某用户在某应用的 Token 失效（撤销 access_token + refresh_token）
- 强制某用户全部退出（本系统 + 所有应用）

### 4. 第三方系统回调登出接口

提供 API 供第三方系统在用户退出时调用，本系统收到后自动撤销该用户在该应用的所有 Token。

## 涉及范围

| 范围 | 说明 |
|------|------|
| **新增表** | `sc_user_sessions`（本地活跃会话追踪） |
| **新增页面** | `app/view/session.html`（会话管理页面） |
| **新增控制器** | `app/controller/Session.php` |
| **新增路由** | `GET /session`、`POST /session/list`、`POST /session/detail`、`POST /session/logout` |
| **新增回调接口** | `POST /oauth/logout_callback`（第三方系统回调登出） |
| **改造** | `Login::login()` — 登录时创建 session 记录 |
| **改造** | `Login::logout()` — 登出时删除 session 记录 |
| **改造** | `OAuth::notifyRelatedAppsLogout()` — 实现真正的跨系统登出通知 |
| **改造** | `template/index.html` — 左侧导航新增"会话管理"菜单 |

## 影响分析

- **性能**：每次登录/登出多一次 INSERT/DELETE，影响可忽略
- **安全**：session 记录不包含敏感信息；回调接口需验证调用方身份
- **兼容性**：不影响现有 OAuth 流程
