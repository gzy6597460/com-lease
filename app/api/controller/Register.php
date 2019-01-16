<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use think\Cookie;
use think\Cache;
use think\Log;
use think\Db;
use app\api\model\Member;

header('Access-Control-Allow-Origin:*');

class Register extends Controller
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
        //验证
        $validate = validate('Phone');
        if (!$validate->check($post)) {
            return json(['success' => false, 'message' => $validate->getError()]);
        }
        $phone = $post['phone'];
        //发送验证码
        $code = mt_rand(100000, 999999);
        //短信模板
        $templateCode = 'SMS_140075102';
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

    /**
     * 手机注册接口
     */
    public function newRegister()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = get_memberinfo($token);
        $member_id = $member_info['id'];
        $code = Cache::get('tel' . $post['phone']);
        if (empty($member_id)) {
            return json(['success' => false, 'message' => '请从微信公众号(小猪圈xiaozhuquan)进入']);
        }
        //验证用户是否存在 修改密码
        $data = db('member')->field('id')->where('id', $member_id)->find();
        if (db('member')->field('phone,id')->where('phone', $post['phone'])->find()) {
            return json(['success' => false, 'message' => '该手机号已注册成功', 'member_id' => $data['id']]);
        } else {
            //验证码是否存在
            if ($code) {
                //验证码正确
                if ($post['code'] == $code) {
                    $passwd = password($post['password']);
                    $data = [
                        'phone' => $post['phone'],
                        'password' => $passwd
                    ];
                    $new_member_id = db('member')->where('id', $member_id)->update($data);
                    if ($new_member_id) {
                        add_score($member_id, 1000, '手机注册后送分');
                        //Log::record('手机注册成功：'.$post['member_id'].'赠送1000积分');
                        return json(['success' => true, 'message' => '注册成功', 'member_id' => $member_id]);
                    } else {
                        return json(['success' => false, 'message' => '注册失败,请从微信公众号(小猪圈xiaozhuquan)进入']);
                    }
                } else {
                    return json(['success' => false, 'message' => '验证码错误']);
                }
            } else {
                return json(['success' => false, 'message' => '验证码超时']);
            }
        }
    }

    /**
     * 用户资料填写接口
     * @post
     * @params
     * @return success message
     */
    public function re_psdata()
    {
        $is_post = Request::instance()->ispost();
        if ($is_post == false) {
            jsonOk(null, null, '无效请求!', false);
        }
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = get_memberinfo($token);
        $member_id = $member_info['id'];
        //验证
        $validate = validate('Identity');
        if (!$validate->check($post)) {
            return json(['success' => false, 'message' => $validate->getError()]);
        }
        //身份证认证接口
        $vali_card = new \app\api\controller\Validates;
        $valiresult = $vali_card->getValicard($post['id_card'], $post['nick_name']);
        $valiresult = object_array(json_decode($valiresult));
        if ($valiresult['respMessage'] != '身份证信息匹配') {
            return json(['success' => false, 'message' => $valiresult['respMessage']]);
        }
        $data = [
            'nick_name' => $post['nick_name'],
            'id_card' => $post['id_card'],
            'address' => $post['address'],
            'create_time' => date('Y-m-d H:i:s'),
            'credit_score' => 80,
        ];
        if (db('member')->where('id', $member_id)->update($data)) {
            add_score($member_id, 300, '实名认证送分');
            return json(['success' => true, 'message' => '实名认证成功']);
        }
    }

    /**
     * 忘记密码接口
     * @post
     * @params phone password weixin_id code
     * @return success
     */
    public function forget_password()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = get_memberinfo($token);
        $member_id = $member_info['id'];
        $code = Cache::get('tel' . $post['phone']);
        //验证用户是否存在 修改密码
        if (db('member')->field('phone')->where('id', $member_id)->where('phone', $post['phone'])->find()) {
            //验证码是否存在
            if ($code) {
                //验证码正确
                if ($post['code'] == $code) {
                    $passwd = password($post['password']);
                    $data = ['password' => $passwd];
                    if (db('member')->where('id', $member_id)->where('phone', $post['phone'])->update($data)) {
                        return json(['success' => true, 'message' => '修改成功']);
                    } else {
                        return json(['success' => false, 'message' => '修改失败']);
                    }
                } else {
                    return json(['success' => false, 'message' => '验证码错误']);
                }
            } else {
                return json(['success' => false, 'message' => '验证码超时']);
            }
        } else {
            return json(['success' => false, 'message' => '该手机未注册']);
        }
    }

}
