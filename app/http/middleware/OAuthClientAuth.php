<?php
declare(strict_types=1);

namespace app\http\middleware;

use think\facade\Db;
use think\Request;

/**
 * OAuth 客户端认证中间件
 * 解析 Basic Auth Header，校验 client_id/client_secret
 *
 * @package app\http\middleware
 * @author  SSO Center
 * @since   1.0.0
 */
class OAuthClientAuth
{
    /**
     * 处理请求
     *
     * @param Request $request 请求对象
     * @param \Closure $next 下一个中间件
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        try {
            // 解析 Basic Auth Header
            $authHeader = $request->header('authorization', '');

            if (empty($authHeader)) {
                return $this->clientError('缺少 Authorization 请求头');
            }

            // 检查是否为 Basic 认证
            if (!str_starts_with($authHeader, 'Basic ')) {
                return $this->clientError('不支持的认证方式，需要 Basic Auth');
            }

            // 解析 base64 编码的 client_id:client_secret
            $encoded = substr($authHeader, 6);
            $decoded = base64_decode($encoded, true);

            if ($decoded === false) {
                return $this->clientError('无效的 Base64 编码');
            }

            $parts = explode(':', $decoded, 2);
            if (count($parts) !== 2) {
                return $this->clientError('无效的认证格式');
            }

            [$clientId, $clientSecret] = $parts;

            // 查询应用
            $site = Db::name('site')
                ->where('client_id', $clientId)
                ->where('is_del', 0)
                ->where('is_use', 1)
                ->find();

            if (!$site) {
                return $this->clientError('无效的 client_id');
            }

            // 验证 client_secret
            if ($site['client_secret'] !== $clientSecret) {
                return $this->clientError('client_secret 不匹配');
            }

            // 将认证后的应用信息注入请求上下文
            $request->oauth_client_id = $clientId;
            $request->oauth_site = $site;

            return $next($request);
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return $this->clientError('客户端认证异常: ' . $e->getMessage());
        }
    }

    /**
     * 返回客户端认证错误响应
     *
     * @param string $message 错误描述
     * @return \think\response\Json
     */
    private function clientError(string $message)
    {
        return json([
            'error' => 'invalid_client',
            'error_description' => $message,
        ], 401);
    }
}
