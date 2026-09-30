# 应用推送接口配置规范（增量变更）

> 变更 ID: `enhance-push-config`
> 前置变更: `user-push-to-apps`（已归档）
> 影响模块: `application`

---

## MODIFIED Requirements

### Requirement: REQ-PUSH-001 推送接口配置存储

> 修改 `sc_site_push_api` 表结构：移除旧字段，新增 appid/appkey 和结构化配置字段。

#### Scenario: 推送接口配置字段（改造后）
- GIVEN 新建或更新推送接口配置
- WHEN 写入 `sc_site_push_api` 表
- THEN 记录 SHALL 包含以下字段：
  - `site_id`（INT，关联 `sc_site.id`）
  - `action`（VARCHAR(16)，操作类型：`create` / `update` / `delete`）
  - `url`（VARCHAR(512)，接口完整 URL）
  - `method`（VARCHAR(8)，HTTP 方法，默认 `POST`）
  - `appid`（VARCHAR(128)，第三方分配的 appid，**新增**）
  - `appkey`（VARCHAR(256)，第三方分配的 appkey，AES-256-CBC 加密存储，**新增**）
  - `extra_headers`（JSON，额外自定义请求头数组，每项 `{name, value}`，**替代旧 `request_headers`**）
  - `body_params`（JSON，请求体参数映射数组，每项 `{name, value, type, required}`，**替代旧 `body_template`**）
  - `response_rules`（JSON，响应验证规则数组，每项 `{field, type, value}`，**替代旧 `success_field` + `success_value`**）
  - `timeout`（INT，请求超时秒数，默认 10）
  - `is_enabled`（TINYINT(1)，是否启用，默认 1）

#### Scenario: site_id + action 唯一约束
- （不变）GIVEN 已存在某应用的 `create` 推送接口配置
- WHEN 尝试插入同应用同 action 的第二条记录
- THEN 系统 SHALL 拒绝写入（UNIQUE KEY 约束冲突）

---

### Requirement: REQ-PUSH-002 添加应用时配置推送接口

> 改造推送配置 UI：新增基础认证区，请求头/请求体/响应验证均改为结构化动态行。

#### Scenario: 推送配置区域展示
- GIVEN 用户打开添加应用弹窗
- WHEN 弹窗加载完成
- THEN 系统 SHALL 在 OAuth 配置区下方显示"推送接口配置"折叠面板
- AND 面板顶部 SHALL 显示"基础认证"子区域（**新增**）
- AND 面板 SHALL 包含三个子配置区：新增用户接口、修改用户接口、删除用户接口

#### Scenario: 基础认证区（新增）
- GIVEN 用户展开推送配置面板
- WHEN 查看"基础认证"子区域
- THEN SHALL 包含：
  - `AppID` 输入框（text，必填，用于 X-trust-appid）
  - `AppKey` 输入框（password 类型，默认隐藏 `••••••••••••`，有"查看"按钮）
- AND appid/appkey SHALL 为应用级别配置（3 个接口共用同一组值）

#### Scenario: 单个推送接口配置字段（改造后）
- GIVEN 用户展开某个推送接口子配置区
- WHEN 查看配置表单
- THEN 表单 SHALL 包含以下字段：
  - 接口地址（URL，必填）
  - 请求方法（下拉选择：`POST` / `PUT` / `DELETE`，默认 `POST`）
  - **自定义请求头**（动态可增删的行，每行有"名称"+"值"，**替代旧 JSON textarea**）
  - **请求体参数**（动态可增删的行，每行有"参数名"+"值"+"类型"(string/bool/int)+"必填"，**替代旧 JSON textarea**）
  - **响应验证规则**（动态可增删的行，每行有"字段"+"类型"(bool/int/string)+"期望值"，**替代旧单字段/值**）
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
  | externalId | `{{username}}` | string | ✓ |
  | email | `{{email}}` | string | |
  | mobile | | string | |
  | status | `{{status_bool}}` | boolean | ✓ |

#### Scenario: 默认预设响应验证规则
- GIVEN 用户首次展开某个接口的验证规则配置区
- WHEN 面板初始化
- THEN 系统 SHALL 默认预设以下规则：

  | 字段 | 类型 | 期望值 |
  |------|------|--------|
  | success | bool | true |
  | code | int | 200 |

#### Scenario: delete 接口无请求体参数
- GIVEN 用户配置 delete 接口
- WHEN 展开 delete 接口配置区
- THEN 系统 SHALL **不**显示"请求体参数"区域（DELETE 请求不传 body）

#### Scenario: 提交时推送配置序列化（改造后）
- GIVEN 用户配置了推送接口
- WHEN 提交添加应用表单
- THEN 请求 SHALL 包含以下字段：
  - `push_appid`（String，必填）
  - `push_appkey`（String，必填）
  - `push_apis`（Array，每项含 `action`、`url`、`method`、`extra_headers`、`body_params`、`response_rules`、`timeout`、`is_enabled`）

---

### Requirement: REQ-PUSH-003 编辑应用时更新推送接口配置

> 回填逻辑适配新字段结构。

#### Scenario: 编辑时回填推送配置（改造后）
- GIVEN 应用已有推送接口配置
- WHEN 用户打开编辑应用弹窗
- THEN 系统 SHALL 调用 `GET /app/push_apis` 获取配置
- AND `appid` SHALL 回填明文
- AND `appkey` SHALL 显示为隐藏状态 `••••••••••••`（不回填明文）
- AND 每个接口的 `extra_headers` SHALL 回填为动态行
- AND 每个接口的 `body_params` SHALL 回填为动态行
- AND 每个接口的 `response_rules` SHALL 回填为动态行

#### Scenario: 编辑后提交更新
- （不变）GIVEN 用户修改了推送接口配置
- WHEN 提交编辑表单
- THEN 系统 SHALL 在事务内先删除该应用旧的 `sc_site_push_api` 记录
- AND 插入新的推送接口配置记录（appkey 加密存储）

---

### Requirement: REQ-PUSH-004 获取应用推送接口配置

> 返回值适配新字段结构，appkey 脱敏。

#### Scenario: 获取成功（改造后）
- GIVEN 应用 ID 存在（`is_del=0`）
- WHEN 调用 `GET /app/push_apis?site_id={id}`
- THEN 系统 SHALL 返回：
  ```json
  {
    "code": 0, "success": true,
    "result": {
      "appid": "889381",
      "has_appkey": true,
      "apis": [{
        "action": "create",
        "url": "...",
        "method": "POST",
        "extra_headers": [{"name": "X-api-version", "value": "1.0-rev0"}],
        "body_params": [{"name": "code", "value": "{{username}}", "type": "string", "required": true}],
        "response_rules": [{"field": "success", "type": "bool", "value": true}],
        "timeout": 10,
        "is_enabled": true
      }]
    }
  }
  ```
- AND `appkey` SHALL NOT 在返回值中出现明文，仅以 `has_appkey: bool` 表示

---

### Requirement: REQ-PUSH-005 详情弹窗展示推送配置

> 展示内容适配新字段结构。

#### Scenario: 详情弹窗展示推送配置（改造后）
- GIVEN 应用已配置推送接口
- WHEN 用户打开详情弹窗
- THEN 系统 SHALL 展示"推送接口配置"区域
- AND 显示 appid（明文）、appkey（隐藏）、每个接口的 URL、method
- AND 以只读列表展示 `extra_headers`、`body_params`、`response_rules`

---

### Requirement: REQ-PUSH-006 推送接口配置参数校验

> 校验规则适配新字段。

#### Scenario: URL 格式校验
- （不变）

#### Scenario: action 枚举校验
- （不变）

#### Scenario: extra_headers 校验（替代旧 request_headers JSON 校验）
- GIVEN `extra_headers` 中某项缺少 `name` 或 `value`
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝并返回错误提示

#### Scenario: body_params 校验（替代旧 body_template JSON 校验）
- GIVEN `body_params` 中某项缺少 `name` 或 `value`，或 `type` 不在 `string`/`boolean`/`int` 范围内
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝并返回错误提示

#### Scenario: response_rules 校验（替代旧 success_field/success_value 校验）
- GIVEN `response_rules` 中某项缺少 `field` 或 `value`，或 `type` 不在 `bool`/`int`/`string` 范围内
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝并返回错误提示

#### Scenario: push_appid / push_appkey 必填校验
- GIVEN 提交了推送配置（`push_apis` 非空）但未提供 `push_appid` 或 `push_appkey`
- WHEN 提交表单
- THEN 验证器 SHALL 拒绝

---

### Requirement: REQ-PUSH-007 删除应用时清理推送配置

> （不变）

---

## ADDED Requirements

### Requirement: REQ-PUSH-008 系统自动签名 Header

每次推送请求时，系统 SHALL 自动计算并填充 7 个签名相关 Header，管理员无需手动配置。

#### Scenario: 自动计算的签名 Header
- GIVEN 推送接口配置了 `appid` 和 `appkey`
- WHEN 系统构建推送请求
- THEN 系统 SHALL 自动计算并添加以下 Header：
  - `Content-Type`: `application/json;charset=UTF-8`
  - `X-trust-signature-version`: `2.0`
  - `X-trust-appid`: 配置的 appid 值
  - `X-trust-timestamp`: 当前 Unix 时间戳（秒）
  - `X-trust-nonce`: 16 位随机字符串（每次请求不同）
  - `Content-MD5-HEX`: POST/PUT 时为请求体 MD5 小写 32 位；DELETE 时为 URL 路径+query 的 MD5
  - `X-trust-signature`: SM3-HMAC(K=appkey, V=排序拼接的上述字段) 结果，32 位小写十六进制

#### Scenario: SM3 签名算法
- GIVEN 签名数据 V 为 7 个字段（`content-md5-hex`、`content-type`、`method`、`x-trust-appid`、`x-trust-nonce`、`x-trust-signature-version`、`x-trust-timestamp`）按字段名字母排序后用 `&` 拼接的字符串
- WHEN 计算签名
- THEN 系统 SHALL 使用 SM3-HMAC 算法，以 appkey 为密钥 K，对 V 进行签名
- AND 输出 32 位小写十六进制字符串作为 `X-trust-signature` 的值

---

### Requirement: REQ-PUSH-009 appkey 加密存储

`appkey` SHALL 使用 AES-256-CBC 加密后存入数据库，不得明文存储。每个应用使用自身 `client_secret` 派生的 AES 密钥加密，实现"按应用独立加密"。

#### Scenario: 加密存储
- GIVEN 用户提交 appkey 明文
- WHEN 保存到 `sc_site_push_api.appkey`
- THEN 系统 SHALL 使用 AES-256-CBC 加密
- AND 加密密钥 SHALL 从该应用的 `sc_site.client_secret` 经 SHA-256 派生得到 32 字节密钥
- AND 不同应用使用不同的 client_secret，因此每个应用的 appkey 加密密钥彼此独立

#### Scenario: 解密使用
- GIVEN 推送时需要计算签名
- WHEN 读取 appkey
- THEN 系统 SHALL 先查询该应用的 `client_secret`
- AND 经 SHA-256 派生 AES 密钥后解密 appkey
- AND 再用于 SM3 签名计算

#### Scenario: client_secret 重置后
- GIVEN 管理员重置了某应用的 `client_secret`
- WHEN 旧密钥派生的 AES 密钥与新的不同
- THEN 已存储的加密 appkey SHALL 无法解密
- AND 管理员需要在重置 client_secret 后重新配置推送 appkey

#### Scenario: 日志脱敏
- WHEN 记录推送日志
- THEN 日志中 SHALL NOT 记录 appkey 明文
