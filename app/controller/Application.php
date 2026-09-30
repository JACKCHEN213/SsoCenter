<?php

namespace app\controller;

use app\common\Key;
use app\common\Uuid;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use app\service\AuditLogService;
use app\service\AuthorizationCodeService;
use app\service\TokenService;
use Exception;
use app\BaseController;
use extra\JWT;
use think\facade\Db;
use think\response\Json;

class Application extends BaseController
{
    private function generatePublicKey(int $site_id): void
    {
        $public_key_path = Db::name('site')->where('id', $site_id)->value('public_key');
        if (is_file(config('common.APP_KEY_PATH') . '/' . $public_key_path)) {
            return;
        }
        // 生成秘钥
        $filename = md5($site_id) . '.pem';
        $public_key = Key::getPublicKey(config('common.JWT_KEY_PATH'), config('common.JWT_KEY_NAME'));
        if (!is_dir(config('common.APP_KEY_PATH'))) {
            mkdir(config('common.APP_KEY_PATH'), 0777, true);
        }
        file_put_contents(config('common.APP_KEY_PATH') . '/' . $filename, $public_key);
        Db::name('site')->where('id', $site_id)->update([
            'public_key' => $filename,
        ]);
    }

    /**
     * 生成 OAuth 2.0 客户端凭证（client_id UUID4 36 位、client_secret 64 位）
     *
     * @return array ['client_id' => string, 'client_secret' => string]
     */
    private function generateClientCredentials(): array
    {
        do {
            $client_id = Uuid::uuid4();
            $exists = Db::name('site')->where('client_id', $client_id)->value('id');
        } while ($exists);

        return [
            'client_id' => $client_id,
            'client_secret' => bin2hex(random_bytes(32)),
        ];
    }

    final public function add(): Json
    {
        $add_data = [
            'name' => input('post.app_name'),
            'request_url' => input('post.app_request_url'),
            'redirect_url' => input('post.app_redirect_url'),
            'logout_callback_url' => input('post.app_logout_callback_url', '') ?: null,
            'image' => input('post.app_img_url', '') ?: null,
        ];
        // T2.1 接收 OAuth 配置参数（授权模式、scope、各 token 有效期）
        $add_data = array_merge($add_data, $this->extractOauthConfig());
        try {
            $site_id = Db::name('site')
                ->where('is_del', 0)
                ->where('name', $add_data['name'])
                ->where('request_url', $add_data['request_url'])
                ->where('redirect_url', $add_data['redirect_url'])
                ->field('id, client_id, client_secret')
                ->find();
            if ($site_id) {
                return sendJson($site_id, ResponseCode::$OK, ResponseMessage::$DATA_ALREADY_EXIST);
            }
            Db::startTrans();
            $site_id = Db::name('site')->insertGetId($add_data);
            $this->generatePublicKey($site_id);
            // T7.1 注册应用时自动生成 client_id/client_secret
            $credentials = $this->generateClientCredentials();
            Db::name('site')->where('id', $site_id)->update($credentials);
            // T1.2 保存推送接口配置
            $this->savePushApis($site_id);
            Db::commit();

            // 记录操作日志
            AuditLogService::app(
                'app_create',
                'success',
                '创建应用成功',
                [
                    'app_name' => $add_data['name'],
                    'request_url' => $add_data['request_url'],
                    'redirect_url' => $add_data['redirect_url'],
                    'allowed_grant_types' => $add_data['allowed_grant_types'] ?? null,
                ],
                ['client_id' => $credentials['client_id']],
                $site_id,
                $add_data['name']
            );

            // 返回值中包含应用 ID 及 OAuth 凭证
            return sendJson([
                'id' => $site_id,
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
            ]);
        } catch (Exception $e) {
            Db::rollback();
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR);
        }
    }

    final public function uploadImage(): Json
    {
        $image = acceptFile('file_data');
        $ext = $image->getOriginalExtension();
        $file = $image->move(config('common.APP_IMAGE_PREFIX'), 'app_image_' . time() . '.' . $ext);
        if (!$file) {
            return sendJson($file->getError(), ResponseCode::$FILE_UPLOAD_FAILED, ResponseMessage::$FILE_UPLOAD_FAILED);
        }
        return sendJson(config('common.APP_IMAGE_PREFIX') . $file->getFilename());
    }

    final public function deleteUploadedImage(): Json
    {
        $image_path = input('delete.image_url');
        if (is_file($image_path)) {
            unlink($image_path);
        }
        $appId = input('delete.app_id');
        if ($appId) {
            try {
                Db::startTrans();
                Db::name('site')->where('id', $appId)->update([
                    'image' => '',
                ]);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                recordLog($e, 'error');
            }
        }
        return sendJson('删除成功');
    }

    final public function update(): Json
    {
        $id = input('put.id/d');
        $update_data = [
            'name' => input('post.app_name'),
            'request_url' => input('post.app_request_url'),
            'redirect_url' => input('post.app_redirect_url'),
            'logout_callback_url' => input('post.app_logout_callback_url', '') ?: null,
            'image' => input('post.app_img_url', '') ?: null,
        ];
        // T7.2 支持更新 OAuth 配置字段（未传则不修改）
        $update_data = array_merge($update_data, $this->extractOauthConfig());
        try {
            $site_id = Db::name('site')
                ->where('is_del', 0)
                ->where('name', $update_data['name'])
                ->where('request_url', $update_data['request_url'])
                ->where('redirect_url', $update_data['redirect_url'])
                ->where('id', '<>', $id)
                ->value('id');
            if ($site_id) {
                return sendJson('不能更新为已存在的应用', ResponseCode::$DATA_ALREADY_EXIST, ResponseMessage::$DATA_ALREADY_EXIST);
            }
            Db::startTrans();
            Db::name('site')
                ->where('id', $id)
                ->update($update_data);
            $this->generatePublicKey($id);
            // T1.3 更新推送接口配置（先删后插）
            $this->savePushApis($id);
            Db::commit();

            // 记录操作日志
            AuditLogService::app(
                'app_update',
                'success',
                '更新应用成功',
                [
                    'app_name' => $update_data['name'],
                    'request_url' => $update_data['request_url'],
                    'redirect_url' => $update_data['redirect_url'],
                ],
                null,
                $id,
                $update_data['name']
            );

            return sendJson('更新成功');
        } catch (Exception $e) {
            Db::rollback();
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR);
        }
    }

    final public function delete(): Json
    {
        $id = input('delete.id/d');
        try {
            Db::startTrans();
            $site = Db::name('site')->where('id', $id)->field('public_key, client_id, name')->find();
            $appName = $site['name'] ?? null;
            if ($site && $site['public_key']) {
                $filepath = config('common.APP_KEY_PATH') . '/' . $site['public_key'];
                if (is_file($filepath)) {
                    unlink($filepath);
                }
            }
            // T7.3 级联清理关联的 OAuth 记录（授权码、access/refresh token）
            if ($site && !empty($site['client_id'])) {
                (new AuthorizationCodeService())->revokeByClient($site['client_id']);
                (new TokenService())->revokeAllTokensByClient($site['client_id']);
            }
            // REQ-PUSH-007 级联清理推送接口配置
            Db::name('site_push_api')->where('site_id', $id)->delete();
            Db::name('site')->where('id', $id)->data(['is_del' => 1])->update();
            Db::commit();

            // 记录操作日志
            AuditLogService::app(
                'app_delete',
                'success',
                '删除应用成功',
                null,
                null,
                $id,
                $appName
            );

            return sendJson('删除成功');
        } catch (Exception $e) {
            Db::rollback();
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR);
        }
    }

    /**
     * 提取请求中携带的 OAuth 配置字段（仅包含实际传入的字段）
     *
     * @return array
     */
    private function extractOauthConfig(): array
    {
        $config = [];

        $allowed_grant_types = input('post.allowed_grant_types');
        if ($allowed_grant_types !== null && $allowed_grant_types !== '') {
            $config['allowed_grant_types'] = (string)$allowed_grant_types;
        }

        $scope = input('post.scope');
        if ($scope !== null && $scope !== '') {
            $config['scope'] = (string)$scope;
        }

        foreach (['access_token_ttl', 'refresh_token_ttl', 'code_ttl'] as $ttl_field) {
            $ttl = input('post.' . $ttl_field);
            if ($ttl !== null && $ttl !== '') {
                $config[$ttl_field] = (int)$ttl;
            }
        }

        return $config;
    }

    /**
     * 保存推送接口配置（先删后插）
     *
     * 支持 extra_headers、body_params、response_rules
     * appid（client_id）和 appkey（client_secret）通过 site_id 关联查询 sc_site 获取，无需存储
     *
     * @param int $siteId 应用 ID
     * @return void
     */
    private function savePushApis(int $siteId): void
    {
        $pushApisRaw = input('post.push_apis');
        if ($pushApisRaw === null || $pushApisRaw === '') {
            return;
        }

        if (is_string($pushApisRaw)) {
            $apis = json_decode($pushApisRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return;
            }
        } else {
            $apis = $pushApisRaw;
        }

        if (!is_array($apis) || empty($apis)) {
            return;
        }

        // 先删除旧的推送配置
        Db::name('site_push_api')->where('site_id', $siteId)->delete();

        // 插入新配置
        foreach ($apis as $api) {
            if (empty($api['url']) || empty($api['action'])) {
                continue;
            }
            $row = [
                'site_id' => $siteId,
                'action' => $api['action'],
                'url' => $api['url'],
                'method' => strtoupper($api['method'] ?? 'POST'),
                'extra_headers' => $this->encodeApiField($api['extra_headers'] ?? null),
                'body_params' => $this->encodeApiField($api['body_params'] ?? null),
                'response_rules' => $this->encodeApiField($api['response_rules'] ?? null),
                'timeout' => (int)($api['timeout'] ?? 10),
                'is_enabled' => isset($api['is_enabled']) ? (int)((bool)$api['is_enabled']) : 1,
            ];
            Db::name('site_push_api')->insert($row);
        }
    }

    /**
     * 将推送配置字段编码为 JSON 字符串（存入数据库）
     *
     * @param mixed $value 数组或字符串
     * @return string|null
     */
    private function encodeApiField($value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 查询单个未删除的应用
     *
     * @param int $id 应用 ID
     * @return array|null 应用记录，不存在时返回 null
     */
    private function getSiteById(int $id): ?array
    {
        return Db::name('site')
            ->where('id', $id)
            ->where('is_del', 0)
            ->find() ?: null;
    }

    /**
     * 生成 APP_KEY 的脱敏预览（首尾各 4 位，中间以 * 代替）
     *
     * @param string $secret 明文 secret
     * @return string 脱敏后的预览
     */
    private function maskSecret(string $secret): string
    {
        if ($secret === '') {
            return '';
        }
        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($secret, 0, 4) . str_repeat('*', $len - 8) . substr($secret, -4);
    }

    /**
     * 应用详情
     * 返回单个应用完整信息（含 client_id，不含 client_secret 明文）
     *
     * @return Json
     */
    final public function detail(): Json
    {
        $id = input('get.id/d');
        try {
            $site = $this->getSiteById($id);
            if (!$site) {
                return sendJson(ResponseMessage::$APP_NOT_FOUND, ResponseCode::$APP_NOT_FOUND, ResponseMessage::$APP_NOT_FOUND, false);
            }
            // 仅下发安全的展示字段，client_secret 明文不返回
            return sendJson([
                'id' => $site['id'],
                'name' => $site['name'],
                'image' => $site['image'],
                'request_url' => $site['request_url'],
                'redirect_url' => $site['redirect_url'],
                'logout_callback_url' => $site['logout_callback_url'],
                'client_id' => $site['client_id'],
                'allowed_grant_types' => $site['allowed_grant_types'],
                'scope' => $site['scope'],
                'access_token_ttl' => $site['access_token_ttl'],
                'refresh_token_ttl' => $site['refresh_token_ttl'],
                'code_ttl' => $site['code_ttl'],
                'has_client_secret' => !empty($site['client_secret']),
                'client_secret_preview' => $this->maskSecret($site['client_secret'] ?? ''),
                'create_time' => $site['create_time'],
                'update_time' => $site['update_time'],
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * 重置应用 APP_KEY（client_secret），返回新值并记录审计日志
     *
     * @return Json
     */
    final public function resetSecret(): Json
    {
        $id = input('post.id/d');
        try {
            $site = $this->getSiteById($id);
            if (!$site) {
                return sendJson(ResponseMessage::$APP_NOT_FOUND, ResponseCode::$APP_NOT_FOUND, ResponseMessage::$APP_NOT_FOUND, false);
            }
            $new_secret = bin2hex(random_bytes(32));
            Db::startTrans();
            Db::name('site')->where('id', $id)->update(['client_secret' => $new_secret]);
            Db::commit();
            // 敏感操作审计日志（不记录明文 secret）
            recordLog("重置应用 APP_KEY：id={$id}, name={$site['name']}", 'info');
            AuditLogService::app(
                'app_reset_secret',
                'success',
                '重置 APP_KEY 成功',
                ['id' => $id, 'name' => $site['name']],
                null,
                $id,
                $site['name']
            );
            return sendJson([
                'id' => $id,
                'client_id' => $site['client_id'],
                'client_secret' => $new_secret,
            ]);
        } catch (Exception $e) {
            Db::rollback();
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$APP_SECRET_RESET_FAILED, ResponseMessage::$APP_SECRET_RESET_FAILED, false);
        }
    }

    /**
     * 获取应用 APP_KEY（明文）
     * 供详情弹窗”查看”使用，仅在有登录态时调用，并记录审计日志
     *
     * @return Json
     */
    final public function getSecret(): Json
    {
        $id = input('post.id/d');
        try {
            $site = $this->getSiteById($id);
            if (!$site) {
                return sendJson(ResponseMessage::$APP_NOT_FOUND, ResponseCode::$APP_NOT_FOUND, ResponseMessage::$APP_NOT_FOUND, false);
            }
            if (empty($site['client_secret'])) {
                return sendJson(ResponseMessage::$APP_CREDENTIALS_NOT_FOUND, ResponseCode::$APP_CREDENTIALS_NOT_FOUND, ResponseMessage::$APP_CREDENTIALS_NOT_FOUND, false);
            }
            recordLog("查看应用 APP_KEY: id={$id}, name={$site['name']}", 'info');
            // 记录审计日志（不记录 secret 明文）
            AuditLogService::app(
                'app_view_secret',
                'success',
                '查看 APP_KEY',
                ['id' => $id, 'name' => $site['name']],
                null,
                $id,
                $site['name']
            );
            return sendJson([
                'id' => $id,
                'client_secret' => $site['client_secret'],
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * 下载应用 OAuth 凭证文件（APP_ID + APP_KEY）
     *
     * @return \think\Response
     */
    final public function downloadSecret()
    {
        $id = input('post.id/d');
        try {
            $site = $this->getSiteById($id);
            if (!$site) {
                return sendJson(ResponseMessage::$APP_NOT_FOUND, ResponseCode::$APP_NOT_FOUND, ResponseMessage::$APP_NOT_FOUND, false);
            }
            if (empty($site['client_secret'])) {
                return sendJson(ResponseMessage::$APP_CREDENTIALS_NOT_FOUND, ResponseCode::$APP_CREDENTIALS_NOT_FOUND, ResponseMessage::$APP_CREDENTIALS_NOT_FOUND, false);
            }
            $filename = $site['name'] . '_oauth_credentials.txt';
            $content = "# OAuth 2.0 应用凭证\n"
                . "# 应用名称: {$site['name']}\n"
                . "# 生成时间: " . date('Y-m-d H:i:s') . "\n"
                . "# 重要：请妥善保管此文件，不要泄露 APP_KEY\n\n"
                . "APP_ID={$site['client_id']}\n"
                . "APP_KEY={$site['client_secret']}\n";
            recordLog("下载应用凭证：id={$id}, name={$site['name']}", 'info');
            return \think\Response::create($content, 'html', 200)->header([
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"app_oauth_credentials.txt\"; filename*=UTF-8''" . rawurlencode($filename),
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    final public function download()
    {
        $filepath = config('common.APP_KEY_PATH') . '/' . base64_decode(input('get._f/s'));
        if (!is_file($filepath)) {
            return redirect('/404');
        }
        return download($filepath, md5(time()));
    }

    /**
     * 访问应用：为当前登录管理员生成 OAuth 授权码，前端拼接回调 URL 后跳转
     * 同时记录操作日志到 sc_operation_log 表
     *
     * @return Json
     */
    final public function visitApp(): Json
    {
        $id = input('post.id/d');
        try {
            // 查询应用（必须存在且启用）
            $site = Db::name('site')
                ->where('id', $id)
                ->where('is_del', 0)
                ->where('is_use', 1)
                ->find();
            if (!$site) {
                return sendJson(ResponseMessage::$APP_NOT_FOUND, ResponseCode::$APP_NOT_FOUND, ResponseMessage::$APP_NOT_FOUND, false);
            }
            if (empty($site['redirect_url'])) {
                return sendJson(ResponseMessage::$APP_REDIRECT_URL_MISSING, ResponseCode::$APP_REDIRECT_URL_MISSING, ResponseMessage::$APP_REDIRECT_URL_MISSING, false);
            }
            if (empty($site['client_id'])) {
                return sendJson(ResponseMessage::$APP_CREDENTIALS_NOT_FOUND, ResponseCode::$APP_CREDENTIALS_NOT_FOUND, ResponseMessage::$APP_CREDENTIALS_NOT_FOUND, false);
            }

            // 从 JWT 中提取当前管理员用户 ID
            $userId = $this->extractUserIdFromJwt();
            if ($userId === null) {
                return sendJson(ResponseMessage::$JWT_ERROR, ResponseCode::$JWT_ERROR, ResponseMessage::$JWT_ERROR, false);
            }

            // 生成随机 state（16 位十六进制字符串）
            $state = bin2hex(random_bytes(8));
            $scope = !empty($site['scope']) ? $site['scope'] : 'trust';
            $ttl = !empty($site['code_ttl']) ? (int)$site['code_ttl'] : 600;

            // 通过 AuthorizationCodeService 生成授权码并写入 DB
            $codeService = new AuthorizationCodeService();
            $code = $codeService->generate(
                $site['client_id'],
                $userId,
                $site['redirect_url'],
                $scope,
                $state,
                $ttl
            );

            // 记录操作日志
            AuditLogService::app(
                'app_visit',
                'success',
                "管理员访问应用：{$site['name']}",
                ['app_id' => $id, 'app_name' => $site['name'], 'user_id' => $userId],
                ['code' => substr($code, 0, 10) . '...', 'redirect_uri' => $site['redirect_url']],
                $id,
                $site['name']
            );

            return sendJson([
                'code' => $code,
                'state' => $state,
                'redirect_uri' => $site['redirect_url'],
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * 记录"访问应用"操作日志（备用接口，前端可异步调用）
     *
     * @return Json
     */
    final public function visitLog(): Json
    {
        $id = input('post.id/d');
        try {
            // 查询应用（必须存在且启用）
            $site = Db::name('site')
                ->where('id', $id)
                ->where('is_del', 0)
                ->where('is_use', 1)
                ->find();
            if (!$site) {
                return sendJson(ResponseMessage::$APP_NOT_FOUND, ResponseCode::$APP_NOT_FOUND, ResponseMessage::$APP_NOT_FOUND, false);
            }

            // 从 JWT 中提取当前管理员用户 ID
            $userId = $this->extractUserIdFromJwt();
            if ($userId === null) {
                return sendJson(ResponseMessage::$JWT_ERROR, ResponseCode::$JWT_ERROR, ResponseMessage::$JWT_ERROR, false);
            }

            // 记录操作日志
            AuditLogService::app(
                'app_visit',
                'success',
                "管理员访问应用：{$site['name']}",
                ['app_id' => $id, 'app_name' => $site['name']],
                null,
                $id,
                $site['name']
            );

            return sendJson('ok');
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * 从 Authorization Header 中提取当前登录用户 ID
     * 兼容原始 JWT 和 base64 包裹的 JWT
     *
     * @return int|null
     */
    private function extractUserIdFromJwt(): ?int
    {
        $token = request()->header('authorization');
        if (empty($token)) {
            return null;
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
                if ($userId > 0) {
                    return $userId;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return null;
    }

    /**
     * T4.3 获取应用的推送接口配置列表
     *
     * 返回字段：extra_headers, body_params, response_rules
     * appid（client_id）和 appkey（client_secret）通过 site_id 关联查询 sc_site 获取，无需存储
     *
     * @return Json
     */
    final public function getPushApis(): Json
    {
        $siteId = input('get.site_id/d');
        try {
            $site = $this->getSiteById($siteId);
            if (!$site) {
                return sendJson(ResponseMessage::$APP_NOT_FOUND, ResponseCode::$APP_NOT_FOUND, ResponseMessage::$APP_NOT_FOUND, false);
            }
            $apis = Db::name('site_push_api')
                ->where('site_id', $siteId)
                ->order('action asc')
                ->select()
                ->toArray();

            // 解码 JSON 字段，转换字段结构
            foreach ($apis as &$api) {
                // 解码新 JSON 字段
                if (!empty($api['extra_headers']) && is_string($api['extra_headers'])) {
                    $api['extra_headers'] = json_decode($api['extra_headers'], true);
                } else {
                    $api['extra_headers'] = [];
                }
                if (!empty($api['body_params']) && is_string($api['body_params'])) {
                    $api['body_params'] = json_decode($api['body_params'], true);
                } else {
                    $api['body_params'] = [];
                }
                if (!empty($api['response_rules']) && is_string($api['response_rules'])) {
                    $api['response_rules'] = json_decode($api['response_rules'], true);
                } else {
                    $api['response_rules'] = [];
                }
                $api['is_enabled'] = (bool)$api['is_enabled'];
            }
            return sendJson($apis);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }
}
