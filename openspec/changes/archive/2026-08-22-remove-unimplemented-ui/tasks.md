# 任务清单：移除未实现的导航项

> 变更 ID: `remove-unimplemented-ui`

---

- [x] **T1** 修改 `app/view/template/index.html`
  - [x] T1.1 移除铃铛通知下拉组件（lines 99-199）
  - [x] T1.2 移除用户下拉中的"账户"、"设置"和分割线，仅保留"登出"（lines 218-222）
  - [x] T1.3 移除"文档中心"菜单项（lines 265-273）
  - [x] T1.4 移除"操作日志"菜单项（lines 274-282）
  - [x] T1.5 移除"系统管理"菜单项及子菜单（lines 283-311）
  - [x] T1.6 移除"系统监控"菜单项（lines 345-353）
  - [x] T1.7 移除"帮助中心"菜单项（lines 355-363）
  - [x] T1.8 移除侧边栏底部"资源下载"菜单项（lines 369-377）
  - [x] T1.9 （可选）移除已隐藏的"页面管理"（lines 312-342，当前 `display:none`）
- [x] **T2** 删除无用页面文件
  - [x] T2.1 `app/view/docs.html`
  - [x] T2.2 `app/view/orders.html`
  - [x] T2.3 `app/view/charts.html`
  - [x] T2.4 `app/view/help.html`
  - [x] T2.5 `app/view/download.html`
  - [x] T2.6 `app/view/system_manage/` 目录下所有文件
- [x] **T3** 清理路由定义（检查 `route/` 下是否有对应路由）
- [x] **T4** 编写增量规范 `specs/navigation/spec.md`
- [x] **T5** 测试验证：确认页面正常渲染，无 JS 报错
