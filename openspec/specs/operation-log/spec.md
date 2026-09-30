# 操作日志规范

> Capability: `operation-log`
> 定义 SSO 身份认证中心的操作审计日志系统。

---

## Purpose

提供统一的操作审计日志机制，记录所有关键业务操作（登录、登出、应用管理、用户管理、推送操作）的完整上下文，便于审计追踪和问题排查。

---


## Requirements

### Requirement: REQ-LOG-001 操作日志存储

系统 SHALL 提供 `sc_operation_log` 表，统一存储所有业务操作的结构化日志。

#### Scenario: 操作日志字段
- GIVEN 一次业务操作完成
- WHEN 写入 `sc_operation_log` 表
- THEN 记录 SHALL 包含以下字段：
  - `operator_id`（INT，操作者 user_id，登录失败时可为 NULL）
  - `operator_name`（VARCHAR(64)，操作者用户名，冗余字段方便展示）
  - `module`（VARCHAR(32)，操作模块：`auth` / `app` / `user`）
  - `action`（VARCHAR(64)，操作动作，如 `login` / `app_create` / `user_push`）
  - `target_type`（VARCHAR(32)，操作对象类型：`app` / `user` / `system`）
  - `target_id`（INT，操作对象 ID）
  - `target_name`（VARCHAR(255)，操作对象名称）
  - `result`（VARCHAR(16)，操作结果：`success` / `failure`）
  - `result_message`（VARCHAR(512)，结果描述）
  - `request_data`（JSON，请求数据）
  - `response_data`（JSON，响应数据）
  - `ip`（VARCHAR(64)，操作者 IP）
  - `user_agent`（VARCHAR(512)，浏览器 User-Agent）
  - `related_type`（VARCHAR(32)，关联记录类型，如 `push_log`）
  - `related_id`（INT，关联记录 ID）
  - `created_at`（DATETIME，操作时间）

---

### Requirement: REQ-LOG-002 认证操作日志

系统 SHALL 在登录/登出时记录操作日志。

#### Scenario: 登录成功
- GIVEN 用户提供正确的用户名和密码
- WHEN 登录成功
- THEN 系统 SHALL 记录操作日志：
  - `module`: `auth`
  - `action`: `login`
  - `operator_id`: 用户 ID
  - `operator_name`: 用户名
  - `target_type`: `user`
  - `target_id`: 用户 ID
  - `target_name`: 用户名
  - `result`: `success`
  - `result_message`: "登录成功"
  - `request_data`: `{"username": "zhangsan", "password": "***"}`（密码脱敏）
  - `ip`: 登录 IP
  - `user_agent`: 浏览器信息

#### Scenario: 登录失败 — 用户不存在
- GIVEN 用户输入不存在的用户名
- WHEN 登录失败
- THEN 系统 SHALL 记录操作日志：
  - `operator_id`: NULL
  - `operator_name`: 输入的用户名
  - `result`: `failure`
  - `result_message`: "用户不存在"

#### Scenario: 登录失败 — 密码错误
- GIVEN 用户输入错误密码
- WHEN 登录失败
- THEN 系统 SHALL 记录操作日志：
  - `operator_id`: NULL
  - `operator_name`: 输入的用户名
  - `result`: `failure`
  - `result_message`: "密码错误"

#### Scenario: 登出
- GIVEN 已登录用户点击登出
- WHEN 登出完成
- THEN 系统 SHALL 记录操作日志：
  - `module`: `auth`
  - `action`: `logout`
  - `result`: `success`

---

### Requirement: REQ-LOG-003 应用操作日志

系统 SHALL 在应用的增删改、重置/查看 APP_KEY 时记录操作日志。

#### Scenario: 创建应用
- GIVEN 管理员提交创建应用表单
- WHEN 创建成功
- THEN 系统 SHALL 记录操作日志：
  - `module`: `app`
  - `action`: `app_create`
  - `target_type`: `app`
  - `target_id`: 新应用 ID
  - `target_name`: 应用名称
  - `result`: `success`
  - `request_data`: `{"app_name": "...", "request_url": "...", "redirect_url": "...", "allowed_grant_types": "..."}`
  - `response_data`: `{"client_id": "..."}`

#### Scenario: 修改应用
- GIVEN 管理员提交修改应用表单
- WHEN 修改成功
- THEN 系统 SHALL 记录操作日志：
  - `action`: `app_update`
  - `request_data`: 包含修改的字段

#### Scenario: 删除应用
- GIVEN 管理员删除应用
- WHEN 删除成功
- THEN 系统 SHALL 记录操作日志：
  - `action`: `app_delete`
  - `target_name`: 被删应用名称

#### Scenario: 重置 APP_KEY
- GIVEN 管理员重置应用 APP_KEY
- WHEN 重置成功
- THEN 系统 SHALL 记录操作日志：
  - `action`: `app_reset_secret`
  - `request_data`: `{"id": 1, "name": "应用A"}`
  - AND SHALL NOT 记录新的 client_secret 明文

#### Scenario: 查看 APP_KEY
- GIVEN 管理员查看应用 APP_KEY
- WHEN 查看操作完成
- THEN 系统 SHALL 记录操作日志：
  - `action`: `app_view_secret`
  - AND SHALL NOT 记录 client_secret 明文

---

### Requirement: REQ-LOG-004 用户操作日志

系统 SHALL 在用户的增删改、推送操作时记录操作日志。

#### Scenario: 新增用户
- GIVEN 管理员新增用户
- WHEN 新增成功
- THEN 系统 SHALL 记录操作日志：
  - `module`: `user`
  - `action`: `user_create`
  - `target_type`: `user`
  - `target_id`: 新用户 ID
  - `target_name`: 用户名
  - `request_data`: `{"username": "...", "email": "..."}`

#### Scenario: 修改用户
- GIVEN 管理员修改用户
- WHEN 修改成功
- THEN 系统 SHALL 记录操作日志：
  - `action`: `user_update`
  - `request_data`: 包含修改的字段

#### Scenario: 删除用户
- GIVEN 管理员删除用户
- WHEN 删除成功
- THEN 系统 SHALL 记录操作日志：
  - `action`: `user_delete`
  - `target_name`: 被删用户名

---

### Requirement: REQ-LOG-005 用户推送日志

推送操作 SHALL 在操作日志中记录完整的 HTTP 请求/响应数据。

#### Scenario: 推送用户 — 记录完整请求
- GIVEN 管理员推送用户到第三方应用
- WHEN 推送请求发出
- THEN 系统 SHALL 在 `request_data` 中记录：
  - `url`: 完整请求 URL
  - `method`: HTTP 方法
  - `headers`: 请求头（`X-trust-signature` 脱敏为 `****`，`appkey` 不记录）
  - `body`: 请求体

#### Scenario: 推送用户 — 记录完整响应
- GIVEN 第三方系统返回响应
- WHEN 推送请求完成
- THEN 系统 SHALL 在 `response_data` 中记录：
  - `status_code`: HTTP 状态码
  - `headers`: 响应头
  - `body`: 响应体

#### Scenario: 推送用户 — 关联 push_log
- GIVEN 推送操作同时写入 `sc_user_push_log`
- WHEN 写入操作日志
- THEN 系统 SHALL 设置 `related_type` = `push_log`，`related_id` = push_log 记录的 ID

#### Scenario: 推送用户 — 结果记录
- GIVEN 推送成功
- WHEN 记录操作日志
- THEN `result`: `success`
- AND `result_message`: "推送成功，externalId=xxx"

- GIVEN 推送失败（响应验证不通过）
- WHEN 记录操作日志
- THEN `result`: `failure`
- AND `result_message`: "推送失败: 响应验证失败: code: 期望 200, 实际 500"

- GIVEN 推送失败（网络错误）
- WHEN 记录操作日志
- THEN `result`: `failure`
- AND `result_message`: "推送失败: 连接超时"

#### Scenario: 批量推送
- GIVEN 管理员批量推送用户到所有应用
- WHEN 推送完成
- THEN 系统 SHALL 记录操作日志：
  - `action`: `user_push_all`
  - `result_message`: "批量推送完成: 2 成功, 1 失败"
  - `response_data`: 每个应用的推送结果列表

---

### Requirement: REQ-LOG-006 敏感数据脱敏

操作日志 SHALL NOT 记录敏感信息的明文。

#### Scenario: 密码脱敏
- GIVEN 登录操作的 `request_data`
- WHEN 写入日志
- THEN `password` 字段 SHALL 替换为 `***`

#### Scenario: APP_KEY 不记录
- GIVEN 重置/查看 APP_KEY 操作
- WHEN 写入日志
- THEN `request_data` 和 `response_data` SHALL NOT 包含 client_secret 明文

#### Scenario: 签名值脱敏
- GIVEN 推送操作的 `request_data.headers`
- WHEN 写入日志
- THEN `X-trust-signature` 字段 SHALL 替换为 `****`

#### Scenario: appkey 不记录
- GIVEN 推送操作的 `request_data.headers`
- WHEN 写入日志
- THEN SHALL NOT 包含任何 appkey 信息

---

### Requirement: REQ-LOG-007 AuditLogService 日志服务

系统 SHALL 提供 `app/service/AuditLogService.php` 作为统一日志写入服务。

#### Scenario: 通用 log 方法
- GIVEN 调用 `AuditLogService::log(module, action, result, ...)`
- WHEN 方法执行
- THEN SHALL 自动获取当前操作者信息（从 JWT 解析）
- AND 自动获取 IP 和 User-Agent
- AND 插入 `sc_operation_log` 记录
- AND 返回日志 ID

#### Scenario: 快捷方法
- GIVEN 需要记录登录日志
- WHEN 调用 `AuditLogService::login(username, result, message, userId)`
- THEN SHALL 自动设置 `module=auth`, `action=login` 并写入

- GIVEN 需要记录应用操作日志
- WHEN 调用 `AuditLogService::app(action, result, message, requestData, responseData, appId, appName)`
- THEN SHALL 自动设置 `module=app`, `target_type=app` 并写入

- GIVEN 需要记录用户操作日志
- WHEN 调用 `AuditLogService::user(action, result, message, requestData, responseData, userId, userName, relatedType, relatedId)`
- THEN SHALL 自动设置 `module=user`, `target_type=user` 并写入

#### Scenario: 操作者获取
- GIVEN 当前请求携带有效 JWT
- WHEN 调用 `getOperator()`
- THEN SHALL 返回 `['id' => user_id, 'name' => username]`

- GIVEN 当前请求无 JWT（如登录失败场景）
- WHEN 调用 `getOperator()`
- THEN SHALL 返回 `['id' => null, 'name' => null]`

---

### Requirement: REQ-LOG-008 操作日志列表页面

系统 SHALL 提供操作日志列表页面，支持筛选和分页。

#### Scenario: 页面入口
- GIVEN 管理员登录系统
- WHEN 查看左侧导航栏
- THEN SHALL 显示"操作日志"菜单项（位于"用户管理"下方）

#### Scenario: 列表页面展示
- GIVEN 管理员点击"操作日志"
- WHEN 页面加载
- THEN SHALL 展示日志列表表格，列包含：序号、时间、操作者、模块、动作、操作对象、结果、IP、操作
- AND SHALL 展示筛选区：操作模块（下拉）、操作动作（下拉）、操作者（文本）、时间范围（日期选择）
- AND SHALL 支持分页

#### Scenario: 结果列展示
- GIVEN 日志记录 result 为 success
- WHEN 渲染结果列
- THEN SHALL 显示绿色 ✓ "成功"

- GIVEN 日志记录 result 为 failure
- WHEN 渲染结果列
- THEN SHALL 显示红色 ✗ "失败"

---

### Requirement: REQ-LOG-009 操作日志详情弹窗

日志列表每行 SHALL 提供"详情"按钮，点击查看完整日志。

#### Scenario: 查看详情
- GIVEN 管理员点击某条日志的"详情"按钮
- WHEN 点击事件触发
- THEN 系统 SHALL 调用 `POST /operation_log/detail`
- AND SHALL 打开详情弹窗
- AND 弹窗 SHALL 展示：操作者、时间、IP、User-Agent、模块、动作、操作对象、结果、结果描述

#### Scenario: 展示请求数据
- GIVEN 日志有 `request_data`
- WHEN 渲染详情弹窗
- THEN SHALL 展示请求数据（格式化 JSON）
- AND 推送操作的请求数据 SHALL 展示 URL、方法、请求头、请求体

#### Scenario: 展示响应数据
- GIVEN 日志有 `response_data`
- WHEN 渲染详情弹窗
- THEN SHALL 展示响应数据（格式化 JSON）
- AND 推送操作的响应数据 SHALL 展示状态码、响应头、响应体

#### Scenario: 展示关联记录
- GIVEN 日志有 `related_type` 和 `related_id`
- WHEN 渲染详情弹窗
- THEN SHALL 展示关联信息（如 "关联: push_log #123"）

---

### Requirement: REQ-LOG-010 操作日志查询接口

系统 SHALL 提供操作日志查询 API。

#### Scenario: list 接口
- GIVEN 管理员请求日志列表
- WHEN 调用 `POST /operation_log/list`
- THEN SHALL 支持以下筛选参数：
  - `module`（String，可选）
  - `action`（String，可选）
  - `operator_name`（String，可选，模糊匹配）
  - `start_time`（String，可选）
  - `end_time`（String，可选）
  - `page`（Int，默认 1）
  - `page_size`（Int，默认 20）
- AND SHALL 返回分页结果：`{ total, page, page_size, list: [...] }`

#### Scenario: detail 接口
- GIVEN 管理员请求日志详情
- WHEN 调用 `POST /operation_log/detail`，参数 `id`
- THEN SHALL 返回完整日志记录（含 `request_data`、`response_data`）

---

### Requirement: REQ-LOG-011 与 sc_user_push_log 的关系

推送操作 SHALL 同时写入 `sc_operation_log` 和 `sc_user_push_log`。

#### Scenario: 双写策略
- GIVEN 管理员推送用户到第三方应用
- WHEN 推送操作执行
- THEN 系统 SHALL 先写入 `sc_user_push_log`（获取 push_log ID）
- AND 再写入 `sc_operation_log`，设置 `related_type=push_log`, `related_id=push_log.id`

#### Scenario: 职责分离
- GIVEN 需要查看用户推送状态
- WHEN 查询推送状态面板
- THEN SHALL 从 `sc_user_push_log` 查询（快速、含 externalId）

- GIVEN 需要查看操作审计记录
- WHEN 查询操作日志页面
- THEN SHALL 从 `sc_operation_log` 查询（全局视图、含完整请求/响应）
