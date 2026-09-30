# 任务清单：操作日志系统

> 变更 ID: `add-operation-log`

---

## 前置条件

- [x] 数据库迁移脚本 `extend/migrations/operation_log.sql`

---

## 阶段 1：数据库

- [x] **T1.1** 创建 `sc_operation_log` 表

---

## 阶段 2：日志服务

- [x] **T2.1** 创建 `app/service/AuditLogService.php`
  - `log()` — 通用日志写入
  - `login()` — 登录日志快捷方法
  - `logout()` — 登出日志快捷方法
  - `app()` — 应用操作日志快捷方法
  - `user()` — 用户操作日志快捷方法
  - `getOperator()` — 从 JWT 获取当前操作者
- [x] **T2.2** 敏感数据脱敏逻辑（密码、APP_KEY、X-trust-signature）

---

## 阶段 3：控制器集成

- [x] **T3.1** `Login::login()` — 集成登录成功/失败日志
- [x] ~~**T3.2** `Login::logout()` — 集成登出日志~~ (系统无后端登出 API，跳过)
- [x] **T3.3** `Application::add()` — 集成创建应用日志
- [x] **T3.4** `Application::update()` — 集成修改应用日志
- [x] **T3.5** `Application::delete()` — 集成删除应用日志
- [x] **T3.6** `Application::resetSecret()` — 集成重置 APP_KEY 日志
- [x] **T3.7** `Application::getSecret()` — 集成查看 APP_KEY 日志
- [x] **T3.8** `User::add()` — 集成新增用户日志
- [x] **T3.9** `User::update()` — 集成修改用户日志
- [x] **T3.10** `User::delete()` — 集成删除用户日志
- [x] **T3.11** `User::pushUser()` — 集成推送用户日志（含完整请求/响应）
- [x] **T3.12** `User::pushUserToAll()` — 集成批量推送日志

---

## 阶段 4：日志查询后端

- [x] **T4.1** 创建 `app/controller/OperationLog.php`
  - `index()` — 渲染页面
  - `list()` — 分页查询（支持 module/action/operator_name/时间范围筛选）
  - `detail()` — 单条详情
- [x] **T4.2** 创建 `app/validate/OperationLog.php` — 参数校验
- [x] **T4.3** 新增路由：`GET /operation_log`、`POST /operation_log/list`、`POST /operation_log/detail`

---

## 阶段 5：前端页面

- [x] **T5.1** 创建 `app/view/operation_log.html` — 日志列表页（筛选 + 表格 + 分页）
- [x] **T5.2** 日志详情弹窗 — 展示完整请求/响应数据
- [x] **T5.3** 修改 `template/index.html` — 左侧导航新增"操作日志"菜单项（位于"用户管理"下方）

---

## 阶段 6：归档

- [x] **T6.1** 增量规范 `specs/operation-log/spec.md`
- [x] **T6.2** 测试通过后归档

---

## 执行顺序

```
阶段 1（数据库）→ 阶段 2（日志服务）→ 阶段 3（控制器集成）
                                                ↓
                              阶段 4（查询后端）→ 阶段 5（前端页面）
                                                ↓
                                           阶段 6（归档）
```
