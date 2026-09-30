# 实施清单：应用管理页面适配 OAuth 2.0 & SSO 登录页

## 阶段一：基础设施

- [x] **T1.1** 创建 `app/common/Uuid.php`，实现 UUID4 生成函数
- [x] **T1.2** 确认 `sc_site.client_id` 字段类型为 VARCHAR(36)，支持 UUID4 格式
- [x] **T1.3** 编写数据迁移脚本：将现有应用的 client_id 转换为 UUID4 格式，为没有 client_id 的应用生成新值
- [x] **T1.4** 执行迁移并验证数据完整性

## 阶段二：后端接口适配

- [x] **T2.1** 修改 `Application::add()` — 接收 OAuth 配置参数（`allowed_grant_types`、`scope`、`access_token_ttl`、`refresh_token_ttl`、`code_ttl`），使用 UUID4 生成 client_id，返回值包含 client_id 和 client_secret
- [x] **T2.2** 修改 `Application::update()` — 支持更新 OAuth 配置字段
- [x] **T2.3** 新增 `Application::detail()` — 返回单个应用完整信息（含 client_id，不含 client_secret 明文）
- [x] **T2.4** 新增 `Application::resetSecret()` — 重置 client_secret，返回新值，记录审计日志
- [x] **T2.5** 新增 `Application::downloadSecret()` — 生成凭证文本文件下载
- [x] **T2.6** 修改 `app/controller/Index.php` — 列表数据查询中返回 `client_id`、`allowed_grant_types` 字段
- [x] **T2.7** 更新 `app/validate/Application.php` 验证器 — 新增 OAuth 配置字段的校验规则
- [x] **T2.8** 更新 `route/application.php` — 添加新路由（detail、reset_secret、download_secret）
- [x] **T2.9** 更新 `app/common/ResponseCode.php` 和 `ResponseMessage.php` — 新增应用相关错误码

## 阶段三：应用管理页面改造（index.html）

### 3.1 添加应用弹窗改造

- [x] **T3.1** 添加弹窗 HTML 结构 — 新增"OAuth 配置"分区：授权模式复选框（4 个）、scope 输入、Token 有效期配置、授权码有效期配置
- [x] **T3.2** 编写 `modifyApp()` 函数改造 — 收集新增的 OAuth 配置字段，组装到 POST/PUT 请求体中
- [x] **T3.3** 添加成功后的 APP_ID/APP_KEY 展示 — 添加成功后在弹窗中展示生成的凭证，提示用户保存

### 3.2 应用列表表格改造

- [x] **T3.4** 修改表头 — 新增"APP_ID"列和"授权模式"列，调整列宽比例
- [x] **T3.5** 修改表格行渲染 — 新增 APP_ID 显示（UUID4 + 点击复制）、授权模式标签（彩色 badge）
- [x] **T3.6** 修改操作按钮 — 新增"详情"按钮，将"下载公钥"保留，新增"下载 APP_KEY"按钮

### 3.3 应用详情弹窗（新增）

- [x] **T3.7** 创建详情弹窗 HTML — 展示 APP_ID（只读+复制）、APP_KEY（隐藏+查看/复制/重置）、完整 OAuth 配置
- [x] **T3.8** 实现 `showAppDetail()` 函数 — 调用 detail 接口加载数据，填充弹窗内容
- [x] **T3.9** 实现 `copyToClipboard()` 函数 — 点击复制按钮，将文本复制到剪贴板并显示"已复制"提示
- [x] **T3.10** 实现 `toggleSecretVisibility()` 函数 — 点击"查看"显示 APP_KEY 明文，再次点击隐藏
- [x] **T3.11** 实现 `resetAppSecret()` 函数 — 二次确认弹窗 → 调用 reset_secret 接口 → 更新展示
- [x] **T3.12** 实现 `downloadAppSecret()` 函数 — 调用 download_secret 接口下载凭证文件

### 3.4 编辑弹窗改造

- [x] **T3.13** 编辑弹窗 HTML 结构 — 与添加弹窗相同 + APP_ID 只读展示
- [x] **T3.14** `initModal()` 改造 — 编辑模式下预填充 OAuth 配置字段值
- [x] **T3.15** `renderGrantTypeBadges()` 函数 — 根据 `allowed_grant_types` 数组渲染彩色标签

## 阶段四：SSO 授权登录页

### 4.1 页面创建

- [x] **T4.1** 创建 `app/view/oauth_authorize.html` — 页面模板，继承 `template/login`，包含两个状态视图（登录表单 + 授权确认）
- [x] **T4.2** 编写 CSS 样式 — SSO 授权页卡片样式、权限列表样式、应用信息展示样式
- [x] **T4.3** 创建 `public/assets/js/oauth-authorize.js` — SSO 登录和授权确认的 JS 逻辑

### 4.2 后端渲染

- [x] **T4.4** 新增 `OAuth::authorizePage()` 方法 — 检测登录状态，查询应用信息，渲染授权页面模板
- [x] **T4.5** 新增 `OAuth::confirmAuthorize()` 方法 — 处理用户"允许"操作，生成授权码或 token，302 回调
- [x] **T4.6** 新增 `OAuth::denyAuthorize()` 方法 — 处理用户"拒绝"操作，302 回调带 error 参数

### 4.3 路由配置

- [x] **T4.7** 在 `route/oauth.php` 新增页面路由 — `GET /oauth/authorize`（渲染页面）、`POST /oauth/authorize/confirm`（确认授权）

### 4.4 前端交互逻辑

- [x] **T4.8** 实现 `ssoLogin(event)` 函数 — AJAX 登录，成功后替换页面内容为授权确认视图
- [x] **T4.9** 实现 `confirmAuthorize()` 函数 — POST 确认授权，获取 code/token 后 window.location 跳转 redirect_uri
- [x] **T4.10** 实现 `denyAuthorize()` 函数 — window.location 跳转 redirect_uri 带 error 参数
- [x] **T4.11** 处理已登录用户直接进入授权确认 — 页面初始渲染时根据 `$is_logged_in` 变量显示对应视图

## 阶段五：样式与交互细节

- [x] **T5.1** 添加授权模式标签 CSS — 四种模式的彩色 badge 样式
- [x] **T5.2** 添加 APP_ID 展示区 CSS — 等宽字体 + 背景色 + 复制按钮样式
- [x] **T5.3** 添加 SSO 授权页 CSS — 卡片布局、应用信息区、权限列表样式
- [x] **T5.4** 复制反馈交互 — 复制成功后显示 toast 提示"已复制到剪贴板"
- [x] **T5.5** APP_KEY 查看确认弹窗 — 查看和重置前的二次确认弹窗

## 阶段六：验证与测试

- [x] **T6.1** 测试添加应用 — 填写完整表单（含 OAuth 配置），验证接口参数和返回值
- [x] **T6.2** 测试编辑应用 — 预填充正确，修改 OAuth 配置后保存成功
- [x] **T6.3** 测试 APP_ID 复制 — 点击复制按钮，验证剪贴板内容
- [x] **T6.4** 测试 APP_KEY 查看/隐藏/复制/重置 — 完整流程
- [x] **T6.5** 测试下载 APP_KEY — 验证下载文件内容格式
- [x] **T6.6** 测试 SSO 登录页 — 第三方系统跳转到 SSO 页面 → 登录 → 授权确认 → 回调
- [x] **T6.7** 测试 SSO 拒绝授权 — 点击拒绝后正确返回 error 参数
- [x] **T6.8** 测试已登录用户 — 访问授权页面直接显示授权确认（跳过登录）
- [x] **T6.9** 测试向后兼容 — 旧应用（无 OAuth 配置）在列表和编辑中正常显示默认值
- [x] **T6.10** 测试响应式 — 移动端下弹窗和表格的正常展示
