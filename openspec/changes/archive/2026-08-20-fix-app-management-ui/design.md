# 设计方案：修复应用管理页面操作栏 & 访问应用 & 重置 APP_KEY bug

## 一、操作栏按钮调整

### 1.1 当前操作栏（移除前）

```
[ 详情 ]  [ 编辑 ]  [ 删除 ]  [ 下载公钥 ]  [ 下载 APP_KEY ]
```

### 1.2 调整后操作栏

```
[ 详情 ]  [ 编辑 ]  [ 删除 ]  [ 访问应用 ]
```

**移除**：
- "下载公钥"按钮 — RSA 公钥下载不再是 OAuth 2.0 标准流程
- "下载 APP_KEY"按钮 — 已整合到详情弹窗中

**新增**：
- "访问应用"按钮 — 点击后以 OAuth 2.0 授权码模式快速访问应用

---

## 二、"访问应用"功能设计

### 2.1 交互流程

```
用户点击"访问应用"
        │
        ▼
前端 POST /app/visit { id: app_id }
        │
        ▼
后端为当前登录用户生成授权码
（关联 user_id、client_id、redirect_uri）
        │
        ▼
返回 { code: xxx, state: yyy }
        │
        ▼
前端拼接: redirect_uri?code=xxx&state=yyy
        │
        ▼
window.open(url, '_blank') 打开新标签页
```

### 2.2 后端接口

**路由**：`POST /app/visit`

**请求参数**：

| 参数 | 位置 | 必选 | 说明 |
|------|------|------|------|
| `Authorization` | Header | 是 | 管理员 JWT Token |
| `id` | Body | 是 | 应用 ID |

**处理逻辑**：

1. 验证管理员登录态（`CheckLogin` 中间件）
2. 查询应用信息（`sc_site`），校验 `is_del=0`、`is_use=1`
3. 生成授权码（10 分钟过期，一次性使用）
4. 生成随机 state（16 位随机字符串）
5. 记录审计日志
6. 返回授权码和 state

**响应**：
```json
{
  "code": 0,
  "success": true,
  "message": "成功",
  "result": {
    "code": "SplxlOBeZQQYbYS6WxSbIA",
    "state": "a3f5b8c9d2e1f406",
    "redirect_uri": "https://app.example.com/callback"
  }
}
```

**授权码存储**：写入 `oauth_authorization_codes` 表，关联当前管理员用户 ID。

**前端拼接 URL**：
```javascript
function visitApp(app_id) {
    $.ajax({
        type: 'POST',
        contentType: 'application/json;charset=UTF-8',
        headers: { Authorization: token },
        url: 'app/visit',
        data: JSON.stringify({ id: app_id }),
        success: (data) => {
            if (data.code === 0) {
                let url = data.result.redirect_uri
                    + '?code=' + encodeURIComponent(data.result.code)
                    + '&state=' + encodeURIComponent(data.result.state);
                window.open(url, '_blank');
            } else {
                messageEx(data.result || '获取授权码失败', 'danger', 800);
            }
        },
        error: () => { messageEx('请求失败', 'danger', 800); }
    });
}
```

### 2.3 授权码生成

复用 `AuthorizationCodeService`（由 `standardize-oauth2-protocol` 创建）：
- 生成 64 位随机字符串
- 关联 `client_id`、`user_id`（当前管理员）、`redirect_uri`
- 有效期 10 分钟
- `scope` = 应用配置的默认 scope
- 写入 `oauth_authorization_codes` 表

---

## 三、Token 变量 Bug 修复

### 3.1 问题根因

```
login.html 登录成功:
  data.result = "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6MS4uLn0.signature"
  localStorage.setItem('token', btoa(data.result))
  // token = "ZXlK...（base64编码的JWT字符串）"

index.html checkToken():
  token = localStorage.getItem('token')
  // token = "ZXlK...（base64编码值，无"."分隔符）"

所有 AJAX 请求:
  headers: { Authorization: token }
  // 发送 "ZXlK..." 给后端

CheckLogin 中间件:
  JWT::decode("ZXlK...", publicKey, [...])
  // "ZXlK..." 不是合法 JWT（无三段"."结构）→ 报错 "Wrong number of segments"
```

**关键发现**：`CheckLogin` 中间件对 GET 请求直接放行（不做 JWT 验证），所以 GET 请求的接口（`detail`、`download_secret`）不报错。而 POST 请求的接口（`add`、`update`、`reset_secret`）会触发 JWT 验证失败。

### 3.2 修复方案

在 `index.html` 的 `checkToken()` 中，用 `atob()` 解码后再赋值：

```javascript
// 修改前
function checkToken() {
    token = window.localStorage.getItem('token');
    if (!token) {
        messageEx('登录失效', 'error', 500);
        window.location.href = 'login.html';
    }
}

// 修改后
function checkToken() {
    let stored = window.localStorage.getItem('token');
    if (!stored) {
        messageEx('登录失效', 'error', 500);
        window.location.href = 'login.html';
        return;
    }
    try {
        token = atob(stored);  // 解码 base64，得到原始 JWT 字符串
    } catch (e) {
        token = stored;  // 兼容处理：如果解码失败则使用原值
    }
    if (!token) {
        messageEx('登录失效', 'error', 500);
        window.location.href = 'login.html';
    }
}
```

### 3.3 影响范围

此修复影响 `index.html` 中所有使用 `token` 变量的 AJAX 请求：

| 接口 | 方法 | 修复前 | 修复后 |
|------|------|--------|--------|
| `app/add` | POST | base64 token → 验证失败 | 原始 JWT → 验证通过 |
| `app/update` | PUT | base64 token → 验证失败 | 原始 JWT → 验证通过 |
| `app/delete` | DELETE | base64 token → 验证失败 | 原始 JWT → 验证通过 |
| `app/detail` | GET | 跳过验证（GET 直接放行） | 原始 JWT → 验证通过 |
| `app/visit` | POST | base64 token → 验证失败 | 原始 JWT → 验证通过 |
| `app/reset_secret` | POST | base64 token → **报错** | 原始 JWT → 验证通过 |
| `app/get_secret` | POST | base64 token → 验证失败 | 原始 JWT → 验证通过 |

---

## 四、操作栏按钮样式

### 4.1 移除按钮

删除以下 HTML 元素：
- `downloadPublicToken` 按钮（含 tooltip）
- `downloadAppSecret` 按钮

### 4.2 新增按钮

```html
<button type="button"
        class="btn btn-success"
        onclick="visitApp({$app['id']})">
    <i class="bi bi-box-arrow-up-right"></i> 访问应用
</button>
```

颜色方案：
- 详情：`btn-primary`（蓝色）
- 编辑：`btn-info`（浅蓝）
- 删除：`btn-danger`（红色）
- 访问应用：`btn-success`（绿色）

---

## 五、`deleteApp` 方法兼容

修复 token 变量后，`deleteApp` 也需要同步使用修复后的 `token`。由于 `token` 是全局变量，修复 `checkToken()` 即可全局生效，无需单独修改 `deleteApp`。

---

## 六、向后兼容

1. **已注册应用**：没有 `redirect_uri` 或 `redirect_url` 为空时，"访问应用"按钮点击后提示"请先配置回调地址"
2. **token 兼容**：如果 localStorage 中存储的 token 不是 base64 编码值（直接是 JWT），`atob()` 解码可能得到乱码，此时用 try-catch 捕获并使用原始值
3. **操作栏**：移除的按钮不影响其他功能
