# 提案：修复"访问应用"流程

## 变更 ID

`fix-visit-app-and-logout-notify`

## 背景与动机

在会话管理（`session-management`）完成后，发现应用管理"访问应用"功能存在一个缺陷：

### 问题：操作日志未记录

当前 `visitApp()` 仅使用 `recordLog()` 记录到系统日志文件，未写入 `sc_operation_log` 表，导致操作日志页面看不到"访问应用"的记录。

## 变更目标

### 1. "访问应用"记录操作日志

改造 `Application::visitApp()` 方法，在生成授权码的同时，通过 `AuditLogService::app()` 将操作记录写入 `sc_operation_log` 表，使操作日志页面可见。

### 2. 保持原有访问流程

保持原有的"访问应用"逻辑：
- 后端生成授权码（通过 `AuthorizationCodeService`）
- 前端拼接 `redirect_uri?code=xxx&state=xxx` URL
- 打开新标签页访问第三方应用

**改造前后对比**：

| 步骤 | 改造前 | 改造后 |
|------|--------|--------|
| 1 | AJAX 调用 `/app/visit` | AJAX 调用 `/app/visit`（不变） |
| 2 | 后端生成授权码，返回 code/state/redirect_uri | 后端生成授权码，**同时记录操作日志**，返回 code/state/redirect_uri |
| 3 | 前端拼接 `redirect_uri?code=xxx&state=xxx` 打开 | 前端拼接 `redirect_uri?code=xxx&state=xxx` 打开（不变） |
| 4 | 仅记录到系统日志文件 | 记录到 `sc_operation_log` 表（可在操作日志页面查看） |

## 涉及范围

| 范围 | 说明 |
|------|------|
| **改造** | `Application::visitApp()` — 在生成授权码的同时，调用 `AuditLogService::app()` 记录操作日志 |

## 影响分析

- **OAuth 流程**：无变化，保持原有流程
- **向后兼容**：完全兼容，仅新增日志记录功能
- **安全性**：授权码通过标准方式生成，有完整审计记录
- **用户体验**：无变化，管理员点击"访问应用"后仍然直接跳转到第三方应用
