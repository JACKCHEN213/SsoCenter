<?php

namespace app\common;

class ResponseMessage
{
    public static string $OK = '成功';

    public static string $UNKNOWN_ERROR = '未知错误';
    public static string $VALIDATE_ERROR = '参数验证失败';
    public static string $FILE_UPLOAD_FAILED = '文件上传失败';
    public static string $DB_ERROR = '数据库操作失败';
    public static string $DATA_ALREADY_EXIST = "数据已经存在了";
    public static string $USER_NOT_FOUND = "用户找不到";
    public static string $WRONG_PASSWORD = "密码错误";
    public static string $JWT_ERROR = "身份验证错误";
    public static string $USER_EXISTS = "用户已经存在了";
    public static string $ADD_USER_SUCCESS = "用户添加成功";
    public static string $JWT_SUCCESS = "身份验证成功";
    public static string $UPDATE_USER_SUCCESS = "用户修改成功";
    public static string $DELETE_USER_SUCCESS = "用户删除成功";
    public static string $BATCH_ADD_ERROR = "批量添加用户失败";
    public static string $BATCH_ADD_SUCCESS = "批量添加用户成功";

    // 应用相关错误消息（20000-29999）
    public static string $APP_NOT_FOUND = "应用不存在";
    public static string $APP_CREDENTIALS_NOT_FOUND = "应用凭证不存在";
    public static string $APP_SECRET_RESET_FAILED = "重置 APP_KEY 失败";
    public static string $APP_REDIRECT_URL_MISSING = "应用未配置回调地址";
    public static string $APP_DISABLED = "应用未启用";

    // OAuth 相关错误消息（30000-39999）
    public static string $OAUTH_INVALID_CLIENT = "无效的客户端凭证";
    public static string $OAUTH_INVALID_GRANT = "无效的授权凭证";
    public static string $OAUTH_INVALID_REQUEST = "无效的请求参数";
    public static string $OAUTH_UNSUPPORTED_GRANT_TYPE = "不支持的授权模式";
    public static string $OAUTH_ACCESS_DENIED = "拒绝访问";
    public static string $OAUTH_TOKEN_EXPIRED = "Token 已过期";
    public static string $OAUTH_TOKEN_REVOKED = "Token 已撤销";
    public static string $OAUTH_INVALID_SCOPE = "无效的授权范围";
    public static string $OAUTH_INVALID_REDIRECT_URI = "无效的回调地址";
    public static string $OAUTH_SIGNATURE_INVALID = "签名验证失败";
    public static string $OAUTH_USER_NOT_FOUND = "OAuth 用户不存在";
    public static string $OAUTH_GRANT_TYPE_NOT_ALLOWED = "该应用未启用此授权模式";
}
