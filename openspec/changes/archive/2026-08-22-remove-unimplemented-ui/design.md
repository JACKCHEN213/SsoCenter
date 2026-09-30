# 设计方案：移除未实现的导航项

## 一、左侧导航栏改造

### 改造前（`app/view/template/index.html` 第 246-387 行）

```
<ul class="app-menu">
  ✓ 应用列表        (nav-index, lines 247-255)
  ✓ 用户管理        (nav-user, lines 256-264)
  ✗ 文档中心        (nav-docs, lines 265-273)         → 移除
  ✗ 操作日志        (nav-orders, lines 274-282)       → 移除
  ✗ 系统管理        (nav-system-manage, lines 283-311) → 移除（含子菜单）
  ✗ 系统监控        (nav-charts, lines 345-353)       → 移除
  ✗ 帮助中心        (nav-help, lines 355-363)         → 移除
  页面管理          (nav-page-manage, lines 312-342)   → 已隐藏(display:none)，保留或移除均可
  footer:
    ✗ 资源下载      (nav-download, lines 369-377)     → 移除
    ✓ 关于我们      (nav-about, lines 378-386)        → 保留
</ul>
```

### 改造后

```
<ul class="app-menu">
  ✓ 应用列表
  ✓ 用户管理
  footer:
    ✓ 关于我们
</ul>
```

## 二、顶部导航栏改造

### 改造前（第 98-228 行）

```
<div class="app-utilities">
  ✗ 铃铛通知下拉     (lines 99-199)    → 移除整个 app-notifications-dropdown
  <!-- 已注释的设置图标 -->              → 已注释，无需处理
  ✓ 用户头像下拉     (lines 213-228)   → 保留但精简
    ✗ "账户" 链接   (line 218)         → 移除
    ✗ "设置" 链接   (line 219)         → 移除
    ✗ 分割线        (lines 220-222)    → 移除（只剩一项无需分割线）
    ✓ "登出" 链接   (lines 223-226)    → 保留
</div>
```

### 改造后

```
<div class="app-utilities">
  ✓ 用户头像下拉
    ✓ "登出" 链接
</div>
```

## 三、页面文件清理

| 文件 | 说明 | 处理 |
|------|------|------|
| `app/view/docs.html` | 文档中心页面 | 删除 |
| `app/view/orders.html` | 操作日志页面 | 删除 |
| `app/view/charts.html` | 系统监控页面 | 删除 |
| `app/view/help.html` | 帮助中心页面 | 删除 |
| `app/view/download.html` | 资源下载页面 | 删除 |
| `app/view/system_manage/notifications.html` | 消息通知页面 | 删除 |
| `app/view/system_manage/account.html` | 账户页面 | 删除 |
| `app/view/system_manage/settings.html` | 设置页面 | 删除 |

## 四、路由清理

检查 `route/` 下是否有指向这些页面的路由定义，如有则移除。
