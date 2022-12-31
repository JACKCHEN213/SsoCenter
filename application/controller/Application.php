<?php

namespace app\controller;

use think\Controller;
use think\facade\Request;

class Application extends Controller
{
    public function add()
    {
        //
    }

    public function uploadImage()
    {
        $image = Request::file('file_data');
        # TODO
        return sendJson();
    }
}
