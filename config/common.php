<?php

return [
    'VALIDATE_NAMESPACE' => 'app\\validate\\',
    'APP_IMAGE_PREFIX' => 'static/app/img/',
    'IMAGE_EXT' => [
        'png',
        'jpg',
        'jpeg',
        'gif',
        'bmp',
        'ico',
    ],
    'MAX_IMAGE_SIZE' => 10 * 1024 * 1024,
    'JWT_KEY_PATH' => env('app_path') . '/common/keys/',
    'PRIVATE_KEY_OPTIONS' => [
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ],
    'JWT_KEY_NAME' => 'jwt',
];
