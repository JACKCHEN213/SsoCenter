## 1. 验证现有实现

- [ ] 1.1 测试成功刷新场景：使用有效 refresh_token 调用 `POST /oauth/token`，验证返回新 token 对
- [ ] 1.2 测试 token 轮换：刷新后验证旧 refresh_token 和旧 access_token 均被撤销
- [ ] 1.3 测试无效 refresh_token：使用过期/已撤销/不存在的 refresh_token，验证返回 `error=invalid_grant`
- [ ] 1.4 测试客户端认证失败：不提供 Basic Auth 或使用错误的 client_id/client_secret，验证返回 `error=invalid_client`
- [ ] 1.5 测试客户端不匹配：使用其他客户端的 refresh_token，验证返回 `error=invalid_grant`

## 2. 修复问题（如有）

- [ ] 2.1 根据验证结果修复发现的 bug（如 token 未正确撤销、新 token 未正确生成等）
- [ ] 2.2 修复后重新运行验证测试，确认问题已解决

## 3. 编写 API 文档

- [ ] 3.1 编写 refresh token 刷新接口文档，包括：接口地址、请求方法、请求参数、认证方式、响应格式、错误码说明
- [ ] 3.2 编写调用流程说明：何时刷新、如何获取 refresh_token、如何处理刷新失败
- [ ] 3.3 编写错误处理指南：常见错误码及处理方法

## 4. 提供调用示例

- [ ] 4.1 提供 curl 命令示例（成功刷新、错误处理）
- [ ] 4.2 提供 PHP (Guzzle) 代码示例
- [ ] 4.3 提供 JavaScript (axios) 代码示例
- [ ] 4.4 提供 Python (requests) 代码示例

## 5. 更新项目文档

- [ ] 5.1 在 CLAUDE.md 或 README 中添加 refresh token 使用说明
- [ ] 5.2 在 OAuth 端点文档中补充 refresh token 刷新流程

## 6. 验证第三方应用集成

- [ ] 6.1 使用文档中的示例代码测试刷新流程
- [ ] 6.2 验证 token 轮换后旧 token 无法继续使用
- [ ] 6.3 验证错误场景下的响应是否符合文档描述
