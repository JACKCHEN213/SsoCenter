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
}
