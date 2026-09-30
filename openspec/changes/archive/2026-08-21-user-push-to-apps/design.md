# 设计方案：用户推送到第三方应用

## 一、架构总览

```
管理员                                     SSO Center                          第三方系统
  │                                           │                                   │
  │ 1. 注册应用 + 配置推送接口                  │                                   │
  ├──────────────────────────────────────────>│                                   │
  │                                           │  保存 push_api 配置到 sc_site_push_api│
  │                                           │                                   │
  │ 2. 用户管理 → 点击"推送"                    │                                   │
  ├──────────────────────────────────────────>│                                   │
  │                                           │  查询所有已配置推送接口的应用         │
  │         推送状态面板                        │                                   │
  │<──────────────────────────────────────────│                                   │
  │                                           │                                   │
  │ 3. 点击某应用"推送"按钮                     │                                   │
  ├──────────────────────────────────────────>│                                   │
  │                                           │  POST 第三方新增用户接口             │
  │                                           ├──────────────────────────────────>│
  │                                           │  响应: {code: 200, ...}            │
  │                                           │<──────────────────────────────────│
  │                                           │  记录推送日志到 sc_user_push_log    │
  │         更新推送状态: 成功 ✓                │                                   │
  │<──────────────────────────────────────────│                                   │
```

---

## 二、数据库设计

### 2.1 sc_site_push_api 表（NEW）

存储每个应用的推送接口配置。一个应用最多 3 条记录（create/update/delete）。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `id` | INT | AUTO_INCREMENT | 主键 |
| `site_id` | INT | NOT NULL | 关联应用 ID（`sc_site.id`） |
| `action` | VARCHAR(16) | NOT NULL | 操作类型：`create` / `update` / `delete` |
| `url` | VARCHAR(512) | NOT NULL | 接口地址（完整 URL，含协议） |
| `method` | VARCHAR(8) | `'POST'` | HTTP 方法：`POST` / `PUT` / `DELETE` |
| `request_headers` | JSON | NULL | 请求头（JSON 对象，如 `{"Authorization": "Bearer xxx"}`） |
| `body_template` | JSON | NULL | 请求体模板（JSON 对象，支持变量占位符） |
| `success_field` | VARCHAR(64) | `'code'` | 响应中用于判断成功的字段名 |
| `success_value` | VARCHAR(64) | `'200'` | 成功的字段值 |
| `timeout` | INT | 10 | 请求超时时间（秒） |
| `is_enabled` | TINYINT(1) | 1 | 是否启用此推送接口 |
| `created_at` | DATETIME | CURRENT_TIMESTAMP | 创建时间 |
| `updated_at` | DATETIME | CURRENT_TIMESTAMP ON UPDATE | 更新时间 |

**索引**：`UNIQUE KEY idx_site_action (site_id, action)`

#### body_template 变量说明

请求体模板支持以下变量占位符，推送时自动替换为实际用户数据：

| 占位符 | 说明 | 示例值 |
|--------|------|--------|
| `{{username}}` | 用户名 | `zhangsan` |
| `{{email}}` | 邮箱 | `zhangsan@example.com` |
| `{{password}}` | 密码（明文或哈希，视第三方要求） | `xxx` |
| `{{user_id}}` | 用户 ID | `1` |
| `{{status}}` | 用户状态（1=正常，0=禁用） | `1` |
| `{{ip}}` | 最后登录 IP | `192.168.1.1` |

示例 body_template：
```json
{
  "username": "{{username}}",
  "email": "{{email}}",
  "password": "{{password}}",
  "status": "{{status}}"
}
```

### 2.2 sc_user_push_log 表（NEW）

记录每次用户推送的详细日志。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `id` | INT | AUTO_INCREMENT | 主键 |
| `user_id` | INT | NOT NULL | 推送的用户 ID |
| `site_id` | INT | NOT NULL | 目标应用 ID |
| `action` | VARCHAR(16) | NOT NULL | 操作类型：`create` / `update` / `delete` |
| `url` | VARCHAR(512) | NOT NULL | 实际请求的 URL |
| `method` | VARCHAR(8) | NOT NULL | HTTP 方法 |
| `request_body` | TEXT | NULL | 实际发送的请求体 |
| `response_code` | INT | NULL | HTTP 响应状态码 |
| `response_body` | TEXT | NULL | 响应体（截取前 2000 字符） |
| `is_success` | TINYINT(1) | 0 | 是否推送成功 |
| `error_message` | VARCHAR(512) | NULL | 失败时的错误信息 |
| `push_time` | DATETIME | CURRENT_TIMESTAMP | 推送时间 |

**索引**：`KEY idx_user_site (user_id, site_id)`，`KEY idx_push_time (push_time)`

---

## 三、推送接口配置 UI 设计

### 3.1 添加/编辑应用 — 推送配置区

在 OAuth 配置区下方新增"推送接口配置"区域，使用可折叠面板（Accordion）：

```
┌──────────────────────────────────────────────────────┐
│ ▼ 推送接口配置                                         │
│                                                      │
│ ┌── 新增用户接口 ─────────────────────────────────────┐│
│ │ 接口地址: [https://app.example.com/api/users  ]    ││
│ │ 请求方法: [POST ▼]                                 ││
│ │ 请求头:   [{"Authorization":"Bearer xxx"}      ]   ││
│ │ 请求体模板:                                         ││
│ │ ┌──────────────────────────────────────────────┐   ││
│ │ │ {                                            │   ││
│ │ │   "username": "{{username}}",                │   ││
│ │ │   "email": "{{email}}",                      │   ││
│ │ │   "password": "{{password}}"                 │   ││
│ │ │ }                                            │   ││
│ │ └──────────────────────────────────────────────┘   ││
│ │ 成功判定: 响应字段 [code] 等于 [200]               ││
│ │ 超时时间: [10] 秒                                   ││
│ │ [✓] 启用此接口                                      ││
│ └────────────────────────────────────────────────────┘│
│                                                      │
│ ┌── 修改用户接口 ─────────────────────────────────────┐│
│ │ （同上结构）                                         ││
│ └────────────────────────────────────────────────────┘│
│                                                      │
│ ┌── 删除用户接口 ─────────────────────────────────────┐│
│ │ （同上结构）                                         ││
│ └────────────────────────────────────────────────────┘│
│                                                      │
└──────────────────────────────────────────────────────┘
```

**交互**：
- 三个接口各自独立配置（可只配部分）
- "请求头"和"请求体模板"为 JSON 格式的 textarea
- "成功判定"由两个字段组成：响应字段名 + 期望值
- 每个接口有独立的"启用"开关
- 提供"测试连接"按钮（可选，Phase 2）

### 3.2 数据提交

提交时将三个接口配置序列化为数组：

```json
{
  "push_apis": [
    {
      "action": "create",
      "url": "https://app.example.com/api/users",
      "method": "POST",
      "request_headers": {"Authorization": "Bearer xxx"},
      "body_template": {"username": "{{username}}", "email": "{{email}}"},
      "success_field": "code",
      "success_value": "200",
      "timeout": 10,
      "is_enabled": true
    },
    {
      "action": "update",
      "url": "https://app.example.com/api/users/{{user_id}}",
      "method": "PUT",
      ...
    },
    {
      "action": "delete",
      "url": "https://app.example.com/api/users/{{user_id}}",
      "method": "DELETE",
      ...
    }
  ]
}
```

---

## 四、用户推送管理 UI 设计

### 4.1 用户列表 — 新增"推送"按钮

操作栏新增"推送"按钮：

```
[ 编辑 ]  [ 删除 ]  [ 推送 ]
```

### 4.2 推送状态弹窗

点击"推送"按钮打开弹窗：

```
┌──────────────────────────────────────────────────────────────┐
│  用户推送状态 — zhangsan                                      │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ 应用名称          │ 推送状态    │ 推送时间         │ 操作│  │
│  ├────────────────────────────────────────────────────────┤  │
│  │ 应用A             │ ✓ 成功      │ 2026-08-21 10:00 │ —  │  │
│  │ 应用B             │ ✗ 失败      │ 2026-08-21 10:01 │[重推]│ │
│  │                   │ 超时: 连接超时│                  │     │  │
│  │ 应用C             │ ○ 未推送    │ —                │[推送]│  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  [ 全部推送 ]              [ 关闭 ]                           │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

**状态图标**：
- ✓ 成功（绿色）
- ✗ 失败（红色），下方显示错误信息
- ○ 未推送（灰色）

**操作**：
- "推送"/"重推"：对该应用触发 create 推送
- "全部推送"：对所有未推送/失败的应用批量推送
- 失败行可展开查看详细日志（请求/响应内容）

---

## 五、推送服务设计

### 5.1 PushService

**文件**：`app/service/PushService.php`

| 方法 | 说明 |
|------|------|
| `pushUser(int $userId, int $siteId, string $action = 'create'): PushResult` | 推送单个用户到单个应用 |
| `pushUserToAll(int $userId): array` | 推送用户到所有已配置的应用 |
| `buildRequestBody(string $template, array $user): string` | 将 body_template 中的占位符替换为实际用户数据 |
| `evaluateResponse(string $responseBody, string $field, string $value): bool` | 根据成功判定规则评估响应 |

### 5.2 推送流程

```php
// PushService::pushUser()
public function pushUser(int $userId, int $siteId, string $action = 'create'): PushResult
{
    // 1. 查询推送接口配置
    $api = Db::name('site_push_api')
        ->where('site_id', $siteId)
        ->where('action', $action)
        ->where('is_enabled', 1)
        ->find();
    if (!$api) {
        return PushResult::notConfigured();
    }

    // 2. 查询用户数据
    $user = Db::name('user')->where('id', $userId)->find();

    // 3. 构建请求
    $url = $this->replaceVariables($api['url'], $user);
    $headers = json_decode($api['request_headers'], true) ?: [];
    $body = $this->buildRequestBody($api['body_template'], $user);

    // 4. 发送 HTTP 请求
    try {
        $response = $this->sendRequest($api['method'], $url, $headers, $body, $api['timeout']);
    } catch (Exception $e) {
        $this->logPush($userId, $siteId, $action, $url, null, null, false, $e->getMessage());
        return PushResult::failed($e->getMessage());
    }

    // 5. 评估响应
    $isSuccess = $this->evaluateResponse(
        $response['body'],
        $api['success_field'],
        $api['success_value']
    );

    // 6. 记录日志
    $this->logPush($userId, $siteId, $action, $url, $response['code'], $response['body'], $isSuccess, null);

    return $isSuccess ? PushResult::success() : PushResult::failed('响应不符合成功判定规则');
}
```

### 5.3 HTTP 请求发送

使用 PHP cURL 发送请求（不引入额外依赖）：

```php
private function sendRequest(string $method, string $url, array $headers, string $body, int $timeout): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_SSL_VERIFYPEER => false,  // 内网环境可关闭
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("cURL 错误: " . $error);
    }

    return ['code' => $httpCode, 'body' => $response];
}
```

---

## 六、后端接口设计

### 6.1 Application 控制器扩展

| 方法 | 路由 | 说明 |
|------|------|------|
| `add()` | `POST /app/add` | 改造：同时保存推送接口配置 |
| `update()` | `PUT /app/update` | 改造：同时更新推送接口配置 |
| `getPushApis()` | `GET /app/push_apis` | 新增：获取应用的推送接口配置 |

### 6.2 User 控制器扩展

| 方法 | 路由 | 说明 |
|------|------|------|
| `pushStatus()` | `POST /user/push_status` | 新增：获取用户推送状态（所有应用的推送记录） |
| `pushUser()` | `POST /user/push` | 新增：推送用户到指定应用 |
| `pushUserToAll()` | `POST /user/push_all` | 新增：推送用户到所有已配置的应用 |
| `pushLog()` | `POST /user/push_log` | 新增：获取某次推送的详细日志 |

### 6.3 接口响应示例

#### pushStatus 响应

```json
{
  "code": 0,
  "success": true,
  "result": [
    {
      "site_id": 1,
      "site_name": "应用A",
      "has_api_config": true,
      "last_push": {
        "is_success": true,
        "push_time": "2026-08-21 10:00:00",
        "action": "create"
      }
    },
    {
      "site_id": 2,
      "site_name": "应用B",
      "has_api_config": true,
      "last_push": {
        "is_success": false,
        "push_time": "2026-08-21 10:01:00",
        "error_message": "cURL 错误: 连接超时",
        "action": "create"
      }
    },
    {
      "site_id": 3,
      "site_name": "应用C",
      "has_api_config": true,
      "last_push": null
    }
  ]
}
```

---

## 七、删除用户时的级联推送

当管理员在用户管理页面删除用户时，如果该用户已推送到其他应用，系统 SHALL：

1. 查询所有配置了 `delete` 推送接口的应用
2. 逐个向第三方系统发送删除用户请求
3. 记录推送日志
4. 展示推送结果（即使部分失败也允许删除本地用户）

---

## 八、向后兼容

1. **现有应用**：没有配置推送接口的应用不受影响，推送状态面板中不显示
2. **现有用户**：未推送过的用户显示"未推送"状态
3. **数据库**：新增表，不修改现有表结构
