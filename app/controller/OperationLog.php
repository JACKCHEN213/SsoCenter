<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use Exception;
use think\facade\Db;
use think\facade\View;
use think\response\Json;

/**
 * 操作日志控制器
 *
 * 提供操作日志的查询接口
 *
 * @package app\controller
 * @author  SSO Center
 * @since   1.0.0
 */
class OperationLog extends BaseController
{
    /**
     * 渲染操作日志页面
     *
     * @return string
     */
    public function index()
    {
        return View::fetch('/operation_log');
    }

    /**
     * 分页查询日志列表
     *
     * @return Json
     */
    public function list(): Json
    {
        try {
            // 获取筛选参数
            $module = input('post.module/s');
            $action = input('post.action/s');
            $operatorName = input('post.operator_name/s');
            $startTime = input('post.start_time/s');
            $endTime = input('post.end_time/s');
            $page = input('post.page/d') ?: 1;
            $pageSize = input('post.page_size/d') ?: 20;

            // 构建查询条件
            $query = Db::name('operation_log');

            if (!empty($module)) {
                $query->where('module', $module);
            }
            if (!empty($action)) {
                $query->where('action', $action);
            }
            if (!empty($operatorName)) {
                $query->where('operator_name', 'like', "%{$operatorName}%");
            }
            if (!empty($startTime)) {
                $query->where('created_at', '>=', $startTime);
            }
            if (!empty($endTime)) {
                $query->where('created_at', '<=', $endTime);
            }

            // 获取总数
            $total = $query->count();

            // 获取分页数据
            $list = $query->field([
                'id',
                'operator_id',
                'operator_name',
                'module',
                'action',
                'target_type',
                'target_id',
                'target_name',
                'result',
                'result_message',
                'ip',
                'created_at',
            ])
                ->order('created_at desc')
                ->page($page, $pageSize)
                ->select()
                ->toArray();

            return sendJson([
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'list' => $list,
            ]);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }

    /**
     * 获取单条日志详情
     *
     * @return Json
     */
    public function detail(): Json
    {
        $id = input('post.id/d');
        try {
            $log = Db::name('operation_log')->where('id', $id)->find();
            if (!$log) {
                return sendJson(null);
            }

            // 解码 JSON 字段
            if (!empty($log['request_data']) && is_string($log['request_data'])) {
                $log['request_data'] = json_decode($log['request_data'], true);
            }
            if (!empty($log['response_data']) && is_string($log['response_data'])) {
                $log['response_data'] = json_decode($log['response_data'], true);
            }

            return sendJson($log);
        } catch (Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR, false);
        }
    }
}
