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
        try {
            $username = input('post.username/s');
            $password = input('post.password/s');
            $email = input('post.email/s');
            $user = Db::name('user')->where('username', $username)->find();
            if ($user) {
                return sendJson("用户已经存在了", ResponseCode::$USER_EXISTS, ResponseMessage::$USER_EXISTS, false);
            }
            Db::name('user')->insert([
                'username' => $username,
                'password' => md5($password),
                'email' =>$email,
            ]);
            return sendJson("添加成功", ResponseCode::$ADD_USER_SUCCESS, ResponseMessage::$ADD_USER_SUCCESS);
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }
}
