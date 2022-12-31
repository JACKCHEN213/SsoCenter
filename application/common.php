<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

use think\response\Json;

function sendJson($code = 0, $message = 'Success', $result = []): Json
{
    return json([
        'code' => $code,
        'message' => $message,
        'result' => $result,
    ]);
}

function getAllRequestParams(): array
{
    $get_params = input('get.') ?: [];
    $post_params = input('post.') ?: [];
    $file_params = input('file.') ?: [];
    return array_merge($file_params, $post_params, $get_params);
}
