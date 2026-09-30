<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\TokenService;
use Exception;
use think\facade\Db;
use think\response\Json;
use think\response\Redirect;

/**
 * OAuth 回调与登出通知控制器
 *
 * @package app\controller
 * @author  SSO Center
 * @since   1.0.0
 */
class OAuthCallback extends BaseController
{
    protected TokenService $tokenService;

    protected function initialize()
    {
        $this->tokenService = new TokenService();
    }

    /**
     * 登出通知 — POST /oauth/logout
     * 携带 Bearer Token 注销用户会话，撤销 token
     *
     * @return Json
     */
    public function logout(): Json
    {
        try {
            // T6.2.1 验证 Bearer Token
            $authHeader = $this->request->header('authorization', '');
            if (!str_starts_with($authHeader, 'Bearer ')) {
                return $this->callbackError('invalid_token', '缺少 Bearer Token', 401);
            }

            $accessToken = substr($authHeader, 7);
            $payload = $this->tokenService->verifyAccessToken($accessToken);
            if ($payload === false) {
                return $this->callbackError('invalid_token', 'Token 无效或已过期', 401);
            }

            // T6.2.2 撤销 token 并注销用户会话
            $this->tokenService->revokeAccessToken($accessToken);

            // 撤销与该 access_token 关联的 refresh_token
            $tokenHash = $this->tokenService->hashAccessToken($accessToken);
            Db::name('oauth_refresh_tokens')
                ->where('access_token_id', $tokenHash)
                ->update(['revoked' => 1]);

            recordLog(sprintf(
                'OAuthCallback logout: client_id=%s, user_id=%s',
                (string)($payload->client_id ?? ''),
                (string)($payload->sub ?? '')
            ), 'info');

            return json([
                'message' => 'succes',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return $this->callbackError('server_error', $e->getMessage(), 500);
        }
    }

    /**
     * 认证回调 — GET /oauth/callback
     * 接收授权码和 state，重定向到前端页面继续处理
     *
     * @return Redirect|Json
     */
    public function callback()
    {
        try {
            // T6.3.1 接收授权码和 state
            $code = input('get.code/s', '');
            $state = input('get.state/s', '');
            $error = input('get.error/s', '');
            $errorDescription = input('get.error_description/s', '');

            if ($error !== '') {
                return $this->callbackError($error, $errorDescription ?: '授权失败', 400);
            }

            if ($code === '') {
                return $this->callbackError('invalid_request', '缺少 code 参数', 400);
            }

            // T6.3.2 处理回调逻辑（重定向到前端继续处理）
            $redirectUrl = '/login/?code=' . urlencode($code) . '&state=' . urlencode($state);
            return redirect($redirectUrl);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return $this->callbackError('server_error', $e->getMessage(), 500);
        }
    }

    /**
     * 返回错误响应
     *
     * @param string $error 错误类型
     * @param string $description 错误描述
     * @param int $httpStatus HTTP 状态码
     * @return Json
     */
    private function callbackError(string $error, string $description, int $httpStatus): Json
    {
        return json([
            'error' => $error,
            'error_description' => $description,
        ], $httpStatus);
    }
}
