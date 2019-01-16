<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/15
 * Time: 13:34
 */

namespace app\api\controller;

header('Access-Control-Allow-Origin:*');

use \think\Db;
use \think\Request;
use \think\Cache;
use app\api\model\Member;
use \think\Cookie;
use \think\Session;
use think\Log;
use \think\config;


class Scoreshop
{
    /** 商城首页获取商品列表*/
    public function get_scoregoods()
    {
        //缓存
        $list = Cache::get('scoregoods_list');
        if (empty($list)) {
            $list = db('score_good')->field('id,good_name,good_path,parameter,reference_price,need_score,remark')
                ->where('is_onshelf', 1)->select();
            if ($list) {
                Cache::set('scoregoods_list', $list, 7200);
            }
        }
        if ($list) {
            jsonOk($list, null, null, true);
        }
        return jsonOk(null, null, '暂无商品', false);
    }
    /** 商城首页获取商品列表*/
    public function get_banner()
    {
        //缓存
        $list = Cache::get('score_banner');
        if (empty($list)) {
            $list = db('score_banner')->select();
            if ($list) {
                Cache::set('score_banner', $list, 7200);
            }
        }
        if ($list) {
            jsonOk($list, null, '获取成功', true);
        }
        return jsonOk(null, null, '暂无轮播图', false);
    }
    /** 商品详情页*/
    public function get_onescoregood()
    {
        $id = input("get.id");
        $good = db('score_good')->where('id', $id)->find();
        if (empty($good)) {
            jsonOk(null, null, '未获取到商品信息，请重试。', false);
        }
        $goods_picture = db('score_good_picture')->field('picture_1,picture_2,picture_3,picture_4,picture_5')->where('goods_id', $id)->find();
        $good['goods_picture'] = $goods_picture;
        jsonOk($good, null, null, true);
    }
    /** 获取积分 */
    public function get_havescore()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);
        $score = \db('member_score')->where('member_id', $member_info['id'])->value('get_score');
        $data = $score ? $score : 0;
        jsonOk($data, null, null, true);
    }
    /** 兑换商品 */
    public function exchange_good()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $scoregood_id = $post['good_id'];

        $member_info = $this->get_memberinfo($token);
        $score_info = \db('member_score')->where('member_id', $member_info['id'])->find();
        $good_info = \db('score_good')->where('id', $scoregood_id)->find();
        if ($score_info['get_score'] < $good_info['need_score']) {
            jsonOk(null, null, '您的积分余额不足!', false);
        }
        //实物需要地址
        if ($good_info['good_type'] == 0) {
            $address_id = $post['addr_id'];
            //确认地址
            $address_info = db('member_addr')->where('member_id', $member_info['id'])->where('id', $address_id)->find();
            if (empty($address_info)) {
                jsonOk(null, null, '未找到地址信息', false);
            }
        }

        Db::startTrans();
        try {
            //确认类型
            switch ($good_info['good_type']) {
                case 0://实物
                    //添加兑换记录
                    $ex_history = Db::name('score_exchange_history')->insertGetId([
                        'member_id' => $member_info['id'],
                        'scoregood_id' => $good_info['id'],
                        'exchange_score' => $good_info['need_score'],
                        'status' => "兑换成功",
                        'logistics_status' => "待发货",
                        'predictDelivery_time' => time() + (7 * 86400),
                        'create_time' => time(),
                        'update_time' => time(),
                    ]);
                    //添加积分订单信息
                    Db::name('score_order')->insert([
                        'member_id' => $member_info['id'],
                        'addr_id' => $address_id,
                        'log_id' => $ex_history,
                        'good_id' => $good_info['id'],
                        'good_img' => $good_info['good_path'],
                        'scoregood_name' => $good_info['good_name'],
                        'name' => $address_info['receiver_name'],
                        'address' => $address_info['street'],
                        'phone' => $address_info['receiver_phone'],
                        'predictDelivery_time' => time() + (7 * 86400),
                        'create_time' => time(),
                        'update_time' => time(),
                        'order_type' => 0,
                        'order_status' => "待发货",
                    ]);
                    break;
                case 1://免租券
                    //添加兑换记录
                    $ex_history = Db::name('score_exchange_history')->insertGetId([
                        'member_id' => $member_info['id'],
                        'scoregood_id' => $good_info['id'],
                        'exchange_score' => $good_info['need_score'],
                        'status' => "兑换成功",
                        'logistics_status' => "未使用",
                        'predictDelivery_time' => time(),
                        'create_time' => time(),
                        'update_time' => time(),
                    ]);
                    break;
            }

            //抵扣积分
            $before = $score_info['get_score'];
            Db::name('member_score')->where('member_id', $member_info['id'])->setDec('get_score', $good_info['need_score']);
            $after = Db::name('member_score')->where('member_id', $member_info['id'])->value('get_score');
            //添加积分日志
            Db::name('score_history')->insert([
                'channel' => "积分商城兑换(商品名称:{$good_info['good_name']}",
                'before' => $before,
                'after' => $after,
                'member_id' => $member_info['id'],
                'score' => '-' . $good_info['need_score'],
                'remark' => "兑换记录id:{$ex_history}",
                'create_time' => date('Y-m-d H:i:s')
            ]);

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            jsonOk(null, null, '兑换失败，请稍后重试!', false);
        }
        jsonOk(null, null, '兑换成功!', true);
    }
    /** 兑换记录*/
    public function exchange_history()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);
        $join = [
            ['score_good g', 'g.id = s.scoregood_id']
        ];
        $list = \db('score_exchange_history')->alias('s')->join($join)
            ->field('g.good_name,g.good_path,g.good_name,s.id,s.create_time,s.predictDelivery_time,s.status,g.good_type,s.logistics_status')
            ->where('s.member_id', $member_info['id'])
            ->order('s.create_time desc')
            ->select();
        if (empty($list)) {
            jsonOk(null, null, '暂无兑换记录', false);
        }
        jsonOk($list, null, '获取成功', true);
    }
    /** 免租券使用*/
    public function use_ticket()
    {
        //验证用户token
        $post = Request::instance()->post();
        $token = $post['token'];
        $log_id = $post['log_id'];
        $order_id = $post['order_id'];
        //确认用户
        $member_info = $this->get_memberinfo($token);
        //确认积分商品状态(兑换记录)
        $log_info = \db('score_exchange_history')->where('member_id',$member_info['id'])->where('id', $log_id)->find();
        $good_info = \db('score_good')->where('id', $log_info['scoregood_id'])->find();

        if (empty($log_info)) {
            jsonOk(null, null, '未找到兑换信息', false);
        }
        if ($good_info['good_type'] != 1) {
            jsonOk(null, null, '非免租券商品', false);
        }
        if ($log_info['logistics_status'] != "未使用") {
            jsonOk(null, null, '该免租券已使用', false);
        }
        //确认订单
        $order_info = db('order')->where('member_id', $member_info['id'])->where('id', $order_id)->find();
        if (empty($order_info)) {
            jsonOk(null, null, '未找到订单信息', false);
        }
        //用券操作
        Db::startTrans();
        try {
            //用券订单更新(加日期)
            $endtime = date('Y-m-d', strtotime($order_info['end_time']) + ($good_info['free_day'] * 86400));
            Db::name('order')->where('id', $order_info['id'])->setInc('extra_day', $good_info['free_day']);
            Db::name('order')->where('id', $order_info['id'])->update([
                'end_time' => $endtime,
                'update_time' => time(),
            ]);
            //兑换记录更新
            Db::name('score_exchange_history')->where('id', $log_info['id'])->update([
                'logistics_status' => "已使用",
                'delivery_time' => time(),
            ]);
            //订单日志log

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            jsonOk(null, null, '使用失败!', false);
        }
        jsonOk(null, null, '使用成功!', true);
    }
    /** 订单信息*/
    public function order_list(){
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);

        $data = \db('score_order')
            ->where('member_id',$member_info['id'])
            ->order('create_time desc')
            ->select();

        if (empty($data)){
            jsonOk(null, null, '暂无订单数据', false);
        }
        jsonOk($data, null, '获取成功', true);
    }
    /**获取用户信息*/
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

    public function test()
    {

    }
}