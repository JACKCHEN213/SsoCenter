# User 模块 — 用户管理

> 状态: IMPLEMENTED
> 最后更新: 2026-08-17

## Purpose

用户管理模块提供用户的增删改查功能，支持单个添加、批量导入（Excel）、分页查询与模糊搜索。所有删除操作为软删除（`is_del` 标记）。

---

## Requirements

### Requirement: REQ-USER-001 用户列表（分页查询）

获取用户列表，支持分页和按用户名模糊搜索。

**实现位置**: `app/controller/User.php` → `index()`
**路由**: `GET /user/`


#### Scenario: 默认分页查询
- Given 数据库中存在用户数据
- When 发送 `GET /user/` 不带参数
- Then 默认 `page=1`，`limit=10`
- And 查询 `is_del=0` 的用户
- And 渲染 `/user` 视图，传入 `users`, `count`, `page`, `limit`, `lastPage` 变量

#### Scenario: 按用户名搜索
- Given 数据库中存在用户名为 `admin` 的用户
- When 发送 `GET /user/?su=admin`
- Then 使用 LIKE `%admin%` 条件搜索用户名
- And 返回匹配的用户列表和分页信息

#### Scenario: 自定义分页
- Given 数据库中存在 25 条用户记录
- When 发送 `GET /user/?page=2&limit=10`
- Then 返回第 11-20 条记录
- And `lastPage = ceil(count / limit)`

---

### Requirement: REQ-USER-002 添加用户（单个）

通过表单提交添加新用户。

**实现位置**: `app/controller/User.php` → `add()` → `doAdd()`
**路由**: `POST /user/`


#### Scenario: 添加成功
- Given 用户名 `newuser` 不存在于数据库
- When 发送 `POST /user/`，参数 `username=newuser`，`password=123456`，`email=new@example.com`
- Then 检查用户名唯一性（`is_del=0` 条件下）
- And 将密码使用 md5 加密后存入 `user` 表
- And 返回: `{ code: 10009, success: true, message: "用户添加成功", result: "用户newuser添加成功" }`

#### Scenario: 用户名已存在
- Given 用户名 `admin` 已存在于数据库（`is_del=0`）
- When 发送 `POST /user/`，参数 `username=admin`
- Then 返回错误: `{ code: 10008, success: false, message: "用户已经存在了", result: "用户已经存在了" }`

---

### Requirement: REQ-USER-003 用户注册（前台入口）

前台用户自助注册，内部调用 `User/doAdd` 方法。

**实现位置**: `app/controller/Signup.php` → `signup()`
**路由**: `POST /signup/signup`


#### Scenario: 注册成功
- Given 用户名 `newuser` 不存在于数据库
- When 发送 `POST /signup/signup`，参数 `username`、`password`、`email`
- Then 内部调用 `User::doAdd(username, password, email)`
- And 返回与 REQ-USER-002 相同的成功响应

#### Scenario: 用户名已存在
- Given 用户名已存在
- When 发送 `POST /signup/signup`
- Then 返回与 REQ-USER-002 相同的用户名已存在错误

---

### Requirement: REQ-USER-004 更新用户

修改已有用户的用户名和邮箱。

**实现位置**: `app/controller/User.php` → `update()` → `doUpdate()`
**路由**: `PATCH /user/`


#### Scenario: 更新成功
- Given 用户 ID 为 1 的用户存在（`is_del=0`）
- When 发送 `PATCH /user/`，参数 `user_id=1`，`username=newname`，`email=new@email.com`
- Then 更新该用户的 `username` 和 `email` 字段
- And 返回: `{ code: 10011, success: true, message: "用户修改成功", result: "用户newname修改成功" }`

#### Scenario: 用户不存在
- Given 用户 ID 为 999 的用户不存在
- When 发送 `PATCH /user/`，参数 `user_id=999`
- Then 返回错误: `{ code: 10007, success: false, message: "身份验证错误", result: "用户不存在" }`

---

### Requirement: REQ-USER-005 删除用户（软删除）

通过设置 `is_del=1` 标记用户为已删除，不实际删除数据库记录。

**实现位置**: `app/controller/User.php` → `delete()` → `doDelete()`
**路由**: `DELETE /user/`


#### Scenario: 删除成功
- Given 用户 ID 为 1 的用户存在（`is_del=0`）
- When 发送 `DELETE /user/`，参数 `user_id=1`
- Then 将 `is_del` 字段设为 1
- And 返回: `{ code: 10012, success: true, message: "用户删除成功", result: "用户admin删除成功" }`

#### Scenario: 用户不存在
- Given 用户 ID 为 999 的用户不存在
- When 发送 `DELETE /user/`，参数 `user_id=999`
- Then 返回错误: `{ code: 10007, success: false, message: "身份验证错误", result: "用户不存在" }`

---

### Requirement: REQ-USER-006 批量添加用户（Excel 导入）

通过上传 Excel 文件批量导入用户数据。

**实现位置**: `app/controller/User.php` → `batchAdd()`
**路由**: `POST /user/batch`


#### Scenario: 批量导入成功
- Given 上传一个有效的 Excel 文件
- And 第一行表头为: A列=`用户名`, B列=`邮箱`, C列=`密码`
- And 从第二行开始包含用户数据
- When 发送 `POST /user/batch`，携带文件
- Then 解析每行数据，逐行调用 `doAdd` 添加用户
- And 返回: `{ code: 10014, success: true, message: "批量添加用户成功", result: ["第2行, 用户xxx添加成功", ...] }`

#### Scenario: 表头格式不正确
- Given 上传的 Excel 第一行表头不符合要求（如 A列不是"用户名"）
- When 发送 `POST /user/batch`
- Then 返回错误: `{ code: 10013, success: false, message: "批量添加用户失败", result: "A列应当是 用户名 ,当前为 xxx" }`

#### Scenario: 数据行存在空值
- Given Excel 中某行的用户名/邮箱/密码为空
- When 发送 `POST /user/batch`
- Then 返回错误: `{ code: 10013, success: false, message: "批量添加用户失败", result: ["第2行用户名不能为空", ...] }`

#### Scenario: Excel 无数据
- Given 上传的 Excel 只有表头没有数据行
- When 发送 `POST /user/batch`
- Then 返回错误: `{ code: 10013, success: false, message: "批量添加用户失败", result: "未读取到数据" }`

---

## 数据库表

| 表名 | 字段 | 说明 |
|------|------|------|
| `user` | `id`, `username`, `password`(md5), `email`, `type`, `ip`, `is_del` | 用户主表 |

---

## 相关路由

| 路由 | 方法 | 控制器/方法 | 说明 |
|------|------|-------------|------|
| `GET /user/` | GET | `User/index` | 用户列表（分页+搜索） |
| `POST /user/` | POST | `User/add` | 添加单个用户 |
| `PATCH /user/` | PATCH | `User/update` | 更新用户信息 |
| `DELETE /user/` | DELETE | `User/delete` | 软删除用户 |
| `POST /user/batch` | POST | `User/batchAdd` | 批量导入用户 |
| `GET /signup/` | GET | `Signup/index` | 渲染注册页面 |
| `POST /signup/signup` | POST | `Signup/signup` | 用户注册 |

---

## 已知技术备注

- 密码使用 **md5** 加密存储（非 bcrypt），安全性较低
- 批量导入中，Excel C 列（密码）被二次 md5 处理（`doAdd` 内部再次 md5），即实际存储为 `md5(md5(原始密码))`
- 删除/更新操作复用 `JWT_ERROR` 错误码（10007）来表示"用户不存在"，语义上不够精确
