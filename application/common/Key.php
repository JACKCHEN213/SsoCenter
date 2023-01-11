<?php

namespace app\common;

class Key
{
    /**
     * @param string $file_dir
     * @param string $filename
     * @return string
     */
    public static function getPrivateKey(string $file_dir, string $filename): string
    {
        $private_key_path = $file_dir . DIRECTORY_SEPARATOR . $filename . '.key';
        if (is_file($private_key_path)) {
            return file_get_contents($private_key_path);
        }
        if (!is_dir($file_dir)) {
            mkdir($file_dir, 0777, true);
        }
        $new_key_pair = openssl_pkey_new(config('common.PRIVATE_KEY_OPTIONS'));
        openssl_pkey_export($new_key_pair, $private_key);

        $details = openssl_pkey_get_details($new_key_pair);
        $public_key_pem = $details['key'];
        file_put_contents($private_key_path, $private_key);
        file_put_contents($private_key_path . '.pem', $public_key_pem);
        return $private_key;
    }

    public static function getPublicKey(string $file_dir, string $filename): string
    {
        $private_key_path = $file_dir . DIRECTORY_SEPARATOR . $filename . '.key';
        if (is_file($private_key_path . '.pem')) {
            return file_get_contents($private_key_path . '.pem');
        }
        $new_key_pair = openssl_pkey_new(config('common.PRIVATE_KEY_OPTIONS'));
        $details = openssl_pkey_get_details($new_key_pair);
        return $details['key'];
    }
}
