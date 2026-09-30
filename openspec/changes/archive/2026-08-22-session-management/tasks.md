# 实施任务清单

## 前置任务

- [x] T0: 数据库迁移 — 创建 `sc_user_sessions` 表，`sc_site` 表新增 `logout_callback_url` 字段

---

## 一、后端基础改造

- [x] T1: 改造 `Login::login()` — 登录成功后 INSERT `sc_user_sessions` 记录（含 `jti`、`ip`、`user_agent`、`login_time`、`expires_at`）

- [x] T2: 改造 `Login::logout()` — 登出时将对应 `sc_user_sessions` 的 `is_active` 置为 0

- [x] T3: 改造 `CheckLogin` 中间件 — JWT 验签通过后，额外校验 `sc_user_sessions` 中该 `jti` 是否 `is_active=1`；若无记录则放行（向后兼容）

- [x] T4: 创建 `app/controller/Session.php` 控制器（继承 BaseController）

- [x] T5: 创建 `app/validate/SessionValidate.php` — 校验 session/list、session/detail、session/logout 的参数

---

## 二、后端接口实现

- [x] T6: 实现 `Session::actionIndex()` — 渲染会话管理页面

- [x] T7: 实现 `Session::actionList()` — 按用户维度聚合查询会话列表（本系统 session + 各应用授权统计）

- [x] T8: 实现 `Session::actionDetail()` — 返回某用户的完整会话详情（本系统 session 列表 + 各应用的授权码/Token 列表）

- [x] T9: 实现 `Session::actionLogout()` — 强制退出逻辑（scope=local/app/all），含撤销 Token、删除授权码、发送登出通知

- [x] T10: 实现 `OAuth::actionLogoutCallback()` — 第三方系统回调登出接口（HTTP Basic Auth 认证 → 匹配用户 → 撤销 Token）

---

## 三、跨系统登出通知

- [x] T11: 改造 `OAuth::notifyRelatedAppsLogout()` — 从 TODO 改为使用 Guzzle 实际发送登出通知（根据 `logout_callback_url`）

---

## 四、前端实现

- [x] T12: 创建 `app/view/session/index.html` — 会话管理页面（用户列表 + 会话详情弹窗 + 强制退出操作）

- [x] T13: 改造 `template/index.html` — 左侧导航"用户管理"下方新增"会话管理"菜单项

- [x] T14: 改造应用管理页面 — 新增/编辑应用时增加"登出回调地址"输入框

---

## 五、路由与集成

- [x] T15: 在 `route/app.php` 添加路由
    - `GET /session` → `Session::actionIndex()`
    - `POST /session/list` → `Session::actionList()`
    - `POST /session/detail` → `Session::actionDetail()`
    - `POST /session/logout` → `Session::actionLogout()`
    - `POST /oauth/logout_callback` → `OAuth::actionLogoutCallback()`（不需要登录态）

- [x] T16: 创建 `app/validate/OauthValidate.php`（如不存在）— 增加 logout_callback 参数校验

---

## 六、OpenSpec 规范归档

- [x] T17: 实现完成后，将 `openspec/changes/session-management/` 移动到 `openspec/changes/archive/`，并将增量规范合并到 `openspec/specs/`
