# SSO 认证页面规范（增量变更）

## ADDED Requirements

### Requirement: REQ-SSO-001 SSO 认证页面入口

系统 SHALL 提供 SSO 认证页面，第三方系统通过 `APP_ID`、`state`、`callback` 三个参数跳转到此页面完成用户认证。

**路由**: `GET /oauth/authorize`
**实现位置**: `app/controller/OAuth.php` → `authorizePage()`

#### Scenario: 新参数名跳转
- GIVEN 第三方系统携带以下参数跳转到认证页面
  - `APP_ID`（UUID4 格式的应用标识）
  - `state`（防 CSRF 随机值）
  - `callback`（回调地址，须与应用注册的 `redirect_url` 一致）
- WHEN 浏览器访问 `GET /oauth/authorize?APP_ID=xxx&state=yyy&callback=zzz`
- THEN 系统 SHALL 校验 `APP_ID` 有效（存在、未删除、已启用）
- AND 系统 SHALL 校验 `callback` 与应用注册的 `redirect_url` 一致
- AND 系统 SHALL 检测用户登录状态
- AND 未登录时 SHALL 渲染登录表单视图
- AND 已登录时 SHALL 渲染授权确认视图

#### Scenario: 旧参数名兼容
- GIVEN 调用方使用旧参数名 `client_id`/`state`/`redirect_uri`
- WHEN 浏览器访问 `GET /oauth/authorize?client_id=xxx&state=yyy&redirect_uri=zzz`
- THEN 系统 SHALL 按相同逻辑处理（新旧参数名等价）
- AND 新参数名优先（同时传递时以新参数名为准）

#### Scenario: APP_ID 无效
- GIVEN `APP_ID` 不存在、已删除、或已禁用
- WHEN 访问认证页面
- THEN 系统 SHALL 返回错误页面"未知的应用"

#### Scenario: callback 不匹配
- GIVEN `callback` 与应用注册的 `redirect_url` 不一致
- WHEN 访问认证页面
- THEN 系统 SHALL 返回错误页面"无效的回调地址"

### Requirement: REQ-SSO-002 认证页面登录流程

认证页面 SHALL 在用户未登录时展示登录表单，登录成功后自动切换到授权确认视图。

#### Scenario: 未登录展示登录表单
- GIVEN 用户未登录，APP_ID 和 callback 校验通过
- WHEN 页面加载
- THEN 系统 SHALL 展示登录表单（用户名 + 密码输入框）
- AND 页面 SHALL 展示应用名称："**[应用名称]** 请求使用您的账户登录"
- AND 登录 SHALL 通过 AJAX 请求 `POST /login/login`

#### Scenario: 登录成功切换授权确认
- GIVEN 用户在认证页面输入账号密码并提交
- WHEN 登录成功
- THEN 系统 SHALL 将 token 存入 localStorage
- AND 页面 SHALL 不跳转首页，直接切换为授权确认视图
- AND 授权确认视图 SHALL 展示应用名称和"允许"/"拒绝"按钮

### Requirement: REQ-SSO-003 授权确认与回调

用户确认授权后，系统 SHALL 生成授权码并回调到 `callback` 地址。

**实现位置**: `app/controller/OAuth.php` → `confirmAuthorize()`

#### Scenario: 用户点击"允许"
- GIVEN 用户已登录，处于授权确认视图
- WHEN 用户点击"允许"按钮
- THEN 前端 SHALL 发送 `POST /oauth/authorize/confirm`，参数 `APP_ID`、`state`、`callback`
- AND 后端 SHALL 生成一次性授权码（64 位随机，10 分钟过期）
- AND 后端 SHALL 返回 `{ redirect_url: "{callback}?code={授权码}&state={state}" }`
- AND 前端 SHALL 使用 `window.location` 跳转到返回的 `redirect_url`

#### Scenario: 回调 URL 格式
- GIVEN 授权码生成成功
- WHEN 回调到第三方系统
- THEN 回调 URL SHALL 为 `{callback}?code={授权码}&state={state}`
- AND `state` 值 SHALL 与传入时完全一致
- AND `code` SHALL 为一次性授权码

#### Scenario: 用户点击"拒绝"
- GIVEN 用户处于授权确认视图
- WHEN 用户点击"拒绝"按钮
- THEN 前端 SHALL 跳转到 `{callback}?error=access_denied&error_description=User+denied+the+request&state={state}`

### Requirement: REQ-SSO-004 固定授权码模式

认证页面 SHALL 固定使用授权码模式，不再需要外部传入 `response_type` 和 `scope`。

#### Scenario: 固定 response_type
- GIVEN 第三方系统跳转到认证页面
- WHEN 系统处理认证请求
- THEN `response_type` SHALL 固定为 `code`
- AND 无需第三方系统传递 `response_type` 参数

#### Scenario: scope 从应用配置读取
- GIVEN 第三方系统跳转到认证页面
- WHEN 系统生成授权码
- THEN `scope` SHALL 从 `sc_site.scope` 字段读取
- AND 无需第三方系统传递 `scope` 参数

## MODIFIED Requirements

### Requirement: authorizePage 参数接收方式

原 `adapt-frontend-oauth2` 中定义的 `authorizePage()` 参数接收方式 SHALL 按以下方式变更。

**原定义**：接收 `client_id`、`response_type`、`redirect_uri`、`scope`、`state` 五个参数

**变更后**：接收 `APP_ID`、`state`、`callback` 三个参数，兼容旧参数名

#### Scenario: 参数名变更
- GIVEN `authorizePage()` 方法
- WHEN 从请求中读取参数
- THEN SHALL 优先读取 `APP_ID`（fallback 到 `client_id`）
- AND SHALL 优先读取 `state`（fallback 到 `state`）
- AND SHALL 优先读取 `callback`（fallback 到 `redirect_uri`）
- AND SHALL 不再读取 `response_type` 参数（固定为 `code`）
- AND SHALL 不再读取 `scope` 参数（从应用配置读取）

#### Scenario: 模板变量变更
- GIVEN `authorizePage()` 渲染模板
- WHEN 传递变量到 `oauth_authorize.html`
- THEN SHALL 传递 `app_id`（替代 `client_id`）
- AND SHALL 传递 `callback`（替代 `redirect_uri`）
- AND SHALL 传递 `state`
- AND SHALL 不再传递 `response_type` 和 `scope`

### Requirement: confirmAuthorize 参数接收方式

原 `adapt-frontend-oauth2` 中定义的 `confirmAuthorize()` 参数接收方式 SHALL 按以下方式变更。

**原定义**：接收 `client_id`、`response_type`、`redirect_uri`、`scope`、`state` 五个参数

**变更后**：接收 `APP_ID`、`state`、`callback` 三个参数，兼容旧参数名

#### Scenario: 参数名变更
- GIVEN `confirmAuthorize()` 方法
- WHEN 从请求中读取参数
- THEN SHALL 优先读取 `APP_ID`（fallback 到 `client_id`）
- AND SHALL 优先读取 `state`（fallback 到 `state`）
- AND SHALL 优先读取 `callback`（fallback 到 `redirect_uri`）

## REMOVED Requirements

### Requirement: response_type 外部传参

原 `adapt-frontend-oauth2` 中定义的 `response_type` 参数 SHALL 从认证页面请求参数中移除。

**移除原因**: 当前仅支持授权码模式，`response_type` 固定为 `code`，无需外部传入。

#### Scenario: 不再接收 response_type
- GIVEN 第三方系统跳转到认证页面
- WHEN 系统处理请求
- THEN SHALL 不要求 `response_type` 参数
- AND `response_type` SHALL 内部固定为 `code`

### Requirement: scope 外部传参

原 `adapt-frontend-oauth2` 中定义的 `scope` 参数 SHALL 从认证页面请求参数中移除。

**移除原因**: `scope` 由应用在注册时配置（`sc_site.scope`），认证页面从应用配置读取，无需外部传入。

#### Scenario: 不再接收 scope
- GIVEN 第三方系统跳转到认证页面
- WHEN 系统处理请求
- THEN SHALL 不要求 `scope` 参数
- AND `scope` SHALL 从 `sc_site.scope` 字段读取
