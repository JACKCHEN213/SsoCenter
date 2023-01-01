<?php

namespace app\http\middleware;

use app\common\ResponseCode;
use app\common\ResponseMessage;
use think\Request;

class CatchException
{
    public function handle(Request $request, \Closure $next)
    {
        try {
            return $next($request);
        } catch (\Throwable $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR);
        }
    }
}
