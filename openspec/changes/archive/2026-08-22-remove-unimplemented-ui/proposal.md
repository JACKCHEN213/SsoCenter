# 提案：移除未实现的导航项

## 变更 ID

`remove-unimplemented-ui`

## 背景与动机

当前系统页面模板 `app/view/template/index.html` 中包含大量来自模板主题的占位导航项，这些功能在 SSO Center 中并未实现，属于模板自带的示例内容。保留这些未实现的入口会误导用户，降低产品专业度。

### 现状

**左侧导航栏**中包含以下未实现的菜单项：
- 文档中心（`/docs.html`）
- 操作日志（`/orders.html`）
- 系统管理（含子菜单：消息通知、账户、设置）
- 系统监控（`/charts.html`）
- 帮助中心（`/help.html`）
- 资源下载（`/download.html`，位于侧边栏底部）

**顶部导航栏**中包含：
- 铃铛通知图标（含 3 条假通知数据）
- 用户下拉菜单中的"账户"和"设置"链接

### 应保留的导航项

**左侧导航栏**：
- 应用列表（`/index.html`）
- 用户管理（`/user.html`）
- 关于我们（`/about.html`，侧边栏底部）

**顶部导航栏**：
- 用户头像下拉菜单（仅保留"登出"）

## 变更目标

1. 移除左侧导航栏中 6 个未实现的菜单项
2. 移除顶部导航栏的铃铛通知组件
3. 移除用户下拉菜单中的"账户"和"设置"链接，仅保留"登出"
4. 清理对应的独立页面文件（`docs.html`、`orders.html`、`charts.html`、`help.html`、`download.html`、`system_manage/` 目录）

## 涉及范围

| 范围 | 说明 |
|------|------|
| **修改** | `app/view/template/index.html` — 移除导航项 HTML |
| **删除** | `app/view/docs.html`、`orders.html`、`charts.html`、`help.html`、`download.html` |
| **删除** | `app/view/system_manage/` 目录（notifications.html、account.html、settings.html） |
| **可选删除** | `public/assets/images/profiles/` 下的假头像图片 |

## 影响分析

- **路由**：如果 `route/app.php` 中定义了这些页面的路由，需同步移除
- **向后兼容**：如有外部链接指向这些页面，将返回 404
- **现有功能**：应用列表、用户管理等核心功能不受影响
