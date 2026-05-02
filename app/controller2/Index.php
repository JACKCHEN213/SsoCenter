<?php

namespace app\controller;

use app\BaseController;

class Index extends BaseController
{
    // public function index()
    // {
    //     return '<style>*{ padding: 0; margin: 0; }</style><iframe src="https://www.thinkphp.cn/welcome?version=' . \think\facade\App::version() . '" width="100%" height="100%" frameborder="0" scrolling="auto"></iframe>';
    // }

    // public function hello($name = 'ThinkPHP8')
    // {
    //     return 'hello,' . $name;
    // }

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
        ])->fetch('/index');
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
