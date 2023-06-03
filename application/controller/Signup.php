<?php

namespace app\controller;

use app\common\ResponseCode;
use app\common\ResponseMessage;
use think\Controller;
use think\Db;

class Signup extends Controller
{
    public function index()
    {
        return $this->fetch('/signup');
    }

    public function signup()
    {
        return action('User/doAdd', [input('post.username/s'), input('post.password/s'), input('post.email/s')]);
    }
}
