<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use app\service\UserSyncService;
use Exception;
use think\response\Json;

/**
 * SCIM 用户同步控制器
 * 提供创建/修改/删除账号接口，认证方式为 SM3 签名验证（VerifySignature 中间件）
 *
 * @package app\controller
 * @author  SSO Center
 * @since   1.0.0
 */
class Scim extends BaseController
{
    protected UserSyncService $userSyncService;

    protected function initialize()
    {
        $this->userSyncService = new UserSyncService();
    }

    /**
     * 创建账号 — POST /api/scim/v2/accounts
     * upsert 语义：不存在则创建，存在则更新
     *
     * @return Json
     */
    public function createAccount(): Json
    {
        try {
            $data = $this->extractAccountData();

            $result = $this->userSyncService->upsert($data);
            $user = $result['user'];

            // 敏感操作记录日志
            recordLog(sprintf(
                'SCIM createAccount: client_id=%s, code=%s, external_id=%s, is_new=%s',
                (string)(request()->oauth_client_id ?? ''),
                $data['code'],
                $data['externalId'],
                $result['is_new'] ? 'yes' : 'no'
            ), 'info');

            return $this->scimResponse(
                $result['is_new'] ? '创建账号成功' : '账号已存在，更新成功',
                '创建账号',
                $data['externalId'],
                ['accountId' => $this->userSyncService->buildAccountId($user)]
            );
        } catch (Exception $e) {
            recordLog($e, 'error');
            return $this->scimError($e->getMessage(), '创建账号');
        }
    }

    /**
     * 修改账号 — PUT /api/scim/v2/accounts/{externalId}
     *
     * @return Json
     */
    public function updateAccount(): Json
    {
        try {
            $externalId = (string)$this->request->route('externalId');
            $data = $this->extractAccountData();

            // Body 中 externalId 须与 URL 中一致
            if ($data['externalId'] !== $externalId) {
                return $this->scimError('Body 中的 externalId 与 URL 不一致', '修改账号', ResponseCode::$VALIDATE_ERROR, 400);
            }

            $user = $this->userSyncService->updateByExternalId($externalId, $data);
            if ($user === false) {
                return $this->scimError('账号不存在', '修改账号', ResponseCode::$OAUTH_USER_NOT_FOUND, 404);
            }

            recordLog(sprintf(
                'SCIM updateAccount: client_id=%s, external_id=%s',
                (string)(request()->oauth_client_id ?? ''),
                $externalId
            ), 'info');

            return $this->scimResponse(
                '修改账号成功',
                '修改账号',
                $externalId,
                ['accountId' => $this->userSyncService->buildAccountId($user)]
            );
        } catch (Exception $e) {
            recordLog($e, 'error');
            return $this->scimError($e->getMessage(), '修改账号');
        }
    }

    /**
     * 删除账号 — DELETE /api/scim/v2/accounts/{externalId}
     * 软删除
     *
     * @return Json
     */
    public function deleteAccount(): Json
    {
        try {
            $externalId = (string)$this->request->route('externalId');
            if (empty($externalId)) {
                return $this->scimError('缺少 externalId', '删除账号', ResponseCode::$VALIDATE_ERROR, 400);
            }

            $user = $this->userSyncService->softDeleteByExternalId($externalId);
            if ($user === false) {
                return $this->scimError('账号不存在', '删除账号', ResponseCode::$OAUTH_USER_NOT_FOUND, 404);
            }

            recordLog(sprintf(
                'SCIM deleteAccount: client_id=%s, external_id=%s',
                (string)(request()->oauth_client_id ?? ''),
                $externalId
            ), 'info');

            return $this->scimResponse(
                '删除账号成功',
                '删除账号',
                $externalId,
                ['accountId' => $this->userSyncService->buildAccountId($user)]
            );
        } catch (Exception $e) {
            recordLog($e, 'error');
            return $this->scimError($e->getMessage(), '删除账号');
        }
    }

    /**
     * 提取并规范化账号请求数据
     *
     * @return array
     */
    private function extractAccountData(): array
    {
        return [
            'code' => (string)input('post.code/s', ''),
            'name' => (string)input('post.name/s', ''),
            'externalId' => (string)input('post.externalId/s', ''),
            'mobile' => input('post.mobile'),
            'email' => input('post.email'),
            'status' => input('post.status'),
        ];
    }

    /**
     * 按规范文档格式返回成功响应
     *
     * @param string $message 响应消息
     * @param string $title 操作标题
     * @param string $externalId 外部 ID
     * @param array $data 响应数据
     * @return Json
     */
    private function scimResponse(string $message, string $title, string $externalId, array $data): Json
    {
        return json([
            'success' => true,
            'code' => 200,
            'message' => $message,
            'title' => $title,
            'externalId' => $externalId,
            'data' => $data,
        ]);
    }

    /**
     * 按规范文档格式返回错误响应
     *
     * @param string $message 错误消息
     * @param string $title 操作标题
     * @param int $code 业务错误码
     * @param int $httpStatus HTTP 状态码
     * @return Json
     */
    private function scimError(string $message, string $title, int $code = 0, int $httpStatus = 500): Json
    {
        if ($code === 0) {
            $code = ResponseCode::$DB_ERROR;
            $message = $message ?: ResponseMessage::$DB_ERROR;
        }
        return json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'title' => $title,
            'externalId' => '',
            'data' => null,
        ], $httpStatus);
    }
}
