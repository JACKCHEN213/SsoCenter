# 应用推送接口配置规范

> 影响模块: `application`

---

## Purpose

允许管理员在注册第三方应用时配置用户推送接口（新增/修改/删除），使得 SSO Center 可以将用户数据同步推送到第三方系统，实现用户数据的一致性。支持 SM3 签名认证、结构化请求参数、多规则响应验证。

---

## Requirements

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
  - `extra_headers`（JSON，额外自定义请求头数组，每项 `{name, value}`）
  - `body_params`（JSON，请求体参数映射数组，每项 `{name, value, type, required}`）
  - `response_rules`（JSON，响应验证规则数组，每项 `{field, type, value}`）
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
  - 自定义请求头（动态可增删的行，每行有"名称"+"值"）
  - 请求体参数（动态可增删的行，每行有"参数名"+"值"+"类型"(string/bool/int)+"必填"）
  - 响应验证规则（动态可增删的行，每行有"字段"+"类型"(bool/int/string)+"期望值"）
  - 超时时间（number，默认 10 秒）
  - 启用开关（checkbox，默认勾选）

#### Scenario: 默认预设请求体参数
- GIVEN 用户首次展开 create 或 update 接口的参数配置区
- WHEN 面板初始化
- THEN 系统 SHALL 默认预设以下 6 个 SCIM 标准参数：

  | 参数名 | 值 | 类型 | 必填 |
  |--------|------|------|------|
  | code | `{{username}}` | string | ✓ |
  | name | `{{username}}` | string | ✓ |
  | externalId | `{{user_id}}` | string | |
  | email | `{{email}}` | string | |
  | mobile | | string | |
  | status | `{{status}}` | int | |

#### Scenario: 默认预设响应验证规则
- GIVEN 用户首次展开某个接口的验证规则配置区
- WHEN 面板初始化
- THEN 系统 SHALL 默认预设以下规则：

  | 字段 | 类型 | 期望值 |
  |------|------|--------|
  | success | boolean | true |
  | code | int | 200 |

#### Scenario: delete 接口无请求体参数
- GIVEN 用户配置 delete 接口
- WHEN 展开 delete 接口配置区
- THEN 系统 SHALL **不**显示"请求体参数"和"响应验证规则"区域（DELETE 请求不传 body）

#### Scenario: 提交时推送配置序列化
- GIVEN 用户配置了推送接口
- WHEN 提交添加应用表单
- THEN 请求 SHALL 包含以下字段：
  - `push_apis`（Array，每项含 `action`、`url`、`method`、`extra_headers`、`body_params`、`response_rules`、`timeout`、`is_enabled`）

---

### Requirement: REQ-PUSH-003 编辑应用时更新推送接口配置

编辑应用时 SHALL 回填已有的推送接口配置，并支持修改。

#### Scenario: 编辑时回填推送配置
- GIVEN 应用已有推送接口配置
- WHEN 用户打开编辑应用弹窗
- THEN 系统 SHALL 调用 `GET /app/push_apis` 获取配置
- AND 每个接口的 `extra_headers`、`body_params`、`response_rules` SHALL 回填为动态行

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
- THEN 系统 SHALL 返回该应用的所有推送接口配置
- AND 返回格式包含每个接口的 `extra_headers`、`body_params`、`response_rules`

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
- AND 以只读列表展示每个接口的 URL、method、extra_headers、body_params、response_rules

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

#### Scenario: extra_headers 校验
- GIVEN `extra_headers` 中某项缺少 `name`
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝并返回错误提示

#### Scenario: body_params 校验
- GIVEN `body_params` 中某项缺少 `name`，或 `type` 不在 `string`/`boolean`/`int` 范围内
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝并返回错误提示

#### Scenario: response_rules 校验
- GIVEN `response_rules` 中某项缺少 `field`，或 `type` 不在 `bool`/`int`/`string` 范围内
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝并返回错误提示

---

### Requirement: REQ-PUSH-007 删除应用时清理推送配置

删除应用时 SHALL 同时清理 `sc_site_push_api` 表中该应用的所有记录。

#### Scenario: 删除应用级联清理推送配置
- GIVEN 应用已配置推送接口
- WHEN 发送 `DELETE /app/delete`
- THEN 系统 SHALL 在事务内同时删除 `sc_site_push_api` 中该应用的所有记录

---

### Requirement: REQ-PUSH-008 系统自动签名 Header

每次推送请求时，系统 SHALL 自动计算并填充签名相关 Header，管理员无需手动配置。

#### Scenario: 签名凭证来源
- GIVEN 应用已配置推送接口
- WHEN 系统构建推送请求
- THEN 系统 SHALL 通过 `site_id` 关联查询 `sc_site` 表获取签名凭证：
  - `client_id` 作为 `X-trust-appid` 的值
  - `client_secret` 作为 SM3-HMAC 签名的密钥（appkey）

#### Scenario: 自动计算的签名 Header
- GIVEN 通过 site_id 获取到 client_id 和 client_secret
- WHEN 系统构建推送请求
- THEN 系统 SHALL 自动计算并添加以下 Header：
  - `Content-Type`: `application/json;charset=UTF-8`
  - `X-trust-signature-version`: `2.0`
  - `X-trust-appid`: 该应用的 `client_id` 值
  - `X-trust-timestamp`: 当前 Unix 时间戳（秒）
  - `X-trust-nonce`: 16 位随机字符串（每次请求不同）
  - `Content-MD5-HEX`: POST/PUT 时为请求体 MD5 小写 32 位；DELETE 时为 URL 路径+query 的 MD5
  - `X-trust-signature`: SM3-HMAC(K=client_secret, V=排序拼接的上述字段) 结果，32 位小写十六进制

#### Scenario: SM3 签名算法
- GIVEN 签名数据 V 为 7 个字段（`content-md5-hex`、`content-type`、`method`、`x-trust-appid`、`x-trust-nonce`、`x-trust-signature-version`、`x-trust-timestamp`）按字段名字母排序后用 `&` 拼接的字符串
- WHEN 计算签名
- THEN 系统 SHALL 使用 SM3-HMAC 算法，以 `client_secret` 为密钥 K，对 V 进行签名
- AND 输出 32 位小写十六进制字符串作为 `X-trust-signature` 的值

---

### Requirement: REQ-PUSH-009 签名凭证简化

签名凭证（client_id 和 client_secret）SHALL 直接从 `sc_site` 表通过 `site_id` 关联获取，无需在 `sc_site_push_api` 表中额外存储。

#### Scenario: 无需手动配置凭证
- GIVEN 管理员配置推送接口
- WHEN 填写推送接口信息
- THEN 系统 SHALL 自动使用应用的 `client_id` 作为 appid、`client_secret` 作为签名密钥
- AND 管理员无需手动输入 appid/appkey

#### Scenario: 日志脱敏
- WHEN 记录推送日志
- THEN 日志中 SHALL NOT 记录 `client_secret` 明文
