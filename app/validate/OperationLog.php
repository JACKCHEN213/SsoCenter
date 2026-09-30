<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

/**
 * 操作日志验证器
 *
 * @package app\validate
 * @author  SSO Center
 * @since   1.0.0
 */
class OperationLog extends Validate
{
    protected $rule = [
        'id' => 'require|integer|gt:0',
        'module' => 'in:auth,app,user',
        'action' => 'max:64',
        'operator_name' => 'max:64',
        'start_time' => 'date',
        'end_time' => 'date',
        'page' => 'integer|gt:0',
        'page_size' => 'integer|gt:0|lt:100',
    ];

    protected $message = [
        'id.require' => '日志 ID 不能为空',
        'id.integer' => '日志 ID 必须是整数',
        'id.gt' => '日志 ID 必须大于 0',
        'module.in' => '模块类型无效',
        'page.integer' => '页码必须是整数',
        'page.gt' => '页码必须大于 0',
        'page_size.integer' => '每页条数必须是整数',
        'page_size.gt' => '每页条数必须大于 0',
        'page_size.lt' => '每页条数必须小于 100',
    ];

    /**
     * 详情场景
     */
    public function sceneDetail(): OperationLog
    {
        return $this->only(['id']);
    }

    /**
     * 列表场景
     */
    public function sceneList(): OperationLog
    {
        return $this->only(['module', 'action', 'operator_name', 'start_time', 'end_time', 'page', 'page_size']);
    }
}
