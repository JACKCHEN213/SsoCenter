<?php

namespace app\controller;

use app\common\ResponseCode;
use app\common\ResponseMessage;
use app\BaseController;
use think\facade\View;

class Signup extends BaseController
{
    public function index()
    {
        return View::fetch('/signup');
    }

    public function signup()
    {
        return action('User/doAdd', [input('post.username/s'), input('post.password/s'), input('post.email/s')]);
    }
}
