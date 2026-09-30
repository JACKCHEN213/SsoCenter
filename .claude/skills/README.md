# Skills 索引

本项目使用的 Claude Code Skills 按功能领域分类。

## 如何使用

当用户提出相关需求时，AI 会自动加载对应的 Skill。你也可以主动要求：
> "使用 add-api-endpoint skill 帮我新增一个用户管理接口"

## 分类列表

### code-quality/ - 代码质量
| Skill | 触发场景 |
|-------|----------|
| `lint-check.md` | "检查代码风格"、"修复格式"、"跑 lint" |
| `security-audit.md` | "安全检查"、"审计代码"、"找漏洞" |
| `performance-check.md` | "性能优化"、"检查慢查询"、"加缓存" |

### development/ - 开发辅助
| Skill | 触发场景 |
|-------|----------|
| `add-api-endpoint.md` | "新增接口"、"加一个 CRUD"、"加 API" |
| `database-change.md` | "加字段"、"新建表"、"改数据库" |
| `debug-issue.md` | "报错了"、"404"、"500"、"排查问题" |

### oauth/ - OAuth 专项
| Skill | 触发场景 |
|-------|----------|
| `add-grant-type.md` | "新增授权模式"、"client_credentials" |
| `integrate-social.md` | "接入微信登录"、"钉钉 SSO" |
| `token-management.md` | "刷新 Token"、"撤销 Token" |

### testing/ - 测试
| Skill | 触发场景 |
|-------|----------|
| `write-unit-test.md` | "写单测"、"测试登录接口" |
| `integration-test.md` | "集成测试"、"端到端测试" |

### deployment/ - 部署运维
| Skill | 触发场景 |
|-------|----------|
| `deploy-checklist.md` | "部署"、"上线检查"、"发布" |
| `migrate-production.md` | "生产环境迁移"、"执行 SQL" |

