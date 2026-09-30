<?php
declare(strict_types=1);

namespace app\service;

use app\common\Key;
use extra\JWT;
use Exception;
use think\facade\Db;

/**
 * 操作审计日志服务
 *
 * 提供统一的日志写入接口，自动获取操作者信息、IP、User-Agent
 * 支持敏感数据脱敏
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class AuditLogService
{
    /**
     * 记录操作日志（通用方法）
     *
     * @param string $module 操作模块：auth / app / user
     * @param string $action 操作动作：login / app_create / user_push 等
     * @param string $result 操作结果：success / failure
     * @param string|null $resultMessage 结果描述
     * @param array|null $requestData 请求数据
     * @param array|null $responseData 响应数据
     * @param string|null $targetType 操作对象类型：app / user / system
     * @param int|null $targetId 操作对象 ID
     * @param string|null $targetName 操作对象名称
     * @param string|null $relatedType 关联记录类型（如 push_log）
     * @param int|null $relatedId 关联记录 ID
     * @return int 日志 ID
     */
    public static function log(
        string $module,
        string $action,
        string $result,
        ?string $resultMessage = null,
        ?array $requestData = null,
        ?array $responseData = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $targetName = null,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): int {
        try {
            $operator = self::getOperator();
            $request = request();

            return (int)Db::name('operation_log')->insertGetId([
                'operator_id' => $operator['id'],
                'operator_name' => $operator['name'],
                'module' => $module,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_name' => $targetName,
                'result' => $result,
                'result_message' => $resultMessage ? mb_substr($resultMessage, 0, 500) : null,
                'request_data' => $requestData ? json_encode($requestData, JSON_UNESCAPED_UNICODE) : null,
                'response_data' => $responseData ? json_encode($responseData, JSON_UNESCAPED_UNICODE) : null,
                'ip' => $request->ip(),
                'user_agent' => mb_substr($request->header('user-agent', ''), 0, 500),
                'related_type' => $relatedType,
                'related_id' => $relatedId,
            ]);
        } catch (Exception $e) {
            recordLog("操作日志写入失败: {$e->getMessage()}", 'error');
            return 0;
        }
    }

    /**
     * 快捷方法：记录登录日志
     *
     * @param string $username 用户名
     * @param string $result 结果：success / failure
     * @param string|null $message 结果描述
     * @param int|null $userId 用户 ID（登录成功时传入）
     * @param array|null $requestData 请求数据（密码已脱敏）
     * @return int 日志 ID
     */
    public static function login(
        string $username,
        string $result,
        ?string $message = null,
        ?int $userId = null,
        ?array $requestData = null
    ): int {
        // 登录失败时，operator_id 为 NULL，但 operator_name 记录输入的用户名
        if ($result === 'failure') {
            return self::logWithOperator(
                null,
                $username,
                'auth',
                'login',
                $result,
                $message,
                $requestData,
                null,
                'user',
                $userId,
                $username
            );
        }

        return self::logWithOperator(
            $userId,
            $username,
            'auth',
            'login',
            $result,
            $message,
            $requestData,
            null,
            'user',
            $userId,
            $username
        );
    }

    /**
     * 快捷方法：记录登出日志
     *
     * @param string $username 用户名
     * @param int $userId 用户 ID
     * @return int 日志 ID
     */
    public static function logout(string $username, int $userId): int
    {
        return self::log(
            'auth',
            'logout',
            'success',
            '用户登出',
            null,
            null,
            'user',
            $userId,
            $username
        );
    }

    /**
     * 快捷方法：记录应用操作日志
     *
     * @param string $action 操作动作：app_create / app_update / app_delete / app_reset_secret / app_view_secret
     * @param string $result 结果：success / failure
     * @param string|null $message 结果描述
     * @param array|null $requestData 请求数据
     * @param array|null $responseData 响应数据
     * @param int|null $appId 应用 ID
     * @param string|null $appName 应用名称
     * @return int 日志 ID
     */
    public static function app(
        string $action,
        string $result,
        ?string $message = null,
        ?array $requestData = null,
        ?array $responseData = null,
        ?int $appId = null,
        ?string $appName = null
    ): int {
        return self::log(
            'app',
            $action,
            $result,
            $message,
            $requestData,
            $responseData,
            'app',
            $appId,
            $appName
        );
    }

    /**
     * 快捷方法：记录用户操作日志
     *
     * @param string $action 操作动作：user_create / user_update / user_delete / user_push / user_push_all
     * @param string $result 结果：success / failure
     * @param string|null $message 结果描述
     * @param array|null $requestData 请求数据
     * @param array|null $responseData 响应数据
     * @param int|null $userId 用户 ID
     * @param string|null $userName 用户名
     * @param string|null $relatedType 关联记录类型（如 push_log）
     * @param int|null $relatedId 关联记录 ID
     * @return int 日志 ID
     */
    public static function user(
        string $action,
        string $result,
        ?string $message = null,
        ?array $requestData = null,
        ?array $responseData = null,
        ?int $userId = null,
        ?string $userName = null,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): int {
        return self::log(
            'user',
            $action,
            $result,
            $message,
            $requestData,
            $responseData,
            'user',
            $userId,
            $userName,
            $relatedType,
            $relatedId
        );
    }

    /**
     * 使用指定的操作者信息记录日志（用于登录场景，operator 与 JWT 不一致）
     *
     * @param int|null $operatorId 操作者 ID
     * @param string|null $operatorName 操作者名称
     * @param string $module 模块
     * @param string $action 动作
     * @param string $result 结果
     * @param string|null $resultMessage 结果描述
     * @param array|null $requestData 请求数据
     * @param array|null $responseData 响应数据
     * @param string|null $targetType 目标类型
     * @param int|null $targetId 目标 ID
     * @param string|null $targetName 目标名称
     * @return int 日志 ID
     */
    private static function logWithOperator(
        ?int $operatorId,
        ?string $operatorName,
        string $module,
        string $action,
        string $result,
        ?string $resultMessage = null,
        ?array $requestData = null,
        ?array $responseData = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $targetName = null
    ): int {
        try {
            $request = request();

            return (int)Db::name('operation_log')->insertGetId([
                'operator_id' => $operatorId,
                'operator_name' => $operatorName,
                'module' => $module,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_name' => $targetName,
                'result' => $result,
                'result_message' => $resultMessage ? mb_substr($resultMessage, 0, 500) : null,
                'request_data' => $requestData ? json_encode($requestData, JSON_UNESCAPED_UNICODE) : null,
                'response_data' => $responseData ? json_encode($responseData, JSON_UNESCAPED_UNICODE) : null,
                'ip' => $request->ip(),
                'user_agent' => mb_substr($request->header('user-agent', ''), 0, 500),
            ]);
        } catch (Exception $e) {
            recordLog("操作日志写入失败: {$e->getMessage()}", 'error');
            return 0;
        }
    }

    /**
     * 获取当前操作者信息（从 JWT Token 中解析）
     *
     * @return array ['id' => int|null, 'name' => string|null]
     */
    public static function getOperator(): array
    {
        $token = request()->header('authorization');
        if (empty($token)) {
            return ['id' => null, 'name' => null];
        }

        $candidates = [$token];
        $decoded = base64_decode($token, true);
        if ($decoded !== false && $decoded !== $token) {
            $candidates[] = $decoded;
        }

        $publicKey = Key::getPublicKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME'));
        foreach ($candidates as $candidate) {
            try {
                $payload = JWT::decode($candidate, $publicKey, array_keys(JWT::$supported_algs));
                $userId = (int)($payload->id ?? 0);
                $username = $payload->username ?? null;
                if ($userId > 0) {
                    return ['id' => $userId, 'name' => $username];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return ['id' => null, 'name' => null];
    }

    /**
     * 脱敏密码字段
     *
     * @param array $data 请求数据
     * @return array 脱敏后的数据
     */
    public static function maskPassword(array $data): array
    {
        if (isset($data['password'])) {
            $data['password'] = '***';
        }
        return $data;
    }

    /**
     * 脱敏请求头中的签名信息
     *
     * @param array $headers 请求头
     * @return array 脱敏后的请求头
     */
    public static function maskHeaders(array $headers): array
    {
        $masked = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if ($lowerKey === 'x-trust-signature') {
                $masked[$key] = '****';
            } elseif ($lowerKey === 'appkey' || $lowerKey === 'x-trust-appkey') {
                // appkey 不记录
                continue;
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }
}
