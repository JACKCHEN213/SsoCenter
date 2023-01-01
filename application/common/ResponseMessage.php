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
}
