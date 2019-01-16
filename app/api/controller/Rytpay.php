<?php

namespace app\api\controller;
header('Access-Control-Allow-Origin:*');
use app\common\controller\Api;
use think\Controller;
use \think\Db;
use \think\Log;
use think\Validate;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use \think\Request;
use \think\config;
use think\Loader;
use app\api\service\Token as tokenService;
use app\api\service\Order as orderService;
use app\api\service\Rytpay as rytpayService;
use app\api\controller\Wechat;


class Rytpay extends Api
{
    /** 获取已绑定银行卡列表*/
    public function getBankcard()
    {
        if (Request::instance()->isGet()) {
            $get = Request::instance()->get();
            if (!isset($get['token'])){
                $this->error('无效请求!',null,null,false,400);
            }
            $tokenService = new tokenService();
            $member_id = $tokenService->check($get['token']);
            $rytpayService = new rytpayService();
            $result = $rytpayService->getSignCard($member_id);
            if ($result['status'] == true){
                $this->success($result['msg'],$result['data'],null,true,200);
            }else{
                $this->error($result['msg'],$result['data'],null,false,200);
            }
            $this->error('获取失败',null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /** 签约申请*/
    public function signUp()
    {
        if (Request::instance()->ispost()) {
            $post = Request::instance()->post();
            //验证
            $validate = validate('Rytpay');
            if (!$validate->scene('sign')->check($post)) {
                $this->error($validate->getError(),null,null,false,422);
            }
            $tokenService = new tokenService();
            $member_id = $tokenService->check($post['token']);
            $params = [
                'member_id'=> $member_id,
                'bankNo' => $post['bankNo'],
                'cardType' => $post['cardType'],
                'phoneNo' => $post['phoneNo'],
                'realName' => $post['realName'],
                'certNo' => $post['certNo'],
                'idType' => $post['idType'],
                'accProp' => $post['accProp'],
                'province' => $post['province'],
                'city' => $post['city'],
                'branchName' => $post['branchName'],
            ];
            $rytpayService = new rytpayService();
            $result = $rytpayService->signUp($params);
            if ($result['status'] == true){
                $this->success($result['msg'],$result['data'],$result['extra'],true,200);
            }else{
                $this->error($result['msg'],$result['data'],null,false,400);
            }
            $this->error('发送失败',null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /** 签约确认*/
    public function signConfirm()
    {
        if (Request::instance()->ispost()) {
            $post = Request::instance()->post();
            //验证
            $validate = validate('Rytpay');
            if (!$validate->scene('signConfirm')->check($post)) {
                $this->error($validate->getError(),null,null,false,422);
            }
            $params = [
                'smsCode' => $post['smsCode'],
                'protocolReqNo' => $post['protocolReqNo'],
            ];
            $rytpayService = new rytpayService();
            $result = $rytpayService->confirm($params);
            if ($result['status'] == true){
                $this->success('绑定成功',$result['msg'],$result['data'],true,200);
            }else{
                $this->error('绑定失败',$result['msg'],null,false,400);
            }
            $this->error('绑定失败',null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /** 解约*/
    public function release()
    {
        if (Request::instance()->ispost()) {
            $post = Request::instance()->post();
            //验证
            //            $validate = validate('Rytpay');
            //            if (!$validate->scene('signConfirm')->check($post)) {
            //                $this->error($validate->getError(),null,null,false,422);
            //            }
            $params = [
                'protocolNo' => $post['protocolNo'],
            ];
            $rytpayService = new rytpayService();
            $result = $rytpayService->release($params);
            return json($result);
            exit;
            switch ($result['status']){
                case true:
                    $this->success($result['msg'],$result['extra'],null,true,200);
                    break;
                case false:
                    $this->error($result['msg'],$result['extra'],null,false,400);
                    break;
                default:
                    $this->error('申请失败',null,null,false,400);
            }
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /** 单笔代扣*/
    public function payOne()
    {
        if (Request::instance()->ispost()) {
            $post = Request::instance()->post();
            //验证
            $validate = validate('Rytpay');
            if (!$validate->scene('payOne')->check($post)) {
                $this->error($validate->getError(),null,null,false,422);
            }
            $params = [
                'orderNo' => $post['orderNo'],
                'txnAmt' => $post['txnAmt'],
                'businessCode' => "10702",
                'accProp' => "0",
                'accNo' => $post['accNo'],
                'realName' => $post['realName'],
                'province' => $post['province'],
                'city' => $post['city'],
                'branchName' => $post['branchName'],
            ];
            $rytpayService = new rytpayService();
            $result = $rytpayService->pay_one($params);
            dump($result);
            exit;
            if ($result['tranStatus'] == "0000"){
                $this->success($result['tranInfo'],$result,null,true,200);
            }else{
                $this->error($result['tranInfo'],$result,null,false,400);
            }
            $this->error('支付失败',null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    /** 银行卡支付*/
    public function bankPay()
    {
        if (Request::instance()->ispost()) {
            $post = Request::instance()->post();
            //验证
            $validate = validate('Rytpay');
            if (!$validate->scene('bankPay')->check($post)) {
                $this->error($validate->getError(),null,null,false,422);
            }
            $tokenService = new tokenService();
            $member_id = $tokenService->check($post['token']);
            $bank_info = \db('member_bank')->where('id',$post['bankcard_id'])->find();
            $order_info = \db('order')->where('id',$post['order_id'])->where('member_id',$member_id)->find();
            if (empty($order_info)){
                $this->error('未找到该订单',null,null,false,422);
            }
            if ($order_info['real_pay']<10){
                $this->error('支付金额过低,请使用其他方式支付',null,null,false,422);
            }
            $params = [
                'orderNo' => $order_info['inside_order_no'],
                'txnAmt' => $order_info['real_pay'],
                'businessCode' => $bank_info['businessCode'],
                'accProp' => $bank_info['accProp'],
                'accNo' => $bank_info['bankNo'],
                'realName' => $bank_info['realName'],
                'province' => $bank_info['province'],
                'city' => $bank_info['city'],
                'branchName' => $bank_info['branchName'],
                'cardType' => $bank_info['cardType'],
            ];
            $rytpayService = new rytpayService();
            $result = $rytpayService->bank_pay($params);
            if ($result['status'] === true){
                $orderService = new orderService();
                $nofity_result = $orderService->bank_notify($post['order_id']);
                if ($nofity_result['status']==true){
                    $this->success($result['msg'].','.$nofity_result['msg'],$result['data'],$result['extra'],true,200);
                }else{
                    $this->success($result['msg'].','.$nofity_result['msg'],$result['data'],$nofity_result['extra'],true,200);
                }

            }else{
                $this->error($result['msg'],$result['data'],null,false,401);
            }
            $this->error('支付失败',null,null,false,400);
        }
        $this->error('无效请求!',null,null,false,400);
    }

    public function Test()
    {
        $a = '15645645.0';
        $b = '15645645';
        if ($a == $b){
            echo 1;
        }else{
            echo 0;
        }
    }

    public function notify($order){
        // 例如:
        $result = db('order')->where('id', $order['id'])->update([
            'status' => '已付款，待发货',
            'trade_state_desc' => '订单支付成功',
            'time_end' => date('Y-m-d H:i:s')
        ]);
        if ($order['order_type'] == '续租订单') {
            $up_order = db('order')->where('id', $order['id'])->update(['status' => '租赁中']);
            $up_old = db('order')->where('inside_order_no', $order['old_order_no'])->update(['status' => '续租中']);
        }
        //商品信息
        $goods_info = db('goods')->where('id', $order['goods_id'])->find();
        //用户信息
        $member_info = db('member')->where('id', $order['member_id'])->find();
        //用户分享体系(推广用户加积分)
        $referee_id = $member_info['referee'];
        //$referee_id = db('member')->where('id',$order['member_id'])->value('referee');
        add_score($referee_id, 3000, '推荐的用户下单送积分');
        //代理商分销体系
        $is_agent_share = $member_info['is_agent_share'];
        if (($is_agent_share == 1)&&($order['real_pay'] >= 10)) {
            $agent_super = db('agent')->where('id', $member_info['agent_super_id'])->find();
            $agent_one = db('agent')->where('id', $member_info['agent_one_id'])->find();
            $agent_two = db('agent')->where('id', $member_info['agent_two_id'])->find();
            $agent_three = db('agent')->where('id', $member_info['agent_three_id'])->find();
            if (empty($agent_one)){
                $agent_one['fee'] = 0;
            }
            if (empty($agent_two)){
                $agent_two['fee'] = 0;
            }
            if (empty($agent_three)){
                $agent_three['fee'] = 0;
            }
            $agent_super_income = $order['real_pay'] * ($agent_super['fee'] - $agent_one['fee']);
            $agent_one_income = $order['real_pay'] * ($agent_one['fee'] - $agent_two['fee']);
            $agent_two_income = $order['real_pay'] * ($agent_two['fee'] - $agent_three['fee']);
            $agent_three_income = $order['real_pay'] * ($agent_three['fee']);
            $agent_data = [
                'order_id' => $order['id'],
                'agent_super_id' => $member_info['agent_super_id'],
                'agent_one_id' => $member_info['agent_one_id'],
                'agent_two_id' => $member_info['agent_two_id'],
                'agent_three_id' => $member_info['agent_three_id'],
                'agent_super_fee' => $agent_super['fee'],
                'agent_one_fee' => $agent_one['fee'],
                'agent_two_fee' => $agent_two['fee'],
                'agent_three_fee' => $agent_three['fee'],
                'agent_super_income' => $agent_super_income,
                'agent_one_income' => $agent_one_income,
                'agent_two_income' => $agent_two_income,
                'agent_three_income' => $agent_three_income,
                'create_time' => time(),
                'update_time' => time(),
            ];
            $agent_result = db('agent_charge')->insert($agent_data);
            Log::record('特级代理信息：' . var_export($agent_super, true) . '一级代理:' . var_export($agent_one, true) . '二级代理：' . var_export($agent_two, true), 'info');
        }
        Log::record('用户id' . var_export($order['member_id'], true) . '订单id:' . var_export($order['id'], true) . '支付信息：' . var_export($result, true), 'info');
//        if ($result) {
//            //推送微信消息
//            $Wechat = new Wechat();
//            $url = $Wechat->toOrderUrl('myOrder');
//            $real_pay = $order['real_pay'] / 100;
//            $message_data = [
//                "touser" => "{$member_info['weixin_id']}",
//                "template_id" => "0HRJBR6iZo7SL74ZhL1oWPFe9l5x5wM_dqoZQyHLrpU",
//                "url" => $url,
//                "topcolor" => "#FF0000",
//                "data" => [
//                    'first' => [
//                        "value" => "您的订单已支付成功。 >>查看订单详情",
//                        "color" => "#173177"
//                    ],
//                    'keyword1' => [
//                        "value" => $member_info['name'],
//                        "color" => "#173177"
//                    ],
//                    'keyword2' => [
//                        "value" => $order['inside_order_no'],
//                        "color" => "#173177"
//                    ],
//                    'keyword3' => [
//                        "value" => "￥" . $real_pay,
//                        "color" => "#173177"
//                    ],
//                    'keyword4' => [
//                        "value" => $goods_info['goods_name'],
//                        "color" => "#173177"
//                    ],
//                    'remark' => [
//                        "value" => "如有问题请致电小猪圈客服热线400-990-2728或直接在微信留言，客服在线时间为工作日10:00——18:00.客服人员将第一时间为您服务。",
//                        "color" => "#173177"
//                    ],
//                ]
//            ];
//            $Wechat->sendTemplateMessage($message_data);
//        }
    }
    /**
     * 生成订单号
     */
    private function build_order_no()
    {
        //time() date('Ymd')
        $no = time() . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        //检测是否存在
        $info = db('order')->where('id', $no)->find();
        (!empty($info)) && $no = $this->build_order_no();
        return $no;
    }

}