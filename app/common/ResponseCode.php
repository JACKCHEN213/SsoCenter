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
    public static int $UPDATE_USER_SUCCESS = 10011;
    public static int $DELETE_USER_SUCCESS = 10012;
    public static int $BATCH_ADD_ERROR = 10013;
    public static int $BATCH_ADD_SUCCESS = 10014;

    // 应用相关错误码（20000-29999）
    public static int $APP_NOT_FOUND = 20001;
    public static int $APP_CREDENTIALS_NOT_FOUND = 20002;
    public static int $APP_SECRET_RESET_FAILED = 20003;
    public static int $APP_REDIRECT_URL_MISSING = 20004;
    public static int $APP_DISABLED = 20005;

    // OAuth 相关错误码（30000-39999）
    public static int $OAUTH_INVALID_CLIENT = 30001;
    public static int $OAUTH_INVALID_GRANT = 30002;
    public static int $OAUTH_INVALID_REQUEST = 30003;
    public static int $OAUTH_UNSUPPORTED_GRANT_TYPE = 30004;
    public static int $OAUTH_ACCESS_DENIED = 30005;
    public static int $OAUTH_TOKEN_EXPIRED = 30006;
    public static int $OAUTH_TOKEN_REVOKED = 30007;
    public static int $OAUTH_INVALID_SCOPE = 30008;
    public static int $OAUTH_INVALID_REDIRECT_URI = 30009;
    public static int $OAUTH_SIGNATURE_INVALID = 30010;
    public static int $OAUTH_USER_NOT_FOUND = 30011;
    public static int $OAUTH_GRANT_TYPE_NOT_ALLOWED = 30012;
}
