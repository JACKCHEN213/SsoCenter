<?php

namespace app\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\View;

class Index extends BaseController
{
    public function index()
    {
        $admin = Db::name('user')
            ->field(['id', 'username', 'password'])
            ->find(1);
        $apps = Db::name('site')
            ->field(['name', 'image', 'request_url', 'redirect_url', 'is_use', 'public_key', 'app_path', 'id',])
            ->where('is_del', 0)
            ->select();
        View::assign([
            'user' => $admin,
            'apps' => $apps,
        ]);
        return View::fetch('/index');
    }

    public function settings()
    {
        return $this->fetch('system_manage.settings');
    }

    public function account()
    {
        return $this->fetch('/account');
    }
}
