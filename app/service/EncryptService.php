<?php
declare(strict_types=1);

namespace app\service;

use Exception;

/**
 * AES-256-CBC 加解密服务
 *
 * 用于应用级敏感数据（如推送 appkey）的加密存储和解密使用。
 * 每个应用使用自身 client_secret 派生的 AES 密钥，实现"按应用独立加密"。
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class EncryptService
{
    private const METHOD = 'aes-256-cbc';

    /**
     * 加密明文（使用应用级 key）
     *
     * @param string $plaintext 明文
     * @param string $appSecret 应用级密钥（如 client_secret），由调用方传入
     * @return string Base64 编码的密文（含 IV 前缀）
     * @throws Exception
     */
    public static function encrypt(string $plaintext, string $appSecret): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key = self::deriveKey($appSecret);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if ($ivLength === false) {
            throw new Exception('无法获取 cipher IV 长度');
        }
        $iv = random_bytes($ivLength);
        $encrypted = openssl_encrypt($plaintext, self::METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new Exception('AES 加密失败: ' . openssl_error_string());
        }
        return base64_encode($iv . $encrypted);
    }

    /**
     * 解密密文（使用应用级 key）
     *
     * @param string $ciphertext Base64 编码的密文（含 IV 前缀）
     * @param string $appSecret 应用级密钥（如 client_secret），由调用方传入
     * @return string 解密后的明文
     * @throws Exception
     */
    public static function decrypt(string $ciphertext, string $appSecret): string
    {
        if ($ciphertext === '') {
            return '';
        }
        $key = self::deriveKey($appSecret);
        $raw = base64_decode($ciphertext, true);
        if ($raw === false) {
            throw new Exception('Base64 解码失败');
        }
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if ($ivLength === false) {
            throw new Exception('无法获取 cipher IV 长度');
        }
        if (strlen($raw) < $ivLength) {
            throw new Exception('密文数据不完整');
        }
        $iv = substr($raw, 0, $ivLength);
        $encrypted = substr($raw, $ivLength);
        $decrypted = openssl_decrypt($encrypted, self::METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new Exception('AES 解密失败: ' . openssl_error_string());
        }
        return $decrypted;
    }

    /**
     * 从应用级 secret 派生 32 字节 AES 密钥（SHA-256）
     *
     * @param string $appSecret 应用级 secret（如 client_secret）
     * @return string 32 字节密钥
     */
    private static function deriveKey(string $appSecret): string
    {
        return hash('sha256', $appSecret, true);
    }
}
