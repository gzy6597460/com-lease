<?php

namespace app\api\controller;

header('Access-Control-Allow-Origin:*');

use app\common\controller\Api;
use app\api\service\Refuel as refuelService;
use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use \think\Log;
use \think\config;
use think\Validate;

class refuel extends Api
{
    public function _initialize()
    {
        parent::_initialize();
    }

    /** 好友加油站 分享授权链接*/
    public function share_refuel()
    {
        if (Request::instance()->isGet()) {
            $get = Request::instance()->get();
            $validate = validate('Refuel');
            if (!$validate->check($get)) {
                $this->error($validate->getError(),null,null,false,400);
            }
            $Wechat = new Wechat();
            $share_url = $Wechat->refuelUrl($get['order_id']);
            if (empty($share_url)){
                $this->error('生成分享链接失败!',null,null,false,400);
            }
            $this->success('生成分享链接成功',$share_url,null,true,200);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /** 好友加油站 订单分享记录*/
    public function get_refuelLog()
    {
        if (Request::instance()->isGet()) {
            $get = Request::instance()->get();
            $validate = validate('Refuel');
            if (!$validate->check($get)) {
                $this->error($validate->getError(),null,null,false,400);
            }
            $refuelService = new refuelService();
            $result = $refuelService->getLog($get['order_id']);
            if ($result['status'] == false){
                $this->error('暂无加油记录',null,null,false,200);
            }
            $this->success($result['msg'],$result['extra'],null,true,200);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /** 好友加油站 立即加油 */
    public function refuel()
    {
        if (Request::instance()->ispost()) {
            $post = Request::instance()->post();
            $validate = validate('Refuelnow');
            if (!$validate->check($post)) {
                $this->error($validate->getError(),null,null,false,400);
            }
            $member_info = get_memberinfo($post['token']);
            $member_id = $member_info['id'];
            $order_id = $post['order_id'];
            //验证是否加过油
            $is_refuel = db('order_refuel')->where('member_id', $member_id)->where('order_id', $order_id)->find();
            if ($is_refuel) {
                $this->error('您已为该用户加过油!',null,null,false,400);
            }
            $refuelService = new refuelService();
            $result = $refuelService->add($order_id,$member_id);

            if ($result['status'] == false){
                $this->error('加油失败',$result['extra'],null,false,400);
            }
            $this->success($result['msg'],$result['extra'],null,true,201);
        }
        $this->error('无效请求!',null,null,false,400);
    }

}