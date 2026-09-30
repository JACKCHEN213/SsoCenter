<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * SCIM 用户同步接口验证器
 *
 * @package app\validate
 * @author  SSO Center
 * @since   1.0.0
 */
class Scim extends Validate
{
    protected $rule = [
        'code' => 'require|max:64',
        'name' => 'require|max:255',
        'externalId' => 'require|max:128',
        'status' => 'require',
        'mobile' => 'max:32',
        'email' => 'email|max:128',
    ];

    protected $message = [
        'code.require' => 'code 参数必填',
        'name.require' => 'name 参数必填',
        'externalId.require' => 'externalId 参数必填',
        'status.require' => 'status 参数必填',
        'email.email' => 'email 格式不正确',
    ];

    /**
     * 创建账号场景
     *
     * @return Scim
     */
    public function sceneCreateAccount(): Scim
    {
        return $this->only(['code', 'name', 'externalId', 'status', 'mobile', 'email']);
    }

    /**
     * 修改账号场景
     *
     * @return Scim
     */
    public function sceneUpdateAccount(): Scim
    {
        return $this->only(['code', 'name', 'externalId', 'status', 'mobile', 'email']);
    }

    /**
     * 删除账号场景（无 Body 参数，externalId 来自 URL）
     *
     * 注意：DELETE 路由未挂载 ValidateParams 中间件，本场景不会被调用。
     * ThinkPHP 的 only([]) 并不表示"不校验任何字段"（空数组会被视为未设置场景），
     * 因此删除接口直接在路由层跳过参数校验。
     *
     * @return Scim
     */
    public function sceneDeleteAccount(): Scim
    {
        return $this->only([]);
    }
}
