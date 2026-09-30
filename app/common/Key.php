<?php

namespace app\common;

use think\facade\Db;

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

    /**
     * 为用户生成 RSA 密钥对
     *
     * @param string $file_dir 密钥存储目录（绝对路径）
     * @param string $username 用户名
     * @return array ['private_path' => '...', 'public_path' => '...'] 相对于项目根目录的路径
     */
    public static function generateUserKeyPair(string $file_dir, string $username): array
    {
        $filename = md5($username);
        $private_key_path = $file_dir . DIRECTORY_SEPARATOR . $filename . '.key';
        $public_key_path = $file_dir . DIRECTORY_SEPARATOR . $filename . '.pem';

        if (is_file($private_key_path) && is_file($public_key_path)) {
            return [
                'private_path' => $private_key_path,
                'public_path' => $public_key_path,
            ];
        }

        if (!is_dir($file_dir)) {
            mkdir($file_dir, 0777, true);
        }

        $new_key_pair = openssl_pkey_new(config('common.PRIVATE_KEY_OPTIONS'));
        openssl_pkey_export($new_key_pair, $private_key);
        $details = openssl_pkey_get_details($new_key_pair);
        $public_key = $details['key'];

        file_put_contents($private_key_path, $private_key);
        file_put_contents($public_key_path, $public_key);

        return [
            'private_path' => $private_key_path,
            'public_path' => $public_key_path,
        ];
    }

    /**
     * 获取用户私钥
     *
     * @param string $file_dir 密钥存储目录
     * @param string $username 用户名
     * @return string 私钥内容
     * @throws \Exception 当私钥文件不存在时
     */
    public static function getUserPrivateKey(string $file_dir, string $username): string
    {
        $path = $file_dir . DIRECTORY_SEPARATOR . md5($username) . '.key';
        if (!is_file($path)) {
            throw new \Exception('用户私钥不存在');
        }
        return file_get_contents($path);
    }

    /**
     * 获取用户公钥
     *
     * @param string $file_dir 密钥存储目录
     * @param string $username 用户名
     * @return string 公钥内容
     * @throws \Exception 当公钥文件不存在时
     */
    public static function getUserPublicKey(string $file_dir, string $username): string
    {
        $path = $file_dir . DIRECTORY_SEPARATOR . md5($username) . '.pem';
        if (!is_file($path)) {
            throw new \Exception('用户公钥不存在');
        }
        return file_get_contents($path);
    }
}
