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
}
