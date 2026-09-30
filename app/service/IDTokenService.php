<?php
declare(strict_types=1);

namespace app\service;

use extra\JWT;
use Exception;

/**
 * ID Token 服务
 * 实现 ID Token 生成（JWT HS256，密钥为应用的 client_secret）
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class IDTokenService
{
    /**
     * 生成 ID Token
     *
     * @param array $user 用户信息
     * @param string $clientId 应用 client_id
     * @param string $clientSecret 应用 client_secret（HS256 签名密钥）
     * @return string JWT 字符串
     * @throws Exception
     */
    public function generate(array $user, string $clientId, string $clientSecret): string
    {
        $now = (int)(microtime(true) * 1000); // 毫秒时间戳
        $exp = $now + 1800000; // 30 分钟后过期

        $payload = [
            'iss' => 'https://www.qianxin.com/',
            'sub' => $user['uuid'] ?? uniqid('user_'),
            'aud' => [$clientId],
            'iat' => $now,
            'exp' => $exp,
            'auth_time' => $now,
            'jti' => uniqid('id_', true),
            'azp' => $clientId,
            'name' => $user['username'] ?? '',
            'email' => $user['email'] ?? '',
            'phone_number' => $user['mobile'] ?? '',
            'active' => ($user['status'] ?? 1) == 1,
            'typ' => 'ID',
            'acr' => '0',
            'business_account' => [
                'code' => $user['code'] ?? ($user['username'] ?? ''),
                'id' => (string)($user['id'] ?? ''),
                'name' => $user['username'] ?? '',
            ],
        ];

        return JWT::encode($payload, $clientSecret, 'HS256');
    }
}
