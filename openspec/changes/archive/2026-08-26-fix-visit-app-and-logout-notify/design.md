# 设计方案：修复"访问应用"流程

## 一、"访问应用"改造

### 1.1 当前实现（问题）

```php
// 后端 Application.php
final public function visitApp(): Json
{
    // 1. 查询应用
    $site = Db::name('site')->where('id', $id)->where('is_del', 0)->where('is_use', 1)->find();
    
    // 2. 生成授权码
    $code = $codeService->generate($site['client_id'], $userId, ...);
    
    // 3. 返回给前端拼接 URL
    return sendJson([
        'code' => $code,
        'state' => $state,
        'redirect_uri' => $site['redirect_url'],
    ]);
}
```

```javascript
// 前端 index.html
function visitApp(app_id) {
    $.ajax({
        url: 'app/visit',
        data: JSON.stringify({ id: app_id }),
        success: function (data) {
            if (data.code === 0 && data.result) {
                let url = data.result.redirect_uri
                    + '?code=' + encodeURIComponent(data.result.code)
                    + '&state=' + encodeURIComponent(data.result.state);
                window.open(url, '_blank');
            }
        }
    });
}
```

**问题**：
- 操作日志未写入 `sc_operation_log`（仅记录到系统日志文件）

### 1.2 改造方案

**方案：保持原有流程，在 visitApp 中增加操作日志记录**

保持原有的"后端生成授权码 → 前端拼接 URL 打开"逻辑，仅在 `visitApp()` 方法中额外调用 `AuditLogService::app()` 记录操作日志。

**后端改造**：

```php
// Application.php - 改造后
final public function visitApp(): Json
{
    $id = input('post.id/d');
    try {
        // 1. 查询应用
        $site = Db::name('site')->where('id', $id)->where('is_del', 0)->where('is_use', 1)->find();
        if (!$site) {
            return sendJson('应用不存在', ResponseCode::$APP_NOT_FOUND, false);
        }

        $userId = $this->extractUserIdFromJwt();

        // 2. 生成授权码（与改造前一致）
        $scope = 'basic_info';
        $state = md5(uniqid());
        $ttl = 600;
        $codeService = new AuthorizationCodeService();
        $code = $codeService->generate($site['client_id'], $userId, $site['redirect_url'], $scope, $state, $ttl);

        // 3. 记录操作日志（新增）
        AuditLogService::app(
            'app_visit',
            'success',
            "管理员访问应用：{$site['name']}",
            ['app_id' => $id, 'app_name' => $site['name'], 'user_id' => $userId],
            ['code' => substr($code, 0, 10) . '...', 'redirect_uri' => $site['redirect_url']],
            $id,
            $site['name']
        );

        // 4. 返回给前端拼接 URL（与改造前一致）
        return sendJson([
            'code' => $code,
            'state' => $state,
            'redirect_uri' => $site['redirect_url'],
        ]);
    } catch (Exception $e) {
        recordLog($e, 'error');
        return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, false);
    }
}
```

**前端改造**：无需修改，保持原有逻辑。

```javascript
// 前端 index.html - 无需修改
function visitApp(app_id) {
    $.ajax({
        type: 'POST',
        contentType: 'application/json;charset=UTF-8',
        headers: { Authorization: token },
        url: '/app/visit',
        data: JSON.stringify({ id: app_id }),
        success: function (data) {
            if (data.code === 0 && data.result) {
                let url = data.result.redirect_uri
                    + '?code=' + encodeURIComponent(data.result.code)
                    + '&state=' + encodeURIComponent(data.result.state);
                window.open(url, '_blank');
            } else {
                messageEx(data.result || data.message || '获取授权码失败', 'danger', 800);
            }
        },
        error: function () {
            messageEx('请求失败', 'danger', 800);
        }
    });
}
```

**路由**：保持原有路由，无需修改。

```php
// route/application.php - 无需修改
Route::post('visit', 'visitApp')->middleware([CheckLogin::class, ValidateParams::class]);
```

### 1.3 改造前后对比

| 步骤 | 改造前 | 改造后 |
|------|--------|--------|
| 1 | AJAX 调用 `/app/visit` | AJAX 调用 `/app/visit`（不变） |
| 2 | 后端生成授权码，返回 code/state/redirect_uri | 后端生成授权码，**同时记录操作日志**，返回 code/state/redirect_uri |
| 3 | 前端拼接 `redirect_uri?code=xxx&state=xxx` 打开 | 前端拼接 `redirect_uri?code=xxx&state=xxx` 打开（不变） |
| 4 | 仅记录到系统日志文件 | 记录到 `sc_operation_log` 表（可在操作日志页面查看） |

---

## 二、操作日志记录

### 2.1 日志写入

在 `visitApp()` 方法中调用 `AuditLogService::app()`：

```php
AuditLogService::app(
    'app_visit',           // action
    'success',             // result
    "管理员访问应用：{$site['name']}", // message
    ['app_id' => $id, 'app_name' => $site['name'], 'user_id' => $userId], // requestData
    ['code' => substr($code, 0, 10) . '...', 'redirect_uri' => $site['redirect_url']], // responseData
    $id,                   // appId
    $site['name']          // appName
);
```

### 2.2 操作日志页面展示

操作日志列表页面（`operation_log.html`）已支持显示 `app_visit` 类型的日志，无需额外改造。

---

## 三、数据库变更

无数据库变更。所有相关表（`sc_operation_log` 等）已存在。

---

## 四、向后兼容

完全向后兼容。改造仅新增日志记录功能，不改变任何已有行为：

- OAuth 授权流程：不变，仍然是后端生成授权码 → 前端拼接 URL → 打开第三方应用
- 前端代码：不变
- 路由：不变
- 第三方应用：无影响，接收授权码的方式不变

---

## 五、实施步骤

1. 修改 `Application::visitApp()` — 在生成授权码后，调用 `AuditLogService::app()` 记录操作日志
2. 测试验证
