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
use \think\Cookie;
use \think\Session;
use \think\Log;
use \think\config;


class Crowdfunding
{
    /** 众筹列表*/
    public function crowdfunding_list()
    {
        $list = db('score_crowdfunding')->select();
        if (empty($list)) {
            jsonOk(null, null, '暂无众筹项目', false);
        }
        jsonOk($list, null, '获取列表', true);
    }
    /** 我的众筹*/
    public function my_crowdfunding()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);//验证用户
        $join =[
            ['score_good g', 'g.id = l.scoregood_id']
        ];
        $data = db('score_crowdfunding_log')
            ->alias('l')
            ->join($join)
            ->field('l.id as log_id,l.*,g.good_type,g.good_name')
            ->where('l.member_id', $member_info['id'])
            ->order('l.status asc')
            ->select();
//        $data =  db('score_crowdfunding_log')->where('member_id', $member_info['id'])->select();
        if (empty($data)) {
            jsonOk(null, null, '无数据', false);
        }
        jsonOk($data, null, '获取成功', true);
    }
    /** 众筹详情页*/
    public function get_crowdfunding()
    {
        $post = Request::instance()->post();
        $crowdfunding_id = $post['crowdfunding_id'];
        $data = db('score_crowdfunding')->where('id', $crowdfunding_id)->find();
        if (empty($data)) {
            jsonOk(null, null, '无数据', false);
        }
        jsonOk($data, null, '获取成功', true);
    }
    /** 参与众筹*/
    public function crowdfunding()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $crowdfunding_id = $post['crowdfunding_id'];
        $member_info = $this->get_memberinfo($token);//验证用户
        $score_info = \db('member_score')
            ->where('member_id', $member_info['id'])->find();//查询积分信息
        $crowdfunding_info = \db('score_crowdfunding')->where('id', $crowdfunding_id)->find();//查询众筹信息

        //检查积分是否足够
        if ($score_info['get_score'] < $crowdfunding_info['need_score']) {
            jsonOk(null, null, '您的积分余额不足!', false);
        }
        //检查众筹项目
        if ($crowdfunding_info['status'] != 1) {
            if ($crowdfunding_info['end_time'] < time()) {
                jsonOk(null, null, '积分众筹已结束!', false);
            }
            if ($crowdfunding_info['status'] == 2) {
                jsonOk(null, null, '积分众筹已结束!', false);
            }
            if ($crowdfunding_info['status'] == 0) {
                jsonOk(null, null, '积分众筹未开始!', false);
            }
        }
        //检查用户参与次数
        if ($crowdfunding_info['restrict_time'] != 0) {
            $member_count = \db('score_crowdfunding_log')
                ->where('member_id', $member_info['id'])
                ->where('crowdfunding_id', $crowdfunding_info['id'])->count();//查询用户参与次数
            if ($member_count >= $crowdfunding_info['restrict_time']) {
                jsonOk(null, null, '您已超出限制众筹次数!', false);
            }
        }
        //实物需要地址
        $address_id = 0;
        if ($crowdfunding_info['crowdfunding_type'] == 0) {
            $address_id = $post['addr_id'];
            //确认地址
            $address_info = db('member_addr')->where('member_id', $member_info['id'])->where('id', $address_id)->find();
            if (empty($address_info)) {
                jsonOk(null, null, '请重新选择收货地址', false);
            }
        }
        //参与众筹操作
        Db::startTrans();
        try {
            //添加众筹记录

            Db::name('score_crowdfunding_log')->insert([
                'member_id' => $member_info['id'],
                'addr_id' => $address_id,
                'crowdfunding_id' => $crowdfunding_info['id'],
                'crowdfunding_name' => $crowdfunding_info['crowdfunding_name'],
                'crowdfunding_img' => $crowdfunding_info['crowdfunding_img'],
                'de_score' => $crowdfunding_info['need_score'],
                'create_time' => time(),
                'status' => 0,
            ]);

            //众筹项目更新
            Db::name('score_crowdfunding')->where('id', $crowdfunding_info['id'])->setInc('member_num');//自增人数
            Db::name('score_crowdfunding')->where('id', $crowdfunding_info['id'])->setInc('now_score', $crowdfunding_info['need_score']);//增加积分
            Db::name('score_crowdfunding')->where('id', $crowdfunding_info['id'])->update([
                'update_time' => time(),
            ]);

            //抵扣积分
            $before = $score_info['get_score'];
            Db::name('member_score')->where('member_id', $member_info['id'])->setDec('get_score', $crowdfunding_info['need_score']);
            $after = Db::name('member_score')->where('member_id', $member_info['id'])->value('get_score');

            //添加积分日志
            Db::name('score_history')->insert([
                'channel' => "积分众筹",
                'before' => $before,
                'after' => $after,
                'member_id' => $member_info['id'],
                'score' => '-' . $crowdfunding_info['need_score'],
                'remark' => "参与({$crowdfunding_info['crowdfunding_name']})众筹项目",
                'create_time' => date('Y-m-d H:i:s')
            ]);
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            jsonOk(null, null, '参与失败!', false);
        }
        $this->check_crowdfunding($crowdfunding_id);
        $after_crowdfunding_info = \db('score_crowdfunding')->where('id', $crowdfunding_id)->find();//查询众筹信息
        jsonOk($after_crowdfunding_info, null, '参与成功!', true);

    }
    /** 检查众筹项目*/
    public function check_crowdfunding($crowdfunding_id)
    {
        $crowdfunding_info = \db('score_crowdfunding')->where('id', $crowdfunding_id)->find();//查询众筹信息
        //是否达到目标积分
        if (($crowdfunding_info['now_score'] >= $crowdfunding_info['target_score'])&&($crowdfunding_info['status'] == 1)) {
            $this->end_crowdfunding($crowdfunding_info);
        }
        //当前时间是否超过结束时间
        if ((time() >= $crowdfunding_info['end_time'])&&($crowdfunding_info['status'] == 1)) {
            //众筹项目结束更新
            Db::name('score_crowdfunding')->where('id', $crowdfunding_info['id'])->update([
                'status' => 3,
                'update_time' => time(),
            ]);
        }
    }
    /** 众筹结束操作 */
    public function end_crowdfunding($crowdfunding_info)
    {
        //众筹项目结束更新
        Db::name('score_crowdfunding')->where('id', $crowdfunding_info['id'])->update([
            'status' => 2,
            'update_time' => time(),
        ]);
        Log::record('项目id' . var_export($crowdfunding_info['id'], true) . '众筹完成...开始处理中标', 'info');
        //抽取中奖用户
        $bid_num = $crowdfunding_info['bid_num'];//抽取人数
        $member_list = Db::name('score_crowdfunding_log')->field('id,member_id')->where('crowdfunding_id', $crowdfunding_info['id'])->select();//抽取人数
        $bid_list = array_rand($member_list, $bid_num);//中奖列表数组
        Log::record('开始处理参与用户记录...', 'info');
        //更众筹项目
        Db::startTrans();
        try {
            $bid_ids = [];//中奖id
            $nobid_ids = [];//未中奖id
            if ($bid_num == 1) {
                foreach ($member_list as $key => $value) {
                    if ($key == $bid_list) {
                        $bid_ids[] = $member_list[$key]['id'];
                    } else {
                        $nobid_ids[] = $member_list[$key]['id'];
                    }
                }
            } else {
                foreach ($member_list as $key => $value) {
                    if (in_array($key, $bid_list)) {
                        $bid_ids[] = $member_list[$key]['id'];
                    } else {
                        $nobid_ids[] = $member_list[$key]['id'];
                    }
                }
            }
            //中奖用户记录更新
            //确定奖品类型
            $bid_type = \db('score_good')->where('id', $crowdfunding_info['scoregood_id'])->value('good_type');
            switch ($bid_type) {
                case 0://添加中奖记录(实物)bid
                    foreach ($bid_ids as $key => $value) {
                        Db::name('score_crowdfunding_log')->where('id', $bid_ids[$key])->update([
                            'scoregood_id' => $crowdfunding_info['scoregood_id'],
                            'scoregood_status' => "待发货",
                            'status' => 1,
                            'update_time' => time()
                        ]);
                        $join = [
                            ['score_good g', 'g.id = l.scoregood_id'],
                            ['member_addr a', 'a.id = l.addr_id']
                        ];
                        $log_info =  Db::name('score_crowdfunding_log')->alias('l')
                            ->field('l.*,g.id as gid,g.good_path,g.good_name,a.street,a.receiver_name,a.receiver_phone')
                            ->join($join)
                            ->where('l.id', $bid_ids[$key])
                            ->find();
                        //添加积分订单信息
                        Db::name('score_order')->insert([
                            'member_id' => $log_info['member_id'],
                            'addr_id' => $log_info['addr_id'],
                            'log_id' => $log_info['id'],
                            'good_id' => $log_info['scoregood_id'],
                            'good_img' => $log_info['good_path'],
                            'scoregood_name' => $log_info['good_name'],
                            'name' => $log_info['street'],
                            'address' => $log_info['receiver_name'],
                            'phone' => $log_info['receiver_phone'],
                            'predictDelivery_time' => time() + (7 * 86400),
                            'create_time' => time(),
                            'update_time' => time(),
                            'order_type' => 1,
                            'order_status' => "待发货",
                        ]);
                        $open_id = \db('member')->where('id',$log_info['member_id'])->value('weixin_id');
                        $delivery_date = date('Y-m-d',time() + (7 * 86400));
                        $Wechat = new Wechat();
                        $url = $Wechat->toOrderUrl('orderInfo');
                        //中奖公众号消息发送
                        $message_data = [
                            "touser"=>$open_id,
                            "template_id"=>"Vd8DUSYKrXmU9GoWP10t1e7VADRvCTbzAidxxRR2KBE",
                            "url"=>$url,
                            "topcolor"=>"#FF0000",
                            "data"=>[
                                'first'=>[
                                    "value"=>"恭喜你！你在小猪圈参与的积分众筹项目成功了哦！小猪将会准时给你发货，请耐心等待哟！",
                                    "color"=>"#173177"
                                ],
                                'keyword1'=>[
                                    "value"=>"{$log_info['good_name']}",
                                    "color"=>"#173177"
                                ],
                                'keyword2'=>[
                                    "value"=>"{$crowdfunding_info['reference_price']}元",
                                    "color"=>"#173177"
                                ],
                                'keyword3'=>[
                                    "value"=>"{$delivery_date}",
                                    "color"=>"#173177"
                                ],
                                'remark'=>[
                                    "value"=>"点击查看订单详情，有疑问可以咨询客服哦",
                                    "color"=>"#173177"
                                ],
                            ]
                        ];
                        $Wechat->sendTemplateMessage($message_data);
                    }
                    break;
                case 1://添加中奖记录(免租券)
                    foreach ($bid_ids as $key => $value) {
                        Db::name('score_crowdfunding_log')->where('id', $bid_ids[$key])->update([
                            'scoregood_id' => $crowdfunding_info['scoregood_id'],
                            'scoregood_status' => "未使用",
                            'status' => 1,
                            'update_time' => time(),
                        ]);
                    }
                    break;
            }
            //未中奖用户记录更新
            Db::name('score_crowdfunding_log')->where('id', 'in', $nobid_ids)->update([
                'scoregood_id' => $crowdfunding_info['scoregood_id'],
                'scoregood_status' => null,
                'status' => 2,
                'update_time' => time(),
            ]);
            Log::record('处理结束...', 'info');
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Log::record('处理失敗.', 'info');
            Db::rollback();
        }
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
        $log_info = db('score_crowdfunding_log')->where('member_id', $member_info['id'])->where('id', $log_id)->find();
        $join =[
            ['score_good g', 'g.id = s.scoregood_id']
        ];
        $crowdfunding_info = db('score_crowdfunding')->alias('s')
            ->join($join)
            ->field('s.id as sid,g.id as gid,s.*,g.free_day,g.good_type')
            ->where('s.id', $log_info['crowdfunding_id'])
            ->find();
//        var_dump($crowdfunding_info);exit;
        if (empty($log_info)) {
            jsonOk(null, null, '未找到奖品信息', false);
        }
        if ($log_info['status'] != 1) {
            jsonOk(null, null, '该记录未众筹成功', false);
        }
        if ($log_info['scoregood_status'] != "未使用") {
            jsonOk(null, null, '该免租券已兑奖', false);
        }
        if ($crowdfunding_info['good_type'] != 1) {
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
            $endtime = date('Y-m-d', strtotime($order_info['end_time']) + ($crowdfunding_info['free_day'] * 86400));
            Db::name('order')->where('id', $order_info['id'])->setInc('extra_day', $crowdfunding_info['free_day']);
            Db::name('order')->where('id', $order_info['id'])->update([
                'end_time' => $endtime,
                'update_time' => time(),
            ]);
            //兑换记录更新
            Db::name('score_crowdfunding_log')->where('id', $log_info['id'])->update([
                'scoregood_status' => '已使用',
                'update_time' => time(),
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

    /** 定时任务-检查众筹开始结束*/
    public function timing_check(){
        Log::record('开始检查众筹项目...', 'info');
        $crowdfunding_list = \db('score_crowdfunding')->where('status',1)->select();//查询众筹项目列表
        Log::record('项目列表' . var_export($crowdfunding_list, true), 'info');
        foreach ($crowdfunding_list as $key =>$value){
            $crowdfunding_info = $crowdfunding_list[$key];
            //是否达到目标积分
            if (($crowdfunding_info['now_score'] >= $crowdfunding_info['target_score'])&&($crowdfunding_info['status'] == 1)) {
                $this->end_crowdfunding($crowdfunding_info);
            }
            //当前时间是否超过结束时间
            if ((time() >= $crowdfunding_info['end_time'])&&($crowdfunding_info['status'] == 1)) {
                //众筹项目结束更新
                Db::name('score_crowdfunding')->where('id', $crowdfunding_info['id'])->update([
                    'status' => 3,
                    'update_time' => time(),
                ]);
            }
        }
        Log::record('检查众筹项目结束...', 'info');
    }

    public function test(){
        var_dump(1);
    }
    /** 获取用户信息*/
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

    /** 获取accesstoken */
    private function getAccessToken()
    {
        $weixin_config = Config::get('weixin_config');//读取微信配置
        $appid= $weixin_config['appid'];
        $appsecret= $weixin_config['appsecret'];
        // 获取缓存
        $access = Cache::get('access_token');
        // 缓存不存在-重新创建
        if (empty($access)) {
            // 获取 access token
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$appsecret";
            $accessToken = httpGet($url);
//            $accessToken = file_get_contents($url);
            $accessToken = json_decode($accessToken);
            // 保存至缓存
            $access = $accessToken->access_token;
            Cache::set('access_token', $access, 7000);
        }
        return $access;
    }

}