<?php
// +----------------------------------------------------------------------
// | Tplay [ WE ONLY DO WHAT IS NECESSARY ]
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://tplay.pengyichen.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 听雨 < 389625819@qq.com >
// +----------------------------------------------------------------------


namespace app\api\controller;

use \think\Db;
use think\Controller;
use think\Log;
use app\api\model\Member;
use \think\Cookie;
use \think\Session;
use app\admin\controller\Smsconfig;
header('Access-Control-Allow-Origin:*');
class Validates extends  Controller
{
    /**
     * 登录和注册验证
     * @get
     * @params weixin_id
     * @return json
     */
    public function valimobile()
    {
        $token = input("get.token");
        if(empty($token)){
            return json(['success' => false, 'message' => '不是微信入口' ]);
        }
        $member_info = get_memberinfo($token);
        $member_id = $member_info['id'];
        $breakFaith = db('member_account')->where('member_id',$member_id)->value('break_faith');
        if (empty($member_info['phone'])){
            return json(['success' => false, 'message' => '未注册' ]);
            exit();
        }
        if ($breakFaith==1){
            return json(['success' => true, 'message' => '已注册','extra'=>'您已经是小猪圈租赁平台的失信会员，请您联系客服恢复信用！']);
            exit();
        }
        return json(['success' => true, 'message' => '已注册','extra'=>null ]);
    }


    /**
     *  是否填写资料
     */
    public function validate_psdata(){
        $token = input("get.token");
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $vali_add = db('member')->where('id',$member_id)->find();
        if ($vali_add['nick_name']&&$vali_add['id_card']&&$vali_add['address']){
            return json(['success' => false, 'message' => '已填写' ]);
        }else{
            return json(['success' => true, 'message' => '未填写' ]);
        }
    }

    //    身份证认证
    /**
     * @return View
     */
    public function getValicard($idCard,$name)
    {
        $host = "https://idenauthen.market.alicloudapi.com";
        $path = "/idenAuthentication";
        $method = "POST";
        $appcode = "188d6cc1c5db4a7aba8de63f17b8a98c";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
        $querys = "";
        $bodys = "idNo=$idCard&name=$name";
        $url = $host . $path;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        //返回内容
        $callbcak = curl_exec($curl);
        $result = json_decode($callbcak,true);
        $result = object_array($result);
        return json_encode($result);

    }

    //获取用户信息
    private function get_memberinfo($token){
        $member_id = db('member_token')->where('token',$token)->value('member_id');
        if (empty($member_id)){
            jsonOk(null,null,'登录状态失效,请重新登录。',false);
        }
        $member_info = db('member')->where('id',$member_id)->find();
        if (empty($member_info)){
            jsonOk(null,null,'未获取到用户信息',false);
        }
        return $member_info;
    }

}
