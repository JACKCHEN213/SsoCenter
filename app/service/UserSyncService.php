<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;
use Exception;

/**
 * 用户同步服务
 * 封装 SCIM 用户同步的业务逻辑（upsert/更新/软删除），
 * 避免在控制器中直接操作数据库
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class UserSyncService
{
    /**
     * 创建或更新用户（upsert 语义）
     * 优先按 external_id 匹配，其次按 code（账号标识）匹配
     *
     * @param array $data 用户数据（code, name, externalId, mobile, email, status）
     * @return array ['is_new' => bool, 'user' => array]
     * @throws Exception
     */
    public function upsert(array $data): array
    {
        $user = $this->findByExternalIdOrCode($data['externalId'], $data['code']);

        if ($user) {
            $this->updateUser((int)$user['id'], $data);
            $user = Db::name('user')->where('id', $user['id'])->find();
            return ['is_new' => false, 'user' => $user];
        }

        $userId = $this->createUser($data);
        $user = Db::name('user')->where('id', $userId)->find();
        return ['is_new' => true, 'user' => $user];
    }

    /**
     * 按 external_id 更新用户
     *
     * @param string $externalId 外部 ID
     * @param array $data 用户数据
     * @return array|false 更新成功返回用户记录，用户不存在返回 false
     * @throws Exception
     */
    public function updateByExternalId(string $externalId, array $data)
    {
        $user = Db::name('user')
            ->where('external_id', $externalId)
            ->where('is_del', 0)
            ->find();

        if (!$user) {
            return false;
        }

        $this->updateUser((int)$user['id'], $data);
        return Db::name('user')->where('id', $user['id'])->find();
    }

    /**
     * 按 external_id 软删除用户
     *
     * @param string $externalId 外部 ID
     * @return array|false 删除成功返回用户记录，用户不存在返回 false
     * @throws Exception
     */
    public function softDeleteByExternalId(string $externalId)
    {
        $user = Db::name('user')
            ->where('external_id', $externalId)
            ->where('is_del', 0)
            ->find();

        if (!$user) {
            return false;
        }

        Db::name('user')->where('id', $user['id'])->update(['is_del' => 1]);
        return $user;
    }

    /**
     * 生成账号 ID（基于 external_id 与用户 ID 的确定性 UUID）
     *
     * @param array $user 用户记录
     * @return string UUID 格式的账号 ID
     */
    public function buildAccountId(array $user): string
    {
        $seed = ($user['external_id'] ?: (string)$user['code']) . '|' . $user['id'];
        $hash = md5($seed);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hash, 4));
    }

    /**
     * 按 external_id 或 code 查找未删除的用户
     *
     * @param string $externalId 外部 ID
     * @param string $code 账号标识
     * @return array|null
     */
    private function findByExternalIdOrCode(string $externalId, string $code): ?array
    {
        $user = Db::name('user')
            ->where('external_id', $externalId)
            ->where('is_del', 0)
            ->find();

        if ($user) {
            return $user;
        }

        $user = Db::name('user')
            ->where('code', $code)
            ->where('is_del', 0)
            ->find();

        return $user ?: null;
    }

    /**
     * 创建用户
     * SCIM 同步的用户不可密码登录，密码字段写入随机 bcrypt 散列
     *
     * @param array $data 用户数据
     * @return int 新用户 ID
     * @throws Exception
     */
    private function createUser(array $data): int
    {
        $insert = [
            'username' => $data['code'],
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'status' => $this->toStatus($data['status']),
            'type' => 2,
            'is_del' => 0,
            'code' => $data['code'],
            'external_id' => $data['externalId'],
        ];

        return (int)Db::name('user')->insertGetId($insert);
    }

    /**
     * 更新用户信息
     *
     * @param int $userId 用户 ID
     * @param array $data 用户数据
     * @return void
     * @throws Exception
     */
    private function updateUser(int $userId, array $data): void
    {
        $update = [
            'status' => $this->toStatus($data['status']),
            'external_id' => $data['externalId'],
        ];

        if (array_key_exists('mobile', $data)) {
            $update['mobile'] = $data['mobile'];
        }
        if (array_key_exists('email', $data)) {
            $update['email'] = $data['email'];
        }

        Db::name('user')->where('id', $userId)->update($update);
    }

    /**
     * 将布尔/字符串状态值转换为数据库状态（1 启用 / 0 禁用）
     *
     * @param mixed $status 状态值
     * @return int
     */
    private function toStatus($status): int
    {
        if (is_bool($status)) {
            return $status ? 1 : 0;
        }
        $normalized = strtolower(trim((string)$status));
        return in_array($normalized, ['1', 'true', 'active', 'enabled'], true) ? 1 : 0;
    }
}
