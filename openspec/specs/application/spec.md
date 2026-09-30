# Application 模块 — 应用管理

> 状态: IMPLEMENTED
> 最后更新: 2026-08-19

## Purpose

应用管理模块负责第三方应用的注册与维护。每个应用包含名称、请求地址、回调地址和图标，注册时自动生成 RSA 公钥文件供应用方使用。支持图片上传和公钥下载。所有删除为软删除。

注册时同时自动生成 OAuth 2.0 客户端凭证（`client_id` 32 位、`client_secret` 64 位随机十六进制字符串），应用可配置授权模式与 Token 有效期等 OAuth 参数；删除应用时级联清理关联的 OAuth 记录（授权码、Access Token、Refresh Token）。

---
## Requirements
### Requirement: REQ-APP-001 添加应用（注册）

注册一个新的第三方应用，系统为其生成 RSA 公钥文件和 OAuth 客户端凭证。

**实现位置**: `app/controller/Application.php` → `add()`
**路由**: `POST /app/add`
**验证器场景**: `Application::sceneAdd()` — `app_name`, `app_request_url`, `app_redirect_url` 均为必填

#### Scenario: 添加成功（新应用）
- Given 不存在名称、请求地址、回调地址完全相同的应用（`is_del=0`）
- When 发送 `POST /app/add`，参数 `app_name`, `app_request_url`, `app_redirect_url`, `app_img_url`(可选)
- Then 开启数据库事务
- And 插入 `site` 表，获取新应用 ID
- And 生成 RSA 公钥文件（文件名为 `md5(site_id).pem`），保存到 `config('common.APP_KEY_PATH')` 目录
- And 将公钥文件名更新到 `site.public_key` 字段
- And 自动生成 OAuth 凭证：`client_id`（32 位十六进制，冲突时重新生成）、`client_secret`（64 位十六进制），写入 `site` 表
- And 提交事务
- And 返回: `{ code: 0, success: true, message: "成功", result: { id: <新应用ID>, client_id: "...", client_secret: "..." } }`

#### Scenario: 应用已存在（重复检测）
- Given 已存在一个 `is_del=0` 的应用，其 `name`、`request_url`、`redirect_url` 与提交数据完全一致
- When 发送 `POST /app/add`
- Then 返回: `{ code: 0, success: true, message: "数据已经存在了", result: { id: <已存在的应用ID>, client_id: "...", client_secret: "..." } }`

#### Scenario: 缺少必填参数
- Given 未提供 `app_name`、`app_request_url` 或 `app_redirect_url`
- When 发送 `POST /app/add`
- Then 验证器拦截，返回: `{ code: 10001, success: false, message: "参数验证失败", result: "<具体校验错误>" }`

---

### Requirement: REQ-APP-002 更新应用

修改已有应用的信息（名称、地址、图标）与 OAuth 配置，并确保公钥文件存在。

**实现位置**: `app/controller/Application.php` → `update()`
**路由**: `PUT /app/update`
**验证器场景**: `Application::sceneUpdate()` — `id`, `app_name`, `app_request_url`, `app_redirect_url` 均为必填

**可选 OAuth 配置参数**（仅传入时更新，未传不修改）：

| 参数 | 说明 | 默认值（建表时） |
|------|------|------------------|
| `allowed_grant_types` | 允许的授权模式，逗号分隔（`authorization_code` / `implicit` / `password` / `client_credentials`） | `authorization_code` |
| `scope` | 默认授权范围 | `trust` |
| `access_token_ttl` | Access Token 有效期（秒） | `28800`（8 小时） |
| `refresh_token_ttl` | Refresh Token 有效期（秒） | `86400`（24 小时） |
| `code_ttl` | 授权码有效期（秒） | `600`（10 分钟） |

**校验规则**（`Application::sceneUpdate()`）：`allowed_grant_types` 逗号分隔，每项须为 `authorization_code` / `implicit` / `password` / `client_credentials` 之一（自定义规则 `checkGrantTypes`）；`scope` 最长 255 字符；三个 TTL 字段须为大于 0 的整数。

#### Scenario: 更新成功
- Given 应用 ID 为 1 存在（`is_del=0`）
- And 更新后的 name+request_url+redirect_url 组合不与其他应用重复
- When 发送 `PUT /app/update`，参数 `id=1`, `app_name`, `app_request_url`, `app_redirect_url`, `app_img_url`(可选), OAuth 配置参数(可选)
- Then 开启事务，更新 `site` 表对应记录（含传入的 OAuth 配置字段）
- And 调用 `generatePublicKey` 确保公钥文件存在
- And 提交事务
- And 返回: `{ code: 0, success: true, message: "成功", result: "更新成功" }`

#### Scenario: 更新数据与其他应用重复
- Given 更新后的 name+request_url+redirect_url 与另一个 `is_del=0` 应用完全相同
- When 发送 `PUT /app/update`
- Then 返回错误: `{ code: 10004, success: false, message: "数据已经存在了", result: "不能更新为已存在的应用" }`

---

### Requirement: REQ-APP-003 删除应用（软删除）

将应用标记为已删除，同时清理对应的公钥文件和关联的 OAuth 记录。

**实现位置**: `app/controller/Application.php` → `delete()`
**路由**: `DELETE /app/delete`
**验证器场景**: `Application::sceneDelete()` — `id` 为必填

#### Scenario: 删除成功
- Given 应用 ID 为 1 存在
- When 发送 `DELETE /app/delete`，参数 `id=1`
- Then 开启事务
- And 删除该应用对应的公钥文件（`config('common.APP_KEY_PATH')` + `public_key` 字段值）
- And 级联清理 OAuth 记录：删除 `oauth_authorization_codes` 中该应用（按 `client_id`）的所有授权码，将 `oauth_access_tokens` / `oauth_refresh_tokens` 中该应用的所有 Token 记录标记为已撤销（`revoked=1`）
- And 将 `site` 表的 `is_del` 字段设为 1
- And 提交事务
- And 返回: `{ code: 0, success: true, message: "成功", result: "删除成功" }`

---

### Requirement: REQ-APP-004 上传应用图标

上传应用的图标图片文件。

**实现位置**: `app/controller/Application.php` → `uploadImage()`
**路由**: `POST /app/upload_image`
**验证器场景**: `Application::sceneUploadImage()` — `file_data` 必填，须为文件，需通过 `checkImage` 自定义校验

#### Scenario: 上传成功
- Given 上传一个合法的图片文件（后缀和大小符合要求）
- When 发送 `POST /app/upload_image`，文件字段名为 `file_data`
- Then 文件移动到 `config('common.APP_IMAGE_PREFIX')` 目录，文件名格式为 `app_image_<timestamp>`
- And 返回: `{ code: 0, success: true, message: "成功", result: "<图片路径>" }`

#### Scenario: 文件后缀不合法
- Given 上传的文件后缀不在 `config('common.IMAGE_EXT')` 白名单中
- When 发送 `POST /app/upload_image`
- Then 返回错误: `{ code: 10001, success: false, message: "参数验证失败", result: "file_data不是一个有效的的图片后缀名" }`

#### Scenario: 文件大小超限
- Given 上传的图片超过 `config('common.MAX_IMAGE_SIZE')` 限制
- When 发送 `POST /app/upload_image`
- Then 返回错误: `{ code: 10001, success: false, message: "参数验证失败", result: "file_data大小最大为xxx, 当前为: xxx" }`

---

### Requirement: REQ-APP-005 删除已上传的图标

删除之前上传的应用图标文件。

**实现位置**: `app/controller/Application.php` → `deleteUploadedImage()`
**路由**: `DELETE /app/delete_uploaded_image`
**验证器场景**: `Application::sceneDeleteUploadedImage()` — `image_url` 为必填

#### Scenario: 删除成功
- Given 图片文件存在于服务器
- When 发送 `DELETE /app/delete_uploaded_image`，参数 `image_url=<图片路径>`
- Then 从磁盘删除该文件
- And 返回: `{ code: 0, success: true, message: "成功", result: "删除成功" }`

---

### Requirement: REQ-APP-006 下载应用公钥

下载应用对应的 RSA 公钥文件。

**实现位置**: `app/controller/Application.php` → `download()`
**路由**: `GET /app/download`

#### Scenario: 下载成功
- Given 参数 `_f` 为公钥文件名的 base64 编码值，且文件存在
- When 发送 `GET /app/download?_f=<base64编码的文件名>`
- Then 以附件形式下载该文件（文件名为 `md5(timestamp)`）

#### Scenario: 文件不存在
- Given 参数 `_f` 对应的文件不存在
- When 发送 `GET /app/download?_f=<无效值>`
- Then 重定向到 `/404` 页面

---

### Requirement: 应用注册自动生成 OAuth 凭证

注册应用时系统 SHALL 自动生成 `client_id`（32 位随机字符串）和 `client_secret`（64 位随机字符串），并在响应中返回给前端展示。

**修改后响应**：
```json
{
  "code": 0,
  "success": true,
  "message": "成功",
  "result": {
    "id": 1,
    "client_id": "ab3sd...",
    "client_secret": "xY9kL..."
  }
}
```

#### Scenario: 注册新应用返回 OAuth 凭证
- GIVEN 提交不重复的应用信息
- WHEN 发送 `POST /app/add`
- THEN 创建应用并自动生成 client_id/client_secret，响应 result 中包含 id、client_id、client_secret

#### Scenario: client_id 唯一性
- GIVEN 生成的 client_id 与现有应用冲突
- WHEN 生成凭证
- THEN 重新生成直至唯一

### Requirement: 应用 OAuth 配置管理

系统 SHALL 支持在应用更新时配置以下 OAuth 相关参数（未传入则不修改）：

| 字段 | 说明 | 默认值 |
|------|------|--------|
| `allowed_grant_types` | 允许的授权模式（逗号分隔） | `authorization_code` |
| `scope` | 默认授权范围 | `trust` |
| `access_token_ttl` | Access Token 有效期（秒） | `28800`（8 小时） |
| `refresh_token_ttl` | Refresh Token 有效期（秒） | `86400`（24 小时） |
| `code_ttl` | 授权码有效期（秒） | `600`（10 分钟） |

#### Scenario: 更新应用 OAuth 配置
- GIVEN 应用存在
- WHEN 发送 `PUT /app/update` 携带 allowed_grant_types / access_token_ttl 等字段
- THEN 对应配置更新成功

### Requirement: 应用删除级联清理 OAuth 记录

删除应用时，除原有公钥文件清理外，系统 SHALL 额外清理：
- `oauth_authorization_codes` 中该应用的所有授权码
- `oauth_access_tokens` 中该应用的所有 Access Token 记录
- `oauth_refresh_tokens` 中该应用的所有 Refresh Token 记录

#### Scenario: 删除应用级联清理
- GIVEN 应用存在且持有授权码与 Token 记录
- WHEN 发送 `DELETE /app/delete`
- THEN 应用软删除，关联的授权码被删除、access/refresh token 被撤销

### Requirement: sc_site 表新增 OAuth 字段

`sc_site` 表 SHALL 新增以下字段（存量记录通过数据迁移脚本补齐 client_id/client_secret）：

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `client_id` | VARCHAR(64) | NULL → 自动生成 | OAuth 客户端标识（UNIQUE） |
| `client_secret` | VARCHAR(128) | NULL → 自动生成 | OAuth 客户端密钥 |
| `allowed_grant_types` | VARCHAR(255) | `'authorization_code'` | 允许的授权模式 |
| `scope` | VARCHAR(255) | `'trust'` | 默认授权范围 |
| `access_token_ttl` | INT | `28800` | Access Token 有效期 |
| `refresh_token_ttl` | INT | `86400` | Refresh Token 有效期 |
| `code_ttl` | INT | `600` | 授权码有效期 |

#### Scenario: 迁移后字段可用
- GIVEN 迁移脚本已执行
- WHEN 应用注册/更新/删除
- THEN 新字段可正常读写，存量应用已补齐 client_id/client_secret

## 数据库表

| 表名 | 字段 | 说明 |
|------|------|------|
| `site` | `id`, `name`, `request_url`, `redirect_url`, `image`, `public_key`, `is_del`, `is_use`, `client_id`, `client_secret`, `allowed_grant_types`, `scope`, `access_token_ttl`, `refresh_token_ttl`, `code_ttl` | 应用主表 |

### site 表 OAuth 凭证与配置字段

（迁移脚本：`extend/migrations/oauth2_standardize.sql`）

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `client_id` | VARCHAR(64) UNIQUE | NULL → 注册时自动生成 | OAuth 客户端标识（32 位十六进制） |
| `client_secret` | VARCHAR(128) | NULL → 注册时自动生成 | OAuth 客户端密钥（64 位十六进制） |
| `allowed_grant_types` | VARCHAR(255) | `'authorization_code'` | 允许的授权模式，逗号分隔 |
| `scope` | VARCHAR(255) | `'trust'` | 默认授权范围 |
| `access_token_ttl` | INT | `28800` | Access Token 有效期（秒） |
| `refresh_token_ttl` | INT | `86400` | Refresh Token 有效期（秒） |
| `code_ttl` | INT | `600` | 授权码有效期（秒） |

> 存量应用通过数据迁移脚本（T1.5）批量补齐 `client_id`/`client_secret`。

---

## 相关路由

| 路由 | 方法 | 控制器/方法 | 说明 |
|------|------|-------------|------|
| `POST /app/add` | POST | `Application/add` | 注册新应用 |
| `PUT /app/update` | PUT | `Application/update` | 更新应用信息 |
| `DELETE /app/delete` | DELETE | `Application/delete` | 软删除应用 |
| `POST /app/upload_image` | POST | `Application/uploadImage` | 上传应用图标 |
| `DELETE /app/delete_uploaded_image` | DELETE | `Application/deleteUploadedImage` | 删除已上传图标 |
| `GET /app/download` | GET | `Application/download` | 下载应用公钥 |
| `GET /app/detail` | GET | `Application/detail` | 获取应用详情（含 client_id，不含 client_secret 明文） |
| `POST /app/reset_secret` | POST | `Application/resetSecret` | 重置 APP_KEY，返回新值明文，记录审计日志 |
| `POST /app/get_secret` | POST | `Application/getSecret` | 获取 APP_KEY 脱敏预览（查看按钮用） |
| `POST /app/download_secret` | POST | `Application/downloadSecret` | 下载 APP_ID/APP_KEY 凭证文本文件 |

---

## 应用详情与凭证管理（adapt-frontend-oauth2）

### Requirement: REQ-APP-007 获取应用详情

返回单个应用的完整信息，包含 OAuth 配置与 client_id，但 **不返回 client_secret 明文**。

**实现位置**: `app/controller/Application.php` → `detail()`
**路由**: `GET /app/detail`
**验证器场景**: `Application::sceneDetail()` — `id` 为必填

#### Scenario: 获取成功
- Given 应用 ID 存在（`is_del=0`）
- When 发送 `GET /app/detail?id=1`
- Then 返回完整信息：id、name、image、request_url、redirect_url、client_id、allowed_grant_types、scope、三个 TTL 字段、`has_client_secret`（bool）、`client_secret_preview`（脱敏预览：首尾各 4 位 + 中间 *）、create_time、update_time

#### Scenario: 应用不存在
- Given 应用 ID 不存在或已删除
- When 发送 `GET /app/detail?id=999`
- Then 返回: `{ code: 20001, success: false, message: "应用不存在", result: false }`

---

### Requirement: REQ-APP-008 重置 APP_KEY

重新生成 client_secret，返回新值明文。仅在审计日志中记录「重置」动作，**不记录明文 secret**。

**实现位置**: `app/controller/Application.php` → `resetSecret()`
**路由**: `POST /app/reset_secret`
**验证器场景**: `Application::sceneResetSecret()` — `id` 为必填
**鉴权**: 必须携带有效 JWT（CheckLogin 中间件）

#### Scenario: 重置成功
- Given 应用 ID 存在（`is_del=0`）
- When 发送 `POST /app/reset_secret`，携带有效 Authorization Header
- Then 生成新的 64 位十六进制 client_secret
- And 更新 site 表
- And 写入审计日志：`"重置应用 APP_KEY：id=<id>, name=<name>"`（不含明文 secret）
- And 返回: `{ code: 0, success: true, message: "成功", result: { id, client_id, client_secret: "<新值明文>" } }`

#### Scenario: 未登录
- Given 未携带 Authorization Header 或 JWT 无效
- When 发送 `POST /app/reset_secret`
- Then 返回: `{ code: 10007, success: false, message: "身份验证错误", result: "登录已失效，请重新登录" }`

---

### Requirement: REQ-APP-009 查看 APP_KEY（脱敏预览）

返回 client_secret 的脱敏预览（首尾各 4 位 + 中间 *），用于前端"查看"按钮。

**实现位置**: `app/controller/Application.php` → `getSecret()`
**路由**: `POST /app/get_secret`
**验证器场景**: `Application::sceneGetSecret()` — `id` 为必填
**鉴权**: 必须携带有效 JWT

#### Scenario: 查看成功
- Given 应用 ID 存在且有 client_secret
- When 发送 `POST /app/get_secret`
- Then 返回: `{ code: 0, success: true, message: "成功", result: { client_secret_preview: "<脱敏值>" } }`

---

### Requirement: REQ-APP-010 下载 APP_ID/APP_KEY 凭证文件

生成包含 APP_ID 和 APP_KEY 的纯文本凭证文件供下载。

**实现位置**: `app/controller/Application.php` → `downloadSecret()`
**路由**: `POST /app/download_secret`
**验证器场景**: `Application::sceneDownloadSecret()` — `id` 为必填
**鉴权**: 必须携带有效 JWT

#### Scenario: 下载成功
- Given 应用 ID 存在且有 client_id 和 client_secret
- When 发送 `POST /app/download_secret`
- Then 返回纯文本文件（Content-Type: text/plain; charset=UTF-8）
- And 文件名: `app_oauth_credentials_<app_name>_<Y-m-d>.txt`（RFC 5987 filename* 编码）
- And 文件内容包含：头部注释 + `APP_ID=<client_id>` + `APP_KEY=<client_secret>`

---

## 公钥生成机制

| 项目 | 说明 |
|------|------|
| 生成方式 | 使用系统级 JWT RSA 密钥对的公钥部分 |
| 文件命名 | `md5(site_id).pem` |
| 存储路径 | `config('common.APP_KEY_PATH')` |
| 生成时机 | 添加/更新应用时，若公钥文件不存在则生成 |
| 清理时机 | 删除应用时，同步删除公钥文件 |
| 来源密钥 | `Key::getPublicKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME'))` |

---

## 相关规范

- `openspec/specs/oauth/spec.md` — OAuth 2.0 端点（消费本模块颁发的 `client_id`/`client_secret` 与 OAuth 配置）
- `openspec/specs/scim/spec.md` — SCIM 用户同步（以 `client_id` + `client_secret` 进行 SM3 签名认证）
