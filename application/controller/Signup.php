<?php

namespace app\controller;

use think\Controller;

class Signup extends Controller
{
    public function index()
    {
        return $this->fetch('/signup');
    }
}
