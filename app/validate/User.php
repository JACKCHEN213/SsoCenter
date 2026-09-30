<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 用户管理验证器
 *
 * @package app\validate
 * @author  SSO Center
 * @since   1.0.0
 */
class User extends Validate
{
    public function scenePushStatus(): User
    {
        return $this->only(['user_id'])
            ->append('user_id', ['require']);
    }

    public function scenePushUser(): User
    {
        return $this->only(['user_id', 'site_id'])
            ->append('user_id', ['require'])
            ->append('site_id', ['require']);
    }

    public function scenePushUserToAll(): User
    {
        return $this->only(['user_id'])
            ->append('user_id', ['require']);
    }

    public function scenePushLog(): User
    {
        return $this->only(['user_id', 'site_id'])
            ->append('user_id', ['require'])
            ->append('site_id', ['require']);
    }
}
