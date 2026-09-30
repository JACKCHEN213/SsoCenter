<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 会话管理参数验证器
 *
 * @package app\validate
 * @author  SSO Center
 * @since   1.0.0
 */
class SessionValidate extends Validate
{
    /**
     * 验证规则
     *
     * @var array
     */
    protected $rule = [
        'user_id' => 'require|integer|gt:0',
        'username' => 'max:64',
        'status' => 'in:active,inactive,all',
        'scope' => 'require|in:local,app,all',
        'session_id' => 'integer|gt:0',
        'site_id' => 'integer|gt:0',
        'page' => 'integer|gt:0',
        'limit' => 'integer|gt:0|lt:100',
    ];

    /**
     * 错误消息
     *
     * @var array
     */
    protected $message = [
        'user_id.require' => '用户 ID 不能为空',
        'user_id.integer' => '用户 ID 必须是整数',
        'user_id.gt' => '用户 ID 必须大于 0',
        'username.max' => '用户名长度不能超过 64 个字符',
        'status.in' => '状态必须是 active、inactive 或 all',
        'scope.require' => '退出范围不能为空',
        'scope.in' => '退出范围必须是 local、app 或 all',
        'session_id.integer' => '会话 ID 必须是整数',
        'session_id.gt' => '会话 ID 必须大于 0',
        'site_id.integer' => '应用 ID 必须是整数',
        'site_id.gt' => '应用 ID 必须大于 0',
        'page.integer' => '页码必须是整数',
        'page.gt' => '页码必须大于 0',
        'limit.integer' => '每页数量必须是整数',
        'limit.gt' => '每页数量必须大于 0',
        'limit.lt' => '每页数量必须小于 100',
    ];

    /**
     * 验证场景
     *
     * @var array
     */
    protected $scene = [
        'list' => ['username', 'status', 'page', 'limit'],
        'detail' => ['user_id'],
        'logout' => ['user_id', 'scope', 'session_id', 'site_id'],
    ];
}
