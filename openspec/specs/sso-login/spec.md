# SSO 授权登录页规范

## Purpose

为第三方应用提供统一的 SSO 登录入口。第三方应用通过跳转 `/oauth/authorize` 并携带 `APP_ID`、`state`、`callback` 三个参数即可发起认证，页面根据登录状态展示登录表单或授权确认视图，完成后回调 `callback` 地址并携带授权码。

## Requirements

### Requirement: 页面定义
系统 SHALL 提供 SSO 授权登录页面。

#### Scenario: 页面文件与路由
- GIVEN 用户访问 OAuth 授权页面
- WHEN 请求 `GET /oauth/authorize`
- THEN 系统 SHALL 渲染 `app/view/oauth_authorize.html` 模板
- AND 模板 SHALL 继承 `{extend name="template/login" /}`

### Requirement: 页面状态 — 用户未登录
当用户未登录时，页面 SHALL 展示登录表单。

#### Scenario: 未登录状态展示
- GIVEN 用户未登录，`APP_ID` 和 `callback` 校验通过
- WHEN 访问 `/oauth/authorize` 页面
- THEN 页面 SHALL 展示以下内容：
  - Logo
  - 标题文字 `"[应用名称] 请求使用您的账户登录"`
  - 应用图标和应用名称
  - 用户名输入框
  - 密码输入框
  - 登录按钮

#### Scenario: 登录表单提交
- GIVEN 用户在登录表单中输入用户名和密码
- WHEN 点击登录按钮
- THEN 系统 SHALL 发送 AJAX 请求到 `POST /login/login`
- AND 密码 SHALL 使用 MD5 加密后提交（与现有登录页一致）
- AND 登录成功后 SHALL 切换到授权确认状态（不刷新页面）

### Requirement: 页面状态 — 用户已登录（授权确认）
当用户已登录时，页面 SHALL 展示授权确认界面。

#### Scenario: 已登录状态展示
- GIVEN 用户已登录
- WHEN 访问 `/oauth/authorize` 页面
- THEN 页面 SHALL 展示以下内容：
  - Logo
  - 标题文字 `"[应用名称] 请求访问您的账户"`
  - 应用图标
  - 应用名称
  - 回调地址
  - 权限列表（如"获取您的基本信息"）
  - "拒绝"按钮
  - "允许"按钮

#### Scenario: 登录成功后状态切换
- GIVEN 用户在登录表单中成功登录
- WHEN 登录完成
- THEN 页面 SHALL 平滑过渡到授权确认视图
- AND 过渡 SHALL 使用 jQuery 淡入淡出替换页面主体内容
- AND 页面 SHALL NOT 刷新

### Requirement: 模板变量
页面 SHALL 接收以下模板变量。

#### Scenario: 模板变量定义
- GIVEN 渲染授权页面
- WHEN 模板加载
- THEN 系统 SHALL 提供以下变量：
  - `$app_name`（来自 `sc_site.name`）
  - `$app_image`（来自 `sc_site.image`）
  - `$app_id`（URL 参数，优先 `APP_ID`，fallback `client_id`）
  - `$callback`（URL 参数，优先 `callback`，fallback `redirect_uri`）
  - `$state`（URL 参数，优先 `state`，fallback `state`）
  - `$is_logged_in`（Session 检测结果）

### Requirement: 交互行为 — 用户允许授权
当用户点击"允许"按钮时，系统 SHALL 完成授权流程并重定向回应用。

#### Scenario: 授权确认请求
- GIVEN 用户已登录
- WHEN 用户点击"允许"按钮
- THEN 前端 SHALL POST 到 `/oauth/authorize/confirm`
- AND 请求参数 SHALL 包含 `APP_ID`、`callback`、`state`（兼容旧名 `client_id`/`redirect_uri`/`state`）
- AND 后端 SHALL 生成一次性授权码（64 位随机，10 分钟过期）
- AND 后端 SHALL 返回 `{ redirect_url: "{callback}?code={code}&state={state}" }`
- AND 前端 SHALL 使用 `window.location` 跳转到返回的 `redirect_url`

### Requirement: 交互行为 — 用户拒绝授权
当用户点击"拒绝"按钮时，系统 SHALL 重定向回应用并携带错误信息。

#### Scenario: 拒绝授权
- GIVEN 用户处于授权确认视图
- WHEN 用户点击"拒绝"按钮
- THEN 前端 SHALL 跳转到 `{callback}?error=access_denied&error_description=User+denied+the+request&state={state}`

### Requirement: GET /oauth/authorize 页面路由
系统 SHALL 提供 GET `/oauth/authorize` 路由用于渲染授权页面。

#### Scenario: 正常访问（新参数名）
- GIVEN 请求携带 `APP_ID`、`state`、`callback` 三个参数
- WHEN 访问 `GET /oauth/authorize?APP_ID=xxx&state=yyy&callback=zzz`
- THEN 系统 SHALL 校验 `APP_ID` 有效（存在、未删除、已启用）
- AND 系统 SHALL 校验 `callback` 与应用注册的 `redirect_url` 一致
- AND 系统 SHALL 检测用户登录状态
- AND 系统 SHALL 渲染 `oauth_authorize.html` 模板

#### Scenario: 旧参数名兼容
- GIVEN 请求携带 `client_id`、`state`、`redirect_uri`（旧参数名）
- WHEN 访问 `GET /oauth/authorize`
- THEN 系统 SHALL 按相同逻辑处理（新旧参数名等价）
- AND 新参数名优先（同时传递时以新参数名为准）

#### Scenario: 无效的 APP_ID
- GIVEN 请求包含无效或不存在的 `APP_ID`
- WHEN 访问 `GET /oauth/authorize`
- THEN 系统 SHALL 返回错误页面，提示"未知的应用"

#### Scenario: callback 不匹配
- GIVEN 请求中的 `callback` 与应用注册的回调地址不一致
- WHEN 访问 `GET /oauth/authorize`
- THEN 系统 SHALL 返回错误页面，提示"无效的回调地址"

### Requirement: POST /oauth/authorize/confirm 确认授权接口
系统 SHALL 提供 POST `/oauth/authorize/confirm` 接口处理用户授权确认。

#### Scenario: 确认授权
- GIVEN 用户已登录，`APP_ID`、`callback` 校验通过
- WHEN 调用 `POST /oauth/authorize/confirm`
- THEN 系统 SHALL 校验 `callback` 与应用注册值一致
- AND 系统 SHALL 生成一次性授权码
- AND 系统 SHALL 返回 `{ redirect_url: "{callback}?code={code}&state={state}" }`
- AND `scope` SHALL 从 `sc_site.scope` 字段读取（不从请求传入）
- AND `response_type` SHALL 固定为 `code`（不从请求传入）

### Requirement: 安全考虑
授权页面和流程 SHALL 满足以下安全要求。

#### Scenario: CSRF 防护
- GIVEN 用户发起授权请求
- WHEN 传递 `state` 参数
- THEN `state` 值 SHALL 在回调 URL 中原样回传

#### Scenario: 重定向保护
- GIVEN 用户发起授权请求
- WHEN 校验 `callback`
- THEN `callback` SHALL 与应用注册值完全匹配（包括协议、域名、端口）
- AND 系统 SHALL 防止开放重定向攻击

#### Scenario: Session 验证
- GIVEN 用户确认授权
- WHEN 调用确认接口
- THEN 系统 SHALL 验证用户 Session 仍然有效

#### Scenario: 授权码限流
- GIVEN 系统生成授权码
- WHEN 授权码生成后
- THEN 授权码 SHALL 仅可使用一次
- AND 授权码 SHALL 在 10 分钟后过期

### Requirement: route/oauth.php 路由定义
OAuth 路由文件 SHALL 定义授权页面相关路由。

#### Scenario: 授权页面路由
- GIVEN OAuth 模块路由配置
- WHEN 加载 `route/oauth.php`
- THEN 路由 SHALL 包含：
  - `GET /oauth/authorize` → `OAuth::authorizePage`（渲染 SSO 授权登录页）
  - `POST /oauth/authorize/confirm` → `OAuth::confirmAuthorize`（处理用户授权确认）
  - `POST /oauth/authorize/deny` → `OAuth::denyAuthorize`（拒绝授权）

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
