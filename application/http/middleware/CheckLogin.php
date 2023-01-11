<?php

namespace app\http\middleware;

use app\common\Key;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use Closure;
use extra\JWT;
use think\Request;

class CheckLogin
{
    final public function handle(Request $request, Closure $next)
    {
        $logon_free = config('common.LOGON_FREE');

        // 验证免登录控制器
        $controller = strtolower($request->controller());
        if (isset($logon_free['controller']) && $logon_free['controller']) {
            if (in_array($controller, $logon_free['controller'])) {
                return $next($request);
            }
        }

        // 验证免登录路由
        $route = $controller . '/' . strtolower($request->action());
        $method = strtolower($request->method());
        if ($method == 'get') {
            return $next($request);
        }
        if (isset($logon_free['route']) && $logon_free['route']) {
            if (isset($logon_free['route'][$route]) && in_array($method, $logon_free['route'][$route])) {
                return $next($request);
            }
        }
        $token = $request->header('authorization');
        try {
            JWT::decode(
                $token,
                Key::getPublicKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME')),
                array_keys(JWT::$supported_algs)
            );
            return $next($request);
        } catch (\Throwable $e) {
            if ($method == 'get') {
                // return redirect('/login');
            }
            return sendJson($e->getMessage(), ResponseCode::$JWT_ERROR, ResponseMessage::$JWT_ERROR);
        }
    }
}
