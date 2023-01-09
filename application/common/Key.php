<?php

namespace app\common;

class Key
{
    /**
     * @param string $filename
     * @return string
     */
    public static function setPrivateKey(string $filename): string
    {
        $new_key_pair = openssl_pkey_new(config('common.PRIVATE_KEY_OPTIONS'));
        openssl_pkey_export($new_key_pair, $private_key);

        $save_dir = config('common.JWT_KEY_PATH');
        if (!is_dir($save_dir)) {
            mkdir($save_dir, 0777, true);
        }
        $details = openssl_pkey_get_details($new_key_pair);
        $public_key_pem = $details['key'];
        file_put_contents($save_dir . DIRECTORY_SEPARATOR . $filename, $private_key);
        file_put_contents($save_dir . DIRECTORY_SEPARATOR . $filename . '.pem', $public_key_pem);
        return $private_key;
    }
}
