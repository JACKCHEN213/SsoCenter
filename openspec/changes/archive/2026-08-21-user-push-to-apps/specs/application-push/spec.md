# 应用推送接口配置规范（增量变更）

> 变更 ID: `user-push-to-apps`
> 影响模块: `application`

---

## ADDED Requirements

### Requirement: REQ-PUSH-001 推送接口配置存储

系统 SHALL 提供 `sc_site_push_api` 表，用于存储每个应用的推送接口配置。一个应用最多 3 条记录（`create` / `update` / `delete` 各一条）。

#### Scenario: 推送接口配置字段
- GIVEN 新建或更新推送接口配置
- WHEN 写入 `sc_site_push_api` 表
- THEN 记录 SHALL 包含以下字段：
  - `site_id`（INT，关联 `sc_site.id`）
  - `action`（VARCHAR(16)，操作类型：`create` / `update` / `delete`）
  - `url`（VARCHAR(512)，接口完整 URL）
  - `method`（VARCHAR(8)，HTTP 方法，默认 `POST`）
  - `request_headers`（JSON，请求头键值对）
  - `body_template`（JSON，请求体模板，支持 `{{username}}` / `{{email}}` / `{{password}}` / `{{user_id}}` / `{{status}}` / `{{ip}}` 占位符）
  - `success_field`（VARCHAR(64)，响应中判断成功的字段名，默认 `code`）
  - `success_value`（VARCHAR(64)，成功的字段值，默认 `200`）
  - `timeout`（INT，请求超时秒数，默认 10）
  - `is_enabled`（TINYINT(1)，是否启用，默认 1）

#### Scenario: site_id + action 唯一约束
- GIVEN 已存在某应用的 `create` 推送接口配置
- WHEN 尝试插入同应用同 action 的第二条记录
- THEN 系统 SHALL 拒绝写入（UNIQUE KEY 约束冲突）

---

### Requirement: REQ-PUSH-002 添加应用时配置推送接口

添加应用表单 SHALL 包含"推送接口配置"区域，管理员可填写第三方系统提供的用户新增/修改/删除接口。

#### Scenario: 推送配置区域展示
- GIVEN 用户打开添加应用弹窗
- WHEN 弹窗加载完成
- THEN 系统 SHALL 在 OAuth 配置区下方显示"推送接口配置"折叠面板
- AND 面板 SHALL 包含三个子配置区：新增用户接口、修改用户接口、删除用户接口

#### Scenario: 单个推送接口配置字段
- GIVEN 用户展开某个推送接口子配置区
- WHEN 查看配置表单
- THEN 表单 SHALL 包含以下字段：
  - 接口地址（URL，必填）
  - 请求方法（下拉选择：`POST` / `PUT` / `DELETE`，默认 `POST`）
  - 请求头（JSON 格式 textarea，可选）
  - 请求体模板（JSON 格式 textarea，可选，支持 `{{变量名}}` 占位符）
  - 成功判定字段（text，默认 `code`）
  - 成功判定值（text，默认 `200`）
  - 超时时间（number，默认 10 秒）
  - 启用开关（checkbox，默认勾选）

#### Scenario: 仅填写部分接口
- GIVEN 用户只配置了"新增用户接口"，未配置修改和删除接口
- WHEN 提交表单
- THEN 系统 SHALL 仅保存 `action=create` 的推送接口配置
- AND 不创建 `update` / `delete` 的接口配置记录

#### Scenario: 提交时推送配置序列化
- GIVEN 用户配置了一个或多个推送接口
- WHEN 提交添加应用表单
- THEN 请求 SHALL 包含 `push_apis` 参数，值为 JSON 数组，每项包含 `action`、`url`、`method`、`request_headers`、`body_template`、`success_field`、`success_value`、`timeout`、`is_enabled`

---

### Requirement: REQ-PUSH-003 编辑应用时更新推送接口配置

编辑应用时 SHALL 回填已有的推送接口配置，并支持修改。

#### Scenario: 编辑时回填推送配置
- GIVEN 应用已有推送接口配置
- WHEN 用户打开编辑应用弹窗
- THEN 系统 SHALL 调用 `GET /app/push_apis` 获取配置
- AND 将已配置的接口信息回填到对应子配置区

#### Scenario: 编辑后提交更新
- GIVEN 用户修改了推送接口配置
- WHEN 提交编辑表单
- THEN 系统 SHALL 在事务内先删除该应用旧的 `sc_site_push_api` 记录
- AND 插入新的推送接口配置记录

---

### Requirement: REQ-PUSH-004 获取应用推送接口配置

系统 SHALL 提供接口返回某应用的所有推送接口配置。

#### Scenario: 获取成功
- GIVEN 应用 ID 存在（`is_del=0`）
- WHEN 调用 `GET /app/push_apis?site_id={id}`
- THEN 系统 SHALL 返回该应用的所有推送接口配置（最多 3 条）
- AND 返回格式：`{ code: 0, success: true, result: [{ action, url, method, request_headers, body_template, success_field, success_value, timeout, is_enabled }] }`

#### Scenario: 无推送配置
- GIVEN 应用未配置任何推送接口
- WHEN 调用 `GET /app/push_apis?site_id={id}`
- THEN 系统 SHALL 返回空数组：`{ code: 0, success: true, result: [] }`

---

### Requirement: REQ-PUSH-005 详情弹窗展示推送配置

应用详情弹窗 SHALL 展示该应用的推送接口配置（只读）。

#### Scenario: 详情弹窗展示推送配置
- GIVEN 应用已配置推送接口
- WHEN 用户打开详情弹窗
- THEN 系统 SHALL 在详情弹窗中展示"推送接口配置"区域
- AND 以只读方式展示每个已配置接口的地址、方法、成功判定规则

#### Scenario: 无推送配置
- GIVEN 应用未配置推送接口
- WHEN 用户打开详情弹窗
- THEN 系统 SHALL 显示"未配置推送接口"提示

---

### Requirement: REQ-PUSH-006 推送接口配置参数校验

`push_apis` 参数 SHALL 经过验证器校验。

#### Scenario: URL 格式校验
- GIVEN `push_apis` 中某项的 `url` 不是合法 URL
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝并返回错误提示

#### Scenario: action 枚举校验
- GIVEN `push_apis` 中某项的 `action` 不在 `create` / `update` / `delete` 范围内
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝并返回错误提示

#### Scenario: request_headers / body_template JSON 格式校验
- GIVEN `push_apis` 中某项的 `request_headers` 或 `body_template` 不是合法 JSON
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝并返回 JSON 格式错误提示

---

### Requirement: REQ-PUSH-007 删除应用时清理推送配置

删除应用时 SHALL 同时清理 `sc_site_push_api` 表中该应用的所有记录。

#### Scenario: 删除应用级联清理推送配置
- GIVEN 应用已配置推送接口
- WHEN 发送 `DELETE /app/delete`
- THEN 系统 SHALL 在事务内同时删除 `sc_site_push_api` 中该应用的所有记录
