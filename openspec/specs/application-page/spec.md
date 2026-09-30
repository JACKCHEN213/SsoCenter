# application-page Specification

## Purpose
TBD - created by archiving change adapt-frontend-oauth2. Update Purpose after archive.
## Requirements
### Requirement: 应用添加表单 — OAuth 配置区
添加应用弹窗 SHALL 包含"OAuth 配置"分区，包含以下字段。

#### Scenario: OAuth 配置字段展示
- GIVEN 用户打开添加应用弹窗
- WHEN 弹窗加载完成
- THEN 系统 SHALL 显示"OAuth 配置"分区
- AND 分区 SHALL 包含以下字段：
  - 授权模式（多选 checkbox，可选值 `authorization_code`、`implicit`、`password`、`client_credentials`，默认勾选 `authorization_code`）
  - 授权范围（text，默认值 `trust`）
  - Access Token 有效期（number + 单位选择，默认 8 小时）
  - Refresh Token 有效期（number + 单位选择，默认 24 小时）
  - 授权码有效期（number + 单位选择，默认 10 分钟）

#### Scenario: 有效期单位转换
- GIVEN 用户选择有效期单位为"小时"并输入数值
- WHEN 提交表单
- THEN 系统 SHALL 将有效期转换为秒数存储

### Requirement: 应用列表 — APP_ID 列
应用列表 SHALL 展示 APP_ID 列。

#### Scenario: APP_ID 展示格式
- GIVEN 应用已生成 client_id
- WHEN 列表加载完成
- THEN APP_ID 列 SHALL 使用等宽字体展示 UUID4 格式的 client_id

#### Scenario: 复制 APP_ID
- GIVEN 用户点击 APP_ID 文本
- WHEN 点击事件触发
- THEN 系统 SHALL 复制 APP_ID 到剪贴板
- AND 系统 SHALL 显示"已复制"toast 提示

#### Scenario: 旧应用兼容
- GIVEN 应用未配置 client_id
- WHEN 列表加载完成
- THEN 系统 SHALL 显示灰色"未配置"标签

### Requirement: 应用列表 — 授权模式列
应用列表 SHALL 展示授权模式列，以彩色标签展示已启用的授权模式。

#### Scenario: 授权模式标签展示
- GIVEN 应用配置了授权模式
- WHEN 列表加载完成
- THEN 系统 SHALL 按以下规则显示标签：
  - `authorization_code` → 蓝色 `#1565c0` 标签，文字"授权码"
  - `implicit` → 绿色 `#2e7d32` 标签，文字"隐式"
  - `password` → 橙色 `#e65100` 标签，文字"密码"
  - `client_credentials` → 紫色 `#6a1b9a` 标签，文字"客户端凭证"

#### Scenario: 未配置授权模式
- GIVEN 应用未配置授权模式
- WHEN 列表加载完成
- THEN 系统 SHALL 显示灰色"未配置"标签

### Requirement: 应用详情弹窗
应用列表 SHALL 提供详情弹窗，展示应用完整信息。

#### Scenario: 打开详情弹窗
- GIVEN 用户点击列表中的"详情"按钮
- WHEN 点击事件触发
- THEN 系统 SHALL 打开详情弹窗
- AND 弹窗 SHALL 包含 APP_ID、APP_KEY、OAuth 配置三个展示区

#### Scenario: APP_ID 展示与复制
- GIVEN 详情弹窗已打开
- WHEN 查看 APP_ID 展示区
- THEN 系统 SHALL 显示只读文本框，内容为 UUID4 格式的 client_id
- AND 系统 SHALL 提供复制按钮，点击后复制 APP_ID

#### Scenario: APP_KEY 查看
- GIVEN 详情弹窗已打开
- WHEN 查看 APP_KEY 展示区
- THEN APP_KEY SHALL 默认隐藏显示为 `••••••••••••`
- AND 系统 SHALL 提供"查看"按钮
- WHEN 用户点击"查看"按钮
- THEN 系统 SHALL 弹出确认框"确认查看 APP_KEY？"
- AND 确认后 SHALL 显示明文 APP_KEY

#### Scenario: APP_KEY 复制
- GIVEN APP_KEY 已显示明文
- WHEN 用户点击"复制"按钮
- THEN 系统 SHALL 复制 APP_KEY 到剪贴板
- AND 系统 SHALL 显示"已复制"toast 提示

#### Scenario: APP_KEY 重置
- GIVEN 详情弹窗已打开
- WHEN 用户点击"重置"按钮
- THEN 系统 SHALL 弹出确认框"重置后旧的 APP_KEY 将立即失效"
- AND 确认后 SHALL 调用 `POST /app/reset_secret` 接口
- AND 重置成功后 SHALL 更新显示新的 APP_KEY

#### Scenario: APP_KEY 下载
- GIVEN 详情弹窗已打开
- WHEN 用户点击"下载"按钮
- THEN 系统 SHALL 调用 `GET /app/download_secret` 接口
- AND 系统 SHALL 下载凭证文件

#### Scenario: OAuth 配置展示
- GIVEN 详情弹窗已打开
- WHEN 查看 OAuth 配置展示区
- THEN 系统 SHALL 以只读方式展示授权模式、授权范围、Token 有效期、回调路径

### Requirement: 下载 APP_KEY 文件
系统 SHALL 提供下载应用凭证文件的功能。

#### Scenario: 下载凭证文件
- GIVEN 用户请求下载 APP_KEY
- WHEN 调用 `GET /app/download_secret?id={site_id}`
- THEN 系统 SHALL 返回 `Content-Type: text/plain`
- AND 系统 SHALL 返回 `Content-Disposition: attachment; filename="{应用名称}_oauth_credentials.txt"`
- AND 文件内容 SHALL 包含：
```
# OAuth 2.0 应用凭证
# 应用名称: {name}
# 生成时间: {datetime}
# 重要：请妥善保管此文件，不要泄露 APP_KEY

APP_ID={client_id}
APP_KEY={client_secret}
```

### Requirement: APP_ID 生成规范
应用注册时 SHALL 自动生成符合 UUID Version 4 标准的 APP_ID。

#### Scenario: APP_ID 生成与存储
- GIVEN 新应用注册
- WHEN 系统创建应用记录
- THEN 系统 SHALL 生成 UUID Version 4 格式的 client_id（如 `550e8400-e29b-41d4-a716-446655440000`）
- AND client_id SHALL 存储在 `sc_site.client_id` 字段（VARCHAR(36)，UNIQUE KEY）

### Requirement: 应用列表表格
应用列表表格表头 SHALL 按新规范调整。

#### Scenario: 表头变更
- GIVEN 应用列表页面加载
- WHEN 渲染表格
- THEN 表头 SHALL 变更为：
| 新表头 | 宽度 |
|--------|------|
| 图标 | 8% |
| APP_ID | 22% |
| 应用名称 | 15% |
| 授权模式 | 20% |
| 回调路径 | 15% |
| 操作 | 20% |

- 变更说明：原"应用图片"→"图标"，原"请求路径"移除，新增 APP_ID 和授权模式列，操作列宽度调整为 20%

### Requirement: 添加/编辑应用接口
添加/编辑应用接口 SHALL 支持 OAuth 配置参数。

#### Scenario: 添加应用接口新增字段
- GIVEN 用户提交添加应用表单
- WHEN 调用添加接口
- THEN 请求 SHALL 包含以下新增字段：
- `allowed_grant_types`（String，逗号分隔的授权模式列表）
- `scope`（String，授权范围）
- `access_token_ttl`（Int，Access Token 有效期，单位：秒）
- `refresh_token_ttl`（Int，Refresh Token 有效期，单位：秒）
- `code_ttl`（Int，授权码有效期，单位：秒）

#### Scenario: 添加接口返回新增字段
- GIVEN 应用添加成功
- WHEN 返回响应
- THEN 响应 SHALL 包含 `client_id`（UUID4 格式的 APP_ID）
- AND 响应 SHALL 包含 `client_secret`（APP_KEY，仅添加时返回一次）

#### Scenario: 编辑应用接口支持 OAuth 配置
- GIVEN 用户编辑已有应用
- WHEN 调用编辑接口
- THEN 接口 SHALL 支持更新 `allowed_grant_types`、`scope`、`access_token_ttl`、`refresh_token_ttl`、`code_ttl` 字段

### Requirement: Application 控制器
Application 控制器 SHALL 提供 OAuth 配置管理相关方法。

#### Scenario: 现有方法适配
- GIVEN Application 控制器
- WHEN 调用 `add()` 方法
- THEN 方法 SHALL 接收 OAuth 配置参数并使用 UUID4 生成 client_id
- WHEN 调用 `update()` 方法
- THEN 方法 SHALL 支持更新 OAuth 配置字段

#### Scenario: 新增方法
- GIVEN Application 控制器
- WHEN 需要获取完整应用信息
- THEN 控制器 SHALL 提供 `detail()` 方法
- WHEN 需要重置 client_secret
- THEN 控制器 SHALL 提供 `resetSecret()` 方法
- WHEN 需要下载凭证文件
- THEN 控制器 SHALL 提供 `downloadSecret()` 方法

### 应用列表操作按钮
应用列表操作按钮 SHALL 按新规范调整。

#### Scenario: 按钮变更
- GIVEN 应用列表页面加载
- WHEN 渲染操作列
- THEN 按钮 SHALL 变更为：
- 详情（新增）
- 编辑（保留）
- 删除（保留）
- 下载公钥（保留）
- 下载 APP_KEY（新增）

### Requirement: REQ-FIX-001 访问应用按钮

应用列表操作栏 SHALL 提供"访问应用"按钮，点击后以 OAuth 2.0 授权码模式快速访问应用。

#### Scenario: 按钮展示
- GIVEN 应用列表页面加载完成
- WHEN 渲染操作列
- THEN 系统 SHALL 展示"访问应用"按钮
- AND 按钮 SHALL 使用 `btn-success` 样式（绿色）
- AND 按钮 SHALL 包含 `bi-box-arrow-up-right` 图标

#### Scenario: 点击访问应用
- GIVEN 用户点击某应用的"访问应用"按钮
- WHEN 点击事件触发
- THEN 系统 SHALL 发送 `POST /app/visit`，参数 `id` 为应用 ID
- AND 请求 Header SHALL 携带 `Authorization: <管理员 JWT Token>`
- WHEN 接口返回成功
- THEN 系统 SHALL 拼接 URL：`{redirect_uri}?code={code}&state={state}`
- AND 系统 SHALL 以 `window.open(url, '_blank')` 打开新标签页

#### Scenario: 授权码获取失败
- GIVEN 用户点击"访问应用"按钮
- WHEN 接口返回错误
- THEN 系统 SHALL 显示错误 toast 提示

### Requirement: REQ-FIX-002 为当前用户生成授权码接口

系统 SHALL 提供接口，为当前登录管理员生成一次性授权码，用于快速访问应用。

**实现位置**: `app/controller/Application.php` → `visitApp()`
**路由**: `POST /app/visit`
**中间件**: `CheckLogin` + `ValidateParams`
**验证器场景**: `Application::sceneVisitApp()` — `id` 必填

#### Scenario: 生成授权码成功
- GIVEN 管理员已登录，应用 ID 有效（`is_del=0`、`is_use=1`）
- AND 应用配置了 `redirect_url`
- WHEN 发送 `POST /app/visit`，参数 `id`
- THEN 系统 SHALL 生成 64 位随机授权码（10 分钟过期，一次性使用）
- AND 系统 SHALL 生成 16 位随机 state
- AND 系统 SHALL 将授权码写入 `oauth_authorization_codes` 表，关联当前管理员 `user_id`、`client_id`、`redirect_uri`、默认 `scope`
- AND 系统 SHALL 记录审计日志
- AND 返回: `{ code: 0, success: true, result: { code: "...", state: "...", redirect_uri: "..." } }`

#### Scenario: 应用不存在或已禁用
- GIVEN 应用 ID 无效，或 `is_del=1`，或 `is_use=0`
- WHEN 发送 `POST /app/visit`
- THEN 返回: `{ code: 20001, success: false, result: "应用不存在或未启用" }`

#### Scenario: 应用未配置回调地址
- GIVEN 应用存在但 `redirect_url` 为空
- WHEN 发送 `POST /app/visit`
- THEN 返回: `{ code: 20004, success: false, result: "应用未配置回调地址" }`

#### Scenario: 缺少 id 参数
- GIVEN 未提供 `id` 参数
- WHEN 发送 `POST /app/visit`
- THEN 验证器 SHALL 拦截，返回参数校验错误

### Requirement: REQ-FIX-003 Token 变量 base64 解码修复

`index.html` 中全局 `token` 变量 SHALL 存储原始 JWT 字符串，而非 base64 编码值。

**修复原因**: 登录时 `localStorage.setItem('token', btoa(jwt_string))` 存储了 base64 编码值。`checkToken()` 直接赋值给 `token` 变量，导致后续所有 AJAX 请求的 `Authorization` Header 携带的是 base64 编码值而非原始 JWT。`CheckLogin` 中间件在 POST 请求时进行 JWT 验证，因 base64 字符串无三段式 `.` 结构而报 "Wrong number of segments"。

#### Scenario: checkToken 解码 token
- GIVEN 用户已登录，`localStorage` 中存在 `token`（base64 编码的 JWT）
- WHEN `checkToken()` 执行
- THEN 系统 SHALL 使用 `atob()` 解码 base64 值，将原始 JWT 赋给全局 `token` 变量

#### Scenario: token 非 base64 兼容
- GIVEN `localStorage` 中的 `token` 不是合法 base64（直接是 JWT 字符串）
- WHEN `checkToken()` 执行 `atob()` 解码
- THEN 系统 SHALL 捕获异常，使用原始值作为 `token`

#### Scenario: token 为空
- GIVEN `localStorage` 中无 `token`
- WHEN `checkToken()` 执行
- THEN 系统 SHALL 跳转登录页

#### Scenario: 修复后 POST 请求验证
- GIVEN `checkToken()` 已修复
- WHEN 用户调用任何 POST 接口（如 `reset_secret`、`get_secret`）
- THEN `Authorization` Header SHALL 携带原始 JWT 字符串
- AND `CheckLogin` 中间件 SHALL 正常通过 JWT 验证

### Requirement: 应用列表操作按钮

应用列表操作栏的按钮组成 SHALL 按以下方式变更。

**原定义**（`adapt-frontend-oauth2` — `Requirement: 应用列表操作按钮`）：
- 详情、编辑、删除、下载公钥、下载 APP_KEY

**变更后**：
- 详情、编辑、删除、访问应用

#### Scenario: 按钮变更
- GIVEN 应用列表页面加载完成
- WHEN 渲染操作列
- THEN 按钮 SHALL 变更为：
  - 详情（保留）
  - 编辑（保留）
  - 删除（保留）
  - 访问应用（新增）
- AND "下载公钥"按钮 SHALL 移除
- AND "下载 APP_KEY"按钮 SHALL 从操作栏移除（详情弹窗中保留）

#### Scenario: 移除 downloadPublicToken 函数
- GIVEN 操作栏"下载公钥"按钮已移除
- WHEN 页面脚本加载
- THEN `downloadPublicToken()` 函数 SHALL 一并移除

