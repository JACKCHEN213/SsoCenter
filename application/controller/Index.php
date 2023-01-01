<?php

namespace app\controller;

use think\Controller;
use think\Db;

class Index extends Controller
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
        return $this->assign([
            'user' => $admin,
            'apps' => $apps,
        ])->fetch();
    }

    public function settings()
    {
        return $this->fetch();
    }

    public function account()
    {
        return $this->fetch();
    }
}
