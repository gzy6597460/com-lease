<?php

namespace app\api\controller;

header('Access-Control-Allow-Origin:*');

use app\api\service\Order as orderService;
use app\api\service\Token as tokenService;
use app\common\controller\Api;
use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use \think\Log;
use \think\config;
use think\Validate;

class Order extends Api
{
    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 获取单个订单信息(续租)
     */
    public function get_reletOrder()
    {
        $order_id = input('get.order_id');
        $join = [
            ['goods g', 'a.goods_id = g.id']
        ];
        $order_data = db('order')->alias('a')
            ->field('g.goods_name,a.id as order_id,out_trade_no,g.id as good_id,g.minimum_price,buy_name,lease_months,buy_num,goods_path,parameter,total_fee,status,start_time,end_time,a.create_time,address')
            ->join($join)
            ->where('a.id', $order_id)
            ->find();

        if ($order_data['status'] == '未付款') {
            $order_data['remain_time'] = strtotime("+1 day", strtotime($order_data['create_time'])) - time();
        }
        $meals = \db('goods_meal')->where('goods_id', $order_data['good_id'])->where('type', 'in', [1, 2])->select();
        return json(['data' => $order_data, 'meals' => $meals]);
    }

    /**
     * 获取单个订单信息(正常)
     */
    public function get_order()
    {
        $order_id = input('get.order_id');
        $join = [
            ['goods g', 'a.goods_id = g.id']
        ];
        $order_data = db('order')->alias('a')->field('g.goods_name,a.id as order_id,out_trade_no,g.id as good_id,g.minimum_price,buy_name,lease_months,buy_num,goods_path,parameter,total_fee,status,start_time,end_time,a.create_time,address')->join($join)->where('a.id', $order_id)->find();
        if ($order_data['status'] == '未付款') {
            $order_data['remain_time'] = strtotime("+1 day", strtotime($order_data['create_time'])) - time();
        }
        $meals = \db('goods_meal')->where('goods_id', $order_data['good_id'])->where('type', 0)->select();
        return json(['data' => $order_data, 'meals' => $meals]);
    }

    /** 用户_根据订单查快递 */
    public function query_express()
    {
        if (Request::instance()->isGet()) {
            $get = Request::instance()->get();
            if ((isset($get['order_id']) == false) || (empty($get['order_id']))) {
                jsonOk(null, null, '未获取到订单信息', false);
            }
            $orderService = new orderService();
            $result = $orderService->getExpress($get['order_id']);
            if ($result['status'] == true) {
                $this->success($result['msg'],$result['data'],$result['extra'],true,200);
            }
            $this->error($result['msg'],$result['data'],$result['extra'],false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /** 创建租赁订单*/
    public function build_order()
    {
        if (Request::instance()->ispost()) {
            $post = Request::instance()->post();
            $token = $post['token'];
            $member_info = get_memberinfo($token);
            if (isset($post['remark']) == false) {
                $post['remark'] = null;
            }
            //验证
            $validate = validate('Order');
            if (!$validate->scene('add')->check($post)) {
                $this->error($validate->getError(),null,null,false,422);
            }
            $params = [
                'member_info' => $member_info,
                'goods_id' => $post['goods_id'],//商品id
                'meal_id' => $post['meal_id'],//套餐id
                'addr_id' => $post['addr_id'],//地址id
                'buy_num' => $post['buy_num'],//数量
                'remark' => $post['remark'],//备注
                'total_fee' => $post['total_fee'],//订单总价
                'real_pay' => $post['real_pay'],//实际支付
                'is_pledge' => $post['is_pledge'],//是否免押金
                'deduction_score' =>$post['deduction_score'],//抵扣积分
            ];
            $orderService = new orderService();
            $result = $orderService->build_general($params);
            switch ($result['status']){
                case true:
                    $this->success($result['msg'],$result['extra'],null,true,200);
                    break;
                case false:
                    $this->error($result['msg'],$result['extra'],null,false,400);
                    break;
                default:
                    $this->error('订单异常',null,null,false,400);
            }
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /** 订单确认收货*/
    public function confirm_order()
    {
        if (Request::instance()->isPost()) {
            $post = Request::instance()->post();
            $validate = validate('Order');
            if (!$validate->scene('confirm')->check($post)){
                $this->error($validate->getError(),null,null,false,422);
            }
            $tokenService = new tokenService();
            $token_result = $tokenService->check($post['token']);
            if ($token_result == false){
                $this->error('您还未登录或登录已超时',null,null,false,401);
            }
            $member_id = $token_result;
            $order_info = \db('Order')->where('id',$post['order_id'])->find();
            if ($order_info['member_id'] != $member_id){
                $this->error('订单信息异常,请联系客服',null,null,false,400);
            }
            $orderService = new orderService();
            $result = $orderService->signIn($post['order_id']);
            if ($result['status'] == true) {
                $this->success($result['data'],$result['extra'],null,true,200);
            }
            $this->error($result['data'],null,null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /**
     * 取消订单
     */
    public function cancel_order()
    {
        if (Request::instance()->isPost()) {
            $post = Request::instance()->post();
            $validate = validate('Order');
            if (!$validate->scene('cancel')->check($post)){
                $this->error($validate->getError(),null,null,false,422);
            }
            $tokenService = new tokenService();
            $token_result = $tokenService->check($post['token']);
            if ($token_result == false){
                $this->error('您还未登录或登录已超时',null,null,false,401);
            }
            $member_id = $token_result;
            $orderService = new orderService();
            $result = $orderService->cancel($post['order_id'],$member_id);
            if ($result['status'] == true) {
                $this->success($result['data'],$result['extra'],null,true,200);
            }
            $this->error($result['data'],null,null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /**
     * 删除订单
     */
    public function del_order()
    {
        if (Request::instance()->isPost()) {
            $post = Request::instance()->post();
            $validate = validate('Order');
            if (!$validate->scene('del')->check($post)){
                $this->error($validate->getError(),null,null,false,422);
            }
            $tokenService = new tokenService();
            $token_result = $tokenService->check($post['token']);
            if ($token_result == false){
                $this->error('您还未登录或登录已超时',null,null,false,401);
            }
            $member_id = $token_result;
            $orderService = new orderService();
            $result = $orderService->del($post['order_id'],$member_id);
            if ($result['status'] == true) {
                $this->success($result['data'],$result['extra'],null,true,200);
            }
            $this->error($result['data'],null,null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /**
     * 更换设备/归还
     */
    public function after_sale()
    {
        if (Request::instance()->isPost()) {
            $post = Request::instance()->post();
            $validate = validate('Order');
            if (!$validate->scene('change')->check($post)){
                $this->error($validate->getError(),null,null,false,422);
            }
            $tokenService = new tokenService();
            $token_result = $tokenService->check($post['token']);
            if ($token_result == false){
                $this->error('您还未登录或登录已超时',null,null,false,401);
            }
            $member_id = $token_result;
            $order_info = \db('order')->where('id', $post['order_id'])->where('member_id',$member_id)->find();
            if (empty($order_info)) {
                $this->error('该订单并非您的订单,请重新登录后操作',null,null,null,false,401);
            }
            $orderService = new orderService();
            switch ($post['change_id']){
                case 0:
                    //更换设备
                    $result = $orderService->change_machine($post['order_id'],$member_id,$post['express_id'],$post['remark']);
                    break;
                case 1:
                    //归还设备
                    $result = $orderService->return_machine($post['order_id'],$member_id,$post['express_id'],$post['remark']);
                    break;
                default:
                    $this->error('请选择售后类型',null,null,null,false,400);
                    break;
            }
            if ($result['status'] == true) {
                $this->success($result['data'],$result['extra'],null,true,200);
            }
            $this->error($result['data'],$result['extra'],null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }
}