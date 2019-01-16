<?php

namespace app\api\controller;

header('Access-Control-Allow-Origin:*');

use app\api\controller\Wechat;
use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use think\Log;
use think\config;

class Goods
{
    /** 搜索 */
    public function search_good(){

    }
    /**
     * 获取商品列表
     */
    public function getgoodslist()
    {
        //缓存
        $goods_list = Cache::get('goods_list');
        if (empty($goods_list)) {
            $join = [
                ['goods_series s', 's.id = g.series_id']
            ];
            $goods_list = db('goods')->alias('g')->field('g.id,g.goods_num,g.goods_name,g.goods_brand,g.goods_path,g.minimum_price,g.reference_price,g.parameter,s.series_name')
                ->join($join)->where('is_onshelf', 1)->select();
            Cache::set('goods_list', $goods_list, 7200);
        }
        if ($goods_list) {
            return json($goods_list);
        }
        return json('暂无数据');

    }

    /**
     * 获取商品信息
     */
    public function getonegood()
    {
        $id = input("get.id");
        $good = db('goods')->where('id', $id)->find();
        if (empty($good)) {
            jsonOk(null, null, '未获取到商品信息，请重试。');
        }
        //$good['meal'] = [3 => $good['three_rent'], 6 => $good['six_rent'], 12 => $good['twelve_rent']];
        $meal_info = db('goods_meal')->field('id,meal_name,meal_days,meal_param,meal_discount,meal_months,insurance_cost,service_cost,meal_price')->where('goods_id', $id)->where('type',0)->select();
        $good['meal'] = $meal_info;
        $goods_picture = db('goods_picture')->where('goods_id', $id)->find();
        for ($i = 1; $i < 6; $i++) {
            if ($goods_picture['picture_' . $i]) {
                $good['goods_picture'][] = $goods_picture['picture_' . $i];
            }
        }
        return json($good);
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

    /**
     * 取消订单
     */
    public function cancel_order()
    {
        $order_id = input("get.order_id");
        $result = db('order')->where('id', $order_id)->where('status', '未付款')->update(['status' => '关闭订单']);
        if (empty($result)) {
            jsonOk(null, null, '取消订单失败', false);
        }
        jsonOk(null, null, '取消订单成功');
    }

    /**
     * 更换设备/换新/归还
     */
    public function change_machine()
    {
        $post = Request::instance()->post();
        $change = $post['change_id'];
        $order_id = $post['order_id'];
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $is_order = db('order')->where('id', $order_id)->where('member_id', $member_id)->find();
        if (empty($is_order)) {
            jsonOk(null, null, '订单不存在或订单状态获取失败', false);
        }
        $data = [
            'order_id' => $order_id,
            'member_id' => $member_id,
            'order_type' => $is_order['order_type'],
            'express_num' => $post['express_id'],
            'inside_order_no' => $is_order['inside_order_no'],
            //            'back_name'     => $post['back_name'],
            //            'back_phone'    => $post['back_phone'],
            //            'back_address'  => $post['back_address'],
            'remark' => $post['remark'],
        ];
        switch ($change) {
            //更换设备
            case 0:
                $up_order = db('order')->where('id', $order_id)->update(['status' => '申请更换中']);
                $is_change = db('order_change')->where('order_id', $order_id)->find();
                if (empty($is_change)) {
                    $result = db('order_change')->insert($data);
                } else {
                    $data1 = [
                        'member_id' => $member_id,
                        'order_type' => $is_order['order_type'],
                        'express_num' => $post['express_id'],
                        'inside_order_no' => $is_order['inside_order_no'],
                        'remark' => $post['remark'],
                    ];
                    $result = db('order_change')->where('order_id', $order_id)->update($data1);
                }
                if (($result === false) || empty($up_order)) {
                    jsonOk(null, $up_order . 'c' . $result, '申请更换设备失败,请重试', false);
                }
                jsonOk(null, null, '申请更换设备成功');
                break;
            //归还设备
            case 1:
                $up_order = db('order')->where('id', $order_id)->update(['status' => '归还设备中']);
                $is_change = db('order_change')->where('order_id', $order_id)->find();
                if (empty($is_change)) {
                    $result = db('order_change')->insert($data);
                } else {
                    $data1 = [
                        'member_id' => $member_id,
                        'order_type' => $is_order['order_type'],
                        'express_num' => $post['express_id'],
                        'inside_order_no' => $is_order['inside_order_no'],
                        'remark' => $post['remark'],
                    ];
                    $result = db('order_change')->where('order_id', $order_id)->update($data1);

                }
                if (($result === false) || empty($up_order)) {
                    jsonOk(null, null, '申请归还失败,请重试', false);
                }
                jsonOk(null, null, '申请归还成功');
                break;
        }

    }

    /**
     * 生成租赁订单
     */
    public function build_order()
    {
        $is_post = Request::instance()->ispost();
        if ($is_post == false) {
            jsonOk(null, null, '无效请求!', false);
        }
        $post = Request::instance()->post();
        $post_data = $this->validate_order($post);//验证post数据

        $member_id = $post_data['member_info']['id'];//用户id
        $goods_id = $post_data['goods_id'];//商品id
        $meal_id = $post_data['meal_id'];//套餐id
        //$addr_id = $post_data['addr_id'];//地址id
        $deduction_score = $post_data['deduction_score'];//抵扣积分
        $buy_num = $post_data['buy_num'];//数量
        $remark = $post_data['remark'];//备注
        $addr = $post_data['addr'];//地址信息
        $post_realPay = $post_data['real_pay'];//前端计算的实际支付
        $post_totalFee = $post_data['total_fee'];//前端计算的总金额
        //$post_pledgeCash = $post_data['pledge_cash'];//前端押金金额
        $is_pledge = $post_data['is_pledge'];//是否免押金(0:需押金,1免押金)

        $inside_order_no = $this->build_order_no();//生成订单号
        $goods_info = db('goods')->where('id', $goods_id)->find();//获取商品价格信息
        $meal_info = db('goods_meal')->where('id', $meal_id)->find();//获取商品套餐信息
        $pledge_cash = $goods_info['pledge_cash'];//商品押金
        $unit_price = $meal_info['meal_price'];//单价
        $total_price = $meal_info['total_price']+$pledge_cash;//加上服务费+押金

//        if (empty($unit_price)) {
//            jsonOk(null, null, '订单价格出错', false);
//        }
        $total_fee = round(($buy_num * $total_price) * 100, 2); //订单总金额
        $real_pay = $total_fee - $deduction_score;//实际支付(扣除积分)

        if (($real_pay != (round($post_realPay * 100, 2))) || $total_fee != (round($post_totalFee * 100, 2)) || $real_pay < 0) {
            Log::record('订单价格出错....后端实际支付:' . var_export($real_pay, true) . '前端实际支付:' . var_export((round($post['real_pay'], 2) * 100), true) . '后端总金额:' . var_export($total_fee, true) . '前端总金额:' . var_export($post_totalFee, true), 'info');
            jsonOk(null, null, '订单价格出错', false);
        }
        //订单未出错,开始创建订单及后续操作
        Db::startTrans();
        try {
            //创建订单
            $order_id = Db::name('order')->insertGetId([
                'inside_order_no' => $inside_order_no,
                'buy_name' => $addr['receiver_name'],
                'phone' => $addr['receiver_phone'],
                'address' => $addr['street'],
                'pledge_cash' => $pledge_cash,
                'meal_id'=>$meal_id,
                'is_pledge' => $is_pledge,
                'total_fee' => $total_fee,
                'real_pay' => $real_pay,
                'deduction_score' => $deduction_score,
                'is_score_deduction' => $post_data['is_score_deduction'],
                'member_id' => $member_id,
                'goods_id' => $meal_info['goods_id'],
                'buy_num' => $buy_num,
                'lease_months' => $meal_info['meal_months'],
                'remark' => $remark,
                'create_time' => date('Y-m-d H:i:s'),
                'extra_score' => round($real_pay / 100),
                'status' => '未付款',
            ]);
            if ($deduction_score > 0) {
                //抵扣积分
                $before_score = Db::name('member_score')->where('member_id', $member_id)->value('get_score');
                Db::name('member_score')->where('member_id', $member_id)->setDec('get_score', $deduction_score);
                $after_score = Db::name('member_score')->where('member_id', $member_id)->value('get_score');
                //添加积分日志
                Db::name('score_history')->insert([
                    'channel' => "租赁订单号({$inside_order_no})租赁积分抵扣",
                    'before' => $before_score,
                    'after' => $after_score,
                    'member_id' => $member_id,
                    'score' => '-' . $deduction_score,
                    'create_time' => date('Y-m-d H:i:s')
                ]);
            }
            Log::record('创建订单成功...', 'info');
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Log::record('创建订单失敗...', 'info');
            jsonOk(null, null, '订单创建失败', false);
            Db::rollback();
        }
        //        //未付款公众号消息发送推送
        //        $nopay_message = config::get('nopay_message');
        //        $nopay_message['touser'] = $post_data['member_info']['weixin_id'];
        //        $nopay_message['data']['ordertape']['value'] = date('Y-m-d H:i:s');
        //        $nopay_message['data']['ordeID']['value'] = $inside_order_no;
        //        $Wechat = new Wechat();
        //        $Wechat->sendTemplateMessage($nopay_message);
        jsonOk($order_id, null, '订单创建成功');
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
        $meals = \db('goods_meal')->where('goods_id', $order_data['good_id'])->where('type',0)->select();
        return json(['data' => $order_data, 'meals' => $meals]);
    }

    /**
     * 生成续租订单
     */
    public function relet_order()
    {
        //jsonOk(null, null, '续租功能维护中,请稍后再试', false);
        $is_post = Request::instance()->ispost();
        if ($is_post == false) {
            jsonOk(null, null, '无效请求!', false);
            exit();
        }
        $post = Request::instance()->post();
        if (isset($post['meal_id']) == false) {
            jsonOk(null, null, '未选择续租套餐', false);
            exit();
        }
        if (isset($post['order_id']) == false) {
            jsonOk(null, null, '未选择续租订单', false);
            exit();
        }
        $meal_id = $post['meal_id'];
        $order_id = $post['order_id'];
        $post_totalFee = round($post['total_fee'] * 100, 2);
        //验证操作
        $old_order_info = db('order')->where('id', $order_id)->find();
        $goods_info = db('goods')->where('id', $old_order_info['goods_id'])->find();
        $meal_info = db('goods_meal')->where('id', $meal_id)->find();
        if (empty($goods_info)) {
            jsonOk(null, null, '未找到该续租商品信息', false);
            exit();
        }//验证商品
        if (empty($old_order_info)) {
            jsonOk(null, null, '未找到要续租订单信息 ', false);
            exit();
        }//验证旧订单
        if (empty($meal_info)) {
            jsonOk(null, null, '未找到该续租套餐信息', false);
            exit();
        }//验证商品
        $new_order_no = $this->build_order_no();//生成订单号
        $end_time = date("Y-m-d", strtotime("+" . $meal_info['meal_months'] . " months", strtotime($old_order_info['end_time'])));//续租时间

        $total_fee = round($old_order_info['buy_num'] * $meal_info['meal_price'] * 100, 2);
        $real_pay = $total_fee;
        if (($total_fee != $post_totalFee) || ($real_pay <= 0)) {
            Log::record('接收前端数据:' . var_export($post, true), 'info');
            jsonOk(null, null, '订单价格出错。', false);
        }
        //订单未出错,开始创建订单及后续操作
        Db::startTrans();
        try {
            //创建订单
            $order_id = Db::name('order')->insertGetId([
                'inside_order_no' => $new_order_no,
                'old_order_no' => $old_order_info['inside_order_no'],
                'member_id' => $old_order_info['member_id'],
                'buy_name' => $old_order_info['buy_name'],
                'phone' => $old_order_info['phone'],
                'address' => $old_order_info['address'],
                'goods_id' => $old_order_info['goods_id'],
                'start_time' => $old_order_info['start_time'],
                'total_fee' => $total_fee,
                'real_pay' => $real_pay,
                'buy_num' => $old_order_info['buy_num'],
                'lease_months' => $meal_info['meal_months'],
                'create_time' => date('Y-m-d H:i:s'),
                'end_time' => $end_time,
                'status' => '未付款',
                'order_type' => '续租订单',
                'relet_id' => $old_order_info['id'],
                'meal_id' => $meal_id,
                'extra_score' => 30,
            ]);
            Log::record('创建续租订单成功...', 'info');
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Log::record('创建订单失敗...', 'info');
            jsonOk(null, null, '订单创建失败', false);
            Db::rollback();
        }
        jsonOk($order_id, null, '订单创建成功', true);
    }

    //确认收货
    public function confirm_order()
    {
        $order_id = input('get.order_id');
        $order_info = db('order')->where('id', $order_id)->find();
        $data = [
            'status' => '租赁中',
            'start_time' => date("Y-m-d", time()),
            'end_time' => date("Y-m-d", strtotime("+" . $order_info['lease_months'] . " months", time()))
        ];
        db('order_express')->where('order_id', $order_id)->update(['confirm_date' => date('Y-m-d')]);
        $result = db('order')->where('id', $order_id)->update($data);
        if ($result) {
            return json(['success' => true, 'message' => '收货成功']);
        } else {
            return json(['success' => false, 'message' => '收货失败']);
        }
    }

    //查物流
    public function query_express()
    {
        $order_id = input('get.order_id');
        if (empty($order_id)) {
            jsonOk(null, null, '未获取到订单信息', false);
        }
        $express_info = db('order_express')->where('order_id', $order_id)->find();

        //缓存
        $info = Cache::get('express' . $express_info['express_num']);
        if (empty($info)) {
            $info = queryExpress($express_info['express_num']);
            Cache::set($order_id . 'express' . $express_info['express_num'], $info, 3600);
        }
        $data = json_decode($info, true);
        if ($data['msg'] == '快递单号或快递公司代码不能为空') {
            return jsonOk([], $express_info['express_date'], '暂无物流信息');
        }
        return jsonOk($data['list'], $express_info['express_date'], '查询成功');
    }

    //获取用户信息
    private function get_memberinfo($token)
    {
        $member_id = db('member_token')->where('token', $token)->value('member_id');
        if (empty($member_id)) {
            jsonOk(null, null, '登录状态失效,请重新登录。', false);
        }
        $member_info = db('member')->where('id', $member_id)->find();
        if (empty($member_info)) {
            jsonOk(null, null, '未获取到用户信息', false);
        }
        return $member_info;
    }

    /** 验证生成订单post数据*/
    private function validate_order($post)
    {
        if (isset($post['token']) == false) {
            jsonOk(null, null, '请先登录', false);
            exit();
        }
        $token = $post['token'];
        $member_info = get_memberinfo($token);
        if (isset($post['goods_id']) == false) {
            jsonOk(null, null, '未选择商品', false);
            exit();
        }
        if (isset($post['meal_id']) == false) {
            jsonOk(null, null, '未选择套餐', false);
            exit();
        }
        if (isset($post['addr_id']) == false) {
            jsonOk(null, null, '未选择收货地址', false);
            exit();
        }
        if (isset($post['deduction_score']) == false) {
            jsonOk(null, null, '未选择抵扣积分', false);
            exit();
        }
        if (isset($post['buy_num']) == false) {
            jsonOk(null, null, '未选择购买数量', false);
            exit();
        }
        if (isset($post['remark']) == false) {
            jsonOk(null, null, '未填写备注', false);
            exit();
        }
        $data['member_info'] = $member_info;//用户信息
        $data['goods_id'] = $post['goods_id'];//商品id
        $data['meal_id'] = $post['meal_id'];//套餐id
        $data['addr_id'] = $post['addr_id'];//地址id
        $data['deduction_score'] = $post['deduction_score'];//抵扣积分
        $data['buy_num'] = $post['buy_num'];//数量
        $data['remark'] = $post['remark'];//备注
        $data['real_pay'] = $post['real_pay'];//前端计算的实际支付
        $data['total_fee'] = $post['total_fee'];//前端计算的总金额
//        $data['pledge_cash'] = $post['pledge_cash'];//前端押金金额
        $data['is_pledge'] = $post['is_pledge'];//是否免押金(0:需押金,1免押金)
        $data['is_score_deduction'] = 0;//是否积分抵扣
        //免押金
        if ($data['is_pledge'] == 1){
            $data['pledge_cash'] = 0;
        }

        //验证抵扣积分是否溢出
        if ($data['deduction_score'] > 0) {
            $data['is_score_deduction'] = 1;
            $before_score = db('member_score')->where('member_id', $member_info['id'])->value('get_score');
            if (empty($before_score) || ($before_score < $data['deduction_score'])) {
                jsonOk(null, null, '您的积分不足!', false);
            }
        }
        //获取用户收货地址信息
        $data['addr'] = db('member_addr')->where('id', $data['addr_id'])->where('member_id', $member_info['id'])->find();
        if (empty($data['addr']) || $data['addr'] == 0) {
            jsonOk(null, null, '请填写收货信息', false);
        }
        return $data;
    }


}
