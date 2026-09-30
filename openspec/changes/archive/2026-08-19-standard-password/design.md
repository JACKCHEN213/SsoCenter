# 技术设计

## 架构

- PHP + ThinkPHP8 + composer
- password_hash

## 实现方案

1. 修改数据库表sc_user, 添加字段jwt_private_path和jwt_public_path分别用于存储用户的私钥/公钥路径
2. 目前存在的用户没有使用公钥，因此需要动态生成私钥/公钥，使用`app/common/Key.php`来生成私钥/公钥，存放路径为`public/static/app/keys/`，文件名为用户名的md5码
3. 更新相关用户的私钥/公钥路径
4. 将存在用户的密码更新为md5+password_hash+公钥加密，密码为用户名
5. 更新系统添加用户接口，将用户加密修改为md5+password_hash+公钥加密，需要提前为待添加用户生成公钥和私钥
6. 登录验证需要修改为md5+password_hash+公钥加密验证

