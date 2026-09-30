<?php

namespace app\controller;

use app\common\Key;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use app\BaseController;
use app\service\AuditLogService;
use app\service\PushService;
use Exception;
use think\facade\Db;
use think\facade\View;
use think\db\Where;
use think\facade\Request;
use think\response\Json;

class User extends BaseController
{
    public function index()
    {
        $page = input('page/d') ?: 1;
        $limit = input('limit/d') ?: 10;
        $su = input('su/s');
        $userModel = Db::name('user')->where('is_del', 0);
        if ($su) {
						$userModel->where('username', 'like', $su);
            // $where['username'] = ["LIKE", "%$su%"];
        }
        // $userModel = Db::name('user')
        //     ->where($where);
        $count = (clone $userModel)->count();
        if ($page && $limit) {
            $userModel->page($page, $limit);
        }
        $data = $userModel->select();
        View::assign([
            'users' => $data,
            'count' => $count,
            'su' => $su,
            'page' => $page,
            'limit' => $limit,
            'lastPage' => ceil($count / $limit),
        ]);
        return View::fetch('/user');
    }

    public function doAdd($username, $password, $email)
    {
        $paths = null;
        try {
            $user = Db::name('user')->where('is_del', 0)->where('username', $username)->find();
            if ($user) {
                return sendJson("用户已经存在了", ResponseCode::$USER_EXISTS, ResponseMessage::$USER_EXISTS, false);
            }

            // 生成用户 RSA 密钥对
            $keyDir = app()->getRootPath() . config('common.USER_KEY_PATH');
            $paths = Key::generateUserKeyPair($keyDir, $username);

            // 读取公钥用于密码加密
            $publicKey = Key::getUserPublicKey($keyDir, $username);

            // 密码加密：md5 → password_hash → 公钥加密
            $encryptedPassword = $this->encryptPassword($password, $publicKey);

            Db::startTrans();
            $userId = (int)Db::name('user')->insertGetId([
                'username' => $username,
                'password' => $encryptedPassword,
                'email' => $email,
                'jwt_private_path' => $paths['private_path'],
                'jwt_public_path' => $paths['public_path'],
            ]);
            Db::commit();

            // 记录操作日志（不记录密码明文）
            AuditLogService::user(
                'user_create',
                'success',
                "用户{$username}添加成功",
                ['username' => $username, 'email' => $email],
                null,
                $userId,
                $username
            );

            return sendJson("用户{$username}添加成功", ResponseCode::$ADD_USER_SUCCESS, ResponseMessage::$ADD_USER_SUCCESS);
        } catch (Exception $e) {
            Db::rollback();
            // 添加失败时清理已生成的密钥文件
            if ($paths) {
                if (is_file($paths['private_path'])) {
                    unlink($paths['private_path']);
                }
                if (is_file($paths['public_path'])) {
                    unlink($paths['public_path']);
                }
            }
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }

    public function add()
    {
        return $this->doAdd(input('post.username/s'), input('post.password/s'), input('post.email/s'));
    }

    public function doUpdate($user_id, $username, $email)
    {
        try {
            if (!Db::name('user')->where('is_del', 0)->find($user_id)) {
                return sendJson(
                    '用户不存在',
                    ResponseCode::$JWT_ERROR,
                    ResponseMessage::$JWT_ERROR,
                    false
                );
            }
            Db::name('user')->where('id', $user_id)->update([
                'username' => $username,
                'email' => $email
            ]);

            // 记录操作日志
            AuditLogService::user(
                'user_update',
                'success',
                "用户{$username}修改成功",
                ['username' => $username, 'email' => $email],
                null,
                (int)$user_id,
                $username
            );

            return sendJson(
                "用户{$username}修改成功",
                ResponseCode::$UPDATE_USER_SUCCESS,
                ResponseMessage::$UPDATE_USER_SUCCESS
            );
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }

    public function update()
    {
        return $this->doUpdate(input('patch.user_id'), input('patch.username'), input('patch.email'));
    }

    public function doDelete($user_id)
    {
        try {
            $user_data = Db::name('user')->where('is_del', 0)->find($user_id);
            if (!$user_data) {
                return sendJson(
                    '用户不存在',
                    ResponseCode::$JWT_ERROR,
                    ResponseMessage::$JWT_ERROR,
                    false
                );
            }

            // T3.7 删除用户前，向配置了 delete 推送接口的第三方应用发送删除请求
            $pushSummary = $this->cascadeDeletePush($user_id);

            Db::name('user')->where('id', $user_id)->update([
                'is_del' => 1,
            ]);

            $msg = "用户{$user_data['username']}删除成功";
            if ($pushSummary) {
                $msg .= "，{$pushSummary}";
            }

            // 记录操作日志
            AuditLogService::user(
                'user_delete',
                'success',
                $msg,
                null,
                null,
                (int)$user_id,
                $user_data['username']
            );

            return sendJson(
                $msg,
                ResponseCode::$DELETE_USER_SUCCESS,
                ResponseMessage::$DELETE_USER_SUCCESS
            );
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }

    /**
     * T3.7 级联推送删除请求到第三方应用
     *
     * @param int $userId 用户 ID
     * @return string|null 推送结果摘要（如 "级联推送：1 成功 / 0 失败"），无推送则返回 null
     */
    private function cascadeDeletePush(int $userId): ?string
    {
        try {
            $pushService = new PushService();

            // 查询配置了 delete 推送接口的应用
            $siteIds = Db::name('site_push_api')
                ->where('action', 'delete')
                ->where('is_enabled', 1)
                ->column('site_id');

            if (empty($siteIds)) {
                return null;
            }

            $successCount = 0;
            $failCount = 0;
            foreach ($siteIds as $siteId) {
                $result = $pushService->pushUser($userId, (int)$siteId, 'delete');
                if ($result->isSuccess) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            return "级联推送：{$successCount} 成功 / {$failCount} 失败";
        } catch (Exception $e) {
            recordLog("级联推送异常: {$e->getMessage()}", 'error');
            return null;
        }
    }

    public function delete()
    {
        return $this->doDelete(input('patch.user_id'));
    }

    public function batchAdd()
    {

        $file = Request::file('file');
        $filepath = $file->getRealPath();
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
        $sheet = $reader->getActiveSheet();
        $rowIter = $sheet->getRowIterator();
        $readColumns = ['A', 'B', 'C'];
        $columnDesc = ['A' => '用户名', 'B' => '邮箱', 'C' => '密码'];
        $userInfo = [];
        $readError = [];
        foreach ($rowIter as $row) {
            $rowIndex = $row->getRowIndex();
            $cellIter = $row->getCellIterator();
            foreach ($cellIter as $key => $cell) {
                if (!in_array($key, $readColumns)) {
                    continue;
                }
                $value = trim($cell->getValue());
                if ($rowIndex == 1) {
                    if ($value !== $columnDesc[$key]) {
                        return sendJson(
                            "{$key}列应当是 {$columnDesc[$key]} ,当前为 {$value}",
                            ResponseCode::$BATCH_ADD_ERROR,
                            ResponseMessage::$BATCH_ADD_ERROR,
                            false
                        );
                    }
                    continue;
                }
                $value = trim($cell->getValue());
                if (!$value) {
                    $readError[] = "第{$rowIndex}行{$columnDesc[$key]}不能为空";
                    continue;
                }
                $userInfo[$rowIndex][$key] = $value;
            }
        }

        if ($readError) {
            return sendJson(
                $readError,
                ResponseCode::$BATCH_ADD_ERROR,
                ResponseMessage::$BATCH_ADD_ERROR,
                false
            );
        }
        if (!$userInfo) {
            return sendJson(
                '未读取到数据',
                ResponseCode::$BATCH_ADD_ERROR,
                ResponseMessage::$BATCH_ADD_ERROR,
                false
            );
        }

        $result = [];
        foreach ($userInfo as $rowIndex => $user) {
            $addRet = $this->doAdd($user['A'], $user['C'], $user['B']);
            $result[] = "第{$rowIndex}行, " . $addRet->getData()['result'];
        }
        return sendJson($result, ResponseCode::$BATCH_ADD_SUCCESS, ResponseMessage::$BATCH_ADD_SUCCESS);
    }

    /**
     * T3.1 查询用户对所有应用的推送状态
     *
     * @return Json
     */
    public function pushStatus(): Json
    {
        $userId = input('post.user_id/d');
        try {
            $user = Db::name('user')->where('id', $userId)->where('is_del', 0)->find();
            if (!$user) {
                return sendJson(ResponseMessage::$USER_NOT_FOUND, ResponseCode::$USER_NOT_FOUND, ResponseMessage::$USER_NOT_FOUND, false);
            }

            // 查询所有已配置推送接口的应用
            $siteIds = Db::name('site_push_api')
                ->where('is_enabled', 1)
                ->group('site_id')
                ->column('site_id');

            if (empty($siteIds)) {
                return sendJson([]);
            }

            $sites = Db::name('site')
                ->where('id', 'in', $siteIds)
                ->where('is_del', 0)
                ->column('name', 'id');

            $result = [];
            foreach ($siteIds as $siteId) {
                $siteId = (int)$siteId;
                // 查询最近一次推送日志
                $lastPush = Db::name('user_push_log')
                    ->where('user_id', $userId)
                    ->where('site_id', $siteId)
                    ->order('push_time desc')
                    ->find();

                // 获取该用户在该应用的 externalId
                $externalId = null;
                $externalIdsJson = Db::name('user')->where('id', $userId)->value('external_ids');
                if (!empty($externalIdsJson)) {
                    $externalIds = is_string($externalIdsJson) ? json_decode($externalIdsJson, true) : $externalIdsJson;
                    if (is_array($externalIds)) {
                        $externalId = $externalIds[(string)$siteId] ?? null;
                    }
                }

                $result[] = [
                    'site_id' => $siteId,
                    'site_name' => $sites[$siteId] ?? '',
                    'has_api_config' => true,
                    'external_id' => $externalId,
                    'last_push' => $lastPush ? [
                        'is_success' => (bool)$lastPush['is_success'],
                        'push_time' => $lastPush['push_time'],
                        'error_message' => $lastPush['failed_rule'] ?? $lastPush['error_message'] ?? null,
                        'action' => $lastPush['action'],
                        'log_id' => $lastPush['id'],
                        'external_id' => $lastPush['external_id'] ?? null,
                        'failed_rule' => $lastPush['failed_rule'] ?? null,
                    ] : null,
                ];
            }
            return sendJson($result);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * T3.2 推送用户到指定应用
     *
     * @return Json
     */
    public function pushUser(): Json
    {
        $userId = input('post.user_id/d');
        $siteId = input('post.site_id/d');
        try {
            // 查询用户和应用名称用于日志记录
            $user = Db::name('user')->where('id', $userId)->where('is_del', 0)->find();
            $site = Db::name('site')->where('id', $siteId)->where('is_del', 0)->find();
            $userName = $user['username'] ?? null;
            $siteName = $site['name'] ?? null;

            $pushService = new PushService();
            $result = $pushService->pushUser($userId, $siteId, 'create');
            recordLog("推送用户：user_id={$userId}, site_id={$siteId}, success=" . ($result->isSuccess ? 'Y' : 'N'), 'info');

            // 记录操作日志（含完整请求/响应数据）
            $resultMsg = $result->isSuccess
                ? "推送成功" . ($result->externalId ? "，externalId={$result->externalId}" : "")
                : "推送失败: " . ($result->errorMessage ?? '未知错误');

            AuditLogService::user(
                'user_push',
                $result->isSuccess ? 'success' : 'failure',
                $resultMsg,
                $result->httpRequestData,
                $result->httpResponseData,
                $userId,
                $userName,
                $result->pushLogId ? 'push_log' : null,
                $result->pushLogId
            );

            return sendJson($result->toArray());
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }

    /**
     * T3.3 推送用户到所有已配置的应用
     *
     * @return Json
     */
    public function pushUserToAll(): Json
    {
        $userId = input('post.user_id/d');
        try {
            // 查询用户名称用于日志记录
            $user = Db::name('user')->where('id', $userId)->where('is_del', 0)->find();
            $userName = $user['username'] ?? null;

            $pushService = new PushService();
            $results = $pushService->pushUserToAll($userId);
            $output = [];
            $successCount = 0;
            $failCount = 0;
            foreach ($results as $item) {
                $r = $item['result'];
                $output[] = [
                    'site_id' => $item['site_id'],
                    'site_name' => $item['site_name'],
                    'is_success' => $r->isSuccess,
                    'error_message' => $r->errorMessage,
                    'external_id' => $r->externalId,
                    'failed_rule' => $r->failedRule,
                ];
                if ($r->isSuccess) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            recordLog("全部推送：user_id={$userId}, success={$successCount}, fail={$failCount}", 'info');

            // 记录批量推送操作日志
            $summaryMsg = "批量推送完成: {$successCount} 成功, {$failCount} 失败";
            AuditLogService::user(
                'user_push_all',
                $failCount > 0 ? 'failure' : 'success',
                $summaryMsg,
                ['user_id' => $userId, 'user_name' => $userName],
                ['results' => $output, 'success_count' => $successCount, 'fail_count' => $failCount],
                $userId,
                $userName
            );

            return sendJson([
                'results' => $output,
                'summary' => "推送完成：{$successCount} 成功，{$failCount} 失败",
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }

    /**
     * T3.4 / T4.7 获取推送日志
     *
     * 支持两种调用方式：
     *   1. 传 log_id: 返回单条日志详情（用于"推送详情"弹窗）
     *   2. 传 user_id + site_id: 返回该应用最近 50 条日志列表
     *
     * @return Json
     */
    public function pushLog(): Json
    {
        $logId = input('post.log_id/d');
        $userId = input('post.user_id/d');
        $siteId = input('post.site_id/d');
        try {
            if ($logId) {
                // 查询单条日志详情
                $log = Db::name('user_push_log')
                    ->where('id', $logId)
                    ->find();
                if (!$log) {
                    return sendJson(null);
                }
                $log['is_success'] = (bool)$log['is_success'];
                if (!empty($log['request_headers']) && is_string($log['request_headers'])) {
                    $log['request_headers'] = json_decode($log['request_headers'], true);
                }
                return sendJson($log);
            }

            // 查询日志列表（兼容旧调用方式）
            $logs = Db::name('user_push_log')
                ->where('user_id', $userId)
                ->where('site_id', $siteId)
                ->order('push_time desc')
                ->limit(50)
                ->select()
                ->toArray();
            foreach ($logs as &$log) {
                $log['is_success'] = (bool)$log['is_success'];
                // 解码 request_headers JSON
                if (!empty($log['request_headers']) && is_string($log['request_headers'])) {
                    $log['request_headers'] = json_decode($log['request_headers'], true);
                }
            }
            return sendJson($logs);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * 密码加密：md5 → password_hash → RSA 公钥加密
     *
     * @param string $password 原始密码
     * @param string $publicKey RSA 公钥内容
     * @return string Base64 编码的加密密码
     * @throws Exception
     */
    private function encryptPassword(string $password, string $publicKey): string
    {
        $md5 = md5($password);
        $hash = password_hash($md5, PASSWORD_BCRYPT);
        if (!openssl_public_encrypt($hash, $encrypted, $publicKey)) {
            throw new Exception('密码公钥加密失败');
        }
        return base64_encode($encrypted);
    }

    /**
     * 迁移现有用户密码
     * 将密码重置为用户名，使用 md5 + password_hash + 公钥加密，并生成用户密钥对
     *
     * @return Json
     */
    public function migratePasswords()
    {
        try {
            $users = Db::name('user')->where('is_del', 0)->select();
            $keyDir = app()->getRootPath() . config('common.USER_KEY_PATH');
            $result = [];

            foreach ($users as $user) {
                try {
                    $username = $user['username'];

                    // 生成用户密钥对
                    $paths = Key::generateUserKeyPair($keyDir, $username);

                    // 读取公钥
                    $publicKey = Key::getUserPublicKey($keyDir, $username);

                    // 密码重置为用户名，然后加密
                    $encryptedPassword = $this->encryptPassword($username, $publicKey);

                    // 更新数据库
                    Db::name('user')->where('id', $user['id'])->update([
                        'password' => $encryptedPassword,
                        'jwt_private_path' => $paths['private_path'],
                        'jwt_public_path' => $paths['public_path'],
                    ]);

                    $result[] = "用户{$username}迁移成功";
                } catch (Exception $e) {
                    $result[] = "用户{$user['username']}迁移失败: " . $e->getMessage();
                }
            }

            return sendJson($result, ResponseCode::$OK, ResponseMessage::$OK);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
        }
    }
}
