<?php
declare(strict_types=1);

namespace app\service;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use think\facade\Db;

/**
 * 推送执行引擎（增强版）
 *
 * 负责将用户数据推送到第三方应用系统
 * 支持 SM3 签名、结构化参数、多规则验证、externalId 管理
 *
 * @package app\service
 * @author  SSO Center
 * @since   1.0.0
 */
class PushService
{
    /**
     * 推送单个用户到单个应用
     *
     * @param int $userId 用户 ID
     * @param int $siteId 应用 ID
     * @param string $action 操作类型：create / update / delete
     * @return PushResult
     */
    public function pushUser(int $userId, int $siteId, string $action = 'create'): PushResult
    {
        // 1. 查询推送接口配置
        $api = Db::name('site_push_api')
            ->where('site_id', $siteId)
            ->where('action', $action)
            ->where('is_enabled', 1)
            ->find();
        if (!$api) {
            return PushResult::notConfigured();
        }

        // 2. 查询用户数据
        $user = Db::name('user')->where('id', $userId)->where('is_del', 0)->find();
        if (!$user) {
            return PushResult::failed('用户不存在');
        }

        // 3. 获取 externalId（update/delete 时需要）
        $externalId = $this->getExternalId($userId, $siteId);

        // 4. URL 变量替换（包含 {{external_id}}）
        $url = $this->replaceVariables($api['url'], $user, $externalId);

        // 5. 构建请求体（create/update 时根据 body_params 结构化构建）
        $body = '';
        if (in_array($action, ['create', 'update'])) {
            $bodyParams = $this->decodeJsonField($api['body_params']);
            $body = $this->buildRequestBody($bodyParams, $user, $externalId);
        }

        // 6. 构建请求头：系统签名 + 自定义 Header
        // 通过 site_id 关联查询 sc_site 获取 client_id（作为 appid）和 client_secret（作为 appkey）
        $siteInfo = Db::name('site')
            ->where('id', $siteId)
            ->where('is_del', 0)
            ->field('client_id, client_secret')
            ->find();

        $appid = $siteInfo['client_id'] ?? '';
        $appkey = $siteInfo['client_secret'] ?? '';

        $headers = $this->buildHeaders($api, $body, parse_url($url, PHP_URL_PATH) ?: '', $appid, $appkey);

        // 构建用于日志的请求数据（脱敏 headers）
        $httpRequestData = [
            'url' => $url,
            'method' => $api['method'],
            'headers' => $this->maskHeaders($headers),
            'body' => $this->parseBodyForLog($body),
        ];

        // 7. 使用 Guzzle 发送请求
        $httpResponseData = null;
        try {
            $response = $this->sendRequest(
                $api['method'],
                $url,
                $headers,
                $body,
                (int)$api['timeout']
            );
            $httpResponseData = [
                'status_code' => $response['code'],
                'headers' => $response['headers'] ?? [],
                'body' => $this->parseBodyForLog($response['body']),
            ];
        } catch (Exception $e) {
            $pushLogId = $this->logPush($userId, $siteId, $action, $url, $api['method'], $this->maskHeaders($headers), $body, null, null, false, null, $e->getMessage());
            return PushResult::failed($e->getMessage(), null, $httpRequestData, $httpResponseData, $pushLogId);
        }

        // 8. 多规则响应验证（无规则时视为成功，适用于 delete 等无响应体场景）
        $rules = $this->decodeJsonField($api['response_rules']);
        $validation = empty($rules)
            ? ValidationResult::success()
            : $this->validateResponse($response['body'], $rules);

        // 9. 提取 externalId
        $respData = json_decode($response['body'], true);
        $newExternalId = null;
        if (is_array($respData) && !empty($respData['externalId'])) {
            $newExternalId = (string)$respData['externalId'];
        }

        // 10. 保存 externalId（创建成功时）
        if ($validation->isSuccess && $newExternalId && $action === 'create') {
            $this->saveExternalId($userId, $siteId, $newExternalId);
        }

        // 11. 记录日志
        $pushLogId = $this->logPush(
            $userId, $siteId, $action, $url, $api['method'],
            $this->maskHeaders($headers), $body,
            $response['code'], $response['body'],
            $validation->isSuccess, $newExternalId, $validation->failedRule
        );

        if ($validation->isSuccess) {
            return PushResult::success($newExternalId, $httpRequestData, $httpResponseData, $pushLogId);
        } else {
            $errorMsg = $validation->failedRule ? '响应验证失败: ' . $validation->failedRule : '响应验证失败';
            return PushResult::failed($errorMsg, $validation->failedRule, $httpRequestData, $httpResponseData, $pushLogId);
        }
    }

    /**
     * 推送用户到所有已配置的应用
     *
     * @param int $userId 用户 ID
     * @return array [['site_id' => int, 'site_name' => string, 'result' => PushResult], ...]
     */
    public function pushUserToAll(int $userId): array
    {
        $siteIds = Db::name('site_push_api')
            ->where('action', 'create')
            ->where('is_enabled', 1)
            ->group('site_id')
            ->column('site_id');

        if (empty($siteIds)) {
            return [];
        }

        $sites = Db::name('site')
            ->where('id', 'in', $siteIds)
            ->where('is_del', 0)
            ->column('name', 'id');

        $results = [];
        foreach ($siteIds as $siteId) {
            $result = $this->pushUser($userId, (int)$siteId, 'create');
            $results[] = [
                'site_id' => (int)$siteId,
                'site_name' => $sites[$siteId] ?? '',
                'result' => $result,
            ];
        }
        return $results;
    }

    /**
     * 根据 body_params 结构化构建请求体
     *
     * @param array $bodyParams 参数映射数组 [{name, value, type, required}, ...]
     * @param array $user 用户数据
     * @param string|null $externalId 外部 ID
     * @return string JSON 字符串
     */
    public function buildRequestBody(array $bodyParams, array $user, ?string $externalId = null): string
    {
        $body = [];
        foreach ($bodyParams as $param) {
            if (empty($param['name'])) {
                continue;
            }
            $rawValue = $this->replaceVariable($param['value'] ?? '', $user, $externalId);
            $type = $param['type'] ?? 'string';

            // 按类型转换
            switch ($type) {
                case 'boolean':
                case 'bool':
                    $body[$param['name']] = $this->toBool($rawValue);
                    break;
                case 'int':
                case 'integer':
                    $body[$param['name']] = (int)$rawValue;
                    break;
                default:
                    $body[$param['name']] = (string)$rawValue;
                    break;
            }
        }
        return json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 构建请求头：系统签名 Header + extra_headers 合并
     *
     * @param array $api 推送接口配置
     * @param string $body 请求体
     * @param string $urlPath URL 路径
     * @param string $appid 第三方 appid
     * @param string $appkey 第三方 appkey（明文）
     * @return array 合并后的请求头
     */
    public function buildHeaders(array $api, string $body, string $urlPath, string $appid, string $appkey): array
    {
        $method = strtoupper($api['method'] ?? 'POST');

        // 系统签名 Header
        $signatureService = new SignatureService();
        $headers = [];

        if (!empty($appid) && !empty($appkey)) {
            $headers = $signatureService->sign($body, $urlPath, $method, $appid, $appkey);
        }

        // 合并 extra_headers 自定义请求头
        $extraHeaders = $this->decodeJsonField($api['extra_headers'] ?? null);
        foreach ($extraHeaders as $header) {
            if (!empty($header['name'])) {
                $headers[$header['name']] = $header['value'] ?? '';
            }
        }

        // 固定附加 x-api-version（参照文档要求）
        if (!isset($headers['X-api-version']) && !isset($headers['x-api-version'])) {
            $headers['X-api-version'] = '1.0-rev0';
        }

        return $headers;
    }

    /**
     * 获取用户在某应用的 externalId
     *
     * @param int $userId 用户 ID
     * @param int $siteId 应用 ID
     * @return string|null externalId
     */
    public function getExternalId(int $userId, int $siteId): ?string
    {
        $externalIdsJson = Db::name('user')->where('id', $userId)->value('external_ids');
        if (empty($externalIdsJson)) {
            return null;
        }
        $externalIds = is_string($externalIdsJson) ? json_decode($externalIdsJson, true) : $externalIdsJson;
        if (!is_array($externalIds)) {
            return null;
        }
        return $externalIds[(string)$siteId] ?? null;
    }

    /**
     * 保存用户在某应用的 externalId
     *
     * @param int $userId 用户 ID
     * @param int $siteId 应用 ID
     * @param string $externalId 外部 ID
     */
    public function saveExternalId(int $userId, int $siteId, string $externalId): void
    {
        try {
            $externalIdsJson = Db::name('user')->where('id', $userId)->value('external_ids');
            $externalIds = [];
            if (!empty($externalIdsJson)) {
                $externalIds = is_string($externalIdsJson) ? (json_decode($externalIdsJson, true) ?: []) : $externalIdsJson;
            }
            $externalIds[(string)$siteId] = $externalId;
            Db::name('user')->where('id', $userId)->update([
                'external_ids' => json_encode($externalIds, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Exception $e) {
            recordLog("保存 externalId 失败: {$e->getMessage()}", 'error');
        }
    }

    /**
     * 根据 response_rules 多规则验证响应
     *
     * @param string $responseBody 响应体
     * @param array $rules 验证规则数组 [{field, type, value}, ...]
     * @return ValidationResult
     */
    public function validateResponse(string $responseBody, array $rules): ValidationResult
    {
        if (empty($responseBody)) {
            return ValidationResult::failed('响应体为空');
        }

        $data = json_decode($responseBody, true);
        if ($data === null) {
            // 非 JSON 响应，尝试直接比较
            if (count($rules) === 1 && ($rules[0]['type'] ?? '') === 'string') {
                $actual = trim($responseBody);
                $expected = (string)($rules[0]['value'] ?? '');
                if ($actual === $expected) {
                    return ValidationResult::success();
                }
                return ValidationResult::failed("{$rules[0]['field']}: 期望 {$expected}(string), 实际 {$actual}");
            }
            return ValidationResult::failed('响应不是合法 JSON');
        }

        foreach ($rules as $rule) {
            $field = $rule['field'] ?? '';
            $type = $rule['type'] ?? 'string';
            $expected = $rule['value'] ?? null;

            if ($field === '') {
                continue;
            }

            $actual = $this->getNestedValue($data, $field);

            $matched = $this->matchValue($actual, $expected, $type);
            if (!$matched) {
                $actualStr = $this->formatValue($actual, $type);
                $expectedStr = $this->formatValue($expected, $type);
                return ValidationResult::failed("{$field}: 期望 {$expectedStr}({$type}), 实际 {$actualStr}");
            }
        }

        return ValidationResult::success();
    }

    /**
     * 发送 HTTP 请求（使用 Guzzle 替代 cURL）
     *
     * @param string $method HTTP 方法
     * @param string $url 请求 URL
     * @param array $headers 请求头
     * @param string $body 请求体
     * @param int $timeout 超时秒数
     * @return array ['code' => int, 'body' => string, 'headers' => array]
     * @throws Exception
     */
    private function sendRequest(string $method, string $url, array $headers, string $body, int $timeout): array
    {
        $client = new Client([
            'timeout' => $timeout,
            'verify' => false,
        ]);

        try {
            $options = [
                'headers' => $headers,
                'http_errors' => false,
            ];
            if (!empty($body) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
                $options['body'] = $body;
            }

            $response = $client->request($method, $url, $options);

            // 扁平化响应头（Guzzle 返回数组值，取最后一个）
            $respHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $respHeaders[$name] = end($values);
            }

            return [
                'code' => $response->getStatusCode(),
                'body' => (string)$response->getBody(),
                'headers' => $respHeaders,
            ];
        } catch (ConnectException $e) {
            throw new Exception('连接失败: ' . $e->getMessage());
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $respHeaders = [];
                foreach ($e->getResponse()->getHeaders() as $name => $values) {
                    $respHeaders[$name] = end($values);
                }
                return [
                    'code' => $e->getResponse()->getStatusCode(),
                    'body' => (string)$e->getResponse()->getBody(),
                    'headers' => $respHeaders,
                ];
            }
            throw new Exception('请求失败: ' . $e->getMessage());
        }
    }

    /**
     * 记录推送日志
     *
     * @return int 插入的日志 ID
     */
    private function logPush(
        int $userId,
        int $siteId,
        string $action,
        string $url,
        ?string $method,
        ?array $requestHeaders,
        ?string $requestBody,
        ?int $responseCode,
        ?string $responseBody,
        bool $isSuccess,
        ?string $externalId,
        ?string $failedRule
    ): int {
        try {
            return (int)Db::name('user_push_log')->insertGetId([
                'user_id' => $userId,
                'site_id' => $siteId,
                'action' => $action,
                'url' => $url,
                'method' => $method ?? 'POST',
                'request_headers' => $requestHeaders ? json_encode($requestHeaders, JSON_UNESCAPED_UNICODE) : null,
                'request_body' => $requestBody,
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'external_id' => $externalId,
                'is_success' => $isSuccess ? 1 : 0,
                'failed_rule' => $failedRule ? mb_substr($failedRule, 0, 256) : null,
            ]);
        } catch (Exception $e) {
            recordLog("推送日志写入失败: {$e->getMessage()}", 'error');
            return 0;
        }
    }

    /**
     * 解析请求/响应体用于日志记录（尝试 JSON 解码，失败则保留字符串）
     *
     * @param string|null $body
     * @return mixed
     */
    private function parseBodyForLog(?string $body)
    {
        if (empty($body)) {
            return null;
        }
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        return $body;
    }

    /**
     * 替换字符串中的 {{变量}} 占位符
     *
     * @param string $str 含占位符的字符串
     * @param array $user 用户数据
     * @param string|null $externalId 外部 ID
     * @return string
     */
    public function replaceVariables(string $str, array $user, ?string $externalId = null): string
    {
        // 单值替换
        $map = [
            '{{username}}' => $user['username'] ?? '',
            '{{email}}' => $user['email'] ?? '',
            '{{password}}' => $user['password'] ?? '',
            '{{user_id}}' => (string)($user['id'] ?? ''),
            '{{status}}' => (string)(($user['is_del'] ?? 0) == 0 ? 1 : 0),
            '{{status_bool}}' => (($user['is_del'] ?? 0) == 0 ? 'true' : 'false'),
            '{{ip}}' => $user['last_login_ip'] ?? ($user['ip'] ?? ''),
            '{{external_id}}' => $externalId ?? '',
        ];
        return str_replace(array_keys($map), array_values($map), $str);
    }

    /**
     * 替换单个变量值
     */
    private function replaceVariable(string $value, array $user, ?string $externalId): string
    {
        return $this->replaceVariables($value, $user, $externalId);
    }

    /**
     * 解码 JSON 字段
     */
    private function decodeJsonField($value): array
    {
        if (empty($value)) {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }

    /**
     * 获取嵌套字段值（支持点号分隔）
     *
     * @param array $data
     * @param string $field 如 "data.code"
     * @return mixed
     */
    private function getNestedValue(array $data, string $field)
    {
        $keys = explode('.', $field);
        $current = $data;
        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }

    /**
     * 按类型匹配值
     */
    private function matchValue($actual, $expected, string $type): bool
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
                return $this->toBool($actual) === $this->toBool($expected);
            case 'int':
            case 'integer':
                return (int)$actual === (int)$expected;
            case 'string':
            default:
                return (string)$actual === (string)$expected;
        }
    }

    /**
     * 转换为布尔值
     */
    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes'], true);
        }
        return (bool)$value;
    }

    /**
     * 格式化值用于日志展示
     */
    private function formatValue($value, string $type): string
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
                return $this->toBool($value) ? 'true' : 'false';
            case 'int':
            case 'integer':
                return (string)(int)$value;
            default:
                return (string)$value;
        }
    }

    /**
     * 脱敏请求头（不记录敏感签名信息）
     */
    private function maskHeaders(array $headers): array
    {
        $masked = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if ($lowerKey === 'x-trust-signature') {
                $masked[$key] = '***';
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }
}
