<?php

namespace app\validate;

use think\Validate;

class Application extends Validate
{
    public function sceneUploadImage(): Application
    {
        return $this->only(['image'])
            ->append('image', ['require', 'file']);
    }
}
