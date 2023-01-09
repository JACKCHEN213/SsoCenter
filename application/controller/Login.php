<?php

namespace app\controller;

use app\common\Key;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use extra\JWT;
use think\Controller;
use think\Db;
use think\response\Json;

class Login extends Controller
{
    public function index()
    {
        return $this->fetch('/login');
    }

    private function getToken(array $jwt_data): string
    {
        $private_key = Key::getPrivateKey(config('common.JWT_KEY_NAME'));
        return JWT::encode($jwt_data, $private_key, 'RS256');
    }

    final public function login(): Json
    {
        try {
            $username = input('post.username/s');
            $password = input('post.password/s');
            $user = Db::name('user')->where('username', $username)->find();
            if (!$user) {
                return sendJson("用户不存在", ResponseCode::$USER_NOT_FOUND, ResponseMessage::$USER_NOT_FOUND);
            }
            if (md5($password) != $user['password']) {
                return sendJson("登录密码错误", ResponseCode::$WRONG_PASSWORD, ResponseMessage::$WRONG_PASSWORD);
            }
            $ip = request()->ip();
            $login_time = date('Y-m-d H:i:s');
            DB::name('login_history')->insert([
                'username' => $username,
                'ip' => $ip,
                'login_time' => $login_time,
            ]);
            $token = $this->getToken([
                'ip' => $ip,
                'login_time' => $login_time,
                'username' => $username,
                'id' => $user['id'],
            ]);
            return sendJson($token);
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR);
        }
    }
}
