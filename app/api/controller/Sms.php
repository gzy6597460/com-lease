<?php

namespace app\api\controller;

use think\Controller;
use think\Request;
use think\Cookie;
use think\Cache;
use think\Log;
use think\Db;
use app\api\model\Member;

class Sms extends Controller
{
    /**
     *  发送短信接口
     * @post
     * @params phone
     * @return  success message
     */
    public function sendSms()
    {
        $post = Request::instance()->post();
        //发送验证码
        $code = mt_rand(100000, 999999);
        //短信模板
        $templateCode = 'SMS_140075102';
        //验证
        $validate = validate('Phone');
        if (!$validate->check($post)) {
            return json(['success' => false, 'message' => $validate->getError()]);
        }
        $phone = $post['phone'];

        $result = sendMsg($phone, $code, $templateCode);
        //\think\Log::write('发送短信结果：'.json($result));
        //$result['Code'] = 'OK';
        if ($result['Code'] == 'OK') {
            //存到缓存当中,并且返回json数据给前端
            Cache::set('tel' . $phone, $code, 90);
            return json(['success' => true, 'message' => '发送成功']);
        } else if ($result['Code'] == 'isv.BUSINESS_LIMIT_CONTROL') {
            return json(['success' => false, 'message' => '用户短信发送频繁，请稍后再试', 'errorcode' => $result['Code']]);
        } else {
            return json(['success' => false, 'message' => '发送失败', 'errorcode' => $result['Code']]);
        }

    }
}