<?php

namespace app\controller;
use think\Controller;

class SystemManage extends Controller
{
    public function settings()
    {
        return $this->fetch('system_manage/settings');
    }

    public function account()
    {
        return $this->fetch('system_manage/account');
    }

    public function notifications()
    {
        return $this->fetch('system_manage/notifications');
    }
}