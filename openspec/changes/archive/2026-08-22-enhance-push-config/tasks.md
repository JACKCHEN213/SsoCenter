# 任务清单：增强推送配置（enhance-push-config）

> 变更 ID: `enhance-push-config`
> 前置变更: `user-push-to-apps`（已归档）

---

## 前置条件

- [x] 安装 Guzzle：`composer require guzzlehttp/guzzle`
- [x] 数据库迁移脚本 `extend/migrations/enhance_push_config.sql`

---

## 阶段 1：数据库迁移

- [x] **T1.1** `sc_site_push_api` 表新增字段：`appid`、`appkey`、`extra_headers`、`body_params`、`response_rules`
- [x] **T1.2** `sc_site_push_api` 表迁移旧数据（`request_headers` → `extra_headers`，`body_template` → `body_params`，`success_field/success_value` → `response_rules`）
- [x] **T1.3** `sc_site_push_api` 表删除旧字段：`request_headers`、`body_template`、`success_field`、`success_value`
- [x] **T1.4** `sc_user_push_log` 表新增字段：`request_headers`、`external_id`、`failed_rule`
- [x] **T1.5** `sc_user` 表新增字段：`external_ids`（JSON）

---

## 阶段 2：签名引擎

- [x] **T2.1** 确认 SM3 依赖（openssl 扩展或纯 PHP 实现）
- [x] **T2.2** 创建 `app/service/SignatureService.php`
  - `contentMd5(string $content): string`
  - `sm3Hmac(string $data, string $key): string`
  - `sign(string $body, string $urlPath, string $method, string $appid, string $appkey): array`
- [x] **T2.3** 创建 `app/service/EncryptService.php`（AES-256-CBC 加解密 appkey）

---

## 阶段 3：推送引擎改造

- [x] **T3.1** 改造 `PushService::pushUser()` — 使用 `body_params` 构建请求体（替代 `body_template`）
- [x] **T3.2** 改造 `PushService::pushUser()` — 使用 `response_rules` 多规则验证（替代 `success_field/success_value`）
- [x] **T3.3** 改造 `PushService::pushUser()` — 自动调用 `SignatureService` 计算签名 Header
- [x] **T3.4** 新增 `PushService::buildHeaders()` — 系统签名 Header + `extra_headers` 合并
- [x] **T3.5** 新增 `PushService::getExternalId()` / `saveExternalId()` — externalId 管理
- [x] **T3.6** 改造 `PushService::pushUser()` — 创建成功后保存 externalId
- [x] **T3.7** 改造 `PushService::logPush()` — 记录 `request_headers`、`external_id`、`failed_rule`
- [x] **T3.8** 改造 `PushService::sendRequest()` — 使用 Guzzle 替代已弃用的 cURL
- [x] **T3.9** 创建 `app/service/ValidationResult.php` — 多规则验证结果对象

---

## 阶段 4：后端接口改造

- [x] **T4.1** 改造 `Application::add()` — 接收 `push_appid`、`push_appkey`、`push_apis[].extra_headers`/`body_params`/`response_rules`
- [x] **T4.2** 改造 `Application::update()` — 同上
- [x] **T4.3** 改造 `Application::getPushApis()` — 返回新字段结构（appkey 脱敏为 `has_appkey`）
- [x] **T4.4** 改造 `Application` 验证器 — 新字段校验（`extra_headers` 数组、`body_params` 数组含 type 枚举、`response_rules` 数组含 type 枚举）
- [x] **T4.5** 改造 `User::pushStatus()` — 返回 `external_id`、`failed_rule`
- [x] **T4.6** 改造 `User::pushUser()` / `pushUserToAll()` — 返回 `external_id`、`failed_rule`
- [x] **T4.7** 改造 `User::pushLog()` — 返回 `request_headers`、`external_id`、`failed_rule`

---

## 阶段 5：前端 — 推送配置 UI 改造（index.html）

- [x] **T5.1** 新增基础认证区（appid + appkey 输入框，appkey 隐藏 + 查看按钮）
- [x] **T5.2** 请求头改造：JSON textarea → 动态可增删的 name-value 行
- [x] **T5.3** 请求体参数改造：JSON textarea → 动态可增删的 name-value-type-required 行，默认预设 6 个 SCIM 字段
- [x] **T5.4** 响应验证规则改造：单字段/值 → 动态可增删的 field-type-value 行，默认预设 `success==true` + `code==200`
- [x] **T5.5** 编辑弹窗回填改造 — 适配新字段结构
- [x] **T5.6** 详情弹窗展示改造 — 适配新字段结构
- [x] **T5.7** 提交逻辑改造 — 序列化新字段格式

---

## 阶段 6：前端 — 推送状态弹窗改造（user.html）

- [x] **T6.1** 推送状态表格新增 `externalId` 列
- [x] **T6.2** 失败状态显示 `failed_rule` 描述（如 `code: 期望200, 实际500`）
- [x] **T6.3** 推送日志弹窗适配新字段（request_headers、external_id、failed_rule）

---

## 阶段 7：归档

- [x] **T7.1** 增量规范 `specs/application-push/spec.md`（MODIFIED 现有 REQ-PUSH-* + ADDED 新增）
- [x] **T7.2** 增量规范 `specs/user-push/spec.md`（MODIFIED 现有 REQ-UPUSH-* + ADDED 新增）
- [x] **T7.3** 更新合并后的 `openspec/specs/application-push/spec.md` 和 `openspec/specs/user-push/spec.md`
- [x] **T7.4** 测试通过后归档到 `archive/`

---

## 执行顺序

```
阶段 1（数据库迁移）──→ 阶段 2（签名引擎）──→ 阶段 3（推送引擎改造）
                                                          ↓
                                    阶段 4（后端接口改造）──→ 阶段 5（应用前端改造）
                                                          ↓
                                    阶段 6（用户前端改造）──→ 阶段 7（归档）
```
