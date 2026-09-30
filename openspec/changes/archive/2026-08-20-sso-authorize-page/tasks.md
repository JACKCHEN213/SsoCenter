# 实施清单：SSO 认证页面（简化参数版）

## 阶段一：后端参数适配

- [x] **T1.1** 修改 `OAuth::authorizePage()` — 接收 `APP_ID`/`state`/`callback` 参数，兼容旧参数名 `client_id`/`state`/`redirect_uri`
- [x] **T1.2** 修改 `OAuth::authorizePage()` — 移除 `response_type` 和 `scope` 入参，`response_type` 固定为 `code`，`scope` 从应用配置读取
- [x] **T1.3** 修改 `OAuth::authorizePage()` — 模板变量改为 `app_id`、`callback`、`state`（移除 `client_id`、`redirect_uri`、`response_type`、`scope`）
- [x] **T1.4** 修改 `OAuth::confirmAuthorize()` — 接收 `APP_ID`/`state`/`callback` 参数，兼容旧参数名
- [x] **T1.5** 修改 `OAuth::confirmAuthorize()` — 移除 `response_type` 和 `scope` 入参，`response_type` 固定为 `code`，`scope` 从应用配置读取

## 阶段二：前端页面适配

- [x] **T2.1** 修改 `app/view/oauth_authorize.html` — 隐藏字段从 `client_id`/`redirect_uri`/`response_type`/`scope`/`state` 改为 `APP_ID`/`callback`/`state`
- [x] **T2.2** 修改 `oauth_authorize.html` 中的 `confirmAuthorize()` JS 函数 — POST 参数改为 `APP_ID`/`callback`/`state`
- [x] **T2.3** 修改 `denyAuthorize()` JS 函数 — 跳转参数使用 `state`（如需要）
- [x] **T2.4** 修改页面中的模板变量引用 — `{$client_id}` → `{$app_id}`，`{$redirect_uri}` → `{$callback}`

## 阶段三：验证与测试

- [x] **T3.1** 测试新参数名：`GET /oauth/authorize?APP_ID=xxx&state=yyy&callback=zzz` 能正常渲染页面
- [x] **T3.2** 测试旧参数名兼容：`GET /oauth/authorize?client_id=xxx&state=yyy&redirect_uri=zzz` 仍能正常工作
- [x] **T3.3** 测试新参数名登录 + 授权：登录后点击"允许"，回调 URL 携带 `code` 和 `state`
- [x] **T3.4** 测试旧参数名登录 + 授权：同上，回调 URL 携带 `code` 和 `state`
- [x] **T3.5** 测试拒绝授权：点击"拒绝"后正确返回 error 参数
- [x] **T3.6** 测试错误情况：无效 `APP_ID`、不匹配的 `callback`、缺失参数
- [x] **T3.7** 测试已登录用户直接进入授权确认页
