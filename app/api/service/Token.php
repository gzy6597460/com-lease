<?php
namespace app\api\service;

use think\Controller;
use think\Db;
use think\Log;
use think\model;

class Token extends Controller
{
    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Token');
    }

    //获取token信息
    public function get($member_id)
    {
        $is_token = $this->model->where('member_id', $member_id)->find();
        if ($is_token){
            if ($is_token['expiretime'] < time()){
                $token = $this->refresh($member_id);
            }else{
                $token = $is_token['token'];
            }
        } else {
            $token =$this->build($member_id);
        }
        if (empty($token)){
            return null;
        }
        return $token;
    }


    //生成token
    public function build_token($member_id)
    {
        $is_token = $this->model->where('member_id', $member_id)->find();
        if ($is_token){
            if ($is_token['expiretime'] < time()){
                $token = $this->refresh($member_id);
            }else{
                $token = $is_token['token'];
            }
        } else {
            $token =$this->build($member_id);
        }
        if (empty($token)){
            return null;
        }
        return $token;
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
            return false;
        }
        $tokenInfo = $tokenInfo->toArray();
        if ($tokenInfo['expiretime'] < time()){
            return false;
        }
        return $tokenInfo['member_id'];
    }
}