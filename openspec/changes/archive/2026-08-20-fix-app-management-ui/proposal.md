# 提案：修复应用管理页面操作栏 & 访问应用 & 重置 APP_KEY bug

## 变更 ID

`fix-app-management-ui`

## 背景与动机

应用管理页面（`adapt-frontend-oauth2`）存在以下三个问题需要修复：

### 问题 1：操作栏按钮冗余

当前操作栏有 4 个按钮：详情、编辑、删除、下载 APP_KEY。其中"下载 APP_KEY"功能已在详情弹窗中提供，操作栏再放一个属于重复，需要精简。同时"下载公钥"按钮也不需要了（RSA 公钥下载已不是 OAuth 2.0 标准流程的一部分）。

### 问题 2：缺少"访问应用"按钮

当前没有"访问应用"按钮。用户希望管理员能从应用列表直接以 OAuth 2.0 授权码模式快速访问某个应用：
- 点击"访问应用"后，后端为当前登录用户生成授权码
- 前端将授权码和随机 state 拼接到应用的回调地址后
- 打开新标签页跳转到该地址

### 问题 3：重置 APP_KEY 报错

点击详情弹窗中的"重置"按钮，返回错误：
```json
{"code":10007,"success":true,"message":"身份验证错误","result":"Wrong number of segments"}
```

**错误分析**：

1. 错误码 `10007` 是 `JWT_ERROR`，来自 `CheckLogin` 中间件
2. `CheckLogin` 中间件对 GET 请求直接放行（第 29-31 行：`if ($method == 'get') { return $next($request); }`），所以 `detail`（GET）和 `download_secret`（GET）可以正常工作
3. 但 `reset_secret` 是 POST 请求，会经过 JWT 验证
4. 前端 `token` 变量来自 `window.localStorage.getItem('token')`，而登录时存储的是 `btoa(data.result)`（即 JWT 字符串的 base64 编码），不是原始 JWT
5. `JWT::decode()` 接收到的是 base64 编码后的字符串（无 `.` 分隔符），所以报 "Wrong number of segments"

**根本原因**：前端 `index.html` 中 `token` 变量存的是 base64 编码值，而 `CheckLogin` 中间件期望原始 JWT 字符串。

**修复方向**：

- **方案 A**（推荐）：在 `checkToken()` 中用 `atob()` 解码后再赋值给 `token` 变量，使 `token` 变量为原始 JWT
- **方案 B**：修改 `CheckLogin` 中间件，先 `base64_decode()` 再验证 JWT

方案 A 更合理，因为中间件是基础设施，不应该为前端编码习惯做特殊处理。

## 变更目标

1. **操作栏精简**：移除"下载公钥"和"下载 APP_KEY"按钮，新增"访问应用"按钮
2. **访问应用功能**：后端新增"为当前用户生成授权码"接口，前端点击后拼接回调 URL 并打开新标签页
3. **重置 APP_KEY 修复**：修复 `token` 变量的 base64 编码问题，使所有 POST 请求的 JWT 验证正常

## 涉及范围

| 范围 | 文件 |
|------|------|
| **修改页面** | `app/view/index.html` — 操作栏按钮调整、新增访问应用 JS、修复 token 变量 |
| **修改控制器** | `app/controller/Application.php` — 新增 `visitApp()` 接口（生成授权码） |
| **修改路由** | `route/application.php` — 新增 visit 路由 |

## 影响分析

- **现有功能**：详情、编辑、删除不受影响
- **安全**：修复 token 变量后，所有 POST 接口的 JWT 验证恢复正常
- **用户体验**：操作栏更简洁，新增"访问应用"快捷入口
