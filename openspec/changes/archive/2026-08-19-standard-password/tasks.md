# 实现任务

## Task 1: 修改现有用户的密码

- [x] 1.1 修改数据表 sc_user，添加 `jwt_private_path` 和 `jwt_public_path` 字段
- [x] 1.2 为当前存在的用户生成各自的 RSA 公钥/私钥对（存放于 `public/static/app/keys/`，文件名为 `md5(username)`）
- [x] 1.3 将生成的公钥和私钥路径更新到数据库对应记录
- [x] 1.4 将用户密码修改为 md5 → password_hash → 公钥加密，密码重置为用户名

## Task 2: 修改添加用户的逻辑

- [x] 2.1 添加新用户时自动为该用户生成 RSA 公钥/私钥对，失败时清理已生成的密钥文件
- [x] 2.2 密码保存调整为 md5 → password_hash → 公钥加密

## Task 3: 登录验证修改

- [x] 3.1 登录鉴权调整为：输入密码 → md5 → 私钥解密 → password_verify

## 验收标准

1. 现有用户的公私钥已生成且路径存储在数据库
2. 现有用户的密码重置为自己的用户名
3. 添加新用户自动拥有自己的公私钥
4. 登录鉴权使用 md5 + password_hash + 公钥加密

## 执行顺序

1. 执行 Task 1
2. 执行 Task 2 和 Task 3

