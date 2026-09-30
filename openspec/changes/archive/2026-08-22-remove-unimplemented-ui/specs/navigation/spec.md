# 导航栏规范（增量变更）

> 变更 ID: `remove-unimplemented-ui`
> 影响模块: `navigation`（页面模板）

---

## REMOVED Requirements

### Requirement: 左侧导航栏 — 文档中心

> 移除未实现的"文档中心"菜单项。

#### Scenario: 移除文档中心入口
- GIVEN 页面模板 `app/view/template/index.html`
- WHEN 渲染左侧导航栏
- THEN SHALL NOT 显示"文档中心"菜单项
- AND SHALL NOT 链接到 `/docs.html`

---

### Requirement: 左侧导航栏 — 操作日志

> 移除未实现的"操作日志"菜单项。

#### Scenario: 移除操作日志入口
- GIVEN 页面模板 `app/view/template/index.html`
- WHEN 渲染左侧导航栏
- THEN SHALL NOT 显示"操作日志"菜单项
- AND SHALL NOT 链接到 `/orders.html`

---

### Requirement: 左侧导航栏 — 系统管理

> 移除未实现的"系统管理"菜单项及子菜单。

#### Scenario: 移除系统管理入口
- GIVEN 页面模板 `app/view/template/index.html`
- WHEN 渲染左侧导航栏
- THEN SHALL NOT 显示"系统管理"菜单项
- AND SHALL NOT 显示子菜单：消息通知、账户、设置

---

### Requirement: 左侧导航栏 — 系统监控

> 移除未实现的"系统监控"菜单项。

#### Scenario: 移除系统监控入口
- GIVEN 页面模板 `app/view/template/index.html`
- WHEN 渲染左侧导航栏
- THEN SHALL NOT 显示"系统监控"菜单项
- AND SHALL NOT 链接到 `/charts.html`

---

### Requirement: 左侧导航栏 — 帮助中心

> 移除未实现的"帮助中心"菜单项。

#### Scenario: 移除帮助中心入口
- GIVEN 页面模板 `app/view/template/index.html`
- WHEN 渲染左侧导航栏
- THEN SHALL NOT 显示"帮助中心"菜单项
- AND SHALL NOT 链接到 `/help.html`

---

### Requirement: 左侧导航栏底部 — 资源下载

> 移除侧边栏底部未实现的"资源下载"菜单项。

#### Scenario: 移除资源下载入口
- GIVEN 页面模板 `app/view/template/index.html`
- WHEN 渲染侧边栏底部 footer 菜单
- THEN SHALL NOT 显示"资源下载"菜单项
- AND SHALL NOT 链接到 `/download.html`

---

### Requirement: 顶部导航栏 — 铃铛通知

> 移除顶部导航栏未实现的铃铛通知组件。

#### Scenario: 移除铃铛通知图标及下拉面板
- GIVEN 页面模板 `app/view/template/index.html`
- WHEN 渲染顶部导航栏
- THEN SHALL NOT 显示铃铛通知图标
- AND SHALL NOT 显示通知下拉面板（含假数据通知）

---

### Requirement: 顶部导航栏 — 用户下拉中的账户和设置

> 移除用户下拉菜单中未实现的"账户"和"设置"链接。

#### Scenario: 精简用户下拉菜单
- GIVEN 页面模板 `app/view/template/index.html`
- WHEN 渲染用户头像下拉菜单
- THEN SHALL NOT 显示"账户"链接
- AND SHALL NOT 显示"设置"链接
- AND SHALL NOT 显示分割线
- AND SHALL 仅显示"登出"链接

---

## ADDED Requirements

### Requirement: 左侧导航栏最终结构

移除未实现项后，左侧导航栏 SHALL 仅包含以下菜单项。

#### Scenario: 左侧导航栏保留项
- GIVEN 页面加载完成
- WHEN 渲染左侧导航栏
- THEN SHALL 显示以下菜单项（按顺序）：
  1. 应用列表（`/index.html`）
  2. 用户管理（`/user.html`）
- AND 侧边栏底部 SHALL 仅显示：
  1. 关于我们（`/about.html`）

---

### Requirement: 顶部导航栏最终结构

精简后的顶部导航栏 SHALL 仅包含用户头像下拉菜单。

#### Scenario: 顶部导航栏保留项
- GIVEN 页面加载完成
- WHEN 渲染顶部导航栏右侧区域
- THEN SHALL 显示用户头像下拉菜单
- AND 下拉菜单 SHALL 仅包含"登出"链接
- AND "登出"点击后 SHALL 清除 localStorage 中的 token 并跳转到 `/login`

---

### Requirement: 独立页面文件清理

未实现功能对应的页面文件 SHALL 删除。

#### Scenario: 删除页面文件
- GIVEN 移除导航项后
- WHEN 清理无用文件
- THEN SHALL 删除以下文件：
  - `app/view/docs.html`
  - `app/view/orders.html`
  - `app/view/charts.html`
  - `app/view/help.html`
  - `app/view/download.html`
  - `app/view/system_manage/notifications.html`
  - `app/view/system_manage/account.html`
  - `app/view/system_manage/settings.html`
