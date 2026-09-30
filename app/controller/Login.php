<?php

namespace app\controller;

use app\common\Key;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use app\service\AuditLogService;
use extra\JWT;
use app\BaseController;
use think\facade\Db;
use think\facade\View;
use think\response\Json;

class Login extends BaseController
{
    public function index()
    {
        return View::fetch('/login');
    }

    private function getToken(array $jwt_data): string
    {
        $private_key = Key::getPrivateKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME'));
        return JWT::encode($jwt_data, $private_key, 'RS256');
    }

    /**
     * 密码验证：md5 → 私钥解密 → password_verify
     *
     * @param string $inputPassword 用户输入的原始密码
     * @param array $user 用户记录（含 password、jwt_private_path）
     * @return bool 验证是否通过
     */
    private function verifyPassword(string $inputPassword, array $user): bool
    {
        // 已迁移用户：使用私钥解密 + password_verify
        if (!empty($user['jwt_private_path'])) {
            $keyDir = app()->getRootPath() . config('common.USER_KEY_PATH');
            $privateKey = Key::getUserPrivateKey($keyDir, $user['username']);
            $encryptedHash = base64_decode($user['password']);
            if (!openssl_private_decrypt($encryptedHash, $decryptedHash, $privateKey)) {
                return false;
            }
            return password_verify(md5($inputPassword), $decryptedHash);
        }

        // 未迁移用户：兼容旧 md5 比对
        return md5($inputPassword) === $user['password'];
    }

    final public function login(): Json
    {
        try {
            $username = input('post.username/s');
            $password = input('post.password/s');
            $requestData = AuditLogService::maskPassword(['username' => $username, 'password' => $password]);
            $user = Db::name('user')->where('username', $username)->where('is_del', 0)->find();
            if (!$user) {
                AuditLogService::login($username, 'failure', '用户不存在', null, $requestData);
                return sendJson("用户不存在", ResponseCode::$USER_NOT_FOUND, ResponseMessage::$USER_NOT_FOUND, false);
            }
            if (!$this->verifyPassword($password, $user)) {
                AuditLogService::login($username, 'failure', '密码错误', null, $requestData);
                return sendJson("登录密码错误", ResponseCode::$WRONG_PASSWORD, ResponseMessage::$WRONG_PASSWORD, false);
            }
            $ip = request()->ip();
            $login_time = date('Y-m-d H:i:s');
            $userAgent = request()->header('user-agent', '');

            // 生成 jti 用于会话追踪
            $jti = \app\common\Uuid::uuid4();

            Db::name('login_history')->insert([
                'username' => $username,
                'ip' => $ip,
                'login_time' => $login_time,
            ]);
            Db::name('user')->where('id', $user['id'])->update([
                'ip' => $ip,
            ]);
            $token = $this->getToken([
                'jti' => $jti,
                'ip' => $ip,
                'login_time' => $login_time,
                'username' => $username,
                'id' => $user['id'],
                'type' => $user['type'],
            ]);

            // 插入会话记录
            $expiresAt = date('Y-m-d H:i:s', time() + 7200); // 与 JWT exp 对齐（2小时）
            Db::name('user_sessions')->insert([
                'user_id' => $user['id'],
                'username' => $username,
                'token_jti' => $jti,
                'ip' => $ip,
                'user_agent' => mb_substr($userAgent, 0, 500),
                'login_time' => $login_time,
                'last_active' => $login_time,
                'expires_at' => $expiresAt,
                'is_active' => 1,
            ]);

            AuditLogService::login($username, 'success', '登录成功', (int)$user['id'], $requestData);
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

    /**
     * 用户登出
     * 将当前会话标记为不活跃
     *
     * @return Json
     */
    public function logout(): Json
    {
        try {
            // 从 JWT 中获取 jti
            $token = request()->header('authorization');
            if (empty($token)) {
                return sendJson('未登录', ResponseCode::$JWT_ERROR, ResponseMessage::$JWT_ERROR, false);
            }

            $payload = $this->resolveToken($token);
            if ($payload === false) {
                return sendJson('Token 无效', ResponseCode::$JWT_ERROR, ResponseMessage::$JWT_ERROR, false);
            }

            $jti = $payload->jti ?? null;
            $userId = $payload->id ?? null;
            $username = $payload->username ?? null;

            if ($jti) {
                // 将会话标记为不活跃
                Db::name('user_sessions')->where('token_jti', $jti)->update(['is_active' => 0]);
            }

            // 记录登出日志
            if ($userId && $username) {
                AuditLogService::logout($username, (int)$userId);
            }

            return sendJson('登出成功');
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }
}
