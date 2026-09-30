<?php
declare(strict_types=1);

namespace app\http\middleware;

use app\common\ResponseCode;
use app\common\ResponseMessage;
use app\service\SignatureService;
use think\facade\Db;
use think\Request;

/**
 * SM3 签名验证中间件
 * 用于 SCIM 接口的签名验证
 *
 * @package app\http\middleware
 * @author  SSO Center
 * @since   1.0.0
 */
class VerifySignature
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
            $signatureService = new SignatureService();

            // 从 Header 提取 appid
            $appid = $request->header('x-trust-appid', '');
            if (empty($appid)) {
                return $this->signatureError('缺少 X-trust-appid 请求头');
            }

            // 根据 appid 查询应用，获取 client_secret
            $site = Db::name('site')
                ->where('client_id', $appid)
                ->where('is_del', 0)
                ->where('is_use', 1)
                ->find();

            if (!$site) {
                return $this->signatureError('无效的应用标识');
            }

            if (empty($site['client_secret'])) {
                return $this->signatureError('应用未配置密钥');
            }

            // 验证签名
            if (!$signatureService->verify($request, $site['client_secret'])) {
                return $this->signatureError('签名验证失败');
            }

            // 将 appid（client_id）注入请求上下文
            $request->oauth_client_id = $appid;
            $request->oauth_site = $site;

            return $next($request);
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return $this->signatureError('签名验证异常: ' . $e->getMessage());
        }
    }

    /**
     * 返回签名错误响应
     *
     * @param string $message 错误描述
     * @return \think\response\Json
     */
    private function signatureError(string $message)
    {
        return json([
            'error' => 'invalid_signature',
            'error_description' => $message,
        ], 403);
    }
}
