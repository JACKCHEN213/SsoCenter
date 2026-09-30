# 导航栏规范

> Capability: `navigation`
> 定义 SSO 身份认证中心页面模板的导航结构与页面文件组织。

---

## Purpose

规定系统左右两侧导航栏的菜单项、链接目标与最终结构，以及独立页面文件的清理范围，确保用户界面仅呈现已实现的功能入口。

---

## Requirements

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

未实现功能对应的页面文件 SHALL 删除，防止残留死链。

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

---

### Requirement: 公共 HTTP 请求函数

页面模板 SHALL 提供基于 axios 的公共 HTTP 请求函数，所有页面可直接使用。

#### Scenario: axios 库引入
- GIVEN 页面加载
- WHEN 模板渲染
- THEN SHALL 引入本地 `/assets/js/axios.min.js`

#### Scenario: 公共请求函数
- GIVEN 页面模板加载完成
- WHEN 页面需要使用 HTTP 请求
- THEN 可使用以下全局函数：
  - `axiosGet(url, params)` — GET 请求
  - `axiosPost(url, data)` — POST 请求
  - `axiosPut(url, data)` — PUT 请求
  - `axiosDelete(url, data)` — DELETE 请求

#### Scenario: 自动附加 Authorization
- GIVEN 用户已登录（localStorage 中有 token）
- WHEN 调用上述任一请求函数
- THEN 请求头 SHALL 自动包含 `Authorization: <token>`

#### Scenario: 响应格式
- GIVEN 请求完成
- WHEN 处理响应
- THEN 响应对象格式为 `{ data: <响应体>, status: <状态码>, ... }`
- AND 可通过 `res.data.success`、`res.data.result` 等访问响应数据

#### Scenario: 401 自动跳转
- GIVEN 请求返回 401 状态码
- WHEN 响应拦截器捕获
- THEN SHALL 自动清除 token 并跳转到登录页
