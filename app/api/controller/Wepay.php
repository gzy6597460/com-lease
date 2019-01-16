<?php

namespace app\api\controller;
header('Access-Control-Allow-Origin:*');
use think\Controller;
use \think\Db;
use think\Request;
use think\Loader;
use think\log;
use app\api\service\Token as tokenService;

class Wepay extends Controller
{
    // 公众号支付
    public function jspay()
    {
        if (Request::instance()->isGet()) {
            $get = Request::instance()->get();
            $token = $get['token'];
            $order_id = $get['order_id'];
            $member_info = $this->get_memberinfo($token);
            $open_id = $member_info['weixin_id'];
            $out_trade_no =$this->build_order_no();
            $up_order = db('order')->where('id',$order_id)->update(['out_trade_no'=>$out_trade_no,'update_time'=>date('Y-m-d H:i:s')]);
            $order = db('order')->where('id',$order_id)->find();
            if (empty($order)){
                return jsonOk($order_id,0,'未找到订单',true);
            }
            if ($order['real_pay'] == 0){
                $result = db('order')->where('id',$order_id)->update(['status'=>'已付款，待发货']);
                return jsonOk(null,1,'支付成功',true);
            }
            $params = [
                'body' => '租赁支付',
                'out_trade_no' => $order['out_trade_no'], //mt_rand().time(),
                'total_fee' => $order['real_pay'],
            ];
            $pay = new \wxpay\JsapiPay;
            $result = $pay->getPayParams($params,$open_id);
            jsonOk($result,0,'发起支付',true);
            //halt($result);
        }
        jsonOk(null,0,'非法请求',false,null,400);
    }


    // H5支付
    public function wappay()
    {
        $params = [
            'body' => '支付测试',
            'out_trade_no' => mt_rand().time(),
            'total_fee' => 1,
        ];

        $result = \wxpay\WapPay::getPayUrl($params);
        halt($result);
    }

    // 订单查询
    public function query()
    {
        $out_trade_no = '153294196949531025';
        $result = \wxpay\Query::exec($out_trade_no);
        halt($result);
    }

    // 退款
    public function refund()
    {
        $params = [
            'out_trade_no' => '290000985120170917160005',
            'total_fee' => 1,
            'refund_fee' => 1,
            'out_refund_no' => time()
        ];
        $result = \wxpay\Refund::exec($params);
        halt($result);
    }

    // 退款查询
    public function refundquery()
    {
        $order_no = '290000985120170917160005';
        $result = \wxpay\RefundQuery::exec($order_no);
        halt($result);
    }

    // 下载对账单
    public function download()
    {
        $result = \wxpay\DownloadBill::exec('20170923');
        echo($result);
    }

    // 通知测试
    public function notify()
    {
        //$open_id = db('order')->where('id',$member_id)->find();
        $notify = new \wxpay\Notify();
        $res= $notify->Handle();
        var_dump($res);
    }

    //获取用户信息
    private function get_memberinfo($token){
        $tokenService = new tokenService();
        $member_id = $tokenService->check($token);
        if (empty($member_id)){
            jsonOk(null,null,'登录状态失效,请重新登录。',false);
        }
        $member_info = db('member')->where('id',$member_id)->find();
        if (empty($member_info)){
            jsonOk(null,null,'未获取到用户信息',false);
        }
        return $member_info;
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