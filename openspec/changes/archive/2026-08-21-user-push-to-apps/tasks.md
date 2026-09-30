# 任务清单：用户推送到第三方应用

> 变更 ID: `user-push-to-apps`
> 状态: PLANNING

---

## 前置条件

- [x] 数据库迁移脚本：创建 `sc_site_push_api` 和 `sc_user_push_log` 表
- [x] 编写迁移脚本 `extend/migrations/user_push_tables.sql`

---

## 阶段 1：后端 — 推送接口配置（应用侧）

- [x] **T1.1** 扩展 `Application` 验证器 — 新增 `push_apis` 参数的校验规则（JSON 格式校验、URL 必填、action 枚举校验）
- [x] **T1.2** 改造 `Application::add()` — 事务内同时保存 `sc_site_push_api` 记录（先删旧的再插入新的）
- [x] **T1.3** 改造 `Application::update()` — 事务内同步更新 `sc_site_push_api` 记录
- [x] **T1.4** 新增 `Application::getPushApis()` 方法 — 返回应用的推送接口配置列表
- [x] **T1.5** 新增路由 `GET /app/push_apis` — 绑定到 `getPushApis`

---

## 阶段 2：后端 — 推送服务

- [x] **T2.1** 创建 `app/service/PushService.php` — 核心推送引擎
  - `pushUser(int $userId, int $siteId, string $action): PushResult`
  - `pushUserToAll(int $userId): array`
  - `buildRequestBody(string $template, array $user): string`
  - `replaceVariables(string $str, array $user): string`
  - `evaluateResponse(string $body, string $field, string $value): bool`
  - `sendRequest(string $method, string $url, array $headers, string $body, int $timeout): array`
  - `logPush(...): void`
- [x] **T2.2** 创建 `app/service/PushResult.php` — 推送结果值对象（success/failed/notConfigured）

---

## 阶段 3：后端 — 用户推送管理接口

- [x] **T3.1** 新增 `User::pushStatus()` 方法 — 查询用户对所有应用的推送状态
- [x] **T3.2** 新增 `User::pushUser()` 方法 — 推送用户到指定应用（调用 PushService）
- [x] **T3.3** 新增 `User::pushUserToAll()` 方法 — 推送用户到所有已配置的应用
- [x] **T3.4** 新增 `User::pushLog()` 方法 — 获取某次推送的详细日志
- [x] **T3.5** 扩展 `User` 验证器 — 新增 pushStatus、pushUser、pushLog 的参数校验场景
- [x] **T3.6** 新增路由：`POST /user/push_status`、`POST /user/push`、`POST /user/push_all`、`POST /user/push_log`
- [x] **T3.7** 改造 `User::delete()` — 删除用户时自动向第三方推送 delete 请求

---

## 阶段 4：前端 — 应用推送配置 UI

- [x] **T4.1** 修改 `index.html` 添加应用弹窗 — 新增"推送接口配置"折叠面板（3 个子面板：新增/修改/删除接口）
- [x] **T4.2** 修改 `index.html` 编辑应用弹窗 — 回填已有推送接口配置
- [x] **T4.3** 修改 `index.html` 提交逻辑 — 将推送配置序列化后随表单一起提交
- [x] **T4.4** 修改 `index.html` 详情弹窗 — 展示推送接口配置（只读）

---

## 阶段 5：前端 — 用户推送管理 UI

- [x] **T5.1** 修改 `user.html` — 操作栏新增"推送"按钮
- [x] **T5.2** 修改 `user.html` — 新增推送状态弹窗（表格展示各应用推送状态）
- [x] **T5.3** 修改 `user.html` — 实现"推送"/"重推"按钮的 AJAX 调用
- [x] **T5.4** 修改 `user.html` — 实现"全部推送"功能
- [x] **T5.5** 修改 `user.html` — 实现推送日志查看（展开行或弹窗）

---

## 阶段 6：归档

- [x] **T6.1** 编写增量规范 `specs/application-push/spec.md`
- [x] **T6.2** 编写增量规范 `specs/user-push/spec.md`
- [x] **T6.3** 测试完成后归档到 `archive/`

---

## 执行顺序

```
阶段 1（应用侧配置）──→ 阶段 2（推送服务）──→ 阶段 3（用户推送接口）
                                                        ↓
                                    阶段 4（应用前端）←──┤──→ 阶段 5（用户前端）
                                                        ↓
                                                   阶段 6（归档）
```
