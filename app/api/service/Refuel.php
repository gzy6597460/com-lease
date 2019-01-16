<?php
namespace app\api\service;

use think\Controller;
use \think\config;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use \think\Log;
use \think\Db;

class Refuel extends Controller
{
    public function getLog($order_id)
    {
        $join = [
            ['member m', 'm.id = r.member_id']
        ];
        $refuel_log = \db('order_refuel')
            ->alias('r')
            ->join($join)
            ->field('m.name,r.create_time')
            ->where('r.order_id', $order_id)
            ->select();
        $add_days = \db('order_refuel')->where('order_id', $order_id)->sum('add_day');
        $add_num = \db('order_refuel')->where('order_id', $order_id)->count();
        $data = [
            'add_days' => $add_days,
            'add_num' => $add_num,
            'refuel_log' => $refuel_log,
        ];
        if (empty($refuel_log)) {
            return array('status'=>true,'msg'=>'暂无加油记录','extra'=>null);
        }
        return array('status'=>true,'msg'=>'获取加油记录成功','extra'=>$data);
    }

    public function add($order_id,$member_id)
    {
        $order_info = \db('order')->where('id', $order_id)->find();
        Db::startTrans();
        try {
            $endtime = date('Y-m-d', strtotime($order_info['end_time']) + (1 * 86400));
            $before = $order_info['extra_day'];
            //增加天数
            Db::name('order')->where('id', $order_id)->setInc('extra_day', 1);
            Db::name('order')->where('id', $order_info['id'])->update([
                'end_time' => $endtime,
            ]);
            $after = Db::name('order')->where('id', $order_id)->value('extra_day');
            Db::name('order_refuel')->insert([
                'order_id' => $order_id,
                'member_id' => $member_id,
                'before' => $before,
                'add_day' => 1,
                'after' => $after,
                'create_time' => time(),
                'update_time' => time(),
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return array('status'=>false,'msg'=>'加油失败','extra'=>$e->getMessage());
        }
        return array('status'=>true,'msg'=>'加油成功','extra'=>null);
    }
}