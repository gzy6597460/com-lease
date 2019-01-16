<?php

namespace app\api\controller;

header('Access-Control-Allow-Origin:*');

use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use think\Log;
use think\image;
use OSS\Core\OssException;


class Agent
{
    private $appid = 'wx5cb120cb7d1c9866';                              //微信公众号APPID
    private $appsecret = '1f26b36c2792e2cb61dd8c00419b7a08';            //密匙
    private $url = 'http://admin.91xzq.com/api/welogin/h5login';        //微信回调地址

    //登录
    public function login()
    {
        $post = Request::instance()->post();
        $username = $post['username'];
        $password = password($post['password']);
        $agent_info = \db('agent')->field('id,username,password')->where('username', $username)->find();
        if ($password != $agent_info['password']) {
            jsonOk(null, null, '密码错误', false);
        }
        $token = $this->build_token($agent_info['id']);
        jsonOk(null, null, '登录成功', true, $token);
    }

    //修改密码
    public function change_password()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agentr_info = $this->get_agentinfo($token);

        $old_password = password($post['oldpassword']);
        if ($old_password != $agentr_info['password']) {
            jsonOk(null, null, '原密码错误', false);
        }
        $newpassword = $post['newpassword'];
        $repeat_password = $post['repeat_password'];
        //验证密码 /^[A-Za-z0-9]{6,16}$/
        $verfy = preg_match("/^[A-Za-z0-9]{6,16}$/", $newpassword);
        if (empty($verfy)) {
            jsonOk(null, null, '新密码格式错误', false);
        }
        if ($newpassword != $repeat_password) {
            jsonOk(null, null, '两次输入的密码不同', false);
        }
        $new_password = password($newpassword);
        $result = \db('agent')->where('id', $agentr_info['id'])->update(
            [
                'password' => $new_password,
                'update_time' => time()
            ]
        );
        if ($result == 1) {
            jsonOk(null, null, '修改成功', true);
        }
        jsonOk(null, null, '修改失败', true);
    }

    //忘记密码
    public function foget_password()
    {
        $post = Request::instance()->post();
        $phone = $post['phone'];
        $smsCode = $post['smsCode'];//短信验证码
        $agent_info = \db('agent')->where('username', $phone)->find();
        //验证码用户
        if (empty($agent_info)) {
            jsonOk(null, null, '用户名错误', false);
        }
        //验证密码
        $newpassword = $post['newpassword'];
        $repeat_password = $post['repeat_password'];
        $verfy = preg_match("/^[A-Za-z0-9]{6,16}$/", $newpassword);
        if (empty($verfy)) {
            jsonOk(null, null, '新密码格式错误', false);
        }
        if ($newpassword != $repeat_password) {
            jsonOk(null, null, '两次输入的密码不同', false);
        }
        //验证短信验证码
        $code = Cache::get('forget' . $phone);
        if (empty($code)) {
            jsonOk(null, null, '验证码超时', false);
        }
        if ($smsCode != $code) {
            jsonOk(null, null, '验证码错误', false);
        }

        $new_password = password($newpassword);
        $result = \db('agent')->where('id', $agent_info['id'])->update(
            [
                'password' => $new_password,
                'update_time' => time()
            ]
        );
        if ($result == 1) {
            jsonOk(null, null, '重置密码成功', true);
        }
        jsonOk(null, null, '重置失败', true);
    }

    //新增代理商
    public function add_newagent()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $agent_id = $agent_info['id'];
        $agent_level = $agent_info['level'];
        if ($agent_level == 3) {
            jsonOk(null, null, '该代理没有添加代理权限', false);
        }
        $add_fee = $agent_info['fee'] - 0.1;//默认
        $add_level = $agent_info['level'] + 1;
        $nick_name = $post['nick_name'];
        $username = $post['username'];
        $is_username = \db('agent')->where('username', $username)->find();
        if ($is_username) {
            jsonOk(null, null, '该用户名已存在', false);
        }
        $phone = $post['phone'];
        $password = password(123456);
        $agent_super_id = $agent_info['agent_super_id'];
        $agent_one_id = $agent_info['agent_one_id'];
        $agent_two_id = $agent_info['agent_two_id'];
        switch ($agent_level) {
            case 0:
                $agent_super_id = $agent_id;
                break;
            case 1:
                $agent_one_id = $agent_id;
                break;
            case 2:
                $agent_two_id = $agent_id;
                break;
        }
        $data = [
            'username' => $username,
            'phone' => $phone,
            'password' => $password,
            'nick_name' => $nick_name,
            'level' => $add_level,
            'fee' => $add_fee,
            'agent_super_id' => $agent_super_id,
            'agent_one_id' => $agent_one_id,
            'agent_two_id' => $agent_two_id,
            'create_time' => time(),
            'update_time' => time(),
        ];
        $result = \db('agent')->insert($data);
        if (empty($result)) {
            jsonOk(null, null, '添加代理商失败', false);
        }
        $params = [
            'username'=>$username,
            'email'=>$phone.'@qq.com',
            'nickname'=>$nick_name,
            'password'=>123456,
            'level'=>$add_level,
            'status'=>'normal'
        ];
        $result = get_object_vars(json_decode(http_post('http://fadmin.91xzq.com/api/adminh5/add_admin',$params)));
        if ($result['msg'] != '成功') {
            jsonOk(null, null, $result['msg'], false);
        }

        Log::record('代理：' . var_export($agent_id, true) . '添加下级代理商成功！', 'info');
        jsonOk(null, null, '添加代理成功', true);
    }

    //分销系统二维码
    public function get_twocode()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $twocode = $agent_info['twocode_url'];
        //$share_url = $this->sharetourl($member_id);
        $share_url = $this->weqrcode($agent_info['id']);
        if (empty($twocode)) {
            //不带LOGO
            Vendor('phpqrcode.phpqrcode');
            //生成二维码图片
            $object = new \QRcode();//实例化二维码类
            $url = $share_url;//网址或者是文本内容
            $level = 1;
            $size = 3;
            $pathname = "./../../computer-h5/uploads/Qrcode";
            if (!is_dir($pathname)) { //若目录不存在则创建之
                mkdir($pathname);
            }
            $img_name = "/agent_{$agent_info['id']}_qrcode_" . rand(10000, 99999) . ".png";
            $ad = $pathname . $img_name;
            $imgad = 'http://h5.91xzq.com/uploads/Qrcode' . $img_name;
            Log::record('代理商推广二维码图片路径' . var_export($imgad, true), 'info');
            $res = db('agent')->where('id', $agent_info['id'])->update(['twocode_url' => $imgad]);
            if (empty($res)) {
                return json(['twocode_url' => null, 'msg' => '生成失败', 'status' => false]);
            }
            $twocode = db('agent')->where('id', $agent_info['id'])->value('twocode_url');
            $errorCorrectionLevel = intval($level);//容错级别
            $matrixPointSize = intval($size);//生成图片大小
            $object->png($url, $ad, $errorCorrectionLevel, $matrixPointSize, 2);
        }
        return json(['twocode_url' => $twocode, 'msg' => '获取成功', 'status' => true]);
    }

    //文案库
    public function get_advertisement()
    {
        $advertisement_list = \db('agent_advertisement')->select();
        if (empty($advertisement_list)) {
            jsonOk(null, null, '获取失败', false);
        }
        jsonOk($advertisement_list, null, '获取成功', true);
    }

    //文案库使用图片（加下载量）
    public function download_advertisement()
    {
        $adver_id = input('get.id');
        $advertisement_add = \db('agent_advertisement')->where('id', $adver_id)->setInc('down_count');
        if (empty($advertisement_add)) {
            jsonOk(null, null, '使用失败', false);
        }
        jsonOk($advertisement_add, null, '使用成功', true);
    }

    //代理商中心信息
    public function get_agent_info()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $amount_total = \db('agent_withdraw')->where('agent_id', $agent_info['id'])->where('status', 1)->sum('amount');
        $nosettlement = \db('agent_withdraw')->where('agent_id', $agent_info['id'])->where('status', 0)->sum('amount');
        $data = [
            'headimg_url' => $agent_info['headimg_url'],
            'nick_name' => $agent_info['nick_name'],
            'balance' => $agent_info['balance'],
            //'balance_disabled' => $agent_info['balance_disabled'],
            'nosettlement' => $nosettlement ? $nosettlement : 0,
            //'amount_total'=>$amount_total
            'amount_total' => $amount_total ? $amount_total : 0
        ];
        if (empty($data)) {
            jsonOk(null, null, '获取失败', false);
        }
        jsonOk($data, null, '获取成功', true);
    }

    //分润记录
    public function income_record()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $level = $agent_info['level'];
        $data = [];
        switch ($level) {
            case 0:
                $agent_id_field = 'agent_super_id';
                $agent_income_field = 'agent_super_income';
                break;
            case 1:
                $agent_id_field = 'agent_one_id';
                $agent_income_field = 'agent_one_income';
                break;
            case 2:
                $agent_id_field = 'agent_two_id';
                $agent_income_field = 'agent_two_income';
                break;
            case 3:
                $agent_id_field = 'agent_three_id';
                $agent_income_field = 'agent_three_income';
                break;
        }
        $today_income = db('agent_charge')->where($agent_id_field, $agent_info['id'])->whereTime('create_time', 'today')->sum($agent_income_field);
        $all_income = db('agent_charge')->where($agent_id_field, $agent_info['id'])->sum($agent_income_field);
        $amount_total = db('agent_withdraw')->where('agent_id', $agent_info['id'])->sum('amount');//已提现
        $extra = [
            'today_income' => $today_income ? $today_income : 0,
            'all_income' => $all_income ? $all_income : 0,
            'amount_total' => $amount_total ? $all_income : 0,
        ];
        //订单金额 分润 姓名 推荐人 时间
        $join = [
            ['xzq_order o', 'c.order_id = o.id'],
            ['xzq_member m', 'm.id=o.member_id'],
        ];
        $agent_id_field = 'c.' . $agent_id_field;
        $income_list = \db('agent_charge')->alias('c')->join($join)->field("m.name,m.agent_super_id,m.agent_one_id,m.agent_two_id,m.agent_three_id,o.real_pay,{$agent_income_field},c.create_time")->where($agent_id_field, $agent_info['id'])->select();
        foreach ($income_list as $key => $value) {
            if ($income_list[$key]['agent_three_id'] != 0) {
                $data[$key]['agent_name'] = \db('agent')->where('id', $income_list[$key]['agent_three_id'])->value('nick_name');
            }
            if (($income_list[$key]['agent_three_id'] == 0) && ($income_list[$key]['agent_two_id'] != 0)) {
                $data[$key]['agent_name'] = \db('agent')->where('id', $income_list[$key]['agent_two_id'])->value('nick_name');
            }
            if (($income_list[$key]['agent_two_id'] == 0) && ($income_list[$key]['agent_one_id'] != 0)) {
                $data[$key]['agent_name'] = \db('agent')->where('id', $income_list[$key]['agent_one_id'])->value('nick_name');
            }
            if (($income_list[$key]['agent_one_id'] == 0) && ($income_list[$key]['agent_super_id'] != 0)) {
                $data[$key]['agent_name'] = \db('agent')->where('id', $income_list[$key]['agent_super_id'])->value('nick_name');
            }
            $data[$key]['member_name'] = $income_list[$key]['name'];
            $data[$key]['real_pay'] = $income_list[$key]['real_pay'];
            $data[$key]['income'] = $income_list[$key]["{$agent_income_field}"];
            $data[$key]['create_time'] = date("Y-m-d h:i:s", $income_list[$key]['create_time']);
        }
        if (empty($extra)) {
            jsonOk(null, null, '获取失败', false);
        }
        jsonOk($data, $extra, '获取成功', true);
    }

    //银行卡列表
    public function get_bankcard()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $card_list = \db('agent_bank')->field('id,card_bank_type,card_bank,card_no')->where('agent_id', $agent_info['id'])->select();
        jsonOk($card_list, null, '获取成功', true);
    }

    //添加银行卡
    public function add_bankcard()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $agent_id = $agent_info['id'];//$post['agentId'];
        //验证
        $validate = validate('Rytpay');
        if (!$validate->scene('payOne')->check($post)) {
            jsonOk(null,null,$validate->getError(),false);
        }
        $name = $post['name'];
        $cardBank = $post['cardBank'];
        $cardBankType = $post['cardBankType'];//银行类型
        $cardSubBank = $post['cardSubBank'];//详细名称
        $idCardNo = $post['idCardNo'];//身份证
        $cardNo = $post['cardNo'];//银行卡号
        $phone = $post['phone'];
        $smsCode = $post['smsCode'];//短信验证码
        $cardProvince = $post['cardProvince'];//省份
        $cardCity = $post['cardCity'];
        $cardArea = $post['cardArea'];

        //验证短信验证码
        $code = Cache::get('bank' . $post['phone']);
        if (empty($code)) {
            jsonOk(null, null, '验证码超时', false);
        }
        if ($smsCode != $code) {
            jsonOk(null, null, '验证码错误', false);
        }

        $data = [
            'agent_id' => $agent_id,
            'card_bank' => $cardBank,
            'card_sub_bank' => $cardSubBank,
            'card_province' => $cardProvince,
            'card_city' => $cardCity,
            'card_area' => $cardArea,
            'card_no' => $cardNo,
            'id_card_no' => $idCardNo,
            'name' => $name,
            'phone' => $phone,
            'card_bank_type' => $cardBankType,
            'create_time' => time(),
            'update_time' => time()
        ];
        $res = \db('agent_bank')->insert($data);
        if ($res) {
            Log::record('代理：' . var_export($agent_id, true) . '添加银行卡成功！', 'info');
            jsonOk(null, null, '添加银行卡成功', true);
        }
        jsonOk(null, null, '添加银行卡失败', false);
    }

    //忘记密码发送验证码
    public function foget_sendSms()
    {
        $post = Request::instance()->post();
        $phone = $post['phone'];
        if (empty($phone)) {
            jsonOk(null, null, '未填写手机号', false);
        }
        //验证  唯一规则： 表名，字段名，排除主键值，主键名
        if (!preg_match("/^1[3456789]{1}\d{9}$/", $phone)) {
            jsonOk(null, null, '手机号码有误,请填写正确手机号', false);
        }
        //发送验证码
        $code = mt_rand(100000, 999999);
        //短信模板
        $templateCode = 'SMS_147620057';
        $result = sendMsg($phone, $code, $templateCode);
        //\think\Log::write('发送短信结果：'.json($result));
        //$result['Code'] = 'OK';
        if ($result['Code'] == 'OK') {
            //存到缓存当中,并且返回json数据给前端
            Cache::set('forget' . $phone, $code, 300);
            jsonOk(null, null, '发送成功', true);
        } else if ($result['Code'] == 'isv.BUSINESS_LIMIT_CONTROL') {
            jsonOk(null, '错误代码:' . $result['Code'], '用户短信发送频繁，请稍后再试', false);
        } else {
            jsonOk(null, '错误代码:' . $result['Code'], '发送失败', false);
        }
    }

    //银行卡发送验证码
    public function bank_sendSms()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $phone = $post['phone'];
        if (empty($phone)) {
            jsonOk(null, null, '未填写手机号', false);
        }
        //验证  唯一规则： 表名，字段名，排除主键值，主键名
        if (!preg_match("/^1[345678]{1}\d{9}$/", $phone)) {
            jsonOk(null, null, '手机号码有误,请填写正确手机号', false);
        }
        //发送验证码
        $code = mt_rand(100000, 999999);
        //短信模板
        $templateCode = 'SMS_147620057';
        $result = sendMsg($phone, $code, $templateCode);
        //\think\Log::write('发送短信结果：'.json($result));
        //$result['Code'] = 'OK';
        if ($result['Code'] == 'OK') {
            //存到缓存当中,并且返回json数据给前端
            Cache::set('bank' . $phone, $code, 300);
            jsonOk(null, null, '发送成功', true);
        } else if ($result['Code'] == 'isv.BUSINESS_LIMIT_CONTROL') {
            jsonOk(null, '错误代码:' . $result['Code'], '用户短信发送频繁，请稍后再试', false);
        } else {
            jsonOk(null, '错误代码:' . $result['Code'], '发送失败', false);
        }
    }

    //分享中心(邀请人数)
    public function sharing_center()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $agent_level = $agent_info['level'];
        switch ($agent_level) {
            case 0:
                $find_field = 'agent_super_id';
                break;
            case 1:
                $find_field = 'agent_one_id';
                break;
            case 2:
                $find_field = 'agent_two_id';
                break;
            case 3:
                $find_field = 'agent_three_id';
                break;
        }
        $all_info = \db('member')->field('id,credit_score')->where($find_field, $agent_info['id'])->select();
        $all_share_num = count($all_info);
        $verify_num = 0;
        foreach ($all_info as $key => $value) {
            if ($all_info[$key]['credit_score'] >= 80) {
                $verify_num++;
            }
        }
        $noverify_num = $all_share_num - $verify_num;
        $data = [
            'all_share_num' => $all_share_num,
            'verify_num' => $verify_num,
            'noverify_num' => $noverify_num
        ];
        jsonOk($data, null, '获取成功', true);
    }

    //分享中心(邀请人数)
    public function share_members()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $agent_level = $agent_info['level'];
        switch ($agent_level) {
            case 0:
                $find_field = 'agent_super_id';
                break;
            case 1:
                $find_field = 'agent_one_id';
                break;
            case 2:
                $find_field = 'agent_two_id';
                break;
            case 3:
                $find_field = 'agent_three_id';
                break;
        }
        $all_info = \db('member')->field('id,headimgurl,name,create_time')->where($find_field, $agent_info['id'])->select();
        jsonOk($all_info, null, null, true);
    }

    //提现
    public function agent_withdraw()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        //开始
        $card_id = $post['card_id'];
        $bankcard_info = \db('agent_bank')->where('id', $card_id)->find();
        if (empty($bankcard_info) || ($agent_info['id'] != $bankcard_info['agent_id'])) {
            jsonOk(null, null, '未找到匹配的银行卡', false);
        }
        $balance = $agent_info['balance'];
        $fee = 200;
        $actual_amount = $balance - $fee;
        $data = [
            'trade_no' => $this->build_order_no(),
            'agent_id' => $agent_info['id'],
            'amount' => $balance,
            'fee' => $fee,
            'actual_amount' => $actual_amount,
            'name' => $bankcard_info['name'],
            'phone' => $bankcard_info['phone'],
            'id_card_no' => $bankcard_info['id_card_no'],
            'card_no' => $bankcard_info['card_no'],
            'create_time' => time(),
            'update_time' => time(),
        ];
        $res = \db('agent_withdraw')->insert($data);
        $update_blance = \db('agent')->where('id', $agent_info['id'])->setDec('balance', $balance);
        if (empty($res)) {
            jsonOk(null, null, '申请提现失败，请重试。', false);
        }
        Log::record('代理：' . var_export($agent_info['id'], true) . '申请提现成功！', 'info');
        jsonOk(null, null, '申请提现成功', true);
    }

    //提现记录
    public function withdraw_record()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        $data = [];
        $record_list = \db('agent_withdraw')->field('id,amount,fee,actual_amount,card_no,status,create_time')->where('agent_id', $agent_info['id'])->select();
        foreach ($record_list as $key => $value) {
            $data[$key] = $record_list[$key];
            $data[$key]['bank_type'] = \db('agent_bank')->where('card_no', $record_list[$key]['card_no'])->value('card_bank_type');
            $data[$key]['card_no'] = substr($record_list[$key]['card_no'], -4);
            $data[$key]['create_time'] = date("Y-m-d h:i:s", $record_list[$key]['create_time']);
        }
        jsonOk($data, null, '获取成功', true);
    }

    //头像上传
    public function heard_upload()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $agent_info = $this->get_agentinfo($token);
        // 获取表单上传文件
        $file = request()->file("heardimg");
        if (empty($file)){
            jsonOk(null,null,'请选择上传文件',false);
        }
        $info = $file->validate(['size'=>1048576,'ext'=>'jpg,png,gif,jpeg'])->move(ROOT_PATH.'public'.DS.'uploads');
        //var_dump($file);
        if (!$info) {// 上传错误提示错误信息
            //处理上传错误信息
            $msg =$file->getError();
            jsonOk(null,$msg,'请选择小于3M的图片',false);
            //echo $file->getError();
        } else {// 上传成功
            $savename = $info->getSaveName();
            $file_name = $info->getFilename();
            vendor('aliyuncs.autoload');
            $accessKeyId = "LTAI4ANIZP6RJaQu";//去阿里云后台获取秘钥
            $accessKeySecret = "NipIP9ERN8nx0oRxG0U4AeCI5JgZ9G";//去阿里云后台获取秘钥
            $endpoint = "oss-cn-shenzhen.aliyuncs.com";//你的阿里云OSS地址
            $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $bucket = "xiaozhuquan";//oss中的文件上传空间
            $object = 'agent_headimg'. '/' .date('Y-m-d') . '/' .$file_name;// $info['imgfile']['savename'];
            ////想要保存文件的名称
            $file = './uploads/' . $savename;//文件路径，必须是本地的。
            try {
                $ossClient->uploadFile($bucket, $object, $file);
                //上传成功，自己编码
                //这里可以删除上传到本地的文件。unlink（$file）；
                $headimg_url = "http://{$bucket}.{$endpoint}/{$object}";
                $res = \db('agent')->where('id',$agent_info['id'])->update(
                    [
                        'headimg_url'=>$headimg_url,
                        'update_time'=>time()
                    ]
                );
                if (empty($res)){
                    jsonOk(null,null,'更新头像失败',true);
                }
                jsonOk($headimg_url,null,'更新头像成功！',true);
            } catch (OssException $e) {
                //上传失败，自己编码
//                printf($e->getMessage() . "\n");
                jsonOk(null,$e->getMessage(),'更新头像失败',false);
            }
        }
    }


    /**/
    private function getAccessToken()
    {
        // 获取缓存
        $access = Cache::get('access_token');
        // 缓存不存在-重新创建
        if (empty($access)) {
            // 获取 access token
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appid&secret=$this->appsecret";
            $accessToken = httpGet($url);
//            $accessToken = file_get_contents($url);
            $accessToken = json_decode($accessToken);
            // 保存至缓存
            $access = $accessToken->access_token;
            Cache::set('access_token', $access, 7000);
        }
        return $access;
    }

    //分销系统二维码链接生成
    private function weqrcode($agent_id)
    {
        $access_token = $this->getAccessToken();
        $data = '{"action_name": "QR_LIMIT_STR_SCENE", "action_info": {"scene": {"scene_str":"2_' . $agent_id . '"}}}';
        $qrcode = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=" . $access_token . "";
        $qrcode1 = httpPost($qrcode, $data);
        $result = json_decode($qrcode1, true);
        Log::record('代理商推广二维码生成data ' . var_export($data, true), 'info');
        Log::record('result ' . var_export($result, true), 'info');
        return $result['url'];
    }

    //获取代理商信息
    private function get_agentinfo($token)
    {
        $agent_id = db('agent_token')->where('token', $token)->value('agent_id');
        if (empty($agent_id)) {
            jsonOk(null, null, '登录状态失效,请重新登录。', false, null, 403);
        }
        $agent_info = db('agent')->where('id', $agent_id)->find();
        if (empty($agent_info)) {
            jsonOk(null, null, '未获取到用户信息', false, null, 403);
        }
        return $agent_info;
    }

    //获取token
    public function build_token($agent_id)
    {
        $token = settoken($agent_id);
        $is_token = db('agent_token')->where('agent_id', $agent_id)->find();
        if ($is_token) {
            $result = db('agent_token')->where('agent_id', $agent_id)->update(['token' => $token]);
        } else {
            $result = db('agent_token')->insert(
                [
                    'token' => $token,
                    'agent_id' => $agent_id,
                    'create_time' => time(),
                    'update_time' => time(),
                ]
            );
        }
        if (empty($result)) {
            return false;
        }
        return $token;
    }

    public function test()
    {
        $post = Request::instance()->post();
        $cardNo = $post['cardNo'];
        $verify_bankcard = check_bankCard($cardNo);
        if ($verify_bankcard == 'false') {
            jsonOk(null, null, '请填写正确的银行卡号', false);
        }
    }

    private function build_order_no()
    {
        mt_srand((double)microtime() * 1000000);
        $no = date('Ymd') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        //检测是否存在
        $info = db('agent_withdraw')->where('trade_no', $no)->find();
        (!empty($info)) && $no = $this->build_order_no();
        return $no;
    }

    /** 结算前日的分润记录 */
    public function settle_accounts(){
        //获取需要结算订单
        $list = \db('agent_charge')->whereTime('create_time','today')->select();
        foreach ($list as $key => $value){
            Db::startTrans();
            try{
                Db::name('agent')->where('id',$value['agent_super_id'])->setInc('balance',$value['agent_super_income']);
                Db::name('agent')->where('id',$value['agent_one_id'])->setInc('balance',$value['agent_one_income']);
                Db::name('agent')->where('id',$value['agent_two_id'])->setInc('balance',$value['agent_two_income']);
                Db::name('agent')->where('id',$value['agent_three_id'])->setInc('balance',$value['agent_three_income']);
                Db::name('agent_charge')->where('id',$value['id'])->update(['status'=>1]);
                Db::commit();
                echo "订单:{$value['id']}-结算成功";
            } catch (\Exception $e) {
                Db::rollback();
                echo "订单:{$value['id']}-结算失败";
            }
        }
        echo "结算结束";
    }
}
