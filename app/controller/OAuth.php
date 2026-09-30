<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\Key;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use app\common\Uuid;
use app\service\AuthorizationCodeService;
use app\service\IDTokenService;
use app\service\TokenService;
use app\service\AuditLogService;
use extra\JWT;
use Exception;
use GuzzleHttp\Client;
use think\facade\Db;
use think\facade\View;
use think\response\Json;
use think\response\Redirect;

/**
 * OAuth 2.0 控制器
 * 实现授权码、隐式、密码、客户端凭证四种授权模式
 *
 * @package app\controller
 * @author  SSO Center
 * @since   1.0.0
 */
class OAuth extends BaseController
{
    protected TokenService $tokenService;
    protected AuthorizationCodeService $authCodeService;
    protected IDTokenService $idTokenService;

    protected function initialize()
    {
        $this->tokenService = new TokenService();
        $this->authCodeService = new AuthorizationCodeService();
        $this->idTokenService = new IDTokenService();
    }
    /**
     * 授权端点 — GET /oauth/authorize
     * 支持 response_type=code（授权码）和 response_type=token（隐式）
     *
     * @return Redirect|Json
     */
    public function authorize()
    {
        try {
            // T4.2.1 校验参数
            $clientId = input('get.client_id/s', '');
            $responseType = input('get.response_type/s', '');
            $redirectUri = input('get.redirect_uri/s', '');
            $scope = input('get.scope/s', 'trust');
            $state = input('get.state/s', '');

            // 校验 client_id
            if (empty($clientId)) {
                return $this->oauthError('invalid_request', '缺少 client_id 参数', 400);
            }

            // 校验 response_type
            if (!in_array($responseType, ['code', 'token'])) {
                return $this->oauthError('invalid_request', 'response_type 必须为 code 或 token', 400);
            }

            // 校验 redirect_uri
            if (empty($redirectUri)) {
                return $this->oauthError('invalid_request', '缺少 redirect_uri 参数', 400);
            }

            // 查询应用
            $site = Db::name('site')
                ->where('client_id', $clientId)
                ->where('is_del', 0)
                ->where('is_use', 1)
                ->find();

            if (!$site) {
                return $this->oauthError('invalid_client', '无效的 client_id', 401);
            }

            // 验证 redirect_uri 与注册值一致
            if ($site['redirect_url'] !== $redirectUri) {
                return $this->oauthError('invalid_redirect_uri', 'redirect_uri 与应用注册的不一致', 400);
            }

            // T4.2.2 检测用户登录状态
            $userId = $this->getLoggedInUserId();
            if ($userId === null) {
                // 未登录，重定向到登录页（携带原始参数）
                $loginUrl = '/login/?oauth_redirect=' . urlencode(request()->url(true));
                return redirect($loginUrl);
            }

            // 获取用户信息
            $user = Db::name('user')->where('id', $userId)->find();
            if (!$user) {
                return $this->oauthError('access_denied', '用户不存在', 403);
            }

            $allowedGrantTypes = explode(',', $site['allowed_grant_types'] ?? 'authorization_code');

            if ($responseType === 'code') {
                // T4.2.3 授权码模式
                if (!in_array('authorization_code', $allowedGrantTypes)) {
                    return $this->oauthError('access_denied', '该应用未启用授权码模式', 403);
                }

                $codeTtl = $site['code_ttl'] ?? 600;
                $code = $this->authCodeService->generate($clientId, $userId, $redirectUri, $scope, $state, $codeTtl);

                // 302 回调，携带 code + state
                $callbackUrl = $redirectUri . '?code=' . urlencode($code) . '&state=' . urlencode($state);
                return redirect($callbackUrl);
            } else {
                // T4.2.4 隐式模式（response_type=token）
                if (!in_array('implicit', $allowedGrantTypes)) {
                    return $this->oauthError('access_denied', '该应用未启用隐式模式', 403);
                }

                // 生成 access_token
                $accessTokenTtl = $site['access_token_ttl'] ?? 28800;
                $now = time();
                $payload = [
                    'iss' => request()->host(true),
                    'sub' => (string)$userId,
                    'aud' => [$clientId],
                    'iat' => $now,
                    'exp' => $now + $accessTokenTtl,
                    'jti' => uniqid('', true),
                    'scope' => $scope,
                    'client_id' => $clientId,
                    'token_type' => 'access',
                ];

                $accessToken = $this->tokenService->generateAccessToken($payload);

                // 存储 token
                $this->tokenService->storeAccessToken([
                    'access_token' => $accessToken,
                    'client_id' => $clientId,
                    'user_id' => $userId,
                    'grant_type' => 'implicit',
                    'scope' => $scope,
                    'expires_in' => $accessTokenTtl,
                ]);

                // 302 回调，token 在 URL fragment 中
                $fragment = 'access_token=' . urlencode($accessToken)
                    . '&token_type=Bearer'
                    . '&expires_in=' . $accessTokenTtl
                    . '&state=' . urlencode($state);
                $callbackUrl = $redirectUri . '#' . $fragment;
                return redirect($callbackUrl);
            }
        } catch (Exception $e) {
            recordLog($e, 'error');
            return $this->oauthError('server_error', $e->getMessage(), 500);
        }
    }

    /**
     * 渲染 SSO 授权登录页 — GET /oauth/authorize
     *
     * 根据登录状态渲染登录表单或授权确认视图
     *
     * @return \think\Response
     */
    public function authorizePage()
    {
        try {
            // T1.1 新参数名优先，兼容旧参数名（向后兼容）
            $clientId    = input('get.APP_ID/s') ?: input('get.client_id/s', '');
            $state       = input('get.state/s') ?: input('get.state/s', '');
            $redirectUri = input('get.callback/s') ?: input('get.redirect_uri/s', '');
            // T1.2 固定授权码模式，scope 从应用配置读取
            $responseType = 'code';

            // 参数校验
            if (empty($clientId) || !Uuid::isValidUuid4($clientId)) {
                return $this->oauthPageError('未知的应用', 'APP_ID 参数缺失或格式无效');
            }
            if (empty($redirectUri)) {
                return $this->oauthPageError('无效的回调地址', 'callback 参数缺失');
            }

            // 查询应用
            $site = Db::name('site')
                ->where('client_id', $clientId)
                ->where('is_del', 0)
                ->where('is_use', 1)
                ->find();
            if (!$site) {
                return $this->oauthPageError('未知的应用', 'APP_ID 不存在或已禁用');
            }
            if ($site['redirect_url'] !== $redirectUri) {
                return $this->oauthPageError('无效的回调地址', 'callback 与应用注册值不一致');
            }

            // T1.3 模板变量使用新名称（app_id / callback / state）
            View::assign([
                'app_name' => $site['name'],
                'app_image' => $site['image'] ?: '',
                'app_id' => $clientId,
                'callback' => $redirectUri,
                'state' => $state,
                'is_logged_in' => false,
                'logged_in_username' => '',
            ]);
            return View::fetch('/oauth_authorize');
        } catch (Exception $e) {
            recordLog($e, 'error');
            return $this->oauthPageError('服务异常', $e->getMessage());
        }
    }

    /**
     * 处理用户授权确认 — POST /oauth/authorize/confirm
     *
     * 根据 response_type 生成授权码或 access_token，返回 redirect_url 供前端跳转
     *
     * @return Json
     */
    public function confirmAuthorize(): Json
    {
        try {
            // T1.4 新参数名优先，兼容旧参数名
            $clientId    = input('post.APP_ID/s') ?: input('post.client_id/s', '');
            $state       = input('post.state/s') ?: input('post.state/s', '');
            $redirectUri = input('post.callback/s') ?: input('post.redirect_uri/s', '');
            // T1.5 固定授权码模式
            $responseType = 'code';

            // 验证登录态
            $userId = $this->getLoggedInUserId();
            if (!$userId) {
                return sendJson('登录已失效，请重新登录', ResponseCode::$JWT_ERROR, ResponseMessage::$JWT_ERROR, false);
            }

            // 参数校验
            if (empty($clientId) || !Uuid::isValidUuid4($clientId)) {
                return sendJson('无效的 APP_ID', ResponseCode::$VALIDATE_ERROR, ResponseMessage::$VALIDATE_ERROR, false);
            }
            if (empty($redirectUri)) {
                return sendJson('缺少 callback', ResponseCode::$VALIDATE_ERROR, ResponseMessage::$VALIDATE_ERROR, false);
            }

            $site = Db::name('site')
                ->where('client_id', $clientId)
                ->where('is_del', 0)
                ->where('is_use', 1)
                ->find();
            if (!$site) {
                return sendJson('应用不存在', ResponseCode::$APP_NOT_FOUND, ResponseMessage::$APP_NOT_FOUND, false);
            }
            if ($site['redirect_url'] !== $redirectUri) {
                return sendJson('callback 与注册值不一致', ResponseCode::$VALIDATE_ERROR, ResponseMessage::$VALIDATE_ERROR, false);
            }

            // T1.5 scope 从应用配置读取，不从请求传入
            $scope = !empty($site['scope']) ? $site['scope'] : 'trust';

            $allowedGrantTypes = array_filter(array_map('trim', explode(',', $site['allowed_grant_types'] ?? '')));

            // SSO 认证页面固定为授权码模式
            if (!in_array('authorization_code', $allowedGrantTypes)) {
                return sendJson('该应用未启用授权码模式', ResponseCode::$OAUTH_GRANT_TYPE_NOT_ALLOWED, ResponseMessage::$OAUTH_GRANT_TYPE_NOT_ALLOWED, false);
            }
            $codeTtl = (int)($site['code_ttl'] ?: 600);
            $code = $this->authCodeService->generate($clientId, $userId, $redirectUri, $scope, $state, $codeTtl);
            // T1.5 回调 URL 格式：{callback}?code={授权码}&state={state}
            $redirectUrl = $redirectUri . '?code=' . urlencode($code) . '&state=' . urlencode($state);

            recordLog("用户授权：user_id={$userId}, client_id={$clientId}, response_type={$responseType}", 'info');
            return sendJson(['redirect_url' => $redirectUrl]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }

    /**
     * 用户拒绝授权 — POST /oauth/authorize/deny
     *
     * 返回带 error 参数的 redirect_url，供前端跳转（防御性接口；主要拒绝流程由 JS 直接跳转）
     *
     * @return Json
     */
    public function denyAuthorize(): Json
    {
        // 兼容新旧参数名（新参数优先）
        $redirectUri = input('post.callback/s') ?: input('post.redirect_uri/s', '');
        $state = input('post.state/s') ?: input('post.state/s', '');
        if (empty($redirectUri)) {
            return sendJson('缺少 callback', ResponseCode::$VALIDATE_ERROR, ResponseMessage::$VALIDATE_ERROR, false);
        }
        $url = $redirectUri
            . '?error=access_denied'
            . '&error_description=' . urlencode('User denied the request')
            . '&state=' . urlencode($state);
        return sendJson(['redirect_url' => $url]);
    }

    /**
     * 渲染 OAuth 错误页面（参数无效、应用不存在等）
     *
     * @param string $title 错误标题
     * @param string $description 错误描述
     * @return \think\Response
     */
    private function oauthPageError(string $title, string $description)
    {
        View::assign([
            'error_title' => $title,
            'error_description' => $description,
        ]);
        return View::fetch('/oauth_authorize');
    }

    /**
     * Token 端点 — POST /oauth/token
     * 支持四种 grant_type
     *
     * @return Json
     */
    public function token(): Json
    {
        try {
            $grantType = input('post.grant_type/s', '');

            // T4.3.6 检查 grant_type
            $validGrantTypes = ['authorization_code', 'refresh_token', 'password', 'client_credentials'];
            if (!in_array($grantType, $validGrantTypes)) {
                return $this->oauthError('unsupported_grant_type', '不支持的 grant_type: ' . $grantType, 400);
            }

            // T4.3.1 客户端信息（由 OAuthClientAuth 中间件注入）
            $site = request()->oauth_site ?? null;
            if (!$site) {
                return $this->oauthError('invalid_client', '客户端认证失败', 401);
            }

            $clientId = $site['client_id'];
            $allowedGrantTypes = explode(',', $site['allowed_grant_types'] ?? 'authorization_code');

            // 检查该应用是否启用了此授权模式。
            // refresh_token 为派生能力（由 authorization_code/password 颁发），
            // 不作为独立启用项，只要客户端持有合法 refresh_token 即可刷新
            if ($grantType !== 'refresh_token' && !in_array($grantType, $allowedGrantTypes)) {
                return $this->oauthError('access_denied', '该应用未启用 ' . $grantType . ' 授权模式', 403);
            }

            switch ($grantType) {
                case 'authorization_code':
                    return $this->handleAuthorizationCodeGrant($site);
                case 'refresh_token':
                    return $this->handleRefreshTokenGrant($site);
                case 'password':
                    return $this->handlePasswordGrant($site);
                case 'client_credentials':
                    return $this->handleClientCredentialsGrant($site);
                default:
                    return $this->oauthError('unsupported_grant_type', '不支持的 grant_type', 400);
            }
        } catch (Exception $e) {
            recordLog($e, 'error');
            return $this->oauthError('server_error', $e->getMessage(), 500);
        }
    }

    /**
     * 获取用户信息 — POST /oauth/userinfo
     *
     * @return Json
     */
    public function userinfo(): Json
    {
        try {
            $accessToken = input('post.access_token/s', '');

            // T4.4.1 验证 access_token
            if (empty($accessToken)) {
                return $this->oauthError('invalid_token', '缺少 access_token', 401);
            }

            $payload = $this->tokenService->verifyAccessToken($accessToken);
            if ($payload === false) {
                return $this->oauthError('invalid_token', 'Token 无效或已过期', 401);
            }

            // 检查数据库中是否有效
            if (!$this->tokenService->isAccessTokenValid($accessToken)) {
                return $this->oauthError('invalid_token', 'Token 已撤销或过期', 401);
            }

            $clientId = $payload->client_id ?? '';
            $userId = (int)($payload->sub ?? 0);

            // 查询应用获取 client_secret
            $site = Db::name('site')->where('client_id', $clientId)->find();
            if (!$site) {
                return $this->oauthError('invalid_client', '应用不存在', 401);
            }

            // 查询用户信息
            $user = Db::name('user')->where('id', $userId)->find();
            if (!$user) {
                return $this->oauthError('invalid_token', '用户不存在', 401);
            }

            // T4.4.2 生成 ID Token
            $idToken = $this->idTokenService->generate($user, $clientId, $site['client_secret']);

            // T4.4.3 按规范文档格式返回
            return json([
                'message' => 'succes',
                'status' => 200,
                'data' => [
                    'id_token' => $idToken,
                ],
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return $this->oauthError('server_error', $e->getMessage(), 500);
        }
    }

    /**
     * 应用通知登出 — POST /oauth/logout
     *
     * @return Json
     */
    public function logout(): Json
    {
        try {
            // T4.5.1 验证 Basic Auth + access_token
            $site = request()->oauth_site ?? null;
            if (!$site) {
                return $this->oauthError('invalid_client', '客户端认证失败', 401);
            }

            $accessToken = input('post.access_token/s', '');
            if (empty($accessToken)) {
                return $this->oauthError('invalid_token', '缺少 access_token', 401);
            }

            // 验证 token
            $payload = $this->tokenService->verifyAccessToken($accessToken);
            $authorization = request()->header('authorization');
            $authCode = base64_decode(str_replace('Basic ', '', $authorization));
            $appList = explode(':', $authCode);
            $appId = $appList[0];
            if ($payload === false) {
                return $this->oauthError('invalid_token', 'Token 无效', 401);
            }

            Db::startTrans();

            // 撤销关联的 refresh_token
            $userId = (int)($payload->sub ?? 0);
            if ($userId > 0) {
                Db::name('oauth_refresh_tokens')
                    ->where('user_id', $userId)
                    ->where('client_id', $site['client_id'])
                    ->update(['revoked' => 1]);
            }

            // T4.5.3 通知关联应用登出
            $this->notifyRelatedAppsLogout($userId, [$appId]);

            // T4.5.2 撤销该 access_token
            $this->tokenService->revokeAccessToken($accessToken);
            
            Db::name('user_sessions')
                ->where('user_id', $userId)
                ->where('is_active', 1)
                ->update(['is_active' => 0]);

            Db::commit();

            return json([
                'message' => 'succes',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            Db::rollback();
            recordLog($e, 'error');
            return $this->oauthError('server_error', $e->getMessage(), 500);
        }
    }

    /**
     * 处理授权码模式
     *
     * @param array $site 应用信息
     * @return Json
     */
    private function handleAuthorizationCodeGrant(array $site): Json
    {
        $code = input('post.code/s', '');
        $redirectUri = input('post.redirect_uri/s', '');

        if (empty($code)) {
            return $this->oauthError('invalid_request', '缺少 code 参数', 400);
        }

        // T4.3.2 消费授权码
        $codeRecord = $this->authCodeService->consume($code, $site['client_id'], $redirectUri);
        if ($codeRecord === false) {
            return $this->oauthError('invalid_grant', '授权码无效或已过期', 400);
        }

        $userId = (int)$codeRecord['user_id'];
        $scope = $codeRecord['scope'] ?? 'trust';
        $accessTokenTtl = $site['access_token_ttl'] ?? 28800;
        $refreshTokenTtl = $site['refresh_token_ttl'] ?? 86400;

        // 生成 access_token
        $now = time();
        $payload = [
            'iss' => request()->host(true),
            'sub' => (string)$userId,
            'aud' => [$site['client_id']],
            'iat' => $now,
            'exp' => $now + $accessTokenTtl,
            'jti' => uniqid('', true),
            'scope' => $scope,
            'client_id' => $site['client_id'],
            'token_type' => 'access',
        ];

        $accessToken = $this->tokenService->generateAccessToken($payload);

        // 存储 token
        $this->tokenService->storeAccessToken([
            'access_token' => $accessToken,
            'client_id' => $site['client_id'],
            'user_id' => $userId,
            'grant_type' => 'authorization_code',
            'scope' => $scope,
            'expires_in' => $accessTokenTtl,
        ]);

        // 生成 refresh_token
        $refreshToken = $this->tokenService->storeRefreshToken([
            'client_id' => $site['client_id'],
            'user_id' => $userId,
            'scope' => $scope,
            'expires_in' => $refreshTokenTtl,
            'access_token' => $accessToken,
        ]);

        return json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenTtl,
            'scope' => $scope,
        ]);
    }

    /**
     * 处理刷新令牌模式
     *
     * @param array $site 应用信息
     * @return Json
     */
    private function handleRefreshTokenGrant(array $site): Json
    {
        $refreshToken = input('post.refresh_token/s', '');

        if (empty($refreshToken)) {
            return $this->oauthError('invalid_request', '缺少 refresh_token 参数', 400);
        }

        // T4.3.3 刷新 token
        $result = $this->tokenService->refreshTokens($refreshToken, $site);
        if ($result === false) {
            return $this->oauthError('invalid_grant', 'refresh_token 无效或已过期', 400);
        }

        return json($result);
    }

    /**
     * 处理密码模式
     *
     * @param array $site 应用信息
     * @return Json
     */
    private function handlePasswordGrant(array $site): Json
    {
        $username = input('post.username/s', '');
        $password = input('post.password/s', '');
        $scope = input('post.scope/s', 'trust');

        if (empty($username) || empty($password)) {
            return $this->oauthError('invalid_request', '缺少 username 或 password 参数', 400);
        }

        // T4.3.4 验证用户名密码
        $user = Db::name('user')->where('username', $username)->where('is_del', 0)->find();
        if (!$user) {
            return $this->oauthError('invalid_grant', '用户不存在', 400);
        }

        // 验证密码（复用 Login 控制器的验证逻辑）
        if (!$this->verifyUserPassword($password, $user)) {
            return $this->oauthError('invalid_grant', '用户名或密码错误', 400);
        }

        $userId = (int)$user['id'];
        $accessTokenTtl = $site['access_token_ttl'] ?? 28800;
        $refreshTokenTtl = $site['refresh_token_ttl'] ?? 86400;

        // 生成 access_token
        $now = time();
        $payload = [
            'iss' => request()->host(true),
            'sub' => (string)$userId,
            'aud' => [$site['client_id']],
            'iat' => $now,
            'exp' => $now + $accessTokenTtl,
            'jti' => uniqid('', true),
            'scope' => $scope,
            'client_id' => $site['client_id'],
            'token_type' => 'access',
        ];

        $accessToken = $this->tokenService->generateAccessToken($payload);

        // 存储 token
        $this->tokenService->storeAccessToken([
            'access_token' => $accessToken,
            'client_id' => $site['client_id'],
            'user_id' => $userId,
            'grant_type' => 'password',
            'scope' => $scope,
            'expires_in' => $accessTokenTtl,
        ]);

        // 生成 refresh_token
        $refreshToken = $this->tokenService->storeRefreshToken([
            'client_id' => $site['client_id'],
            'user_id' => $userId,
            'scope' => $scope,
            'expires_in' => $refreshTokenTtl,
            'access_token' => $accessToken,
        ]);

        return json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenTtl,
            'scope' => $scope,
        ]);
    }

    /**
     * 处理客户端凭证模式
     *
     * @param array $site 应用信息
     * @return Json
     */
    private function handleClientCredentialsGrant(array $site): Json
    {
        $scope = input('post.scope/s', 'trust');
        $accessTokenTtl = $site['access_token_ttl'] ?? 28800;

        // T4.3.5 生成 access_token（无 refresh_token，无 user_id）
        $now = time();
        $payload = [
            'iss' => request()->host(true),
            'sub' => $site['client_id'],
            'aud' => [$site['client_id']],
            'iat' => $now,
            'exp' => $now + $accessTokenTtl,
            'jti' => uniqid('', true),
            'scope' => $scope,
            'client_id' => $site['client_id'],
            'token_type' => 'access',
        ];

        $accessToken = $this->tokenService->generateAccessToken($payload);

        // 存储 token
        $this->tokenService->storeAccessToken([
            'access_token' => $accessToken,
            'client_id' => $site['client_id'],
            'user_id' => null,
            'grant_type' => 'client_credentials',
            'scope' => $scope,
            'expires_in' => $accessTokenTtl,
        ]);

        return json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenTtl,
            'scope' => $scope,
        ]);
    }

    /**
     * 验证用户密码（复用 Login 控制器的验证逻辑）
     *
     * @param string $password 用户输入的原始密码
     * @param array $user 用户记录
     * @return bool
     */
    private function verifyUserPassword(string $password, array $user): bool
    {
        // 已迁移用户：使用私钥解密 + password_verify
        if (!empty($user['jwt_private_path'])) {
            try {
                $keyDir = app()->getRootPath() . config('common.USER_KEY_PATH');
                $privateKey = Key::getUserPrivateKey($keyDir, $user['username']);
                $encryptedHash = base64_decode($user['password']);
                if (!openssl_private_decrypt($encryptedHash, $decryptedHash, $privateKey)) {
                    return false;
                }
                return password_verify(md5($password), $decryptedHash);
            } catch (Exception $e) {
                return false;
            }
        }

        // 未迁移用户：兼容旧 md5 比对
        return md5($password) === $user['password'];
    }

    /**
     * 获取当前登录用户 ID
     *
     * 兼容两种前端存储格式：
     *  - 原始 JWT 字符串（新 SSO 登录流程）
     *  - base64 包裹的 JWT（旧管理后台 localStorage 存储方式）
     *
     * @return int|null
     */
    private function getLoggedInUserId(): ?int
    {
        // 登录态优先取 Authorization 头（与 CheckLogin 中间件一致），
        // 兼容浏览器重定向流程下的 Cookie 形式
        $token = request()->header('authorization');
        if (empty($token)) {
            $token = request()->cookie('authorization', '');
        }
        if (empty($token)) {
            return null;
        }

        $candidates = [$token];
        // 兼容前端可能以 base64 形式存储的 JWT（旧管理后台 btoa 存储）
        $decoded = base64_decode($token, true);
        if ($decoded !== false && $decoded !== $token) {
            $candidates[] = $decoded;
        }

        $publicKey = Key::getPublicKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME'));
        foreach ($candidates as $candidate) {
            try {
                $payload = JWT::decode($candidate, $publicKey, array_keys(JWT::$supported_algs));
                $userId = (int)($payload->id ?? 0);
                if ($userId > 0) {
                    return $userId;
                }
            } catch (\Throwable $e) {
                // 尝试下一个候选
                continue;
            }
        }
        return null;
    }

    /**
     * 通知关联应用登出
     *
     * 查询用户所有有效 token 关联的应用，向配置了 logout_callback_url 的应用发送登出通知。
     *
     * @param int $userId 用户 ID
     * @param array $siteIds 不通知的应用 ID 列表
     * @return void
     */
    private function notifyRelatedAppsLogout(int $userId, array $siteIds = []): void
    {
        if ($userId <= 0) {
            return;
        }

        // 查询该用户所有有效的 access_token 关联的应用
        $query = Db::name('oauth_access_tokens')
            ->where('user_id', $userId)
            ->where('revoked', 0);

        $tokens = $query->select();

        $notifiedClients = [];
        foreach ($tokens as $token) {
            $clientId = $token['client_id'];
            if (in_array($clientId, $siteIds)) {
                continue;
            }
            if (in_array($clientId, $notifiedClients)) {
                continue;
            }
            $user = Db::name('user')->where('id', $userId)->where('is_del', 0)->find();
            if (!$user) {
                continue;
            }
            $notifiedClients[] = $clientId;

            // 查询应用的登出回调 URL
            $site = Db::name('site')->where('client_id', $clientId)->find();
            if ($site && !empty($site['logout_callback_url'])) {
                // 使用 Guzzle 发送登出通知
                try {
                    $client = new Client(['timeout' => 5, 'verify' => false]);

                    $body = [
                        'externalId' => $userId,
                        'code' => $user['username'],
                    ];
                    $response = $client->request('POST', $site['logout_callback_url'], [
                        'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                        'http_errors' => false,
                    ]);

                    // 扁平化响应头（Guzzle 返回数组值，取最后一个）
                    $respHeaders = [];
                    foreach ($response->getHeaders() as $name => $values) {
                        $respHeaders[$name] = end($values);
                    }

                    $responseInfo = [
                        'http status code' => $response->getStatusCode(),
                        'body' => json_decode((string)$response->getBody(), true),
                        'headers' => $respHeaders,
                    ];
                    
                    AuditLogService::log(
                        'user',
                        'session_logout',
                        'success',
                        "强制退出用户 {$user['username']} 的" . $site['name'] . '会话',
                        [
                            'url' => $site['logout_callback_url'],
                            'body' => $body
                        ],
                        $responseInfo,
                        'user',
                        $userId,
                        $user['username']
                    );
                    recordLog("登出通知成功: site={$site['name']}, user_id={$userId}", 'info');
                } catch (Exception $e) {
                    recordLog("登出通知失败: site={$site['name']}, error={$e->getMessage()}", 'error');
                }
            }
        }
    }

    /**
     * 第三方系统回调登出 — POST /oauth/logout_callback
     *
     * 第三方系统在用户退出时调用此接口，通知本系统撤销该用户在该应用的所有 Token。
     * 使用 HTTP Basic Auth 认证（client_id + client_secret）。
     *
     * @return Json
     */
    public function logoutCallback(): Json
    {
        try {
            // 验证 Basic Auth
            $site = request()->oauth_site ?? null;
            if (!$site) {
                return $this->oauthError('invalid_client', '客户端认证失败', 401);
            }

            $clientId = $site['client_id'];

            // 获取请求参数
            $userId = input('post.user_id/d');
            $externalId = input('post.external_id/s');

            if (!$userId && !$externalId) {
                return $this->oauthError('invalid_request', '缺少 user_id 或 external_id', 400);
            }

            // 根据 external_id 匹配用户
            if (!$userId && $externalId) {
                // 从 user 表的 external_ids JSON 字段中查找
                $users = Db::name('user')
                    ->where('is_del', 0)
                    ->select();

                foreach ($users as $u) {
                    if (!empty($u['external_ids'])) {
                        $externalIds = json_decode($u['external_ids'], true);
                        if (is_array($externalIds) && isset($externalIds[(string)$site['id']]) && $externalIds[(string)$site['id']] === $externalId) {
                            $userId = (int)$u['id'];
                            break;
                        }
                    }
                }

                if (!$userId) {
                    return $this->oauthError('invalid_grant', '找不到匹配的用户', 404);
                }
            }

            $result = [
                'access_tokens_revoked' => 0,
                'refresh_tokens_revoked' => 0,
                'auth_codes_deleted' => 0,
            ];

            Db::startTrans();

            // 撤销该用户在该应用的所有 access_token
            $result['access_tokens_revoked'] = Db::name('oauth_access_tokens')
                ->where('user_id', $userId)
                ->where('client_id', $clientId)
                ->where('revoked', 0)
                ->update(['revoked' => 1]);

            // 撤销 refresh_token
            $result['refresh_tokens_revoked'] = Db::name('oauth_refresh_tokens')
                ->where('user_id', $userId)
                ->where('client_id', $clientId)
                ->where('revoked', 0)
                ->update(['revoked' => 1]);

            // 删除授权码
            $result['auth_codes_deleted'] = Db::name('oauth_authorization_codes')
                ->where('user_id', $userId)
                ->where('client_id', $clientId)
                ->delete();

            Db::commit();

            recordLog("第三方登出回调: client_id={$clientId}, user_id={$userId}, revoked={$result['access_tokens_revoked']}", 'info');

            return json([
                'code' => 0,
                'success' => true,
                'message' => '登出成功',
                'result' => $result,
            ]);
        } catch (Exception $e) {
            Db::rollback();
            recordLog($e, 'error');
            return $this->oauthError('server_error', $e->getMessage(), 500);
        }
    }

    /**
     * 返回 OAuth 错误响应
     *
     * @param string $error 错误类型
     * @param string $description 错误描述
     * @param int $httpStatus HTTP 状态码
     * @return Json
     */
    private function oauthError(string $error, string $description, int $httpStatus = 400): Json
    {
        return json([
            'error' => $error,
            'error_description' => $description,
        ], $httpStatus);
    }
}
