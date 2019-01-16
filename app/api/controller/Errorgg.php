<?php
namespace app\api\controller;

use think\Controller;
use \think\Db;
use \think\Cookie;
use \think\Session;
use \think\Cache;
class Errorgg extends Controller
{
    public function index()
    {
        return $this->fetch();
    }
}