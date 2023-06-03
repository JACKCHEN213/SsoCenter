<?php

namespace app\controller;

use app\common\ResponseCode;
use app\common\ResponseMessage;
use think\Controller;
use think\Db;
use think\db\Where;

class User extends Controller
{
    public function index()
    {
        $page = input('page/d') ?: 1;
        $limit = input('limit/d') ?: 10;
        $su = input('su/s');
        $where = new Where([
            'is_del' => 0,
        ]);
        if ($su) {
            $where['username'] = ["LIKE", "%$su%"];
        }
        $userModel = Db::name('user')
            ->where($where);
        $count = (clone $userModel)->count();
        if ($page && $limit) {
            $userModel->page($page, $limit);
        }
        $data = $userModel->select();
        return $this->assign([
            'users' => $data,
            'count' => $count,
            'su' => $su,
            'page' => $page,
            'limit' => $limit,
            'lastPage' => ceil($count / $limit),
        ])->fetch('/user');
    }

    public function doAdd($username, $password, $email)
    {
        try {
            $user = Db::name('user')->where('username', $username)->find();
            if ($user) {
                return sendJson("用户已经存在了", ResponseCode::$USER_EXISTS, ResponseMessage::$USER_EXISTS, false);
            }
            Db::name('user')->insert([
                'username' => $username,
                'password' => md5($password),
                'email' => $email,
            ]);
            return sendJson("用户{$username}添加成功", ResponseCode::$ADD_USER_SUCCESS, ResponseMessage::$ADD_USER_SUCCESS);
        } catch (\Exception $e) {
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
            if (!Db::name('user')->find($user_id)) {
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
            $user_data = Db::name('user')->find($user_id);
            if (!$user_data) {
                return sendJson(
                    '用户不存在',
                    ResponseCode::$JWT_ERROR,
                    ResponseMessage::$JWT_ERROR,
                    false
                );
            }
            Db::name('user')->where('id', $user_id)->update([
                'is_del' => 1,
            ]);
            return sendJson(
                "用户{$user_data['username']}删除成功",
                ResponseCode::$DELETE_USER_SUCCESS,
                ResponseMessage::$DELETE_USER_SUCCESS
            );
        } catch (\Exception $e) {
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$UNKNOWN_ERROR, ResponseMessage::$UNKNOWN_ERROR, false);
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
        $columnDesc = ['A' => '用户名', 'B' => '密码', 'C' => '邮箱'];
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
            $addRet = $this->doAdd($user['A'], $user['B'], md5($user['C']));
            $result[] = "第{$rowIndex}行, " . $addRet->getData()['result'];
        }
        return sendJson($result, ResponseCode::$BATCH_ADD_SUCCESS, ResponseMessage::$BATCH_ADD_SUCCESS);
    }
}
