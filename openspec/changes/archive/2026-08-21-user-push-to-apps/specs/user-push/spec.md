# 用户推送管理规范（增量变更）

> 变更 ID: `user-push-to-apps`
> 影响模块: `user`

---

## ADDED Requirements

### Requirement: REQ-UPUSH-001 推送日志存储

系统 SHALL 提供 `sc_user_push_log` 表，记录每次用户推送的详细日志。

#### Scenario: 推送日志字段
- GIVEN 一次推送操作完成（无论成功或失败）
- WHEN 写入 `sc_user_push_log` 表
- THEN 记录 SHALL 包含以下字段：
  - `user_id`（INT，推送的用户 ID）
  - `site_id`（INT，目标应用 ID）
  - `action`（VARCHAR(16)，操作类型：`create` / `update` / `delete`）
  - `url`（VARCHAR(512)，实际请求的 URL）
  - `method`（VARCHAR(8)，HTTP 方法）
  - `request_body`（TEXT，实际发送的请求体）
  - `response_code`（INT，HTTP 响应状态码，可为 NULL）
  - `response_body`（TEXT，响应体，截取前 2000 字符，可为 NULL）
  - `is_success`（TINYINT(1)，是否推送成功）
  - `error_message`（VARCHAR(512)，失败时的错误信息，可为 NULL）
  - `push_time`（DATETIME，推送时间）

---

### Requirement: REQ-UPUSH-002 用户列表 — 推送按钮

用户管理页面操作栏 SHALL 新增"推送"按钮。

#### Scenario: 推送按钮展示
- GIVEN 用户管理页面加载完成
- WHEN 渲染用户列表操作列
- THEN 每行操作栏 SHALL 包含"推送"按钮（位于"删除"按钮右侧）

---

### Requirement: REQ-UPUSH-003 推送状态弹窗

点击"推送"按钮 SHALL 打开推送状态弹窗，展示该用户推送到所有已配置应用的状态。

#### Scenario: 打开推送状态弹窗
- GIVEN 用户点击某用户的"推送"按钮
- WHEN 点击事件触发
- THEN 系统 SHALL 调用 `POST /user/push_status` 接口
- AND 系统 SHALL 打开推送状态弹窗
- AND 弹窗标题 SHALL 显示"用户推送状态 — {用户名}"

#### Scenario: 推送状态表格展示
- GIVEN 推送状态弹窗已打开
- WHEN 数据加载完成
- THEN 弹窗 SHALL 展示表格，列包含：应用名称、推送状态、推送时间、操作
- AND 每行 SHALL 对应一个已配置推送接口的应用

#### Scenario: 推送状态图标
- GIVEN 某应用已有推送日志
- WHEN 渲染推送状态列
- THEN 系统 SHALL 按以下规则显示状态：
  - 最近一次推送成功 → 绿色 ✓ + "成功"
  - 最近一次推送失败 → 红色 ✗ + "失败"，下方显示错误信息摘要
  - 无推送记录 → 灰色 ○ + "未推送"

#### Scenario: 无已配置应用
- GIVEN 没有任何应用配置推送接口
- WHEN 打开推送状态弹窗
- THEN 系统 SHALL 显示"暂无已配置推送接口的应用"提示

---

### Requirement: REQ-UPUSH-004 推送用户到指定应用

推送状态弹窗 SHALL 支持对单个应用触发推送。

#### Scenario: 推送未推送的用户
- GIVEN 推送状态弹窗中某应用显示"未推送"
- WHEN 用户点击该行的"推送"按钮
- THEN 系统 SHALL 调用 `POST /user/push`，参数 `user_id` + `site_id`
- AND 系统 SHALL 向第三方应用的新增用户接口发送 HTTP 请求
- AND 请求体 SHALL 将 `body_template` 中的 `{{username}}` / `{{email}}` 等占位符替换为实际用户数据
- AND 系统 SHALL 根据 `success_field` / `success_value` 判断响应是否成功
- AND 系统 SHALL 记录推送日志到 `sc_user_push_log`
- AND 弹窗 SHALL 刷新显示最新推送状态

#### Scenario: 重新推送失败的用户
- GIVEN 推送状态弹窗中某应用显示"失败"
- WHEN 用户点击该行的"重推"按钮
- THEN 系统 SHALL 重新调用 `POST /user/push`
- AND 系统 SHALL 记录新的推送日志（不影响历史日志）
- AND 弹窗 SHALL 刷新显示最新状态

#### Scenario: 推送成功
- GIVEN 第三方接口返回符合成功判定规则的响应
- WHEN 推送操作完成
- THEN 系统 SHALL 更新该行状态为绿色 ✓ "成功"
- AND 系统 SHALL 显示推送时间

#### Scenario: 推送失败 — 网络错误
- GIVEN 第三方接口无法连接（超时或 DNS 解析失败）
- WHEN 推送操作执行
- THEN 系统 SHALL 记录 `error_message`（如"cURL 错误: 连接超时"）
- AND 系统 SHALL 更新该行状态为红色 ✗ "失败"
- AND 系统 SHALL 在错误信息处显示失败原因摘要

#### Scenario: 推送失败 — 响应不符合成功判定
- GIVEN 第三方接口响应正常但 `success_field` 的值不等于 `success_value`
- WHEN 推送操作执行
- THEN 系统 SHALL 记录响应内容和失败原因
- AND 系统 SHALL 更新该行状态为红色 ✗ "失败"

#### Scenario: 应用未配置推送接口
- GIVEN 某应用未配置对应 action 的推送接口（或接口未启用）
- WHEN 尝试推送
- THEN 系统 SHALL 返回提示"该应用未配置推送接口"
- AND 不记录推送日志

---

### Requirement: REQ-UPUSH-005 全部推送

推送状态弹窗 SHALL 支持"全部推送"按钮，一次性推送所有未推送/失败的应用。

#### Scenario: 全部推送执行
- GIVEN 推送状态弹窗中存在多个未推送或失败的应用
- WHEN 用户点击"全部推送"按钮
- THEN 系统 SHALL 调用 `POST /user/push_all`，参数 `user_id`
- AND 系统 SHALL 依次向每个未推送/失败的应用发送推送请求
- AND 每个应用的推送结果独立记录日志
- AND 弹窗 SHALL 刷新显示所有应用的最新推送状态

#### Scenario: 部分成功部分失败
- GIVEN 全部推送中有 2 个成功、1 个失败
- WHEN 推送操作全部完成
- THEN 系统 SHALL 分别更新每行的状态
- AND 系统 SHALL 以 toast 提示总结："推送完成：2 成功，1 失败"

---

### Requirement: REQ-UPUSH-006 推送日志查看

推送状态弹窗 SHALL 支持查看某次推送的详细日志。

#### Scenario: 查看推送日志
- GIVEN 推送状态弹窗中某行显示"失败"
- WHEN 用户点击该行展开或点击"查看日志"按钮
- THEN 系统 SHALL 调用 `POST /user/push_log`，参数 `user_id` + `site_id`
- AND 系统 SHALL 展示详细日志：请求 URL、请求方法、请求体、响应状态码、响应体、错误信息、推送时间

---

### Requirement: REQ-UPUSH-007 删除用户时级联推送

删除用户时，若该用户已推送到配置了 `delete` 接口的第三方应用，系统 SHALL 自动向第三方发送删除用户请求。

#### Scenario: 删除用户触发级联推送
- GIVEN 用户已推送到应用 A（应用 A 配置了 `delete` 推送接口）
- WHEN 管理员删除该用户
- THEN 系统 SHALL 在事务内先向应用 A 的删除接口发送请求
- AND 系统 SHALL 记录推送日志
- AND 无论第三方响应成功或失败，系统 SHALL 继续删除本地用户
- AND 系统 SHALL 以 toast 提示推送结果："已删除用户，级联推送：1 成功 / 0 失败"

#### Scenario: 未推送过的用户删除
- GIVEN 用户从未推送到任何应用
- WHEN 管理员删除该用户
- THEN 系统 SHALL 直接删除本地用户
- AND 不触发任何级联推送

---

### Requirement: REQ-UPUSH-008 推送服务（PushService）

系统 SHALL 提供 `app/service/PushService.php` 作为推送执行引擎。

#### Scenario: PushService 核心方法
- GIVEN PushService 已实例化
- WHEN 调用 `pushUser(int $userId, int $siteId, string $action)`
- THEN 服务 SHALL 查询 `sc_site_push_api` 获取接口配置
- AND 查询用户数据
- AND 将 `body_template` 中的占位符替换为实际用户数据
- AND 使用 cURL 发送 HTTP 请求
- AND 根据 `success_field` / `success_value` 评估响应
- AND 记录推送日志到 `sc_user_push_log`
- AND 返回 `PushResult` 对象（包含 `is_success`、`error_message`）

#### Scenario: 请求体变量替换
- GIVEN `body_template` 为 `{"username": "{{username}}", "email": "{{email}}"}`
- WHEN 替换用户 `zhangsan`（email: `zhangsan@example.com`）的数据
- THEN 实际请求体 SHALL 为 `{"username": "zhangsan", "email": "zhangsan@example.com"}`

#### Scenario: URL 变量替换
- GIVEN `url` 为 `https://app.example.com/api/users/{{user_id}}`
- WHEN 替换用户 ID 为 5
- THEN 实际请求 URL SHALL 为 `https://app.example.com/api/users/5`

---

### Requirement: REQ-UPUSH-009 推送相关后端接口

User 控制器 SHALL 新增推送管理相关方法。

#### Scenario: pushStatus 接口
- GIVEN 用户 ID 存在
- WHEN 调用 `POST /user/push_status`，参数 `user_id`
- THEN 系统 SHALL 查询所有已配置推送接口的应用
- AND 对每个应用查询最近一次推送日志
- AND 返回：`{ site_id, site_name, has_api_config, last_push: { is_success, push_time, error_message, action } | null }`

#### Scenario: pushUser 接口
- GIVEN 用户 ID 和应用 ID 均存在
- WHEN 调用 `POST /user/push`，参数 `user_id` + `site_id`
- THEN 系统 SHALL 调用 PushService 执行推送
- AND 返回推送结果：`{ is_success, error_message }`

#### Scenario: pushUserToAll 接口
- GIVEN 用户 ID 存在
- WHEN 调用 `POST /user/push_all`，参数 `user_id`
- THEN 系统 SHALL 对所有已配置推送接口的应用依次执行推送
- AND 返回每个应用的推送结果列表

#### Scenario: pushLog 接口
- GIVEN 推送日志存在
- WHEN 调用 `POST /user/push_log`，参数 `user_id` + `site_id`
- THEN 系统 SHALL 返回该用户在该应用的所有推送日志（按时间倒序）

---

## 相关规范

- `openspec/changes/user-push-to-apps/specs/application-push/spec.md` — 推送接口配置（应用侧）
- `openspec/specs/user/spec.md` — 用户模块基础规范
