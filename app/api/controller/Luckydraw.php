<?php
namespace app\api\controller;
header('Access-Control-Allow-Origin:*');

use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use \think\Log;
use \think\config;

use app\api\service\Token as tokenService;

class Luckydraw
{
    /** 奖品列表 */
    public function prize_list()
    {
        $prize = \db('score_luckydraw')->select();
        jsonOk($prize, null, '获取转盘奖品', true);
    }

    /** 中奖名单 */
    public function lucky_list()
    {
        $join = [
            ['member m', 'm.id = l.member_id']
        ];
        $data = \db('score_luckydraw_log')->alias('l')
            ->field('l.id,l.member_id,l.member_name,m.phone,l.prize_id,l.prize_name,l.create_time')
            ->join($join)
            ->order('l.prize_id asc')
            ->limit(50)
            ->select();
        foreach ($data as $key => $value) {
            $data[$key]['create_time'] = date('Y-m-d', $data[$key]['create_time']);
        }
//        shuffle($data);
        $num = count($data);
        jsonOk($data, $num, '获取中奖名单', true);
    }

    /** 个人中奖记录 */
    public function mylucky_list()
    {
        //验证用户token
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);
        $join = [
            ['score_luckydraw p', 'p.id = l.prize_id']
        ];

        $data = \db('score_luckydraw_log')->alias('l')
            ->join($join)
            ->field('p.prize_img,p.prize_name,p.prize_type,l.id,l.create_time,l.redeem_time,l.status')
            ->where('l.member_id', $member_info['id'])
            ->order('l.status asc')
            ->order('l.create_time desc')
            ->select();
        if (empty($data)) {
            jsonOk(null, null, '暂无中奖信息', false);
        }
        jsonOk($data, null, '获取成功', true);
    }

    /** 转盘抽奖 */
    public function luckydraw()
    {
        //验证用户token
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);
        $score_info = \db('member_score')
            ->where('member_id', $member_info['id'])->find();//查询积分信息
        $config = Config::get('lucky_draw');//读取抽奖配置

        //检查积分是否足够
        if ($score_info['get_score'] < $config['need_score']) {
            jsonOk(null, null, '您的积分余额不足!', false);
        }
        //检查今日抽奖次数
        $lucky_time = \db('score_luckydraw_log')->where('member_id', $member_info['id'])->whereTime('create_time', 'today')->count();
        if ($lucky_time >= 3) {
            jsonOk(null, null, '您已今日抽奖次数已满3次,请明日再来。', false);
        }

        //读取奖品参数列表
        $prize = \db('score_luckydraw')->where('prize_num', '>', 0)->select();

        //取概率
        foreach ($prize as $key => $value) {
            $rate[] = $prize[$key]['probability'];
        }
        $prize_count = count($prize);
        //确定抽奖区间
        $sum = 0;
        $section = [0];
        for ($i = 0; $i < count($rate); $i++) {
            $sum += $rate[$i];
            $section[] = $sum;
        }
        $rand_num = rand(1, $section[$prize_count]);
        $length = count($section);
        for ($i = 0, $j = 1; $i < $length; $i++, $j++) {
            if ($rand_num > $section[$i] && $rand_num <= $section[$j]) {
                break; //此时的$j即为中奖产品的序号，结束循环
            }
        }
        $lucky_num = $j;//中奖id
        $lucky_angles = $config['angle'][$lucky_num];//中奖角度区间
        $lucky_interval = $lucky_angles[array_rand($lucky_angles)]; //随机一个区间
        $lucky_angle = rand($lucky_interval[0], $lucky_interval[1]);//中奖角度
        $residue_prize = \db('score_luckydraw')->where('id', $lucky_num)->find();//抽中奖品信息
        if ($residue_prize['prize_num'] > 0) {
            Db::startTrans();
            try {
                //添加抽奖记录
                Db::name('score_luckydraw_log')->insert([
                    'member_id' => $member_info['id'],
                    'member_name' => $member_info['name'],
                    'prize_id' => $lucky_num,
                    'prize_name' => $residue_prize['prize_name'],
                    'create_time' => time(),
                ]);
                //扣库存
                if ($residue_prize['prize_num'] != 999) {
                    Db::name('score_luckydraw')->where('id', $residue_prize['id'])->setDec('prize_num');
                }

                //抵扣积分
                $before = $score_info['get_score'];
                Db::name('member_score')->where('member_id', $member_info['id'])->setDec('get_score', $config['need_score']);
                $after = Db::name('member_score')->where('member_id', $member_info['id'])->value('get_score');
                //添加积分日志
                Db::name('score_history')->insert([
                    'channel' => "积分抽奖",
                    'before' => $before,
                    'after' => $after,
                    'member_id' => $member_info['id'],
                    'score' => '-' . $config['need_score'],
                    'remark' => "中奖奖品:{$lucky_num}",
                    'create_time' => date('Y-m-d H:i:s')
                ]);

                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                jsonOk(null, null, '抽奖失败!', false);
            }
            $data = [
                'lucky_angle' => $lucky_angle,
                'lucky_prize' => $residue_prize,
            ];
            jsonOk($data, null, '抽奖成功!', true);
        }
    }

    /** 实物兑奖 */
    public function redeem()
    {
        //验证用户token
        $post = Request::instance()->post();
        $token = $post['token'];
        $log_id = $post['log_id'];
        $address_id = $post['addr_id'];
        //确认用户
        $member_info = $this->get_memberinfo($token);
        //确认奖品状态
        $log_info = db('score_luckydraw_log')->where('member_id', $member_info['id'])->where('id', $log_id)->find();
        $prize_info = db('score_luckydraw')->where('id', $log_info['prize_id'])->find();
        if (empty($log_info)) {
            jsonOk(null, null, '未找到奖品信息', false);
        }
        if ($log_info['status'] != 0) {
            jsonOk(null, null, '奖品已兑奖或已作废', false);
        }
        if ($prize_info['prize_type'] != 0) {
            jsonOk(null, null, '非实物奖品', false);
        }
        //确认地址
        $address_info = db('member_addr')->where('member_id', $member_info['id'])->where('id', $address_id)->find();
        if (empty($address_info)) {
            jsonOk(null, null, '未找到地址信息', false);
        }

        //兑奖操作
        Db::startTrans();
        try {
            //创建积分订单
            Db::name('score_order')->insert([
                'member_id' => $member_info['id'],
                'addr_id' => $address_id,
                'log_id' => $log_id,
                'good_id' => $prize_info['id'],
                'good_img' => $prize_info['prize_img'],
                'scoregood_name' => $prize_info['prize_name'],
                'name' => $address_info['street'],
                'address' => $address_info['receiver_name'],
                'phone' => $address_info['receiver_phone'],
                'predictDelivery_time' => time() + (7 * 86400),
                'create_time' => time(),
                'update_time' => time(),
                'order_type' => 2,
                'order_status' => "待发货",
            ]);
            //兑换记录更新
            Db::name('score_luckydraw_log')->where('id', $log_info['id'])->update([
                'status' => 1,
                'redeem_time' => time(),
            ]);
            //订单日志log

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            jsonOk(null, null, '兑奖失败!', false);
        }
        jsonOk(null, null, '兑奖成功!', true);
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
        //确认奖品状态
        $log_info = db('score_luckydraw_log')->where('member_id', $member_info['id'])->where('id', $log_id)->find();
        $prize_info = db('score_luckydraw')->where('id', $log_info['prize_id'])->find();
        if (empty($log_info)) {
            jsonOk(null, null, '未找到奖品信息', false);
        }
        if ($log_info['status'] != 0) {
            jsonOk(null, null, '该奖品已兑奖或已作废', false);
        }
        if ($prize_info['prize_type'] != 1) {
            jsonOk(null, null, '非免租券奖品', false);
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
            $endtime = date('Y-m-d', strtotime($order_info['end_time']) + ($prize_info['free_day'] * 86400));
            Db::name('order')->where('id', $order_info['id'])->setInc('extra_day', $prize_info['free_day']);
            Db::name('order')->where('id', $order_info['id'])->update([
                'end_time' => $endtime,
                'update_time' => time(),
            ]);
            //兑换记录更新
            Db::name('score_luckydraw_log')->where('id', $log_info['id'])->update([
                'status' => 1,
                'redeem_time' => time(),
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

    /** 获取用户信息 */
    private function get_memberinfo($token)
    {
        $tokenService = new tokenService();
        $member_id = $tokenService->check($token);
        if (empty($member_id)) {
            jsonOk(null, null, '登录状态失效,请重新登录。', false);
        }
        $member_info = db('member')->where('id', $member_id)->find();
        if (empty($member_info)) {
            jsonOk(null, null, '未获取到用户信息', false);
        }
        return $member_info;
    }
}