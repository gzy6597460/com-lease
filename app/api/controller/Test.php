<?php

namespace app\api\controller;

use app\common\controller\Api;
use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Log;
use \think\config;
use app\api\controller\Bankinfo as bankInfo;

class Test extends Api
{

    public function test2(){
        $bank = new bankInfo();
        $bank_name = $bank->getname('6236681930006690620');
        dump($bank_name);
    }

    public function test()
    {
        $result = $this->get_area();
        dump($result);
    }

    //淘宝接口：根据ip获取所在城市名称
    public function get_area($ip = '')
    {
        if ($ip == '') {
            $ip = $this->GetIp();
        }
        $url = "http://ip.taobao.com/service/getIpInfo.php?ip={$ip}";
        $ret = $this->https_request($url);
        $arr = json_decode($ret, true);
        return $arr;
    }

    //POST请求函数
    function https_request($url, $data = null)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        if (!empty($data)) {//如果有数据传入数据
            curl_setopt($curl, CURLOPT_POST, 1);//CURLOPT_POST 模拟post请求
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);//传入数据
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);

        return $output;
    }

    // 获取ip
    function GetIp()
    {
        $realip = '';
        $unknown = 'unknown';
        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown)) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                foreach ($arr as $ip) {
                    $ip = trim($ip);
                    if ($ip != 'unknown') {
                        $realip = $ip;
                        break;
                    }
                }
            } else if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']) && strcasecmp($_SERVER['HTTP_CLIENT_IP'], $unknown)) {
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            } else if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown)) {
                $realip = $_SERVER['REMOTE_ADDR'];
            } else {
                $realip = $unknown;
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), $unknown)) {
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } else if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), $unknown)) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else if (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), $unknown)) {
                $realip = getenv("REMOTE_ADDR");
            } else {
                $realip = $unknown;
            }
        }
        $realip = preg_match("/[\d\.]{7,15}/", $realip, $matches) ? $matches[0] : $unknown;
        return $realip;
    }


    public function testdb()
    {
        //代理商分销体系
        Db::startTrans();
        try {
            Db::name('order')->insert([
                'member_id' => 1,
            ]);
            Db::name('member')->insert([
                'i2323d' => 1,
            ]);
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            Log::record('出错原因:' . var_export($e->getMessage(), true), 'info');
            dump($e->getMessage());
        }
    }
}
