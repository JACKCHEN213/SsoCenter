# 用户推送管理规范

> 影响模块: `user`

---

## Purpose

在用户管理页面提供推送功能，管理员可查看用户推送到各第三方应用的状态，手动触发推送/重推，查看推送日志，并在删除用户时自动向第三方系统发送删除请求。支持 SM3 签名认证、结构化参数构建、多规则响应验证和 externalId 跟踪。

---

## Requirements

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
  - `request_headers`（JSON，实际发送的请求头，脱敏不含签名）
  - `request_body`（TEXT，实际发送的请求体）
  - `response_code`（INT，HTTP 响应状态码，可为 NULL）
  - `response_body`（TEXT，响应体，截取前 2000 字符，可为 NULL）
  - `external_id`（VARCHAR(128)，第三方返回的 externalId，创建成功时保存）
  - `is_success`（TINYINT(1)，是否推送成功）
  - `failed_rule`（VARCHAR(256)，未满足的验证规则描述，可为 NULL）
  - `error_message`（VARCHAR(512)，网络错误信息，可为 NULL）
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

#### Scenario: 推送状态表格展示
- GIVEN 推送状态弹窗已打开
- WHEN 数据加载完成
- THEN 弹窗 SHALL 展示表格，列包含：应用名称、操作类型、推送状态、externalId、推送时间、操作
- AND 每行 SHALL 对应一个已配置推送接口（`is_enabled=1`）的应用

#### Scenario: 推送状态图标
- GIVEN 某应用有推送日志
- WHEN 渲染推送状态列
- THEN 系统 SHALL 按以下规则显示状态：
  - 最近一次推送成功 → 绿色 ✓ + "成功"
  - 最近一次推送失败 → 红色 ✗ + "失败"，下方显示失败原因（优先 `failed_rule`，其次 `error_message`）
  - 无推送记录 → 灰色 ○ + "未推送"

#### Scenario: externalId 列展示
- GIVEN 某应用推送成功且保存了 externalId
- WHEN 渲染 externalId 列
- THEN 系统 SHALL 显示该应用的 externalId 值
- AND 未推送或推送失败时显示 "—"

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
- AND 系统 SHALL 执行以下流程：
  1. 根据 `body_params` 结构化构建请求体（按 type 转换值类型，替换 `{{变量}}`）
  2. 自动计算 SM3 签名 Header（Content-MD5-HEX、X-trust-signature 等）
  3. 合并 `extra_headers` 中的自定义 Header
  4. 使用 Guzzle HTTP 客户端发送请求
  5. 根据 `response_rules` 多规则验证响应（所有规则 AND 关系）
  6. 记录推送日志（含 `request_headers`、`failed_rule`）
  7. 如成功且返回 `externalId`，保存到用户表
- AND 弹窗 SHALL 刷新显示最新推送状态

#### Scenario: 推送成功
- GIVEN 第三方接口返回符合所有 `response_rules` 的响应
- WHEN 推送操作完成
- THEN 系统 SHALL 更新该行状态为绿色 ✓ "成功"
- AND 显示推送时间和 externalId

#### Scenario: 推送失败 — 响应验证不通过
- GIVEN 第三方接口响应正常但不满足某条 `response_rules`
- WHEN 推送操作执行
- THEN 系统 SHALL 记录 `failed_rule`（如 `code: 期望 200(int), 实际 500(int)`）
- AND 更新状态为红色 ✗ "失败"
- AND 在状态下方显示失败规则描述

#### Scenario: 推送失败 — 网络错误
- GIVEN 第三方接口无法连接（超时或 DNS 解析失败）
- WHEN 推送操作执行
- THEN 系统 SHALL 记录 `error_message`（如"连接失败: ..."）
- AND 更新状态为红色 ✗ "失败"

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
- AND 系统 SHALL 依次向每个应用发送推送请求
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
- GIVEN 推送状态弹窗中某行有推送记录
- WHEN 用户点击该行"日志"按钮
- THEN 系统 SHALL 调用 `POST /user/push_log`，参数 `user_id` + `site_id`
- AND 系统 SHALL 展示详细日志：请求 URL、请求方法、请求头（脱敏）、请求体、响应状态码、响应体、externalId、失败规则描述、错误信息、推送时间

---

### Requirement: REQ-UPUSH-007 删除用户时级联推送

删除用户时，若该用户已推送到配置了 `delete` 接口的第三方应用，系统 SHALL 自动向第三方发送删除用户请求。

#### Scenario: 删除用户触发级联推送
- GIVEN 用户已推送到应用 A（应用 A 配置了 `delete` 推送接口且用户有 `externalId`）
- WHEN 管理员删除该用户
- THEN 系统 SHALL 在事务内：
  1. 向应用 A 的 delete 接口发送请求（URL 中 `{{external_id}}` 替换为实际值）
  2. 自动计算 SM3 签名 Header
  3. Content-MD5-HEX 计算 URL 路径+query 的 MD5（非请求体）
  4. 记录推送日志
  5. 无论第三方响应成功或失败，继续删除本地用户
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
- THEN 服务 SHALL：
  1. 查询 `sc_site_push_api` 获取接口配置（含 `extra_headers`、`body_params`、`response_rules`）
  2. 通过 `site_id` 关联查询 `sc_site` 获取 `client_id`（作为 appid）和 `client_secret`（作为签名密钥）
  3. 查询用户数据
  4. 获取 externalId（update/delete 时）
  5. 根据 `body_params` 结构化构建请求体（按 type 转换值类型，替换 `{{变量}}`）
  6. 自动调用 SignatureService 计算签名 Header（使用 client_id 和 client_secret）
  7. 合并 `extra_headers` 自定义 Header
  8. 使用 Guzzle HTTP 客户端发送请求（替代已弃用的 cURL）
  9. 根据 `response_rules` 多规则验证响应（所有规则 AND 关系，支持 bool/int/string 类型）
  10. 提取并保存 externalId（创建成功时）
  11. 记录推送日志（含 `request_headers`、`external_id`、`failed_rule`）
  12. 返回 `PushResult` 对象（包含 `is_success`、`external_id`、`failed_rule`、`error_message`）

#### Scenario: 请求体变量替换
- GIVEN `body_params` 为 `[{name:"code", value:"{{username}}", type:"string"}, {name:"status", value:"{{status_bool}}", type:"boolean"}]`
- WHEN 替换用户 `zhangsan`（status=1）的数据
- THEN 实际请求体 SHALL 为 `{"code":"zhangsan","status":true}`

#### Scenario: 响应多规则验证
- GIVEN `response_rules` 为 `[{field:"success", type:"boolean", value:"true"}, {field:"code", type:"int", value:"200"}]`
- WHEN 第三方返回 `{"success":true, "code":200}`
- THEN 系统 SHALL 依次验证每条规则
- AND 所有规则通过时判定为成功

#### Scenario: 响应验证失败描述
- GIVEN `response_rules` 包含 `{field:"code", type:"int", value:"200"}`
- WHEN 第三方返回 `{"code":500}`
- THEN 系统 SHALL 记录 `failed_rule` 为 `code: 期望 200(int), 实际 500(int)`

---

### Requirement: REQ-UPUSH-009 推送相关后端接口

User 控制器 SHALL 新增推送管理相关方法。

#### Scenario: pushStatus 接口
- GIVEN 用户 ID 存在
- WHEN 调用 `POST /user/push_status`，参数 `user_id`
- THEN 返回每条记录 SHALL 包含：`{ site_id, site_name, external_id, last_push: { is_success, push_time, external_id, failed_rule, error_message, action } | null }`

#### Scenario: pushUser 接口
- GIVEN 用户 ID 和应用 ID 均存在
- WHEN 调用 `POST /user/push`，参数 `user_id` + `site_id`
- THEN 返回 SHALL 为：`{ is_success, external_id, failed_rule, error_message }`

#### Scenario: pushUserToAll 接口
- GIVEN 用户 ID 存在
- WHEN 调用 `POST /user/push_all`，参数 `user_id`
- THEN 系统 SHALL 对所有已配置推送接口的应用依次执行推送
- AND 返回每个应用的推送结果列表（含 `external_id`、`failed_rule`）

#### Scenario: pushLog 接口
- GIVEN 推送日志存在
- WHEN 调用 `POST /user/push_log`，参数 `user_id` + `site_id`
- THEN 返回每条日志 SHALL 包含：`url, method, request_headers, request_body, response_code, response_body, external_id, is_success, failed_rule, error_message, push_time`

---

### Requirement: REQ-UPUSH-010 sc_user 表扩展 external_ids

`sc_user` 表 SHALL 新增 `external_ids` 字段，存储用户在各第三方系统中的 externalId 映射。

#### Scenario: external_ids 字段
- GIVEN 用户推送成功，第三方返回 `externalId`
- WHEN 系统记录推送结果
- THEN 系统 SHALL 更新 `sc_user.external_ids` 字段
- AND 格式为 JSON 对象：`{"1": "ext_id_for_app1", "2": "ext_id_for_app2"}`（key 为 site_id）

#### Scenario: 首次写入
- GIVEN 用户 `external_ids` 为 NULL
- WHEN 第一次推送成功
- THEN 系统 SHALL 创建 JSON 对象并写入：`{"1": "ext_id_1"}`

#### Scenario: 追加写入
- GIVEN 用户 `external_ids` 为 `{"1": "ext_1"}`
- WHEN 向应用 2 推送成功，返回 `ext_2`
- THEN 系统 SHALL 更新为 `{"1": "ext_1", "2": "ext_2"}`

---

### Requirement: REQ-UPUSH-011 SignatureService 签名服务

系统 SHALL 提供 `app/service/SignatureService.php`，自动计算 SM3 签名 Header。

#### Scenario: sign 方法
- GIVEN 请求体 body、URL 路径 urlPath、HTTP 方法 method、appid、appkey
- WHEN 调用 `sign(body, urlPath, method, appid, appkey)`
- THEN SHALL 返回完整签名 Header 数组：
  - `Content-Type`: `application/json;charset=UTF-8`
  - `X-trust-signature-version`: `2.0`
  - `X-trust-appid`: appid 值
  - `X-trust-timestamp`: 当前 Unix 秒级时间戳
  - `X-trust-nonce`: 16 位随机字符串
  - `Content-MD5-HEX`: POST/PUT 为 md5(body)；DELETE 为 md5(urlPath+query)
  - `X-trust-signature`: SM3-HMAC(K=appkey, V=排序拼接串) 的 32 位小写十六进制

---

### Requirement: REQ-UPUSH-012 EncryptService 加解密服务（保留但推送不再使用）

系统 SHALL 提供 `app/service/EncryptService.php`，用于应用级敏感数据的 AES-256-CBC 加解密。

> **注意**：推送功能不再使用 EncryptService。签名凭证（client_id 和 client_secret）直接通过 site_id 从 sc_site 表获取，无需加密存储 appkey。此服务保留供其他可能需要的场景使用。

#### Scenario: encrypt 方法
- GIVEN 明文和应用级 secret
- WHEN 调用 `encrypt(plaintext, appSecret)`
- THEN SHALL 使用 SHA-256 从 `appSecret` 派生 32 字节 AES 密钥
- AND 返回 AES-256-CBC 加密后的密文（IV + 密文，Base64 编码）

#### Scenario: decrypt 方法
- GIVEN 密文和应用级 secret
- WHEN 调用 `decrypt(ciphertext, appSecret)`
- THEN SHALL 使用相同的密钥派生方式还原明文
- AND 若 `appSecret` 不匹配，SHALL 抛出异常

---

### Requirement: REQ-UPUSH-013 HTTP 客户端改用 Guzzle

PushService 的 HTTP 请求 SHALL 使用 Guzzle HTTP 客户端，替代 PHP 8.5 已弃用的 cURL 扩展。

#### Scenario: Guzzle 依赖
- GIVEN 项目需要发送 HTTP 请求到第三方系统
- WHEN 安装依赖
- THEN 系统 SHALL 通过 `composer require guzzlehttp/guzzle` 安装 Guzzle

#### Scenario: 请求发送
- GIVEN PushService 需要发送推送请求
- WHEN 调用 `sendRequest(method, url, headers, body, timeout)`
- THEN 系统 SHALL 使用 `GuzzleHttp\Client` 发送请求
- AND SHALL NOT 使用 `curl_init()` / `curl_close()` 等已弃用函数

#### Scenario: 异常处理
- GIVEN 第三方系统无法连接（超时、DNS 解析失败）
- WHEN Guzzle 抛出 `ConnectException`
- THEN 系统 SHALL 捕获异常并记录 `error_message`（如 "连接失败: ..."）

#### Scenario: HTTP 错误状态码
- GIVEN 第三方系统返回 4xx/5xx 状态码
- WHEN 请求完成
- THEN 系统 SHALL 正常读取响应体并交给 `response_rules` 验证
- AND SHALL NOT 因 HTTP 错误状态码抛出异常（`http_errors: false`）

---

## 相关规范

- `openspec/specs/application-push/spec.md` — 推送接口配置（应用侧）
