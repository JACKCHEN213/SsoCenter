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
        $token = $request->header('authorization');
        $payload = JWT::decode(
            $token,
            Key::getPublicKey(config('common.JWT_KEY_NAME')),
            array_keys(JWT::$supported_algs)
        );
        var_dump($payload);
        return $next($request);
    }
}
