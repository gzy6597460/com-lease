<?php
namespace app\api\controller;

use app\api\service\Token as tokenService;
use app\api\service\Sign as signService;
use app\common\controller\Api;
use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Log;
use \think\config;
header('Access-Control-Allow-Origin:*');

class Sign extends Api
{
    /**
     * 获取签到数据
     */
    public function get_signdata()
    {
        if (Request::instance()->isGet()) {
            $get = Request::instance()->get();
            if ((isset($get['token']) == false) || (empty($get['token']))) {
                $this->error('无效请求!',null,null,false,400);
            }
            $tokenService = new tokenService();
            $token_result = $tokenService->check($get['token']);
            if (empty($token_result)){
                $this->error('您还未登录或登录已超时',null,null,false,403);
            }
            $signService = new signService();
            $result = $signService->getData($token_result);
            if ($result) {
                $this->success('获取成功',$result,null,true,200);
            }
            $this->error('获取失败',null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }



    /**
     * 签到加积分
     */
    public function sign()
    {
        if (Request::instance()->isGet()) {
            $get = Request::instance()->get();
            if ((isset($get['token']) == false) || (empty($get['token']))) {
                $this->error('无效请求!',null,null,false,400);
            }
            $tokenService = new tokenService();
            $token_result = $tokenService->check($get['token']);
            if (empty($token_result)){
                $this->error('您还未登录或登录已超时',null,null,false,403);
            }
            $signService = new signService();
            $result = $signService->sign($token_result);
            if ($result['status']==true) {
                $this->success($result['msg'],$result['data'],$result['extra'],true,201);
            }
            $this->error($result['msg'],null,$result['extra'],false,401);
        }
        $this->error('无效请求!',null,null,false,400);
    }
}