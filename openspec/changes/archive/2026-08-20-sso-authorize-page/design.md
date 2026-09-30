# 设计方案：SSO 认证页面（简化参数版）

## 一、参数映射

| 用户参数 | 含义 | 映射到内部字段 | 来源 |
|----------|------|---------------|------|
| `APP_ID` | 应用标识（UUID4） | `client_id` | URL Query |
| `state` | 防 CSRF 随机值 | `state` | URL Query |
| `callback` | 认证成功后的回调地址 | `redirect_uri` | URL Query |

### 固定值（不再需要外部传入）

| 参数 | 固定值 | 说明 |
|------|--------|------|
| `response_type` | `code` | 固定为授权码模式 |
| `scope` | 应用配置的 `scope` 值 | 从 `sc_site.scope` 读取，不从请求传入 |

---

## 二、交互流程

```
第三方系统                              SSO Center                        用户浏览器
    │                                      │                                │
    │                                      │   用户点击"登录XXX系统"          │
    │                                      │<───────────────────────────────│
    │                                      │                                │
    │                                      │   浏览器跳转到：                │
    │                                      │   /oauth/authorize             │
    │                                      │   ?APP_ID=xxx                  │
    │                                      │   &state=yyy                   │
    │                                      │   &callback=https://...        │
    │                                      │───────────────────────────────>│
    │                                      │                                │
    │                                      │   检测登录状态                  │
    │                                      │   ├── 未登录 → 渲染登录表单     │
    │                                      │   └── 已登录 → 渲染授权确认     │
    │                                      │───────────────────────────────>│
    │                                      │                                │
    │                                      │   [未登录] 用户输入账号密码      │
    │                                      │<───────────────────────────────│
    │                                      │                                │
    │                                      │   POST /login/login            │
    │                                      │<───────────────────────────────│
    │                                      │                                │
    │                                      │   登录成功 → 切换为授权确认页    │
    │                                      │───────────────────────────────>│
    │                                      │                                │
    │                                      │   用户点击"允许"                │
    │                                      │<───────────────────────────────│
    │                                      │                                │
    │                                      │   POST /oauth/authorize/confirm│
    │                                      │   { APP_ID, state, callback }  │
    │                                      │<───────────────────────────────│
    │                                      │                                │
    │                                      │   返回 { redirect_url }        │
    │                                      │───────────────────────────────>│
    │                                      │                                │
    │   callback?code=xxx&state=yyy        │                                │
    │<─────────────────────────────────────┤                                │
    │                                      │                                │
```

---

## 三、页面状态

### 状态 A：用户未登录

```
┌────────────────────────────────────────┐
│              [Logo]                    │
│                                        │
│   "[应用名称]" 请求使用您的账户登录      │
│                                        │
│   用户名: [________________]            │
│   密码:   [________________]            │
│                                        │
│   [         登录         ]              │
└────────────────────────────────────────┘
```

- 登录使用 AJAX 请求 `POST /login/login`
- 登录成功后不跳转首页，直接切换到状态 B

### 状态 B：用户已登录（授权确认）

```
┌────────────────────────────────────────┐
│              [Logo]                    │
│                                        │
│   "[应用名称]" 请求访问您的账户          │
│                                        │
│   [应用图标]                            │
│   回调地址: {callback}                  │
│                                        │
│   [    拒绝    ]       [    允许    ]   │
└────────────────────────────────────────┘
```

---

## 四、后端改造

### 4.1 `OAuth::authorizePage()` 改造

**改造前**（接收 5 个参数）：
```php
$clientId     = input('get.client_id/s', '');
$responseType = input('get.response_type/s', '');
$redirectUri  = input('get.redirect_uri/s', '');
$scope        = input('get.scope/s', 'trust');
$state        = input('get.state/s', '');
```

**改造后**（接收 3 个参数，兼容旧参数名）：
```php
// 新参数名优先，兼容旧参数名
$clientId    = input('get.APP_ID/s') ?: input('get.client_id/s', '');
$state       = input('get.state/s') ?: input('get.state/s', '');
$redirectUri = input('get.callback/s') ?: input('get.redirect_uri/s', '');
$responseType = 'code';  // 固定授权码模式
$scope       = '';        // 从应用配置读取，不从请求传入
```

**参数校验简化**：
- 移除 `response_type` 校验（固定为 `code`）
- 移除 `scope` 入参校验（从应用配置读取）

**模板变量**：
```php
View::assign([
    'app_name'    => $site['name'],
    'app_image'   => $site['image'] ?: '',
    'app_id'      => $clientId,      // 新参数名
    'callback'    => $redirectUri,   // 新参数名
    'state'       => $state,         // 保持 state
    'is_logged_in' => !empty($user),
    'logged_in_username' => $user['username'] ?? '',
]);
```

### 4.2 `OAuth::confirmAuthorize()` 改造

**改造前**：
```php
$clientId     = input('post.client_id/s', '');
$responseType = input('post.response_type/s', '');
$redirectUri  = input('post.redirect_uri/s', '');
$scope        = input('post.scope/s', 'trust');
$state        = input('post.state/s', '');
```

**改造后**：
```php
$clientId    = input('post.APP_ID/s') ?: input('post.client_id/s', '');
$state       = input('post.state/s') ?: input('post.state/s', '');
$redirectUri = input('post.callback/s') ?: input('post.redirect_uri/s', '');
$responseType = 'code';  // 固定
$scope       = $site['scope'] ?? 'trust';  // 从应用配置读取
```

### 4.3 回调格式

认证成功后的 302 回调 URL：
```
{callback}?code={授权码}&state={state}
```

示例：
```
https://app.example.com/login?code=SplxlOBeZQQYbYS6WxSbIA&state=af0ifjsldkj
```

---

## 五、前端改造

### 5.1 `oauth_authorize.html` 改造

**隐藏字段名称变更**：

```html
<!-- 改造前 -->
<input type="hidden" name="client_id" value="{$client_id}">
<input type="hidden" name="redirect_uri" value="{$redirect_uri}">
<input type="hidden" name="response_type" value="{$response_type}">
<input type="hidden" name="scope" value="{$scope}">
<input type="hidden" name="state" value="{$state}">

<!-- 改造后 -->
<input type="hidden" name="APP_ID" value="{$app_id}">
<input type="hidden" name="callback" value="{$callback}">
<input type="hidden" name="state" value="{$state}">
```

**confirmAuthorize 请求改造**：

```javascript
// 改造前
$.post('oauth/authorize/confirm', {
    client_id: appId,
    redirect_uri: redirectUri,
    response_type: responseType,
    scope: scope,
    state: state
}, ...);

// 改造后
$.post('oauth/authorize/confirm', {
    APP_ID: appId,
    callback: callbackUrl,
    state: state
}, ...);
```

---

## 六、向后兼容

为保证现有调用方不受影响，后端同时支持新旧两套参数名：

| 新参数名 | 旧参数名 | 优先级 |
|----------|----------|--------|
| `APP_ID` | `client_id` | 新参数优先 |
| `state` | `state` | 新参数优先 |
| `callback` | `redirect_uri` | 新参数优先 |

读取逻辑：`input('get.APP_ID/s') ?: input('get.client_id/s', '')`

这样：
- 新的第三方系统使用 `APP_ID`/`state`/`callback`
- 旧的调用方仍可使用 `client_id`/`state`/`redirect_uri`
- 两种调用方式产生完全相同的效果

---

## 七、路由

路由无需变更。现有路由：
```
GET  /oauth/authorize          → OAuth::authorizePage
POST /oauth/authorize/confirm  → OAuth::confirmAuthorize
```

第三方系统跳转示例：
```
https://sso.example.com/oauth/authorize?APP_ID=550e8400-e29b-41d4-a716-446655440000&state=af0ifjsldkj&callback=https://app.example.com/login
```
