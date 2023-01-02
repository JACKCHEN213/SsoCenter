<?php

namespace app\controller;

use think\Controller;

class ResetPassword extends Controller
{
    public function index()
    {
        return $this->fetch('/reset_password');
    }
}
