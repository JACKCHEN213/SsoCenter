# 提案：增强推送配置 — SM3 签名与结构化参数

## 变更 ID

`enhance-push-config`

## 背景与动机

`user-push-to-apps` 变更已实现用户推送到第三方应用的基础功能（应用侧配置推送接口、用户侧推送管理）。但在实际对接中发现，第三方系统遵循 SCIM v2 风格的账号同步接口规范（详见 `extend/账号同步说明.md`），要求：

1. **SM3 签名认证**：每次请求携带 7 个签名相关 Header，其中 `X-trust-signature` 由国密 SM3 算法计算
2. **固定请求体字段**：`code`、`name`、`externalId`、`email`、`status` 等 SCIM 标准字段
3. **统一响应结构**：`{ success, code, message, title, externalId, data }` 的标准 JSON
4. **externalId 回传**：创建用户时第三方返回 `externalId`，后续修改/删除操作以此为参数

### 现有实现的问题

| 问题 | 现状 | 应改为 |
|------|------|--------|
| 请求头配置 | JSON textarea 自由填写 | 7 个签名 Header **系统自动计算**；额外 Header 以**动态可增删的行**（name + value）添加 |
| 请求体参数 | JSON textarea 自由模板 | **动态可增删的表格行**，每行有 `参数名`/`值`/`类型`/`必填`；默认预设 SCIM 标准字段 |
| 响应验证 | 单个 `success_field` + `success_value`（仅字符串比较） | **多条验证规则**，每条有 `字段`/`类型`（bool/int/string）/`期望值`，AND 关系 |
| 签名能力 | 无 | 自动计算 SM3 签名（Content-MD5-HEX、X-trust-signature 等） |
| externalId | 不追踪 | 创建成功后保存 `externalId`，修改/删除时自动携带 |
| appkey 安全 | 无 | AES-256-CBC 加密存储，日志脱敏 |

## 变更目标

### 1. 数据库改造

- `sc_site_push_api` 表：
  - **移除** `request_headers`、`body_template`、`success_field`、`success_value`
  - **新增** `appid`、`appkey`（加密）、`extra_headers`（JSON 数组）、`body_params`（JSON 数组）、`response_rules`（JSON 数组）
- `sc_user_push_log` 表：
  - **新增** `request_headers`（JSON）、`external_id`、`failed_rule`
- `sc_user` 表：
  - **新增** `external_ids`（JSON）

### 2. SM3 签名引擎

新增 `SignatureService.php`，自动计算 7 个签名 Header：
- `Content-Type`、`X-trust-signature-version`、`X-trust-appid`、`X-trust-timestamp`、`X-trust-nonce`、`Content-MD5-HEX`、`Method` → 排序拼接
- `X-trust-signature` = SM3-HMAC(K=appkey, V=排序拼接串)

### 3. 推送配置 UI 改造

- 基础认证区：appid + appkey 输入框（应用级别）
- 请求头：动态可增删的 name-value 行（替代 JSON textarea）
- 请求体参数：动态可增删的 name-value-type-required 行（替代 JSON textarea），默认预设 6 个 SCIM 字段
- 响应验证规则：动态可增删的 field-type-value 行（替代单字段/值），默认预设 `success==true` + `code==200`

### 4. 推送引擎改造

- PushService 自动调用 SignatureService 计算签名
- 根据 `body_params` 结构化构建请求体（支持 `{{变量}}` 替换）
- 根据 `response_rules` 多规则验证响应（支持 bool/int/string 类型匹配）
- 创建成功后保存 `externalId`，修改/删除时自动携带

## 涉及范围

| 范围 | 说明 |
|------|------|
| **依赖** | `composer require guzzlehttp/guzzle`（替代已弃用的 cURL） |
| **数据库迁移** | `sc_site_push_api` 表结构改造、`sc_user_push_log` 新增字段、`sc_user` 新增 `external_ids` |
| **新增服务** | `SignatureService.php`（SM3 签名）、`EncryptService.php`（AES 加解密） |
| **改造服务** | `PushService.php`（签名、结构化参数、多规则验证、externalId 管理、Guzzle 替代 cURL） |
| **改造页面** | `index.html`（推送配置 UI 全面改造）、`user.html`（推送状态弹窗增加 externalId 列） |
| **改造控制器** | `Application`（推送配置接口适配）、`User`（推送接口适配） |

## 影响分析

- **数据迁移**：已有推送配置需要从旧字段迁移到新字段（`request_headers` → `extra_headers`，`body_template` → `body_params`，`success_field/success_value` → `response_rules`）
- **向后兼容**：迁移脚本需处理存量数据；迁移后旧字段可保留但不再使用
- **安全**：appkey 必须加密存储
