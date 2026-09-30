# 应用管理页面修复规范（增量变更）

## ADDED Requirements

### Requirement: REQ-FIX-001 访问应用按钮

应用列表操作栏 SHALL 提供"访问应用"按钮，点击后以 OAuth 2.0 授权码模式快速访问应用。

#### Scenario: 按钮展示
- GIVEN 应用列表页面加载完成
- WHEN 渲染操作列
- THEN 系统 SHALL 展示"访问应用"按钮
- AND 按钮 SHALL 使用 `btn-success` 样式（绿色）
- AND 按钮 SHALL 包含 `bi-box-arrow-up-right` 图标

#### Scenario: 点击访问应用
- GIVEN 用户点击某应用的"访问应用"按钮
- WHEN 点击事件触发
- THEN 系统 SHALL 发送 `POST /app/visit`，参数 `id` 为应用 ID
- AND 请求 Header SHALL 携带 `Authorization: <管理员 JWT Token>`
- WHEN 接口返回成功
- THEN 系统 SHALL 拼接 URL：`{redirect_uri}?code={code}&state={state}`
- AND 系统 SHALL 以 `window.open(url, '_blank')` 打开新标签页

#### Scenario: 授权码获取失败
- GIVEN 用户点击"访问应用"按钮
- WHEN 接口返回错误
- THEN 系统 SHALL 显示错误 toast 提示

### Requirement: REQ-FIX-002 为当前用户生成授权码接口

系统 SHALL 提供接口，为当前登录管理员生成一次性授权码，用于快速访问应用。

**实现位置**: `app/controller/Application.php` → `visitApp()`
**路由**: `POST /app/visit`
**中间件**: `CheckLogin` + `ValidateParams`
**验证器场景**: `Application::sceneVisitApp()` — `id` 必填

#### Scenario: 生成授权码成功
- GIVEN 管理员已登录，应用 ID 有效（`is_del=0`、`is_use=1`）
- AND 应用配置了 `redirect_url`
- WHEN 发送 `POST /app/visit`，参数 `id`
- THEN 系统 SHALL 生成 64 位随机授权码（10 分钟过期，一次性使用）
- AND 系统 SHALL 生成 16 位随机 state
- AND 系统 SHALL 将授权码写入 `oauth_authorization_codes` 表，关联当前管理员 `user_id`、`client_id`、`redirect_uri`、默认 `scope`
- AND 系统 SHALL 记录审计日志
- AND 返回: `{ code: 0, success: true, result: { code: "...", state: "...", redirect_uri: "..." } }`

#### Scenario: 应用不存在或已禁用
- GIVEN 应用 ID 无效，或 `is_del=1`，或 `is_use=0`
- WHEN 发送 `POST /app/visit`
- THEN 返回: `{ code: 20001, success: false, result: "应用不存在或未启用" }`

#### Scenario: 应用未配置回调地址
- GIVEN 应用存在但 `redirect_url` 为空
- WHEN 发送 `POST /app/visit`
- THEN 返回: `{ code: 20004, success: false, result: "应用未配置回调地址" }`

#### Scenario: 缺少 id 参数
- GIVEN 未提供 `id` 参数
- WHEN 发送 `POST /app/visit`
- THEN 验证器 SHALL 拦截，返回参数校验错误

### Requirement: REQ-FIX-003 Token 变量 base64 解码修复

`index.html` 中全局 `token` 变量 SHALL 存储原始 JWT 字符串，而非 base64 编码值。

**修复原因**: 登录时 `localStorage.setItem('token', btoa(jwt_string))` 存储了 base64 编码值。`checkToken()` 直接赋值给 `token` 变量，导致后续所有 AJAX 请求的 `Authorization` Header 携带的是 base64 编码值而非原始 JWT。`CheckLogin` 中间件在 POST 请求时进行 JWT 验证，因 base64 字符串无三段式 `.` 结构而报 "Wrong number of segments"。

#### Scenario: checkToken 解码 token
- GIVEN 用户已登录，`localStorage` 中存在 `token`（base64 编码的 JWT）
- WHEN `checkToken()` 执行
- THEN 系统 SHALL 使用 `atob()` 解码 base64 值，将原始 JWT 赋给全局 `token` 变量

#### Scenario: token 非 base64 兼容
- GIVEN `localStorage` 中的 `token` 不是合法 base64（直接是 JWT 字符串）
- WHEN `checkToken()` 执行 `atob()` 解码
- THEN 系统 SHALL 捕获异常，使用原始值作为 `token`

#### Scenario: token 为空
- GIVEN `localStorage` 中无 `token`
- WHEN `checkToken()` 执行
- THEN 系统 SHALL 跳转登录页

#### Scenario: 修复后 POST 请求验证
- GIVEN `checkToken()` 已修复
- WHEN 用户调用任何 POST 接口（如 `reset_secret`、`get_secret`）
- THEN `Authorization` Header SHALL 携带原始 JWT 字符串
- AND `CheckLogin` 中间件 SHALL 正常通过 JWT 验证

### Requirement: 应用列表操作按钮

应用列表操作栏的按钮组成 SHALL 按以下方式变更。

**原定义**（`adapt-frontend-oauth2` — `Requirement: 应用列表操作按钮`）：
- 详情、编辑、删除、下载公钥、下载 APP_KEY

**变更后**：
- 详情、编辑、删除、访问应用

#### Scenario: 按钮变更
- GIVEN 应用列表页面加载完成
- WHEN 渲染操作列
- THEN 按钮 SHALL 变更为：
  - 详情（保留）
  - 编辑（保留）
  - 删除（保留）
  - 访问应用（新增）
- AND "下载公钥"按钮 SHALL 移除
- AND "下载 APP_KEY"按钮 SHALL 从操作栏移除（详情弹窗中保留）

#### Scenario: 移除 downloadPublicToken 函数
- GIVEN 操作栏"下载公钥"按钮已移除
- WHEN 页面脚本加载
- THEN `downloadPublicToken()` 函数 SHALL 一并移除

## REMOVED Requirements

### Requirement: 操作栏"下载公钥"按钮

原 `adapt-frontend-oauth2` 中定义的"下载公钥"按钮 SHALL 从操作栏移除。

**移除原因**: RSA 公钥下载不再是 OAuth 2.0 标准流程的一部分。

#### Scenario: 按钮不存在
- GIVEN 应用列表页面加载完成
- WHEN 渲染操作列
- THEN SHALL 不存在"下载公钥"按钮
- AND SHALL 不存在 `downloadPublicToken()` 函数调用

### Requirement: 操作栏"下载 APP_KEY"按钮

原 `adapt-frontend-oauth2` 中定义的"下载 APP_KEY"按钮 SHALL 从操作栏移除。

**移除原因**: "下载 APP_KEY"功能已整合到应用详情弹窗中，操作栏无需重复提供。

#### Scenario: 按钮不存在
- GIVEN 应用列表页面加载完成
- WHEN 渲染操作列
- THEN SHALL 不存在独立的"下载 APP_KEY"按钮
- AND 用户仍可通过详情弹窗中的"下载"按钮下载凭证文件
