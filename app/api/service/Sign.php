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

class Sign extends Controller
{
    public function getData($member_id){
        $sign_data = db('member_score')->where('member_id', $member_id)->find();
        if (empty($sign_data)) {
            $data = [
                'member_id' => $member_id,
                'get_score' => 0,
                'update_time' => date('Y-m-d H:i:s'),
            ];
            db('member_score')->insert($data);
            $sign_data = db('member_score')->where('member_id', $member_id)->find();
        }
        $day_score = $this->get_days_array($sign_data['con_days']);
        if ($sign_data['sign_time'] == date('Y-m-d')) {$today_sign = '今日已签到';}else{$today_sign = '今日未签到';}
        if ($sign_data['con_days']<7){$nextday =7-$sign_data['con_days'];}else if ($sign_data['con_days'] >=7 and $sign_data['con_days']<15){$nextday =15-$sign_data['con_days'];}else if ($sign_data['con_days'] >=15 and $sign_data['con_days']<30){$nextday =30-$sign_data['con_days'];}
        return array('sign_data' => $sign_data, 'dayandscore' => $day_score, 'msg' => $today_sign, 'nextday' => $nextday);
    }

    /** 签到*/
    public function sign($member_id)
    {
        $sign_data = db('member_score')->where('member_id', $member_id)->find();
        if (empty($sign_data)) {
            $data = [
                'member_id' => $member_id,
                'get_score' => 0,
                'update_time' => date('Y-m-d H:i:s'),
            ];
            db('member_score')->insert($data);
            $sign_data = db('member_score')->where('member_id', $member_id)->find();
        }
        //今日已签到
        if ($sign_data['sign_time'] == date('Y-m-d')) {
            return array('status' => false, 'msg' => '今日已签到', 'data' => null, 'extra' => null);
        }
        Db::startTrans();
        try {
            //签到
            $sign_day = strtotime(date('Y-m-d')) - strtotime($sign_data['sign_time']);
            if ($sign_day == 86400) {
                $con_days = $sign_data['con_days'] + 1;
            } else {
                $con_days = 1;
            }
            //连续签到超过7天
            Db::name('member_score')->where('member_id', $member_id)->update([
                'sign_time' => date('Y-m-d'),
                'get_score' => $sign_data['get_score'] + 5,
                'con_days' => $con_days,
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            Db::name('score_history')->insert([
                'score' => 5,
                'member_id' => $member_id,
                'create_time' => date('Y-m-d H:i:s'),
                'channel' => '日常签到'
            ]);

            //正常签到之后 处理连续签到
            $after_sign_data = db('member_score')->where('member_id', $member_id)->find();
            $extra_score = 0;
            switch ($after_sign_data['con_days']){
                case 7:
                    $extra_score = 20;
                    break;
                case 15:
                    $extra_score = 50;
                    break;
                case 30:
                    $extra_score = 100;
                    //清零签到日期
                    Db::name('member_score')->where('member_id', $member_id)->update(['con_days' => 0]);
                    break;
            }
            //连续加积分 ->setInc('get_score',$extra_score)
            if ($extra_score >0){
                add_score($member_id, $extra_score, '签到额外送分');
            }
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::record(var_export($member_id, true).'签到失败.原因:'.var_export($e->getMessage(), true), 'info');
            return array('status'=>false,'msg'=>'签到失败','data' =>null,'extra'=>$e->getMessage());
        }
        return array('status'=>true,'msg'=>'签到成功','data' => $after_sign_data['con_days'],'extra'=>$extra_score);
    }

    //    天数和分数数组
    private function get_days_array($con_days)
    {
        $s = (int)(($con_days-1)/7);
        $day = [];
        $seven_score = 20;
        $fifteen_score = 50;
        $thirty_score = 100;
        for ($i = 1; $i < 31; $i++) {
            switch ($i) {
                case 7:
                    $day[$i . "天"] = 5 + $seven_score;
                    break;
                case 15:
                    $day[$i . "天"] = 5 + $fifteen_score;
                    break;
                case 30:
                    $day[$i . "天"] = 5 + $thirty_score;
                    break;
                default:
                    $day[$i . "天"] = 5;
            }
        }
        $day=array_slice($day,$s*7 ,7);
        return $day;
    }
}