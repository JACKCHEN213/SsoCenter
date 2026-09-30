# 设计方案：增强推送配置（enhance-push-config）

> 本文档仅描述与 `user-push-to-apps` 的**差异和改造点**，未提及的部分沿用原有设计。

---

## 一、数据库改造

### 1.1 sc_site_push_api 表改造

#### 移除字段

| 字段 | 替代方案 |
|------|----------|
| `request_headers`（JSON） | → `extra_headers`（JSON 数组） |
| `body_template`（JSON） | → `body_params`（JSON 数组） |
| `success_field`（VARCHAR） | → `response_rules`（JSON 数组） |
| `success_value`（VARCHAR） | → `response_rules`（JSON 数组） |

#### 新增字段

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `appid` | VARCHAR(128) | NOT NULL | 第三方分配的 appid（用于 X-trust-appid） |
| `appkey` | VARCHAR(256) | NOT NULL | 第三方分配的 appkey（AES-256-CBC 加密存储） |
| `extra_headers` | JSON | NULL | 额外自定义请求头数组：`[{name, value}, ...]` |
| `body_params` | JSON | NULL | 请求体参数映射数组：`[{name, value, type, required}, ...]` |
| `response_rules` | JSON | NOT NULL | 响应验证规则数组：`[{field, type, value}, ...]` |

#### 迁移 SQL（示意）

```sql
-- 新增字段
ALTER TABLE sc_site_push_api
  ADD COLUMN appid VARCHAR(128) NOT NULL DEFAULT '' AFTER method,
  ADD COLUMN appkey VARCHAR(256) NOT NULL DEFAULT '' AFTER appid,
  ADD COLUMN extra_headers JSON NULL AFTER appkey,
  ADD COLUMN body_params JSON NULL AFTER extra_headers,
  ADD COLUMN response_rules JSON NOT NULL AFTER body_params;

-- 迁移旧数据（需要手动确认 appid/appkey，此处仅迁移结构）
-- request_headers (JSON object) → extra_headers (JSON array of {name, value})
-- body_template (JSON object) → body_params (JSON array of {name, value, type, required})
-- success_field/success_value → response_rules (JSON array of {field, type, value})

-- 迁移完成后移除旧字段
ALTER TABLE sc_site_push_api
  DROP COLUMN request_headers,
  DROP COLUMN body_template,
  DROP COLUMN success_field,
  DROP COLUMN success_value;
```

### 1.2 sc_user_push_log 表新增字段

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `request_headers` | JSON | 实际发送的请求头（脱敏，不含 appkey） |
| `external_id` | VARCHAR(128) | 第三方返回的 externalId（创建成功时保存） |
| `failed_rule` | VARCHAR(256) | 未满足的验证规则描述 |

### 1.3 sc_user 表新增字段

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `external_ids` | JSON | 各应用的 externalId 映射：`{"1": "ext_id_1", "2": "ext_id_2"}`（key 为 site_id） |

---

## 二、SM3 签名引擎

### 2.1 SignatureService

**文件**：`app/service/SignatureService.php`

参照 `extend/账号同步说明.md` 第 1.2 节实现：

```
sign(body, urlPath, method, appid, appkey) → 返回完整签名 Header 数组
```

#### 签名步骤

```
步骤 1：计算 Content-MD5-HEX
  - POST/PUT：md5(request body)，32 位小写十六进制
  - DELETE：md5(urlPath + query)，32 位小写十六进制

步骤 2：构造 7 个字段
  content-type=application/json;charset=UTF-8
  x-trust-signature-version=2.0
  x-trust-appid={appid}
  x-trust-timestamp={当前 Unix 秒级时间戳}
  x-trust-nonce={16 位随机字符串}
  content-md5-hex={步骤 1}
  method={HTTP 方法}

步骤 3：按字段名字母排序，用 & 拼接 → V

步骤 4：SM3-HMAC(K=appkey, data=V) → 32 位小写十六进制签名

步骤 5：返回 Header 数组
```

#### SM3 依赖

PHP 环境需要 SM3 支持。方案：
- 优先检查 `openssl` 扩展是否支持 SM3（`openssl_get_cipher_methods()`）
- 否则引入纯 PHP SM3 实现（如 `zgabancev/sm3` 或自行实现）

---

## 三、推送配置 UI 改造

### 3.1 基础认证区（新增）

在推送接口配置面板顶部新增：

```
┌── 基础认证 ─────────────────────────────────────────────────┐
│ AppID:    [889381                                     ]    │
│ AppKey:   [•••••••••••• [查看]                        ]    │
└─────────────────────────────────────────────────────────────┘
```

- appid 和 appkey 为应用级别配置，所有 3 个接口共用
- appkey 默认隐藏，点击"查看"弹出确认后显示明文
- 编辑时 appkey 不回填明文，留空表示不修改

### 3.2 请求头（改造）

**旧**：JSON textarea
**新**：动态可增删的行表格

```
── 自定义请求头（点击 + 添加）──
┌──────────────────┬──────────────────┬────┐
│ 名称              │ 值                │    │
├──────────────────┼──────────────────┼────┤
│ X-api-version    │ 1.0-rev0         │ [×] │
└──────────────────┴──────────────────┴────┘
[+ 添加请求头]
```

- 7 个签名 Header 由系统自动计算，此处仅配置额外 Header
- 自定义 Header 不能覆盖系统签名 Header

### 3.3 请求体参数（改造）

**旧**：JSON textarea
**新**：动态可增删的行表格，每行有 4 列

```
── 请求体参数（点击 + 添加）──
┌──────────────┬──────────────────┬──────────┬──────┬────┐
│ 参数名        │ 值                │ 类型      │ 必填 │    │
├──────────────┼──────────────────┼──────────┼──────┼────┤
│ code         │ {{username}}     │ [string▼]│ [✓] │ [×] │
│ name         │ {{username}}     │ [string▼]│ [✓] │ [×] │
│ externalId   │ {{username}}     │ [string▼]│ [✓] │ [×] │
│ email        │ {{email}}        │ [string▼]│ [ ] │ [×] │
│ mobile       │                  │ [string▼]│ [ ] │ [×] │
│ status       │ {{status_bool}}  │ [bool▼]  │ [✓] │ [×] │
└──────────────┴──────────────────┴──────────┴──────┴────┘
[+ 添加参数]
```

- 默认预设 6 个 SCIM 标准字段（create/update 时）
- delete 接口无此区域
- 值支持 `{{变量}}` 占位符或固定值
- 类型可选：`string` / `boolean` / `int`

### 3.4 响应验证规则（改造）

**旧**：两个输入框（成功字段 + 成功值）
**新**：动态可增删的行表格，每行有 3 列

```
── 响应验证规则（点击 + 添加）──
┌──────────────┬──────────┬──────────┬────┐
│ 字段          │ 类型      │ 期望值    │    │
├──────────────┼──────────┼──────────┼────┤
│ success      │ [bool▼]  │ [true▼]  │ [×] │
│ code         │ [int▼]   │ [200   ]  │ [×] │
└──────────────┴──────────┴──────────┴────┘
[+ 添加规则]
```

- 默认预设 2 条规则：`success==true` + `code==200`
- 类型可选：`bool` / `int` / `string`
- 字段支持点号路径（如 `data.accountId`）
- 所有规则 AND 关系

---

## 四、推送引擎改造

### 4.1 PushService 改造点

```php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

public function pushUser(int $userId, int $siteId, string $action = 'create'): PushResult
{
    // ... 查询配置和用户（同旧版）

    // 【改造】获取 externalId（update/delete 时需要）
    $externalId = $this->getExternalId($userId, $siteId);

    // 【改造】根据 body_params 结构化构建请求体
    $body = '';
    if (in_array($action, ['create', 'update'])) {
        $body = $this->buildRequestBody(
            json_decode($api['body_params'], true),  // 新字段
            $user, $externalId
        );
    }

    // URL 变量替换（包含 {{external_id}}）
    $url = $this->replaceVariables($api['url'], $user, $externalId);

    // 【改造】构建请求头：系统签名 + 自定义 Header
    $headers = $this->buildHeaders($api, $body, parse_url($url, PHP_URL_PATH), $api['method']);

    // 【改造】使用 Guzzle 发送请求（替代已弃用的 cURL）
    $response = $this->sendRequest($api['method'], $url, $headers, $body, $api['timeout']);

    // 【改造】多规则响应验证
    $rules = json_decode($api['response_rules'], true);  // 新字段
    $validation = $this->validateResponse($response['body'], $rules);

    // 【改造】提取并保存 externalId
    $respData = json_decode($response['body'], true);
    $newExternalId = $respData['externalId'] ?? null;
    if ($validation->isSuccess() && $newExternalId) {
        $this->saveExternalId($userId, $siteId, $newExternalId);
    }

    // 记录日志（新增 request_headers, external_id, failed_rule 字段）
    ...
}
```

### 4.2 请求体构建

```php
private function buildRequestBody(array $bodyParams, array $user, ?string $externalId): string
{
    $body = [];
    foreach ($bodyParams as $param) {
        $value = $this->replaceVariable($param['value'], $user, $externalId);
        // 按类型转换
        switch ($param['type']) {
            case 'boolean': $body[$param['name']] = (bool) $value; break;
            case 'int':     $body[$param['name']] = (int) $value; break;
            default:        $body[$param['name']] = (string) $value; break;
        }
    }
    return json_encode($body, JSON_UNESCAPED_UNICODE);
}
```

### 4.3 响应验证

```php
private function validateResponse(string $responseBody, array $rules): ValidationResult
{
    $data = json_decode($responseBody, true);
    if ($data === null) {
        return ValidationResult::failed('响应不是合法 JSON');
    }
    foreach ($rules as $rule) {
        $actual = $this->getNestedField($data, $rule['field']);
        $expected = $rule['value'];
        $matched = match ($rule['type']) {
            'bool'   => (bool) $actual === (bool) $expected,
            'int'    => (int) $actual === (int) $expected,
            'string' => (string) $actual === (string) $expected,
        };
        if (!$matched) {
            return ValidationResult::failed(
                "{$rule['field']}: 期望 {$expected}({$rule['type']}), 实际 {$actual}"
            );
        }
    }
    return ValidationResult::success();
}
```

### 4.4 Guzzle HTTP 客户端（替代 cURL）

PHP 8.5 起 `curl_close()` 已弃用（自 8.0 起实际无效），改用 Guzzle HTTP 客户端。

```php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

private function sendRequest(string $method, string $url, array $headers, string $body, int $timeout): array
{
    $client = new Client([
        'timeout' => $timeout,
        'verify' => false,  // 内网环境可关闭 SSL 验证
    ]);

    try {
        $response = $client->request($method, $url, [
            'headers' => $headers,
            'body' => $body,
            'http_errors' => false,  // 不抛异常，自行处理状态码
        ]);

        return [
            'code' => $response->getStatusCode(),
            'body' => (string) $response->getBody(),
        ];
    } catch (ConnectException $e) {
        throw new Exception("连接失败: " . $e->getMessage());
    } catch (RequestException $e) {
        if ($e->hasResponse()) {
            return [
                'code' => $e->getResponse()->getStatusCode(),
                'body' => (string) $e->getResponse()->getBody(),
            ];
        }
        throw new Exception("请求失败: " . $e->getMessage());
    }
}
```

**composer 安装**：

```bash
composer require guzzlehttp/guzzle
```

### 4.5 AES 加解密

```php
class EncryptService
{
    private static string $method = 'aes-256-cbc';
    private static string $key;  // 从环境变量读取

    public static function encrypt(string $plaintext): string { ... }
    public static function decrypt(string $ciphertext): string { ... }
}
```

- appkey 加密后存入 `sc_site_push_api.appkey`
- 推送时解密 appkey 用于 SM3 签名
- 密钥从 `.env` 中读取：`PUSH_APPKEY_SECRET=xxx`

---

## 五、后端接口改造

### 5.1 Application::add() / update()

接收参数变更：

| 旧参数 | 新参数 |
|--------|--------|
| `push_apis[].request_headers` | `push_apis[].extra_headers`（数组 `[{name, value}]`） |
| `push_apis[].body_template` | `push_apis[].body_params`（数组 `[{name, value, type, required}]`） |
| `push_apis[].success_field` | `push_apis[].response_rules`（数组 `[{field, type, value}]`） |
| `push_apis[].success_value` | ↑ 合并到 response_rules |
| （无） | `push_appid`（必填） |
| （无） | `push_appkey`（必填，加密存储） |

### 5.2 Application::getPushApis()

返回值变更：

```json
{
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
```

### 5.3 User::pushStatus()

返回新增 `external_id` 字段：

```json
{
  "site_id": 1,
  "site_name": "应用A",
  "last_push": {
    "is_success": true,
    "push_time": "...",
    "external_id": "ext_001",
    "failed_rule": null,
    "error_message": null
  }
}
```

### 5.4 User::pushUser() / pushUserToAll()

返回值新增 `external_id` 和 `failed_rule`：

```json
{
  "is_success": true,
  "external_id": "ext_001",
  "failed_rule": null,
  "error_message": null
}
```

---

## 六、数据迁移方案

### 6.1 sc_site_push_api 迁移

```sql
-- 1. 新增字段
ALTER TABLE sc_site_push_api
  ADD COLUMN appid VARCHAR(128) NOT NULL DEFAULT '' AFTER method,
  ADD COLUMN appkey VARCHAR(256) NOT NULL DEFAULT '' AFTER appid,
  ADD COLUMN extra_headers JSON NULL AFTER appkey,
  ADD COLUMN body_params JSON NULL AFTER extra_headers,
  ADD COLUMN response_rules JSON NOT NULL
    AFTER body_params;

-- 2. 迁移旧字段（需要人工确认 appid/appkey 后执行）
-- request_headers 是 JSON object → extra_headers 是 JSON array of {name, value}
-- 可用 MySQL JSON 函数转换，或写 PHP 脚本迁移

-- 3. 验证迁移完成后删除旧字段
ALTER TABLE sc_site_push_api
  DROP COLUMN request_headers,
  DROP COLUMN body_template,
  DROP COLUMN success_field,
  DROP COLUMN success_value;
```

### 6.2 sc_user_push_log 迁移

```sql
ALTER TABLE sc_user_push_log
  ADD COLUMN request_headers JSON NULL AFTER method,
  ADD COLUMN external_id VARCHAR(128) NULL AFTER response_body,
  ADD COLUMN failed_rule VARCHAR(256) NULL AFTER is_success;
```

### 6.3 sc_user 迁移

```sql
ALTER TABLE sc_user
  ADD COLUMN external_ids JSON NULL AFTER ip;
```

---

## 七、安全设计

1. **appkey 加密存储**：AES-256-CBC，密钥从 `.env` 的 `PUSH_APPKEY_SECRET` 读取
2. **日志脱敏**：`request_headers` 中不记录 `X-trust-signature` 以外的敏感信息
3. **前端隐藏**：appkey 默认 `••••••••••••`，"查看"需确认
4. **接口脱敏**：`getPushApis` 仅返回 `has_appkey: bool`，不返回明文
