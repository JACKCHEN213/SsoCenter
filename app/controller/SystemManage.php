<?php

namespace app\controller;
use app\BaseController;
use think\facade\View;

class SystemManage extends BaseController
{
    public function settings()
    {
        return View::fetch('system_manage/settings');
    }

    public function account()
    {
        return View::fetch('system_manage/account');
    }

    public function notifications()
    {
        return View::fetch('system_manage/notifications');
    }
}