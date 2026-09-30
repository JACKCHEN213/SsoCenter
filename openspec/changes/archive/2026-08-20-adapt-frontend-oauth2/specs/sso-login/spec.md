## ADDED Requirements

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
- GIVEN 用户未登录
- WHEN 访问 `/oauth/authorize` 页面
- THEN 页面 SHALL 展示以下内容：
  - Logo
  - 标题文字 `"[应用名称] 请求使用您的账户登录"`
  - 应用图标和应用名称
  - 用户名输入框
  - 密码输入框
  - 登录按钮
  - "没有账户？注册"链接

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
- AND 过渡 SHALL 使用 `innerHTML` 或 jQuery 替换页面主体内容
- AND 页面 SHALL NOT 刷新


### Requirement: 模板变量
页面 SHALL 接收以下模板变量。

#### Scenario: 模板变量定义
- GIVEN 渲染授权页面
- WHEN 模板加载
- THEN 系统 SHALL 提供以下变量：
  - `$app_name`（来自 `sc_site.name`）
  - `$app_image`（来自 `sc_site.image`）
  - `$client_id`（URL 参数）
  - `$redirect_uri`（URL 参数）
  - `$scope`（URL 参数）
  - `$state`（URL 参数）
  - `$response_type`（URL 参数）
  - `$is_logged_in`（Session 检测结果）


### Requirement: 交互行为 — 用户允许授权
当用户点击"允许"按钮时，系统 SHALL 完成授权流程并重定向回应用。

#### Scenario: 授权码模式
- GIVEN 用户已登录且 `response_type=code`
- WHEN 用户点击"允许"按钮
- THEN 系统 SHALL POST 到 `/oauth/authorize/confirm`
- AND 请求参数 SHALL 包含 `client_id`、`redirect_uri`、`scope`、`state`、`response_type`
- AND 后端 SHALL 生成授权码
- AND 页面 SHALL 跳转到 `redirect_uri?code={code}&state={state}`

#### Scenario: 隐式模式
- GIVEN 用户已登录且 `response_type=token`
- WHEN 用户点击"允许"按钮
- THEN 系统 SHALL POST 到 `/oauth/authorize/confirm`
- AND 后端 SHALL 生成 access_token
- AND 页面 SHALL 跳转到 `redirect_uri#access_token={token}&token_type=Bearer&expires_in={expires}&state={state}`


### Requirement: 交互行为 — 用户拒绝授权
当用户点击"拒绝"按钮时，系统 SHALL 重定向回应用并携带错误信息。

#### Scenario: 拒绝授权
- GIVEN 用户已登录
- WHEN 用户点击"拒绝"按钮
- THEN 页面 SHALL 跳转到 `redirect_uri?error=access_denied&error_description=User+denied+the+request&state={state}`


### Requirement: GET /oauth/authorize 页面路由
系统 SHALL 提供 GET `/oauth/authorize` 路由用于渲染授权页面。

#### Scenario: 正常访问
- GIVEN 请求包含有效的 `client_id` 和 `redirect_uri`
- WHEN 访问 `GET /oauth/authorize`
- THEN 系统 SHALL 校验 `client_id` 有效（存在、未删除、已启用）
- AND 系统 SHALL 校验 `redirect_uri` 与应用注册值一致
- AND 系统 SHALL 检测用户 Session 登录状态
- AND 系统 SHALL 渲染 `oauth_authorize.html` 模板

#### Scenario: 无效的 client_id
- GIVEN 请求包含无效或不存在的 `client_id`
- WHEN 访问 `GET /oauth/authorize`
- THEN 系统 SHALL 返回 400 错误页面，提示"未知的应用"

#### Scenario: redirect_uri 不匹配
- GIVEN 请求中的 `redirect_uri` 与应用注册的回调地址不一致
- WHEN 访问 `GET /oauth/authorize`
- THEN 系统 SHALL 返回 400 错误页面，提示"无效的回调地址"

#### Scenario: 不支持的 response_type
- GIVEN 请求中的 `response_type` 不是 `code` 或 `token`
- WHEN 访问 `GET /oauth/authorize`
- THEN 系统 SHALL 返回 400 错误页面，提示"不支持的授权类型"


### Requirement: POST /oauth/authorize/confirm 确认授权接口
系统 SHALL 提供 POST `/oauth/authorize/confirm` 接口处理用户授权确认。

#### Scenario: 确认授权 — 授权码模式
- GIVEN 用户已登录且 `response_type=code`
- WHEN 调用 `POST /oauth/authorize/confirm`
- THEN 系统 SHALL 生成授权码
- AND 系统 SHALL 返回 `{ redirect_url: "redirect_uri?code={code}&state={state}" }`

#### Scenario: 确认授权 — 隐式模式
- GIVEN 用户已登录且 `response_type=token`
- WHEN 调用 `POST /oauth/authorize/confirm`
- THEN 系统 SHALL 生成 access_token
- AND 系统 SHALL 返回 `{ redirect_url: "redirect_uri#access_token={token}&token_type=Bearer&expires_in={expires}&state={state}" }`


### Requirement: 安全考虑
授权页面和流程 SHALL 满足以下安全要求。

#### Scenario: CSRF 防护
- GIVEN 用户发起授权请求
- WHEN 传递 `state` 参数
- THEN `state` 参数 SHALL 在重定向时原样回传

#### Scenario: 重定向保护
- GIVEN 用户发起授权请求
- WHEN 校验 `redirect_uri`
- THEN `redirect_uri` SHALL 与应用注册值完全匹配（包括协议、域名、端口）
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
OAuth 路由文件 SHALL 新增页面路由定义。

#### Scenario: 新增授权页面路由
- GIVEN OAuth 模块路由配置
- WHEN 加载 `route/oauth.php`
- THEN 路由 SHALL 包含：
  - `GET /oauth/authorize` → `OAuth::authorizePage`（渲染 SSO 授权登录页）
  - `POST /oauth/authorize/confirm` → `OAuth::confirmAuthorize`（处理用户授权确认）
