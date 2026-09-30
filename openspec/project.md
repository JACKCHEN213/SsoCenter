# SSO Center — 项目级上下文

> 本文档为 OpenSpec 工作流提供项目级上下文，与 CLAUDE.md 互补。

---

## 项目简介

SSO Center 是一个基于 ThinkPHP 8.1 的 OAuth 2.0 统一身份认证中心，为多应用提供单点登录（SSO）能力。第三方系统通过注册应用获取公钥，用户通过本系统登录后，各接入应用可验证 Token 获取用户身份信息。

---

## 技术栈

| 类别 | 技术 |
|------|------|
| 语言 | PHP 8.1.4 |
| 框架 | ThinkPHP 8.1.4 |
| 数据库 | MariaDB 10.8+ / MySQL 5.7+ |
| 认证方式 | JWT（RS256 非对称签名） |
| Web 服务器 | Nginx / PHP Built-in Server |
| 密钥格式 | RSA PEM |

---

## 数据库设计

**数据库名**: `sso_center`
**表前缀**: `sc_`
**字符集**: `utf8mb4`
**引擎**: InnoDB

### ER 关系图

```
┌──────────────────┐       ┌──────────────────┐
│    sc_user        │       │  sc_login_history │
├──────────────────┤       ├──────────────────┤
│ id (PK)          │──┐    │ id (PK)          │
│ username         │  │    │ username         │◄── 记录 user 登录
│ password (md5)   │  └───►│ ip               │
│ email            │       │ login_time       │
│ status           │       └──────────────────┘
│ type             │
│ ip               │       ┌──────────────────┐
│ is_del           │       │     sc_site       │
│ create_time      │       ├──────────────────┤
│ update_time      │       │ id (PK)          │
└──────────────────┘       │ name             │
                           │ image            │
                           │ request_url      │
                           │ redirect_url     │
                           │ is_use           │
                           │ is_del           │
                           │ public_key       │
                           │ app_path         │
                           │ create_time      │
                           │ update_time      │
                           └──────────────────┘
```

---

### 表结构详情

#### `sc_user` — 用户表

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `id` | int(11) | AUTO_INCREMENT, PK | 自增主键 |
| `username` | varchar(255) | NULL | 用户名（登录账号） |
| `password` | varchar(255) | NULL | 密码（md5 加密存储） |
| `email` | varchar(255) | NULL | 邮箱地址 |
| `status` | tinyint(4) | 1 | 账号状态（1=正常） |
| `type` | int(11) | 2 | 用户类型（1=管理员, 2=普通用户） |
| `ip` | varchar(255) | NULL | 最后登录 IP |
| `is_del` | tinyint(4) | 0 | 软删除标记（0=正常, 1=已删除） |
| `create_time` | datetime | current_timestamp() | 创建时间 |
| `update_time` | datetime | current_timestamp() ON UPDATE | 更新时间（自动更新） |

**初始数据**: 内置管理员账号 `admin`（type=1），密码为 md5 加密值。

---

#### `sc_login_history` — 登录记录表

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `id` | int(11) | AUTO_INCREMENT, PK | 自增主键 |
| `username` | varchar(255) | NULL | 登录用户名 |
| `ip` | varchar(255) | NULL | 登录 IP 地址 |
| `login_time` | datetime | NULL | 登录时间 |

**说明**: 每次登录成功后插入一条记录，仅记录 username 不关联 user_id，用于登录审计追踪。

---

#### `sc_site` — 应用（接入站点）表

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `id` | int(11) | AUTO_INCREMENT, PK | 自增主键（即 AppID） |
| `name` | varchar(255) | NULL | 应用名称 |
| `image` | varchar(255) | NULL | 应用图标路径 |
| `request_url` | varchar(255) | NULL | 应用请求地址 |
| `redirect_url` | varchar(255) | NULL | OAuth 回调地址 |
| `is_use` | tinyint(4) | 1 | 是否启用（1=启用, 0=禁用） |
| `is_del` | tinyint(4) | 0 | 软删除标记（0=正常, 1=已删除） |
| `public_key` | varchar(255) | NULL | RSA 公钥文件名（如 `a1b2c3.pem`） |
| `app_path` | varchar(255) | NULL | 应用路径（保留字段，当前未使用） |
| `create_time` | datetime | current_timestamp() | 创建时间 |
| `update_time` | datetime | current_timestamp() ON UPDATE | 更新时间（自动更新） |

**唯一性约束（业务层）**: `name` + `request_url` + `redirect_url` 组合在 `is_del=0` 条件下唯一。

---

## 密钥与文件存储

| 项目 | 配置键 | 说明 |
|------|--------|------|
| JWT 私钥 | `config('common.JWT_KEY_PATH')` + `config('common.JWT_KEY_NAME').key` | 系统级 RSA 私钥，用于签发 JWT |
| JWT 公钥 | `config('common.JWT_KEY_PATH')` + `config('common.JWT_KEY_NAME').key.pem` | 系统级 RSA 公钥，用于验证 JWT |
| 应用公钥 | `config('common.APP_KEY_PATH')` + `md5(site_id).pem` | 每个应用的公钥文件（内容为系统公钥副本） |
| 应用图标 | `config('common.APP_IMAGE_PREFIX')` + `app_image_<timestamp>` | 上传的应用图标文件 |

---

## 模块与规范索引

| 模块 | 规范文件 | 说明 |
|------|----------|------|
| 认证 | `specs/auth/spec.md` | 登录、Token 验证、中间件拦截 |
| 用户 | `specs/user/spec.md` | 用户 CRUD、批量导入、注册 |
| 应用 | `specs/application/spec.md` | 应用注册、公钥管理、图标上传 |

---

## 全局约定

### 响应格式

所有 API 统一使用 `sendJson()` 函数返回 JSON：

```json
{
  "code": 0,          // 响应码，0=成功，非0=错误
  "success": true,    // 布尔值，是否成功
  "message": "成功",   // 响应消息
  "result": "..."     // 响应数据（成功时为业务数据，失败时为错误详情）
}
```

### 错误码体系

| 范围 | 模块 |
|------|------|
| `0` | 成功 |
| `10000-10099` | 通用错误（未知错误、验证失败、文件上传、数据库、数据重复） |
| `10005-10006` | 认证错误（用户不存在、密码错误） |
| `10007, 10010` | JWT 错误/成功 |
| `10008-10009, 10011-10014` | 用户操作（存在、添加成功、更新成功、删除成功、批量操作） |

### 软删除约定

所有表使用 `is_del` 字段标记删除状态：
- `0` = 正常
- `1` = 已删除

查询时默认添加 `WHERE is_del = 0` 条件。

### 中间件链

```
CatchException → CheckLogin → ValidateParams → Controller
```

- **CatchException**: 全局异常捕获，防止未处理异常泄露
- **CheckLogin**: JWT Token 校验（支持免登录白名单）
- **ValidateParams**: 基于控制器名自动匹配验证器类，按 action 名匹配场景

---

## 未实现功能（CLAUDE.md 规划中但代码尚未实现）

- [ ] OAuth 2.0 端点（`/oauth/authorize`, `/oauth/token`, `/oauth/userinfo`, `/oauth/revoke`）
- [ ] 四种授权模式（授权码、隐式、密码、客户端）
- [ ] Refresh Token 机制
- [ ] 会话管理（跨系统登出、失效）
- [ ] 审计日志（除登录外的操作审计）
- [ ] 用户推送至其他系统
- [ ] 重置密码功能（仅有页面，无逻辑）
