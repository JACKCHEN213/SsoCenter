<?php

namespace app\controller;

use app\common\Key;
use app\common\ResponseCode;
use app\common\ResponseMessage;
use Exception;
use think\Controller;
use think\Db;
use think\response\Json;

class Application extends Controller
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

    final public function add(): Json
    {
        $add_data = [
            'name' => input('post.app_name'),
            'request_url' => input('post.app_request_url'),
            'redirect_url' => input('post.app_redirect_url'),
            'image' => input('post.app_img_url', '') ?: null,
        ];
        try {
            $site_id = Db::name('site')
                ->where('is_del', 0)
                ->where('name', $add_data['name'])
                ->where('request_url', $add_data['request_url'])
                ->where('redirect_url', $add_data['redirect_url'])
                ->value('id');
            if ($site_id) {
                return sendJson($site_id, ResponseCode::$OK, ResponseMessage::$DATA_ALREADY_EXIST);
            }
            Db::startTrans();
            $site_id = Db::name('site')->insertGetId($add_data);
            $this->generatePublicKey($site_id);
            Db::commit();

            return sendJson($site_id);
        } catch (Exception $e) {
            Db::rollback();
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR);
        }
    }

    final public function uploadImage(): Json
    {
        $image = acceptFile('file_data');
        $file = $image->move(config('common.APP_IMAGE_PREFIX'), 'app_image_' . time());
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
        return sendJson('删除成功');
    }

    final public function update(): Json
    {
        $id = input('put.id/d');
        $update_data = [
            'name' => input('post.app_name'),
            'request_url' => input('post.app_request_url'),
            'redirect_url' => input('post.app_redirect_url'),
            'image' => input('post.app_img_url', '') ?: null,
        ];
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
            Db::commit();

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
            $public_key_file = Db::name('site')->where('id', $id)->value('public_key');
            $filepath = config('common.APP_KEY_PATH') . '/' . $public_key_file;
            if (is_file($filepath)) {
                unlink($filepath);
            }
            Db::name('site')->where('id', $id)->setField(['is_del' => 1]);
            Db::commit();

            return sendJson('删除成功');
        } catch (Exception $e) {
            Db::rollback();
            recordLog($e, 'error');
            return sendJson($e->getMessage(), ResponseCode::$DB_ERROR, ResponseMessage::$DB_ERROR);
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
}
