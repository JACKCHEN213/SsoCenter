# 增量规范：应用管理

## MODIFIED Requirements

### REQ-APP-VISIT-001: 访问应用流程（改造）

**描述**：管理员在应用管理页面点击"访问应用"时，系统须通过标准 OAuth 2.0 授权码模式访问第三方应用，而非绕过授权端点直接生成授权码。

**改造前**：
1. 前端 AJAX 调用 `/app/visit`
2. 后端生成授权码，返回 `{code, state, redirect_uri}`
3. 前端拼接 `redirect_uri?code=xxx&state=xxx` 打开新标签页
4. 第三方应用收到 code（可能未换取 token）

**改造后**：
1. 前端直接拼接 `/oauth/authorize?response_type=code&client_id=xxx&redirect_uri=xxx&state=xxx`
2. 浏览器打开该 URL（管理员已登录，自动通过授权）
3. SSO 生成授权码，302 重定向到 `redirect_uri?code=xxx&state=xxx`
4. 第三方应用收到 code，按标准流程换取 access_token

**场景：管理员点击"访问应用"**

```
GIVEN 管理员已登录 SSO 系统
AND 应用 "应用A" 的 client_id = "uuid-xxx", redirect_url = "https://app-a.com/callback"
WHEN 管理员点击"访问应用"按钮
THEN 浏览器打开新标签页，URL 为 /oauth/authorize?response_type=code&client_id=uuid-xxx&redirect_uri=https%3A%2F%2Fapp-a.com%2Fcallback&state=随机值
AND 由于管理员已登录，SSO 自动检测登录状态
AND SSO 生成授权码并写入 sc_oauth_authorization_codes 表
AND 302 重定向到 https://app-a.com/callback?code=xxx&state=随机值
AND 第三方应用收到授权码
```

**场景：应用配置不完整**

```
GIVEN 应用 "应用B" 的 client_id 为空或 redirect_url 为空
WHEN 管理员点击"访问应用"按钮
THEN 前端提示"应用配置不完整（缺少 client_id 或 redirect_url）"
AND 不打开新标签页
```

---

### REQ-APP-VISIT-002: 访问应用操作日志（新增）

**描述**：管理员点击"访问应用"时，系统须记录操作日志到 `sc_operation_log` 表，在操作日志页面可见。

**日志字段**：
- `module` = "app"
- `action` = "app_visit"
- `result` = "success"
- `target_type` = "app"
- `target_id` = 应用 ID
- `target_name` = 应用名称
- `result_message` = "管理员访问应用：{应用名称}"

**接口**：`POST /app/visit_log`

**场景：记录访问应用日志**

```
GIVEN 管理员 "admin" 点击"访问应用"按钮（应用 ID=1，名称="应用A"）
WHEN 前端异步 POST /app/visit_log {id: 1, app_name: "应用A"}
THEN sc_operation_log 表插入一条记录
AND module = "app"
AND action = "app_visit"
AND result = "success"
AND target_type = "app"
AND target_id = 1
AND target_name = "应用A"
AND operator_name = "admin"
AND 操作日志页面可见此记录
```

---

### REQ-APP-VISIT-003: 访问应用按钮属性（新增）

**描述**：应用列表的"访问应用"按钮须包含 `data-client-id`、`data-redirect-url`、`data-app-name` 属性，供前端 JavaScript 读取。

**场景：按钮属性正确**

```
GIVEN 应用 "应用A" 的 client_id = "uuid-xxx", redirect_url = "https://app-a.com/callback"
WHEN 渲染应用列表页面
THEN "访问应用"按钮包含 data-client-id="uuid-xxx"
AND data-redirect-url="https://app-a.com/callback"
AND data-app-name="应用A"
```

