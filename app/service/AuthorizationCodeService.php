<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;
use Exception;

/**
 * 授权码服务
 * 实现授权码生成、消费（一次性）、过期清理
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class AuthorizationCodeService
{
    /**
     * 生成授权码
     *
     * @param string $clientId 应用 client_id
     * @param int $userId 授权用户 ID
     * @param string $redirectUri 回调地址
     * @param string $scope 授权范围
     * @param string $state 客户端 state 参数
     * @param int $ttl 有效期（秒），默认 600
     * @return string 授权码
     * @throws Exception
     */
    public function generate(string $clientId, int $userId, string $redirectUri, string $scope, string $state, int $ttl = 600): string
    {
        // 清理过期授权码
        $this->cleanup();

        $code = bin2hex(random_bytes(32)); // 64 位授权码
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        Db::name('oauth_authorization_codes')->insert([
            'code' => $code,
            'client_id' => $clientId,
            'user_id' => $userId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'state' => $state,
            'expires_at' => $expiresAt,
        ]);

        return $code;
    }

    /**
     * 消费授权码（验证 + 删除，一次性使用）
     *
     * @param string $code 授权码
     * @param string $clientId 应用 client_id
     * @param string $redirectUri 回调地址
     * @return array|false 成功返回授权码记录，失败返回 false
     */
    public function consume(string $code, string $clientId, string $redirectUri)
    {
        $record = Db::name('oauth_authorization_codes')
            ->where('code', $code)
            ->where('client_id', $clientId)
            ->find();

        if (!$record) {
            return false;
        }

        // 检查是否过期
        if (strtotime($record['expires_at']) < time()) {
            // 删除已过期记录
            Db::name('oauth_authorization_codes')->where('id', $record['id'])->delete();
            return false;
        }

        // 检查 redirect_uri 是否匹配
        if ($record['redirect_uri'] !== $redirectUri) {
            return false;
        }

        // 删除授权码（一次性使用）
        Db::name('oauth_authorization_codes')->where('id', $record['id'])->delete();

        return $record;
    }

    /**
     * 清理过期授权码
     *
     * @return int 清理的记录数
     */
    public function cleanup(): int
    {
        return Db::name('oauth_authorization_codes')
            ->where('expires_at', '<', date('Y-m-d H:i:s'))
            ->delete();
    }

    /**
     * 删除某应用的所有授权码（应用删除时级联清理）
     *
     * @param string $clientId 应用 client_id
     * @return int 删除的记录数
     */
    public function revokeByClient(string $clientId): int
    {
        return Db::name('oauth_authorization_codes')
            ->where('client_id', $clientId)
            ->delete();
    }
}
