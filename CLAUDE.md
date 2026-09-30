# 项目: SSO 身份认证中心 (SSO Center)

## 项目概述
基于 ThinkPHP 8.1 的 OAuth 2.0 认证服务，支持多应用接入和单点登录。

### 核心能力
- **OAuth 2.0 服务**: 支持授权码、隐式、密码、客户端四种模式
- **应用管理**: 第三方系统注册（生成 AppID/AppSecret）
- **用户管理**: 用户同步、推送至其他系统
- **会话管理**: 跨系统登录状态控制（登出、失效）
- **审计日志**: 登录、登出、用户/应用操作全记录

### OAuth 2.0 端点
| 端点 | 方法 | 用途 |
|------|------|------|
| `/oauth/authorize` | GET | 授权码模式 - 用户授权页面 |
| `/oauth/token` | POST | 获取/刷新 Access Token |
| `/oauth/userinfo` | GET | 获取用户信息（需 Token） |
| `/oauth/revoke` | POST | 撤销 Token |

### Token 策略
- **Access Token**: JWT（RS256 签名），有效期 2 小时
- **Refresh Token**: 数据库存储（`oauth_refresh_tokens` 表），有效期 30 天
- **JWT 密钥**: `app/common/keys/jwt.key`（私钥），`jwt.key.pub`（公钥）

---

## 技术栈
- **PHP**: 8.5（运行路径: `/usr/local/php8.0/bin/php`）
- **框架**: ThinkPHP 8.1.4
- **HTTP 客户端**: Guzzle（`composer require guzzlehttp/guzzle`）
- **数据库**: MySQL 5.7+（配置见 `.env`）
- **服务器**: Nginx / PHP Built-in Server

### 本地开发
```bash
php think run  # 启动开发服务器（默认端口 8000）
```

## 核心目录

```
app/
├── controller/          # 【业务入口】所有 API 和页面控制器
│   ├── Login.php        # 登录/登出
│   ├── User.php         # 用户管理（CRUD）
│   ├── Application.php  # 应用注册/管理
│   └── OAuth.php        # OAuth 2.0 端点（如存在）
├── common/              # 【公共组件】
│   ├── ResponseCode.php   # 响应码常量（错误码定义）
│   ├── ResponseMessage.php # 响应消息
│   └── Key.php          # RSA 密钥生成工具
├── http/middleware/     # 【中间件】
│   ├── CheckLogin.php   # 登录态校验
│   ├── ValidateParams.php # 参数验证
│   └── CatchException.php # 全局异常捕获
└── validate/            # 【验证器】参数校验规则
route/app.php             # 【路由定义】所有 URL 映射
extend/JWT.php            # JWT 生成/验证类（PSR-4 自动加载）
```

## 编码规范（必须遵守）

### Controller 规范

- 每个 Controller 必须继承 BaseController
- 方法命名: action[动作名]（如 actionLogin、actionRegister）
返回值格式:
```
// 成功
return sendJson($site_id);
// 失败
return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR);
```

### 错误码规范

- 定义在 app/common/ResponseCode.php
- 格式: const ERROR_NAME = 10001;（5 位数字）
- 业务错误码范围:
    - 10000-19999: 用户相关
    - 20000-29999: 应用相关
    - 30000-39999: OAuth 相关
    - 40000-49999: 系统/参数错误

### 数据库操作规范

- 使用 ThinkPHP 的 Db 类（Db::table('users')->where(...)）
    ```
    $site_id = Db::name('site')
                ->where('is_del', 0)
                ->where('name', $add_data['name'])
                ->where('request_url', $add_data['request_url'])
                ->where('redirect_url', $add_data['redirect_url'])
                ->value('id');
    ```
- 禁止拼接 SQL 字符串（防止 SQL 注入）
- 所有写操作必须使用事务（Db::startTrans()、Db::commit()、Db::rollback()）

## 安全红线（不可违反）

- ❌ 严禁在代码中硬编码 AppSecret、密码、Token
- ❌ 严禁在日志中记录明文密码
- ❌ 严禁在 Controller 中直接操作数据库（业务逻辑封装到 Model 或 Service）
- ✅ 所有密码必须 password_hash() 加密（PASSWORD_BCRYPT）
- ✅ 所有外部输入必须经过验证器验证

## 配置说明

- 应用配置: config/app.php
- 数据库配置: config/database.php（实际敏感信息在 .env）
- 路由定义: route/app.php

### 环境变量（.env）

```
# 必须配置
DB_TYPE=
DB_HOST=
DB_NAME=
DB_USER=
DB_PASS=
DB_PORT=
DB_CHARSET=
DB_PREFIX=

# 可选
APP_DEBUG=false
APP_TRACE=false
```

## 新增功能指南

当需要添加新功能时，AI 应：
1. 在 route/app.php 定义路由
2. 在 app/controller/ 创建或修改 Controller
3. 如需参数校验，在 app/validate/ 添加验证器
4. 如涉及数据库变更，必须告知我生成迁移脚本

## OpenSpec 工作流（新增功能的标准流程）

本项目使用 OpenSpec 管理所有功能变更。当你需要添加新功能时，**必须**遵循以下流程：

### 目录结构

```
openspec/
├── project.md # 项目级上下文（与本文件互补）
├── specs/ # 【已实现功能】当前系统的真实规范
│ ├── user/
│ │   └── spec.md # 用户模块的能力说明
│ ├── application/
│ │   └── spec.md # 应用管理模块的能力
│ └── oauth/
│     └── spec.md # OAuth 2.0 能力规范
└── changes/ # 【进行中的变更】每个功能独立文件夹
    ├── [change-id]/ # 例如: add-refresh-token
    │    ├── proposal.md # 为什么要改、改什么（需人工确认）
    │    ├── design.md # 技术方案（复杂功能才写）
    │    ├── tasks.md # 实施清单（逐步执行）
    │    └── specs/ # 增量规范（本次新增/修改的规范）
    │        └── [domain]/
    │            └── spec.md # 用 ADDED/MODIFIED/REMOVED 标记
    └── archive/ # 已完成的变更（历史记录）
```

