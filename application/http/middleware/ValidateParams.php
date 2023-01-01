<?php

namespace app\http\middleware;

use app\common\ResponseCode;
use app\common\ResponseMessage;
use Closure;
use think\Request;

class ValidateParams
{
    public function handle(Request $request, Closure $next)
    {
        $validate_class = config('common.VALIDATE_NAMESPACE') . $request->controller();
        if (!class_exists($validate_class)) {
            return $next($request);
        }
        $validate = new $validate_class();
        // 验证操作
        if ($validate->scene($request->action())->batch()->check(getAllRequestParams())) {
            return $next($request);
        }
        $error = $validate->getError();
        if (is_array($error) && $error) {
            $error = implode("; ", $error);
        }
        return sendJson($error, ResponseCode::$VALIDATE_ERROR, ResponseMessage::$VALIDATE_ERROR);
    }
}
