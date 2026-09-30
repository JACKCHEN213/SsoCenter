<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use app\service\AuditLogService;
use Exception;
use GuzzleHttp\Client;
use think\facade\Db;
use think\facade\View;
use think\response\Json;

/**
 * 会话管理控制器
 *
 * 提供会话列表查询、详情查看、强制退出等功能
 *
 * @package app\controller
 * @author  SSO Center
 * @since   1.0.0
 */
class Session extends BaseController
{
    /**
     * 渲染会话管理页面
     *
     * @return string
     */
    public function index()
    {
        return View::fetch('/session/index');
    }

    /**
     * 分页查询会话列表（按用户聚合）
     *
     * @return Json
     */
    public function list(): Json
    {
        try {
            $username = input('post.username/s');
            $page = input('post.page/d') ?: 1;
            $limit = input('post.limit/d') ?: 10;

            // 按用户聚合查询（所有未删除的用户）
            $query = Db::name('user')
                ->alias('u')
                ->where('u.is_del', 0)
                ->field([
                    'u.id as user_id',
                    'u.username',
                ]);

            if (!empty($username)) {
                $query->where('u.username', 'like', "%{$username}%");
            }

            // 获取总数
            $total = (clone $query)->count();

            // 获取分页用户
            $users = $query->page($page, $limit)->select()->toArray();

            if (empty($users)) {
                return sendJson([
                    'total' => $total,
                    'list' => [],
                ]);
            }

            $userIds = array_column($users, 'user_id');

            // 查询本地会话统计
            $sessions = Db::name('user_sessions')
                ->whereIn('user_id', $userIds)
                ->field([
                    'user_id',
                    'COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_count',
                    'MAX(login_time) as last_login_time',
                ])
                ->group('user_id')
                ->select()
                ->toArray();

            $sessionMap = [];
            foreach ($sessions as $s) {
                $sessionMap[$s['user_id']] = $s;
            }

            // 查询各用户的最近登录 IP
            $lastIps = Db::name('user_sessions')
                ->whereIn('user_id', $userIds)
                ->field('user_id, ip, login_time')
                ->order('login_time desc')
                ->select()
                ->toArray();

            $ipMap = [];
            foreach ($lastIps as $ip) {
                if (!isset($ipMap[$ip['user_id']])) {
                    $ipMap[$ip['user_id']] = $ip['ip'];
                }
            }

            // 查询各应用授权统计
            $appAuths = Db::name('oauth_access_tokens')
                ->alias('t')
                ->join('sc_site s', 't.client_id = s.client_id')
                ->whereIn('t.user_id', $userIds)
                ->where('t.revoked', 0)
                ->field([
                    't.user_id',
                    's.id as site_id',
                    's.name as site_name',
                    'COUNT(DISTINCT t.id) as access_tokens',
                ])
                ->group('t.user_id, s.id')
                ->select()
                ->toArray();

            // 构建应用授权映射
            $authMap = [];
            foreach ($appAuths as $auth) {
                $userId = $auth['user_id'];
                if (!isset($authMap[$userId])) {
                    $authMap[$userId] = [];
                }
                $authMap[$userId][] = [
                    'site_id' => $auth['site_id'],
                    'site_name' => $auth['site_name'],
                    'access_tokens' => (int)$auth['access_tokens'],
                ];
            }

            // 组装结果
            $list = [];
            foreach ($users as $user) {
                $uid = $user['user_id'];
                $session = $sessionMap[$uid] ?? null;

                $list[] = [
                    'user_id' => $uid,
                    'username' => $user['username'],
                    'local_sessions' => [
                        'active_count' => $session ? (int)$session['active_count'] : 0,
                        'last_login_ip' => $ipMap[$uid] ?? null,
                        'last_login_time' => $session ? $session['last_login_time'] : null,
                    ],
                    'app_authorizations' => $authMap[$uid] ?? [],
                ];
            }

            return sendJson([
                'total' => $total,
                'list' => $list,
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * 获取某用户的完整会话详情
     *
     * @return Json
     */
    public function detail(): Json
    {
        $userId = input('post.user_id/d');
        try {
            $user = Db::name('user')->where('id', $userId)->where('is_del', 0)->find();
            if (!$user) {
                return sendJson(ResponseMessage::$USER_NOT_FOUND, ResponseCode::$USER_NOT_FOUND, ResponseMessage::$USER_NOT_FOUND, false);
            }

            // 查询本地会话列表
            $localSessions = Db::name('user_sessions')
                ->where('user_id', $userId)
                ->order('login_time desc')
                ->select()
                ->toArray();

            foreach ($localSessions as &$session) {
                $session['is_active'] = (bool)$session['is_active'];
            }

            // 查询应用授权详情
            $appAuths = Db::name('oauth_access_tokens')
                ->alias('t')
                ->join('sc_site s', 't.client_id = s.client_id')
                ->where('t.user_id', $userId)
                ->field([
                    's.id as site_id',
                    's.name as site_name',
                    's.client_id',
                    't.id as token_id',
                    't.access_token',
                    't.scope',
                    't.expires_at',
                    't.revoked',
                    't.created_at',
                ])
                ->order('t.created_at desc')
                ->select()
                ->toArray();

            // 按应用分组
            $authMap = [];
            foreach ($appAuths as $auth) {
                $siteId = $auth['site_id'];
                if (!isset($authMap[$siteId])) {
                    $authMap[$siteId] = [
                        'site_id' => $siteId,
                        'site_name' => $auth['site_name'],
                        'client_id' => $auth['client_id'],
                        'access_tokens' => [],
                    ];
                }
                $authMap[$siteId]['access_tokens'][] = [
                    'token_prefix' => substr($auth['access_token'], 0, 10) . '...',
                    'scope' => $auth['scope'],
                    'expires_at' => $auth['expires_at'],
                    'revoked' => (bool)$auth['revoked'],
                    'created_at' => $auth['created_at'],
                ];
            }

            // 查询授权码
            $authCodes = Db::name('oauth_authorization_codes')
                ->alias('c')
                ->join('sc_site s', 'c.client_id = s.client_id')
                ->where('c.user_id', $userId)
                ->field([
                    's.id as site_id',
                    's.name as site_name',
                    'c.code',
                    'c.scope',
                    'c.expires_at',
                    'c.created_at',
                ])
                ->select()
                ->toArray();

            foreach ($authCodes as $code) {
                $siteId = $code['site_id'];
                if (!isset($authMap[$siteId])) {
                    $authMap[$siteId] = [
                        'site_id' => $siteId,
                        'site_name' => $code['site_name'],
                        'client_id' => '',
                        'access_tokens' => [],
                        'auth_codes' => [],
                    ];
                }
                if (!isset($authMap[$siteId]['auth_codes'])) {
                    $authMap[$siteId]['auth_codes'] = [];
                }
                $authMap[$siteId]['auth_codes'][] = [
                    'code' => substr($code['code'], 0, 10) . '...',
                    'scope' => $code['scope'],
                    'expires_at' => $code['expires_at'],
                    'created_at' => $code['created_at'],
                ];
            }

            // 查询 refresh tokens
            $refreshTokens = Db::name('oauth_refresh_tokens')
                ->alias('r')
                ->join('sc_site s', 'r.client_id = s.client_id')
                ->where('r.user_id', $userId)
                ->field([
                    's.id as site_id',
                    'r.refresh_token',
                    'r.scope',
                    'r.expires_at',
                    'r.revoked',
                    'r.created_at',
                ])
                ->select()
                ->toArray();

            foreach ($refreshTokens as $rt) {
                $siteId = $rt['site_id'];
                if (isset($authMap[$siteId])) {
                    if (!isset($authMap[$siteId]['refresh_tokens'])) {
                        $authMap[$siteId]['refresh_tokens'] = [];
                    }
                    $authMap[$siteId]['refresh_tokens'][] = [
                        'token_prefix' => substr($rt['refresh_token'], 0, 10) . '...',
                        'scope' => $rt['scope'],
                        'expires_at' => $rt['expires_at'],
                        'revoked' => (bool)$rt['revoked'],
                        'created_at' => $rt['created_at'],
                    ];
                }
            }

            return sendJson([
                'user_id' => $userId,
                'username' => $user['username'],
                'local_sessions' => $localSessions,
                'app_authorizations' => array_values($authMap),
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * 强制退出
     *
     * @return Json
     */
    public function logout(): Json
    {
        $userId = input('post.user_id/d');
        $scope = input('post.scope/s', 'local'); // local / app / all
        $sessionId = input('post.session_id/d');
        $siteId = input('post.site_id/d');

        try {
            $user = Db::name('user')->where('id', $userId)->where('is_del', 0)->find();
            if (!$user) {
                return sendJson(ResponseMessage::$USER_NOT_FOUND, ResponseCode::$USER_NOT_FOUND, ResponseMessage::$USER_NOT_FOUND, false);
            }

            $result = [
                'local_sessions_revoked' => 0,
                'access_tokens_revoked' => 0,
                'refresh_tokens_revoked' => 0,
                'auth_codes_deleted' => 0,
                'apps_notified' => [],
            ];

            Db::startTrans();

            // 退出应用授权
            if ($scope === 'app' || $scope === 'all') {
                $clientIds = [];
                if ($scope === 'app' && $siteId) {
                    // 退出指定应用
                    $clientId = Db::name('site')->where('id', $siteId)->value('client_id');
                    if ($clientId) {
                        $clientIds[] = $clientId;
                    }
                } else {
                    // 退出所有应用
                    $clientIds = Db::name('site')
                        ->where('is_del', 0)
                        ->whereNotNull('client_id')
                        ->column('client_id');
                }

                if (!empty($clientIds)) {
                    // 撤销 access tokens
                    $result['access_tokens_revoked'] = Db::name('oauth_access_tokens')
                        ->where('user_id', $userId)
                        ->whereIn('client_id', $clientIds)
                        ->where('revoked', 0)
                        ->update(['revoked' => 1]);

                    // 撤销 refresh tokens
                    $result['refresh_tokens_revoked'] = Db::name('oauth_refresh_tokens')
                        ->where('user_id', $userId)
                        ->whereIn('client_id', $clientIds)
                        ->where('revoked', 0)
                        ->update(['revoked' => 1]);

                    // 删除授权码
                    $result['auth_codes_deleted'] = Db::name('oauth_authorization_codes')
                        ->where('user_id', $userId)
                        ->whereIn('client_id', $clientIds)
                        ->delete();

                    // 发送登出通知
                    $sites = Db::name('site')
                        ->whereIn('client_id', $clientIds)
                        ->whereNotNull('logout_callback_url')
                        ->where('logout_callback_url', '<>', '')
                        ->select()
                        ->toArray();

                    foreach ($sites as $site) {
                        $body = [
                            'externalId' => $userId,
                            'code' => $user['username'],
                        ];
                        $notified = $this->sendLogoutNotification($site, $body, $userId, $user['username']);
                        if ($notified) {
                            AuditLogService::log(
                                'user',
                                'session_logout',
                                'success',
                                "强制退出用户 {$user['username']} 的" . $site['name'] . '会话',
                                [
                                    'url' => $site['logout_callback_url'],
                                    'body' => $body
                                ],
                                $notified,
                                'user',
                                $userId,
                                $user['username']
                            );
                            $result['apps_notified'][] = $site['name'];
                        }
                    }
                }
            }

            // 退出本系统会话
            if ($scope === 'local' || $scope === 'all') {
                if ($scope === 'local' && $sessionId) {
                    // 退出指定会话
                    $affected = Db::name('user_sessions')
                        ->where('id', $sessionId)
                        ->where('user_id', $userId)
                        ->update(['is_active' => 0]);
                    $result['local_sessions_revoked'] = $affected;
                } else {
                    // 退出所有本系统会话
                    $affected = Db::name('user_sessions')
                        ->where('user_id', $userId)
                        ->where('is_active', 1)
                        ->update(['is_active' => 0]);
                    $result['local_sessions_revoked'] = $affected;
                }
            }

            Db::commit();

            // 记录操作日志
            $scopeText = ['local' => '本系统', 'app' => '指定应用', 'all' => '全部'];
            AuditLogService::log(
                'user',
                'session_logout',
                'success',
                "强制退出用户 {$user['username']} 的" . ($scopeText[$scope] ?? $scope) . '会话',
                ['user_id' => $userId, 'scope' => $scope, 'site_id' => $siteId, 'session_id' => $sessionId],
                $result,
                'user',
                $userId,
                $user['username']
            );

            return sendJson($result);
        } catch (Exception $e) {
            Db::rollback();
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * 发送登出通知到第三方系统
     *
     * @param array $site 应用信息
     * @param array $body 请求数据
     * @param int $userId 用户 ID
     * @param string $username 用户名
     * @return bool|array 是否发送成功
     */
    private function sendLogoutNotification(array $site, array $body, int $userId, string $username)
    {
        try {
            $client = new Client(['timeout' => 5, 'verify' => false]);
            $response = $client->request('POST', $site['logout_callback_url'], [
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'http_errors' => false,
            ]);

            // 扁平化响应头（Guzzle 返回数组值，取最后一个）
            $respHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $respHeaders[$name] = end($values);
            }

            $responseInfo = [
                'http status code' => $response->getStatusCode(),
                'body' => json_decode((string)$response->getBody(), true),
                'headers' => $respHeaders,
            ];
            return $responseInfo;
        } catch (Exception $e) {
            recordLog("登出通知失败: site={$site['name']}, error={$e->getMessage()}", 'error');
            return false;
        }
    }
}
