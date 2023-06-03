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
        $private_key = Key::getPrivateKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME'));
        return JWT::encode($jwt_data, $private_key, 'RS256');
    }

    final public function login(): Json
    {
        try {
            $username = input('post.username/s');
            $password = input('post.password/s');
            $user = Db::name('user')->where('username', $username)->find();
            if (!$user) {
                return sendJson("用户不存在", ResponseCode::$USER_NOT_FOUND, ResponseMessage::$USER_NOT_FOUND, false);
            }
            if (md5($password) != $user['password']) {
                return sendJson("登录密码错误", ResponseCode::$WRONG_PASSWORD, ResponseMessage::$WRONG_PASSWORD, false);
            }
            $ip = request()->ip();
            $login_time = date('Y-m-d H:i:s');
            Db::name('login_history')->insert([
                'username' => $username,
                'ip' => $ip,
                'login_time' => $login_time,
            ]);
            Db::name('user')->where('id', $user['id'])->update([
                'ip' => $ip,
            ]);
            $token = $this->getToken([
                'ip' => $ip,
                'login_time' => $login_time,
                'username' => $username,
                'id' => $user['id'],
                'type' => $user['type'],
            ]);
            return sendJson($token);
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }

    private function resolveToken(string $token)
    {
        // 获取公钥
        $publicKey = Key::getPublicKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME'));
        // 解析token
        try {
            return JWT::decode($token, $publicKey, ['HS256', 'RS256']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function checkAuthorization(string $authorization)
    {
        try {
            $userData = $this->resolveToken($authorization);
            if ($userData === false) {
                return sendJson('token错误', ResponseCode::$JWT_ERROR, ResponseMessage::$JWT_ERROR, false);
            }
            if (!Db::name('user')->find($userData->id)) {
                return sendJson(
                    '用户' . $userData->username . '不存在',
                    ResponseCode::$JWT_ERROR,
                    ResponseMessage::$JWT_ERROR,
                    false
                );
            }
            return sendJson([
                'username' => $userData->username,
                'id' => base64_encode($userData->id),
                'type' => $userData->type,
            ], ResponseCode::$JWT_SUCCESS, ResponseMessage::$JWT_SUCCESS);
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }

    public function verify()
    {
        return $this->checkAuthorization(input('token/s'));
    }
}
