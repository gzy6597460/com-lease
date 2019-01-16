<?php

namespace app\api\service;

use app\api\controller\Wechat;
use app\api\controller\Welogin;
use think\Controller;
use \think\config;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use \think\Log;
use \think\Db;

class Order extends Controller
{
    /** 创建租赁订单*/
    public function build_general($params)
    {
        //接收参数
        $member_info = $params['member_info'];
        $member_id = $member_info['id'];//用户id
        $goods_id = $params['goods_id'];//商品id
        $meal_id = $params['meal_id'];//套餐id
        $buy_num = $params['buy_num'];//数量
        $remark = $params['remark'];//备注
        $is_pledge = $params['is_pledge'];//是否免押金(0:需押金,1免押金)
        //$post_pledgeCash = $post['pledge_cash'];//前端押金金额

        //查询所需信息操作
        $goods_info = db('goods')->where('id', $goods_id)->find();//获取商品价格信息
        $meal_info = db('goods_meal')->where('id', $meal_id)->find();//获取商品套餐信息
        $address_info = db('member_addr')->where('id', $params['addr_id'])->where('member_id', $member_id)->find(); //获取用户收货地址信息
        //验证抵扣积分是否溢出
        if ($params['deduction_score'] > 0) {
            $is_score_deduction = 1;
            $before_score = db('member_score')->where('member_id', $member_info['id'])->value('get_score');
            if (empty($before_score) || ($before_score < $params['deduction_score'])) {
                return array('status' => false, 'msg' => '抵扣积分超过剩余积分,请修改后再试', 'extra' => null);
            }
        } else {
            $is_score_deduction = 0;
        }
        //生成订单号
        $inside_order_no = $this->build_order_no();
        //检查价格
        $check_price = $this->check_price($params, $goods_info, $meal_info, $is_pledge);
        if ($check_price['status'] == false) {
            return array('status' => false, 'msg' => $check_price['msg'], 'extra' => $check_price['extra']);
        }
        $price_info = $check_price['extra'];
        //订单未出错,开始创建订单及后续操作
        Db::startTrans();
        try {
            //创建订单
            $order_id = Db::name('order')->insertGetId([
                'inside_order_no' => $inside_order_no,
                'member_id' => $member_id,
                'buy_name' => $address_info['receiver_name'],
                'phone' => $address_info['receiver_phone'],
                'address' => $address_info['street'],
                'is_pledge' => $is_pledge,
                'pledge_cash' => $price_info['pledge_cash'],
                'total_fee' => $price_info['total_fee'],
                'real_pay' => $price_info['real_pay'],
                'is_score_deduction' => $is_score_deduction,
                'deduction_score' => $price_info['deduction_score'],
                'meal_id' => $meal_id,
                'goods_id' => $meal_info['goods_id'],
                'lease_months' => $meal_info['meal_months'],
                'buy_num' => $buy_num,
                'remark' => $remark,
                'create_time' => date('Y-m-d H:i:s'),
                'extra_score' => $price_info['extra_score'],
                'status' => '未付款',
            ]);
            if ($price_info['deduction_score'] > 0) {
                $channel = "租赁订单号($inside_order_no)租赁积分抵扣";
                $before_score = Db::name('member_score')->where('member_id', $member_id)->value('get_score');
                Db::name('member_score')->where('member_id', $member_id)->setDec('get_score', $price_info['deduction_score']);
                $after_score = Db::name('member_score')->where('member_id', $member_id)->value('get_score');
                //添加积分日志
                Db::name('score_history')->insert([
                    'channel' => $channel,
                    'before' => $before_score,
                    'after' => $after_score,
                    'member_id' => $member_id,
                    'score' => '-' . $price_info['deduction_score'],
                    'create_time' => date('Y-m-d H:i:s')
                ]);
            }
            Log::record('创建订单成功...', 'info');
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::record('创建订单失敗...', 'info');
            return array('status' => false, 'msg' => '订单创建失败', 'extra' => $e->getMessage());
        }
        //        //未付款公众号消息发送推送
        //        $nopay_message = config::get('nopay_message');
        //        $nopay_message['touser'] = $post_data['member_info']['weixin_id'];
        //        $nopay_message['data']['ordertape']['value'] = date('Y-m-d H:i:s');
        //        $nopay_message['data']['ordeID']['value'] = $inside_order_no;
        //        $Wechat = new Wechat();
        //        $Wechat->sendTemplateMessage($nopay_message);
        return array('status' => true, 'msg' => '订单创建成功', 'extra' => $order_id);
    }

    /* 检查价格*/
    private function check_price($data, $goods_info, $meal_info, $is_pledge)
    {
        $deduction_score = $data['deduction_score'];//抵扣积分
        $buy_num = $data['buy_num'];//数量
        $post_realPay = $data['real_pay'];//前端计算的实际支付
        $post_totalFee = $data['total_fee'];//前端计算的总金额
        //押金
        if ($is_pledge == 1) {
            $pledge_cash = 0;//无押金
        } else {
            $pledge_cash = $goods_info['pledge_cash'];//商品押金
        }
        //进行价格验证
        $total_price = $meal_info['total_price'] + $pledge_cash;//订单总价(租金+服务费)+押金
        $total_fee = round(($buy_num * $total_price) * 100, 2);//订单总金额
        $real_pay = $total_fee - $deduction_score;//实际支付(扣除积分)
        $extra_score = round($meal_info['total_price']);

        if (($real_pay != (round($post_realPay * 100, 2))) || $total_fee != (round($post_totalFee * 100, 2)) || $real_pay < 0) {
            Log::record('订单价格出错....后端实际支付:' . var_export($real_pay, true)
                . '前端实际支付:' . var_export((round($post_realPay, 2) * 100), true)
                . '后端总金额:' . var_export($total_fee, true)
                . '前端总金额:' . var_export($post_totalFee, true), 'info');
            return array('status' => false, 'msg' => '付款失败,订单状态异常,请查询后再试', 'extra' => '订单价格出错');
        }
        $data = [
            'total_fee' => $total_fee,
            'pledge_cash' => $pledge_cash,
            'real_pay' => $real_pay,
            'deduction_score' => $deduction_score,
            'extra_score' => $extra_score
        ];
        return array('status' => true, 'msg' => '订单价格审核通过', 'extra' => $data);
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

    /** 查询快递*/
    public function getExpress($order_id)
    {
        $express_info = db('order_express')->where('order_id', $order_id)->find();
        $order_info = db('order')->where('id', $order_id)->find();
        if (empty($express_info)) {
            return array('status' => true, 'msg' => '查询成功', 'data' => '暂无物流信息', 'extra' => $order_info['update_time']);
        }
        //缓存
        $info = Cache::get($order_id . 'express' . $express_info['express_num']);
        if (empty($info)) {
            $info = queryExpress($express_info['express_num']);
            $info = object_array(json_decode($info));
            if ($info['state'] == 3) {
                Cache::set($order_id . 'express' . $express_info['express_num'], $info, 432000);
            } else {
                Cache::set($order_id . 'express' . $express_info['express_num'], $info, 3600);
            }
        }
        switch ($info['code']) {
            case "OK":
                return array('status' => true, 'msg' => '查询成功', 'data' => $info['list'], 'extra' => $express_info['express_date']);
                break;
            case 205:
                return array('status' => false, 'msg' => '查询失败[code:205]', 'data' => $info['msg'], 'extra' => $express_info['express_date']);
                break;
            case -1:
                return array('status' => false, 'msg' => '查询失败[code:-1]', 'data' => '暂无物流信息', 'extra' => $express_info['express_date']);
                break;
            default:
                return array('status' => true, 'msg' => '查询失败', 'data' => '暂无物流信息', 'extra' => $express_info['express_date']);
        }
    }

    /** 确认收货*/
    public function signIn($order_id)
    {
        $order_info = db('order')->where('id', $order_id)->find();
        Db::startTrans();
        try {
            Db::name('order_express')->where('order_id', $order_id)->update(['confirm_date' => date('Y-m-d')]);
            Db::name('order')->where('id', $order_id)->update([
                'status' => '租赁中',
                'start_time' => date("Y-m-d", time()),
                'end_time' => date("Y-m-d", strtotime("+" . $order_info['lease_months'] . " months", time()))
            ]);
            Log::record('订单号:' . var_export($order_id, true) . '收货成功...', 'info');
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::record('收货失败..订单号:' . var_export($order_id, true), 'info');
            return array('status' => false, 'data' => '收货失败', 'extra' => $e->getMessage());
        }
        return array('status' => true, 'data' => '收货成功', 'extra' => $order_id);
    }

    /** 取消订单*/
    public function cancel($order_id, $member_id)
    {
        Db::startTrans();
        try {
            Db::name('order')->where('id', $order_id)->where('member_id', $member_id)->where('status', '未付款')->update(['status' => '关闭订单']);
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::record('收货失败..订单号:' . var_export($order_id, true), 'info');
            return array('status' => false, 'data' => '取消订单失败', 'extra' => $e->getMessage());
        }
        return array('status' => true, 'data' => '取消订单成功', 'extra' => $order_id);
    }

    /** 更换设备*/
    public function change_machine($order_id, $member_id, $express_id, $remark)
    {
        $order_info = \db('order')->where('id', $order_id)->where('member_id', $member_id)->find();
        Db::startTrans();
        try {
            Db::name('order')->where('id', $order_info['id'])->update(['status' => '申请更换中']);
            $is_change = db('order_change')->where('order_id', $order_info['id'])->where('order_type', '更换设备')->find();
            if (empty($is_change)) {
                Db::name('order_change')->insert([
                    'order_id' => $order_info['id'],
                    'member_id' => $member_id,
                    'order_type' => '更换设备',
                    'express_num' => $express_id,
                    'inside_order_no' => $order_info['inside_order_no'],
                    'remark' => $remark,
                ]);
            } else {
                Db::name('order_change')->where('order_id', $order_info['id'])->update([
                    'member_id' => $member_id,
                    'express_num' => $express_id,
                    'inside_order_no' => $order_info['inside_order_no'],
                    'remark' => $remark,
                ]);
            }
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return array('status' => false, 'data' => '申请更换设备失败,请重试', 'extra' => $e->getMessage());
        }
        return array('status' => true, 'data' => '申请更换设备成功', 'extra' => $order_info['id']);
    }


    /** 归还设备*/
    public function return_machine($order_id, $member_id, $express_id, $remark)
    {
        $order_info = \db('order')->where('id', $order_id)->where('member_id', $member_id)->find();
        Db::startTrans();
        try {
            Db::name('order')->where('id', $order_info['id'])->update(['status' => '归还设备中']);
            $is_change = db('order_change')->where('order_id', $order_info['id'])->where('order_type', '归还设备')->find();
            if (empty($is_change)) {
                Db::name('order_change')->insert([
                    'order_id' => $order_info['id'],
                    'member_id' => $member_id,
                    'order_type' => '归还设备',
                    'express_num' => $express_id,
                    'inside_order_no' => $order_info['inside_order_no'],
                    'remark' => $remark,
                ]);
            } else {
                Db::name('order_change')->where('order_id', $order_info['id'])->update([
                    'member_id' => $member_id,
                    'express_num' => $express_id,
                    'inside_order_no' => $order_info['inside_order_no'],
                    'remark' => $remark,
                ]);
            }
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return array('status' => false, 'data' => '申请归还失败,请重试', 'extra' => $e->getMessage());
        }
        return array('status' => true, 'data' => '申请归还成功', 'extra' => $order_id);
    }

    /** 删除订单*/
    public function del($order_id, $member_id)
    {
        Db::startTrans();
        try {
            Db::name('order')->where('id', $order_id)->where('member_id', $member_id)->where('status', '关闭订单')->update(['is_del' => 1]);
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::record('删除失败..订单号:' . var_export($order_id, true), 'info');
            return array('status' => false, 'data' => '删除订单失败', 'extra' => $e->getMessage());
        }
        return array('status' => true, 'data' => '删除订单成功', 'extra' => $order_id);
    }

    //银行支付
    public function bank_notify($order_id)
    {
        $order_info = db('order')->where('id', $order_id)->find();
        Db::startTrans();
        try {
            Db::name('order')->where('id', $order_id)->update([
                'pay_way' => 1,
                'status' => '已付款，待发货',
                'trade_state_desc' => '订单支付成功',
                'time_end' => date('Y-m-d H:i:s')
            ]);
            if ($order_info['order_type'] == '续租订单') {
                Db::name('order')->where('id', $order_id)->update(['status' => '租赁中']);
                Db::name('order')->where('inside_order_no', $order_info['old_order_no'])->update(['status' => '续租中']);//续租
            }
            //            //商品信息
            //            $goods_info = db('goods')->where('id', $order_info['goods_id'])->find();
            //用户信息
            $member_info = db('member')->where('id', $order_info['member_id'])->find();
            //用户分享体系(推广用户加积分)
            $referee_id = $member_info['referee'];
            //$referee_id = db('member')->where('id',$order['member_id'])->value('referee');
            add_score($referee_id, 3000, '推荐的用户下单送积分');
            //代理商分销体系
            $is_agent_share = $member_info['is_agent_share'];
            if (($is_agent_share == 1) && ($order_info['real_pay'] >= 10)) {
                $this->agent_profit($order_info, $member_info);
            }
            Log::record('用户id' . var_export($order_info['member_id'], true) . '订单id:' . var_export($order_info['id'], true), 'info');
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::record('支付回调处理失败..订单号:' . var_export($order_id, true), 'info');
            Log::record('失败原因:' . var_export($e->getMessage(), true), 'info');
            return array('status' => false, 'msg' => '处理订单失败', 'extra' => $e->getMessage());
        }
        return array('status' => true, 'msg' => '处理订单成功', 'extra' => $order_id);

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

    /** 代理商分润 */
    public function agent_profit($order_info, $member_info)
    {
        //代理商分销体系
        Db::startTrans();
        try {
            $agent_super = db('agent')->where('id', $member_info['agent_super_id'])->find();
            $agent_one = db('agent')->where('id', $member_info['agent_one_id'])->find();
            $agent_two = db('agent')->where('id', $member_info['agent_two_id'])->find();
            $agent_three = db('agent')->where('id', $member_info['agent_three_id'])->find();
            if (empty($agent_one)) {
                $agent_one['fee'] = 0;
            }
            if (empty($agent_two)) {
                $agent_two['fee'] = 0;
            }
            if (empty($agent_three)) {
                $agent_three['fee'] = 0;
            }
            $agent_super_income = $order_info['real_pay'] * ($agent_super['fee'] - $agent_one['fee']);
            $agent_one_income = $order_info['real_pay'] * ($agent_one['fee'] - $agent_two['fee']);
            $agent_two_income = $order_info['real_pay'] * ($agent_two['fee'] - $agent_three['fee']);
            $agent_three_income = $order_info['real_pay'] * ($agent_three['fee']);

            Db::name('agent_charge')->insert([
                'order_id' => $order_info['id'],
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
            ]);
            Log::record('特级代理信息：' . var_export($agent_super, true) . '一级代理:' . var_export($agent_one, true) . '二级代理：' . var_export($agent_two, true), 'info');
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::record('代理商分润出错..订单号:' . var_export($order_info['id'], true), 'info');
            Log::record('出错原因:' . var_export($e->getMessage(), true), 'info');
        }

    }
}