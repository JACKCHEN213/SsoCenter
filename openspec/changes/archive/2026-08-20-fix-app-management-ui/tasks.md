# 实施清单：修复应用管理页面操作栏 & 访问应用 & 重置 APP_KEY bug

## 阶段一：Token 变量 Bug 修复

- [x] **T1.1** 修改 `app/view/index.html` 中 `checkToken()` 函数 — 使用 `atob()` 解码 localStorage 中的 base64 token 值，赋值给 `token` 全局变量
- [x] **T1.2** 添加 try-catch 兼容处理 — 如果 token 不是 base64 编码则直接使用原值
- [x] **T1.3** 验证：点击"重置 APP_KEY"按钮，确认不再报 "Wrong number of segments" 错误
- [x] **T1.4** 验证：点击添加/编辑/删除应用，确认所有 POST 请求的 JWT 验证正常通过

## 阶段二：操作栏按钮调整

- [x] **T2.1** 删除操作栏中的"下载公钥"按钮 HTML（含 tooltip 的 `downloadPublicToken` 按钮）
- [x] **T2.2** 删除操作栏中的"下载 APP_KEY"按钮 HTML（`downloadAppSecret` 按钮）
- [x] **T2.3** 删除 `downloadPublicToken()` 函数（JS）
- [x] **T2.4** 新增"访问应用"按钮 HTML — 使用绿色 `btn-success` 样式，图标 `bi-box-arrow-up-right`
- [x] **T2.5** 调整操作栏列宽 — 4 个按钮（详情、编辑、删除、访问应用）

## 阶段三：访问应用功能 — 后端

- [x] **T3.1** 在 `app/controller/Application.php` 新增 `visitApp()` 方法
  - [x] T3.1.1 接收 `id` 参数，查询应用信息
  - [x] T3.1.2 校验应用存在且 `is_del=0`、`is_use=1`
  - [x] T3.1.3 校验 `redirect_url` 不为空
  - [x] T3.1.4 生成授权码（64 位随机字符串）
  - [x] T3.1.5 生成随机 state（16 位随机字符串）
  - [x] T3.1.6 从 JWT token 中提取当前用户 ID
  - [x] T3.1.7 将授权码写入 `oauth_authorization_codes` 表（关联 user_id、client_id、redirect_uri、scope）
  - [x] T3.1.8 记录审计日志
  - [x] T3.1.9 返回 `{ code, state, redirect_uri }`
- [x] **T3.2** 在 `route/application.php` 新增路由 `POST visit, 'visitApp'`，配置 `CheckLogin` + `ValidateParams` 中间件
- [x] **T3.3** 在 `app/validate/Application.php` 新增 `sceneVisitApp()` 验证场景（`id` 必填）
- [x] **T3.4** 在 `app/common/ResponseCode.php` / `ResponseMessage.php` 新增相关错误码（如应用未配置回调地址等）

## 阶段四：访问应用功能 — 前端

- [x] **T4.1** 新增 `visitApp(app_id)` 函数 — POST 请求 `/app/visit` 获取授权码和 state
- [x] **T4.2** 拼接回调 URL：`redirect_uri?code={code}&state={state}`
- [x] **T4.3** 使用 `window.open(url, '_blank')` 打开新标签页
- [x] **T4.4** 错误处理：授权码获取失败时显示 toast 提示

## 阶段五：验证与测试

- [x] **T5.1** 测试操作栏：确认只有"详情"、"编辑"、"删除"、"访问应用"四个按钮
- [x] **T5.2** 测试"访问应用"：点击后新标签页打开 `redirect_uri?code=xxx&state=yyy`
- [x] **T5.3** 测试授权码有效性：访问回调地址后，授权码能被正确消费（由 `standardize-oauth2-protocol` 的 token 端点处理）
- [x] **T5.4** 测试重置 APP_KEY：修复后点击重置不再报错，成功返回新 secret
- [x] **T5.5** 测试查看 APP_KEY：修复后详情弹窗的"查看"功能正常
- [x] **T5.6** 测试 token 兼容性：如果 localStorage 中 token 不是 base64 格式，页面仍能正常工作
