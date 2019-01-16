<?php

namespace app\api\controller;

use think\Controller;
use think\Request;
use \think\Db;
use think\Log;
use think\Cache;
use app\api\controller\Token;
use app\api\controller\Member;
use app\api\service\Member as memberService;

header('Access-Control-Allow-Origin:*');

/**
 * 微信授权登录类
 * User: summer
 * Date: 2017/11/27
 * Time: 13:57
 */
class Welogin extends Controller
{
    private $appid = 'wx5cb120cb7d1c9866';                 //微信公众号APPID
    private $appsecret = '1f26b36c2792e2cb61dd8c00419b7a08';             //密匙
    private $url = 'http://admin.91xzq.com/api/welogin/h5login';       //微信回调地址
    private $biz = 'MzUzNDkxNTI3NQ';       //唯一标示

    /** 授权登录连接 */
    public function start($state)
    {
        $member_id = input("get.member_id");
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->appid . '&redirect_uri=' . urlencode($this->url) . '&response_type=code&scope=snsapi_userinfo&state=' . $state . '#wechat_redirect';
        header('location:' . $url);
        return $url;
    }

    /*** 微信H5登录*/
    public function h5login()
    {
        //echo '进来了';
        $code = $_GET['code'];
        $state = $_GET['state'];
        if ($state == 'state') {
            $state = null;
            $referee_type = 'system';
            $referee_id = 0;
        } else {
            $state = explode("_", $state);
            $referee_type = $state[0];
            $referee_id = $state[1];
        }

        $access_token = $this->getUserAccessToken($code);
        $UserInfo = $this->getUserInfo($access_token);
        if ($UserInfo) {
            $memberSer = new memberService();
            $memberSer->member_login($UserInfo,$referee_type,$referee_id,'http://h5.91xzq.com/index.html');
        }
        return json(['success' => false, 'message' => '获取用户信息失败']);
    }

    /*** 微信H5登录-0元租机*/
    public function h5zero()
    {
        //echo '进来了';
        $code = $_GET['code'];
        $state = $_GET['state'];
        if ($state == 'state') {
            $state = null;
            $referee_type = 'system';
            $referee_id = 0;
        } else {
            $state = explode("_", $state);
            $referee_type = $state[0];
            $referee_id = $state[1];
        }

        $access_token = $this->getUserAccessToken($code);
        $UserInfo = $this->getUserInfo($access_token);
        if ($UserInfo) {
            $memberSer = new memberService();
            $memberSer->member_login($UserInfo,$referee_type,$referee_id,'http://h5.91xzq.com/leasehold.html');
        }
        return json(['success' => false, 'message' => '获取用户信息失败']);
    }

    /**
     * 获取授权token
     * @param $code
     * @return bool|string
     */
    private function getUserAccessToken($code)
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$this->appid&secret=$this->appsecret&code=$code&grant_type=authorization_code";
        $res = file_get_contents($url);
        return json_decode($res);
    }

    /**
     * 获取用户信息
     * @param $accessToken
     * @return mixed
     */
    private function getUserInfo($accessToken)
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=$accessToken->access_token&openid=$accessToken->openid&lang=zh_CN";
        $UserInfo = file_get_contents($url);
        return json_decode($UserInfo, true);
    }

    /**
     * 获取全局token
     * @param $code
     * @return bool|string
     */
    private function getAllaccessToken()
    {
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appid&secret=$this->appsecret";
        $res = httpGet($url);
        $res = json_decode($res, true);
        return $res['access_token'];
    }

    //    全局用户信息
    private function getuserallinfo($allaccessToken, $openid)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$allaccessToken&openid=$openid&lang=zh_CN";
        $UserInfo = httpGet($url);
        $res = json_decode($UserInfo, true);
        return $res;
    }
    /**
     * 此AccessToken   与 getUserAccessToken不一样
     * 获得AccessToken
     * @return mixed
     */
//    private
    public function getAccessToken()
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


    /**
     * 获取JS证明
     * @param $accessToken
     * @return mixed
     */
    private function _getJsapiTicket($accessToken)
    {

        // 获取缓存
        $ticket = cache('jsapi_ticket');
        // 缓存不存在-重新创建
        if (empty($ticket)) {
            // 获取js_ticket
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=" . $accessToken . "&type=jsapi";
            $jsTicket = file_get_contents($url);
            $jsTicket = json_decode($jsTicket);
            // 保存至缓存
            $ticket = $jsTicket->ticket;
            cache('jsapi_ticket', $ticket, 7000);
        }
        return $ticket;
    }

    /**
     * 获取JS-SDK调用权限
     */
    public function shareAPi(Request $request)
    {
        header("Access-Control-Allow-Origin:*");
        // 获取accesstoken
        $accessToken = $this->getAccessToken();
        // 获取jsapi_ticket
        $jsapiTicket = $this->_getJsapiTicket($accessToken);
        //$icon = 'http://xiaozhuquan.oss-cn-shenzhen.aliyuncs.com/uploads/20181015/987fa8bbc16034cebbfa83ef946ff465.png';
        // -------- 生成签名 --------
        $wxConf = [
            'jsapi_ticket' => $jsapiTicket,
            'noncestr' => md5(time() . '!@#$%^&*()_+'),
            'timestamp' => time(),
            'url' => $request->post('url'),  //这个就是你要自定义分享页面的Url啦
        ];
        $string1 = sprintf('jsapi_ticket=%s&noncestr=%s&timestamp=%s&url=%s', $wxConf['jsapi_ticket'], $wxConf['noncestr'], $wxConf['timestamp'], $wxConf['url']);
        // 计算签名
        $wxConf['signature'] = sha1($string1);
        $wxConf['appid'] = $this->appid;
        return json($wxConf);
    }

    //微信关注公众号二维码生成
    public function weqrcode($member_id)
    {
        $access_token = $this->getAccessToken();
        $data = '{"action_name": "QR_LIMIT_STR_SCENE", "action_info": {"scene": {"scene_str":"1_' . $member_id . '"}}}';
        $qrcode = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=" . $access_token . "";
        $qrcode1 = httpPost($qrcode, $data);
        $result = json_decode($qrcode1, true);
        Log::record('data ' . var_export($data, true), 'info');
        Log::record('result ' . var_export($result, true), 'info');
        return $result['url'];
    }

    //长连接转短链接
    public function long2short($longurl = '')
    {
        $access_token = $this->getAccessToken();
        //$access_token = $this->getAllaccessToken();
        $data = '{"action":"long2short","long_url":"' . $longurl . '"}';
        $shorturl = "https://api.weixin.qq.com/cgi-bin/shorturl?access_token=" . $access_token . "";
        $shorturl = httpPost($shorturl, $data);
        $shorturl = json_decode($shorturl, true);
        return $shorturl['short_url'];
    }


    //生成授权分享链接
    public function sharetourl($member_id = '')
    {
        $share_url = $this->start($member_id);
        //$share_url = $this->long2short($share_url);
        return $share_url;
    }

    //用户授权链接生成
    public function shareforurl()
    {
        $token = input('get.token');
        $member_info = get_memberinfo($token);
        $state = 'user_' . $member_info['id'];
        $share_url = $this->start($state);
        //$share_url = $this->long2short($share_url);
        return json($share_url);
    }

    //代理商授权链接生成
    public function accredit_url()
    {
        $post = Request::instance()->post();
        $agent_id = $post['id'];
        $state = 'agent_' . $agent_id;
        $share_url = $this->start($state);
        //$share_url = $this->long2short($share_url);
        return $share_url;
    }

    //生成授权二维码
    public function share_twocode()
    {
        $referee_id = input("get.member_id");
        $share_url = $this->sharetourl($referee_id);
        $qrcode = getQrcode($share_url);
        return $qrcode;
    }

    //用户转发分享二维码
    public function get_twocode()
    {
        $token = input('get.token');
        $member_info = get_memberinfo($token);
        $member_id = $member_info['id'];
        $twocode = db('member')->where('id', $member_id)->value('twocode_url');
        //        $share_url = $this->sharetourl($member_id);
        $share_url = $this->weqrcode($member_id);
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
            $img_name = "/qrcode_" . $member_id . rand(10000, 99999) . ".png";
            $ad = $pathname . $img_name;
            $imgad = 'http://h5.91xzq.com/uploads/Qrcode' . $img_name;
            db('member')->where('id', $member_id)->update(['twocode_url' => $imgad]);
            $errorCorrectionLevel = intval($level);//容错级别
            $matrixPointSize = intval($size);//生成图片大小
            $object->png($url, $ad, $errorCorrectionLevel, $matrixPointSize, 2);
        }
        $twocode = db('member')->where('id', $member_id)->value('twocode_url');
        return json(['twocode_url' => $twocode, 'share_url' => $share_url]);
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
    //是否关注公众号
    //        $all_access_token =$this->getAllaccessToken();
    //        $subscribe_msg = $this->getuserallinfo($all_access_token,$access_token->openid);
    //        if($subscribe_msg['subscribe'] !== 1){
    //                    $weixin_id = db('member')->where('weixin_id',$UserInfo['openid'])->find();
    //                    if (empty($weixin_id)){
    //                        $data = [
    //                            'name' => $UserInfo['nickname'],
    //                            'sex' => $UserInfo['sex'],
    //                            'weixin_id' => $UserInfo['openid'],
    //                            'headimgurl' => $UserInfo['headimgurl'],
    //                            'referee' => $referee
    //                        ];
    //                        db('member')->insert($data);
    //                    }
    //                    //$url = $this->weqrcode();
    //            $url = "https://mp.weixin.qq.com/mp/profile_ext?action=home&__biz=$this->biz==&scene=110#wechat_redirect";
    //                    //$url ="https://mp.weixin.qq.com/mp/profile_ext?action=home&__biz=".$this->biz."&scene=110#wechat_redirect";
    //            $this->redirect($url);
    //        }
}
