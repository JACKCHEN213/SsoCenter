<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * OAuth 参数验证器
 *
 * @package app\validate
 * @author  SSO Center
 * @since   1.0.0
 */
class OauthValidate extends Validate
{
    /**
     * 验证规则
     *
     * @var array
     */
    protected $rule = [
        'user_id' => 'integer|gt:0',
        'username' => 'max:64',
        'external_id' => 'max:128',
        'access_token' => 'require',
        'client_id' => 'require',
        'client_secret' => 'require',
        'grant_type' => 'require|in:authorization_code,refresh_token,password,client_credentials',
        'code' => 'require',
        'redirect_uri' => 'require|url',
        'refresh_token' => 'require',
        'scope' => 'max:255',
    ];

    /**
     * 错误消息
     *
     * @var array
     */
    protected $message = [
        'user_id.integer' => '用户 ID 必须是整数',
        'user_id.gt' => '用户 ID 必须大于 0',
        'username.max' => '用户名长度不能超过 64 个字符',
        'external_id.max' => '外部 ID 长度不能超过 128 个字符',
        'access_token.require' => 'Access Token 不能为空',
        'client_id.require' => 'Client ID 不能为空',
        'client_secret.require' => 'Client Secret 不能为空',
        'grant_type.require' => 'Grant Type 不能为空',
        'grant_type.in' => 'Grant Type 必须是 authorization_code、refresh_token、password 或 client_credentials',
        'code.require' => '授权码不能为空',
        'redirect_uri.require' => '回调地址不能为空',
        'redirect_uri.url' => '回调地址必须是有效的 URL',
        'refresh_token.require' => 'Refresh Token 不能为空',
        'scope.max' => 'Scope 长度不能超过 255 个字符',
    ];

    /**
     * 验证场景
     *
     * @var array
     */
    protected $scene = [
        'logout_callback' => ['user_id', 'username', 'external_id'],
        'token' => ['grant_type', 'client_id', 'client_secret'],
        'authorization_code' => ['code', 'redirect_uri'],
        'refresh_token' => ['refresh_token'],
        'password' => ['username', 'password'],
        'userinfo' => ['access_token'],
        'logout' => ['access_token'],
    ];
}
