<?php

namespace app\validate;

use think\File;
use think\Validate;

class Application extends Validate
{
    protected function checkImage(File $image)
    {
        if (!$image->checkExt(config('common.IMAGE_EXT'))) {
            return 'file_data不是一个有效的的图片后缀名';
        }
        if (!$image->checkSize(config('common.MAX_IMAGE_SIZE'))) {
            return 'file_data大小最大为' . formatFileSize(config('common.MAX_IMAGE_SIZE'))
                . ', 当前为: ' . formatFileSize($image->getSize());
        }
        if (!is_dir(config('common.APP_IMAGE_PREFIX'))) {
            mkdir(config('common.APP_IMAGE_PREFIX'), 0777, true);
        }
        return true;
    }

    public function sceneAdd(): Application
    {
        return $this->only(['app_redirect_url', 'app_name', 'app_request_url', 'app_img_url'])
            ->append('app_name', ['require'])
            ->append('app_request_url', ['require'])
            ->append('app_redirect_url', ['require']);
    }

    public function sceneUploadImage(): Application
    {
        return $this->only(['file_data'])
            ->append('file_data', ['require', 'file', 'checkImage']);
    }

    public function sceneDeleteUploadedImage(): Application
    {
        return $this->only(['image_url'])
            ->append('image_url', ['require']);
    }

    public function sceneDelete(): Application
    {
        return $this->only(['id'])
            ->append('id', ['require']);
    }

    public function sceneUpdate(): Application
    {
        return $this->only(['id', 'app_redirect_url', 'app_name', 'app_request_url', 'app_img_url'])
            ->append('id', ['require'])
            ->append('app_name', ['require'])
            ->append('app_request_url', ['require'])
            ->append('app_redirect_url', ['require']);
    }
}
