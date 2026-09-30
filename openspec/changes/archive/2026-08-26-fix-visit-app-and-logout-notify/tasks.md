# 实施任务清单

## 一、后端改造

- [x] T1: 修改 `Application::visitApp()` — 在生成授权码后，调用 `AuditLogService::app('app_visit', ...)` 记录操作日志

---

## 二、测试验证

- [x] T2: 验证"访问应用"流程 — 点击按钮后在新标签页打开 `redirect_uri?code=xxx&state=xxx`，与改造前行为一致

- [x] T3: 验证操作日志 — 在操作日志页面能看到 `app_visit` 类型的记录
