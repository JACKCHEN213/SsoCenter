<?php

namespace app\common;

class ResponseCode
{
    public static int $OK = 0;

    public static int $UNKNOWN_ERROR = 10000;
    public static int $VALIDATE_ERROR = 10001;
    public static int $FILE_UPLOAD_FAILED = 10002;
    public static int $DB_ERROR = 10003;
    public static int $DATA_ALREADY_EXIST = 10004;
    public static int $USER_NOT_FOUND = 10005;
    public static int $WRONG_PASSWORD = 10006;
    public static int $JWT_ERROR = 10007;
    public static int $USER_EXISTS = 10008;
    public static int $ADD_USER_SUCCESS = 10009;
    public static int $JWT_SUCCESS = 10010;
}
