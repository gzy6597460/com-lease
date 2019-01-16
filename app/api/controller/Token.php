<?php
namespace app\api\controller;

use app\common\controller\Api;
use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Log;
use \think\config;

class Token extends Api
{

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Token');
    }

    /**
     * 创建Token
     *
     */
    public function build($member_id)
    {
        $make_token = settoken($member_id);
        $res = $this->model->allowField(true)->save([
            'token'  => $make_token,
            'createtime' => time(),
            'expiretime' => time() + 86400,
            'member_id' => $member_id
        ]);
        return $make_token;
    }

    /**
     * 刷新Token
     *
     */
    public function refresh($member_id)
    {
        //$member_id = Request::instance()->post('member_id');
        $make_token = settoken($member_id);
        $res = $this->model->allowField(true)->save([
            'token'  => $make_token,
            'expiretime' => time() + 86400
        ],['member_id' => $member_id]);
        if (empty($res)){
            return false;
        }
        return $make_token;
    }


    /**
     * 检测Token是否过期
     */
    public function check($token)
    {
        $tokenInfo = $this->model->where('token',$token)->find();
        if (empty($tokenInfo)){
            $this->error('您还未登录,请重新登录',null,null,false,404);
        }
        $tokenInfo = $tokenInfo->toArray();
        if ($tokenInfo['expiretime'] < time()){
            $this->error('登录已超时,请重新登录',null,null,false,401);
        }
        return $tokenInfo['member_id'];
    }
}