<?php

namespace app\controller;

use app\BaseController;
use think\facade\View;

class ResetPassword extends BaseController
{
    public function index()
    {
        return View::fetch('/reset_password');
    }
}
