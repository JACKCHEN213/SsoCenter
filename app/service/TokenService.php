<?php
declare(strict_types=1);

namespace app\service;

use app\common\Key;
use think\facade\Db;
use extra\JWT;
use Exception;

/**
 * Token 服务
 * 实现 Access Token 生成（JWT RS256）、Refresh Token 生成、Token 存储/验证/撤销/刷新
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class TokenService
{
    /**
     * 生成 Access Token（JWT RS256 签名）
     *
     * @param array $payload Token 载荷
     * @return string JWT 字符串
     * @throws Exception
     */
    public function generateAccessToken(array $payload): string
    {
        $privateKey = Key::getPrivateKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME'));
        return JWT::encode($payload, $privateKey, 'RS256');
    }

    /**
     * 生成 Refresh Token（128 位随机字符串）
     *
     * @return string
     */
    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(64)); // 128 位十六进制
    }

    /**
     * 生成 Access Token 的 SHA256 摘要（用于数据库存储索引）
     *
     * @param string $accessToken JWT 字符串
     * @return string SHA256 摘要
     */
    public function hashAccessToken(string $accessToken): string
    {
        return hash('sha256', $accessToken);
    }

    /**
     * 验证并解析 Access Token
     *
     * @param string $token JWT 字符串
     * @return object|false 解析成功返回 payload 对象，失败返回 false
     */
    public function verifyAccessToken(string $token)
    {
        try {
            $publicKey = Key::getPublicKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME'));
            return JWT::decode($token, $publicKey, ['RS256']);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 检查 Access Token 在数据库中是否有效（未撤销、未过期）
     *
     * @param string $accessToken JWT 字符串
     * @return bool
     */
    public function isAccessTokenValid(string $accessToken): bool
    {
        $hash = $this->hashAccessToken($accessToken);
        $record = Db::name('oauth_access_tokens')->where('access_token', $hash)->find();

        if (!$record) {
            return false;
        }

        if ($record['revoked']) {
            return false;
        }

        if (strtotime($record['expires_at']) < time()) {
            return false;
        }

        return true;
    }

    /**
     * 存储 Access Token 记录到数据库
     *
     * @param array $data Token 数据
     * @return void
     */
    public function storeAccessToken(array $data): void
    {
        Db::name('oauth_access_tokens')->insert([
            'access_token' => $this->hashAccessToken($data['access_token']),
            'client_id' => $data['client_id'],
            'user_id' => $data['user_id'] ?? null,
            'grant_type' => $data['grant_type'],
            'scope' => $data['scope'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', time() + $data['expires_in']),
        ]);
    }

    /**
     * 存储 Refresh Token 记录到数据库
     *
     * @param array $data Token 数据
     * @return string 生成的 refresh_token 值
     */
    public function storeRefreshToken(array $data): string
    {
        $refreshToken = $this->generateRefreshToken();

        Db::name('oauth_refresh_tokens')->insert([
            'refresh_token' => $refreshToken,
            'client_id' => $data['client_id'],
            'user_id' => $data['user_id'],
            'scope' => $data['scope'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', time() + $data['expires_in']),
            'access_token_id' => $this->hashAccessToken($data['access_token'] ?? ''),
        ]);

        return $refreshToken;
    }

    /**
     * 撤销 Access Token
     *
     * @param string $accessToken JWT 字符串
     * @return void
     */
    public function revokeAccessToken(string $accessToken): void
    {
        $hash = $this->hashAccessToken($accessToken);
        Db::name('oauth_access_tokens')->where('access_token', $hash)->update(['revoked' => 1]);
    }

    /**
     * 撤销 Refresh Token
     *
     * @param string $refreshToken Refresh Token 值
     * @return void
     */
    public function revokeRefreshToken(string $refreshToken): void
    {
        Db::name('oauth_refresh_tokens')->where('refresh_token', $refreshToken)->update(['revoked' => 1]);
    }

    /**
     * 验证 Refresh Token 有效性
     *
     * @param string $refreshToken Refresh Token 值
     * @param string $clientId 客户端 ID
     * @return array|false 有效返回记录，无效返回 false
     */
    public function verifyRefreshToken(string $refreshToken, string $clientId)
    {
        $record = Db::name('oauth_refresh_tokens')
            ->where('refresh_token', $refreshToken)
            ->where('client_id', $clientId)
            ->find();

        if (!$record) {
            return false;
        }

        if ($record['revoked']) {
            return false;
        }

        if (strtotime($record['expires_at']) < time()) {
            return false;
        }

        return $record;
    }

    /**
     * 刷新 Token（返回新 token 对，旧 token 失效）
     *
     * @param string $refreshToken Refresh Token 值
     * @param array $client 客户端信息
     * @return array|false 成功返回 ['access_token', 'refresh_token', 'expires_in', 'scope']，失败返回 false
     * @throws Exception
     */
    public function refreshTokens(string $refreshToken, array $client)
    {
        // 验证 refresh_token
        $rtRecord = $this->verifyRefreshToken($refreshToken, $client['client_id']);
        if ($rtRecord === false) {
            return false;
        }

        // 撤销旧 token
        $this->revokeRefreshToken($refreshToken);
        if (!empty($rtRecord['access_token_id'])) {
            Db::name('oauth_access_tokens')->where('access_token', $rtRecord['access_token_id'])->update(['revoked' => 1]);
        }

        // 生成新 access_token
        $accessTokenTtl = $client['access_token_ttl'] ?? 28800;
        $refreshTokenTtl = $client['refresh_token_ttl'] ?? 86400;
        $scope = $rtRecord['scope'] ?? 'trust';

        $now = time();
        $payload = [
            'iss' => request()->host(true),
            'sub' => (string)$rtRecord['user_id'],
            'aud' => [$client['client_id']],
            'iat' => $now,
            'exp' => $now + $accessTokenTtl,
            'jti' => uniqid('', true),
            'scope' => $scope,
            'client_id' => $client['client_id'],
            'token_type' => 'access',
        ];

        $accessToken = $this->generateAccessToken($payload);

        // 存储新 token
        $this->storeAccessToken([
            'access_token' => $accessToken,
            'client_id' => $client['client_id'],
            'user_id' => $rtRecord['user_id'],
            'grant_type' => 'refresh_token',
            'scope' => $scope,
            'expires_in' => $accessTokenTtl,
        ]);

        $newRefreshToken = $this->storeRefreshToken([
            'client_id' => $client['client_id'],
            'user_id' => $rtRecord['user_id'],
            'scope' => $scope,
            'expires_in' => $refreshTokenTtl,
            'access_token' => $accessToken,
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenTtl,
            'scope' => $scope,
        ];
    }

    /**
     * 级联撤销某应用的所有 token
     *
     * @param string $clientId 客户端 ID
     * @return void
     */
    public function revokeAllTokensByClient(string $clientId): void
    {
        Db::name('oauth_access_tokens')->where('client_id', $clientId)->update(['revoked' => 1]);
        Db::name('oauth_refresh_tokens')->where('client_id', $clientId)->update(['revoked' => 1]);
    }

    /**
     * 级联撤销某用户的所有 token
     *
     * @param int $userId 用户 ID
     * @return void
     */
    public function revokeAllTokensByUser(int $userId): void
    {
        Db::name('oauth_access_tokens')->where('user_id', $userId)->update(['revoked' => 1]);
        Db::name('oauth_refresh_tokens')->where('user_id', $userId)->update(['revoked' => 1]);
    }
}
