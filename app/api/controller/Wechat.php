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
header('Access-Control-Allow-Origin:*');

use \think\Db;
use think\Loader;
use \think\Cookie;
use \think\Cache;
use \think\Session;
use \think\Request;
use \think\config;
use \think\Log;
use app\api\controller\Welogin;
use think\Controller;
use app\api\controller\Token;

class Wechat extends Controller
{
    protected $AppID = 'wx5cb120cb7d1c9866';
    protected $AppSecret = '1f26b36c2792e2cb61dd8c00419b7a08';    // AppID(应用ID)  wx034459c0d8451a3f
    protected $Token = 'xlq123123';     // AppSecret   f9531c4b9b10f1d377c300c3d2793774
    protected $Crypt = 'MaS5VoId67f7qfmyeKvZOsMqKB3GHPel6zDbrvCFHwZ';     // 微信后台填写的TOKEN

    public function index()
    {
        //验证消息 input("get.member_id");
        if (isset($_GET['echostr'])) { //微信服务器和你的服务器第一次通讯会带上echostr
            $echoStr = input("get.echostr"); //$_GET["echostr"];
            if ($this->checkSignature()) {
                header('content-type:text');
                echo $echoStr;
                exit;
            }
        } else {
            Loader::import('wechat.wechatcallbackapit');
            $wechatCallbackapiTest = new \wechatcallbackapit();
            $newmenu = '{
                "button": [
                    {
                        "type": "view", 
                        "name": "小猪圈", 
                        "url" : "https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx5cb120cb7d1c9866&redirect_uri=http%3A%2F%2Fadmin.91xzq.com%2Fapi%2Fwelogin%2Fh5login&response_type=code&scope=snsapi_userinfo&state=system_0#wechat_redirect"
                    },
                    {
                          "type": "view",
                           "name": "0元租",
                           "url" : "https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx5cb120cb7d1c9866&redirect_uri=http%3A%2F%2Fadmin.91xzq.com%2Fapi%2Fwelogin%2Fh5zero&response_type=code&scope=snsapi_userinfo&state=system_0#wechat_redirect"
                    },       
                    {
                        "name": "关于我们",
                                "sub_button": [
                                    {
                                        "type": "view",
                                        "name": "官网",
                                        "url": "http://www.91xzq.com"
                                    },
                                    {
                                        "type": "view",
                                        "name": "代理中心",
                                        "url": "http://h5.91xzq.com/agent/personal.html"
                                    }
                                ]
                        } 
                ]
            }';
            $wechatCallbackapiTest->create_menu($newmenu);//创建菜单
            $wechatCallbackapiTest->responseMsg();
        }
    }
    // {
    //                        "type": "view",
    //                        "name": "0元租机",
    //                        "url" : "https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx5cb120cb7d1c9866&redirect_uri=http%3A%2F%2Fadmin.91xzq.com%2Fapi%2Fwelogin%2Fh5zero&response_type=code&scope=snsapi_userinfo&state=system_0#wechat_redirect"
    //                    },

    //检查签名
    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = 'xlq123123';
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }
    // 消息加密KEY（EncodingAESKey）

    /** 模板消息授权连接 */
    public function toOrderUrl($state)
    {
        $order_url = 'http://admin.91xzq.com/api/wechat/h5_order';
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->AppID . '&redirect_uri=' . urlencode($order_url) . '&response_type=code&scope=snsapi_userinfo&state=' . $state . '#wechat_redirect';
        header('location:' . $url);
        return $url;
    }

    /**
     * 模板消息跳转
     */
    public function h5_order()
    {
        //echo '进来了';
        $code = $_GET['code'];
        $where = $_GET['state'];
        //        $Welogin = new Welogin();
        $access_token = $this->getUserAccessToken($code);
        $UserInfo = $this->getUserInfo($access_token);
        if ($UserInfo) {
            Log::record('用户模板信息跳转...', 'info');
            $weixin_id = db('member')->where('weixin_id', $UserInfo['openid'])->find();
            if ($weixin_id) {
                $token = $this->build_token($weixin_id['id']);
                $this->redirect('http://h5.91xzq.com/' . $where . '.html?token=' . $token);
            }
        }
        return json(['success' => false, 'message' => '获取用户信息失败']);
    }

    /** 好友加油站授权连接*/
    public function refuelUrl($state)
    {
        $refuel_url = 'http://admin.91xzq.com/api/wechat/h5_refuel';
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->AppID . '&redirect_uri=' . urlencode($refuel_url) . '&response_type=code&scope=snsapi_userinfo&state=' . $state . '#wechat_redirect';
        //header('location:' . $url);
        return $url;
    }
    /** 好友加油站授权登录*/
    public function h5_refuel()
    {
        //echo '进来了';
        $code = $_GET['code'];
        $order_id = $_GET['state'];
        $order_info = \db('order')->where('id',$order_id)->find();
        if (empty($order_info)){
            jsonOk(null,null,'未获取到订单信息',false);
        }
        //        $Welogin = new Welogin();
        $access_token = $this->getUserAccessToken($code);
        Log::record('获取access_token...' . var_export($access_token, true), 'info');
        $UserInfo = $this->getUserInfo($access_token);
        if ($UserInfo) {
            Log::record('好友加油站—授权进入...', 'info');
            $member_info = db('member')->where('weixin_id', $UserInfo['openid'])->find();
            //用户存在判断是自己还是他人
            if ($member_info) {
                if ($order_info['member_id'] == $member_info['id']){
                    //自己-分享页面
                    $token = $this->build_token($member_info['id']);
                    $this->redirect('http://h5.91xzq.com/forward.html?token=' . $token .'&order_id='.$order_info['id'].'&pageType=share');
                }else{
                    //他人-加油页面
                    $token = $this->build_token($member_info['id']);
                    $this->redirect('http://h5.91xzq.com/forward.html?token=' . $token .'&order_id='.$order_info['id'].'&pageType=refuel');
                }
            }else{
                //新用户授权进入进行注册
                $res = db('member')->insert([
                    'name' => $UserInfo['nickname'],
                    'sex' => $UserInfo['sex'],
                    'weixin_id' => $UserInfo['openid'],
                    'headimgurl' => $UserInfo['headimgurl'],
                    'create_time' => date("Y-m-d H:i:s"),
                    'referee' => $order_info['member_id']? $order_info['member_id']:0,
                ]);
                if ($res){
                    $member_info = db('member')->where('weixin_id',$UserInfo['openid'])->find();
                    db('member_account')->insert([
                        'member_id'=>$member_info['id'],
                        'addr_id'=>0
                    ]);
                    $referee_count = \db('score_history')->where('member_id',$order_info['member_id'])->where('channel','推荐新用户奖励')->count();
                    Log::record('老用户___'. var_export($order_info['member_id'], true).'推荐次数' . var_export($referee_count, true), 'info');
                    if($referee_count<8){
                        add_score($order_info['member_id'],100,'推荐新用户奖励');
                    }
                    Log::record('新用户登录...' . var_export($member_info['id'], true), 'info');
                    $token = $this->build_token($member_info['id']);
                    $this->redirect('http://h5.91xzq.com/forward.html?token=' . $token .'&order_id='.$order_info['id'].'&pageType=refuel');
                }
            }

        }
        jsonOk(null,null,'获取用户信息失败',false);
    }



    /** 发送模板消息*/
    public function sendTemplateMessage($data)
    {
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return false;
        }
        $result = http_post("https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$access_token}", json_encode($data));
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || !empty($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                return false;
            }
            return $json;
        }
        return false;
    }


    /** 获取accesstoken */
    private function getAccessToken()
    {
        // 获取缓存
        $access = Cache::get('access_token');
        // 缓存不存在-重新创建
        if (empty($access)) {
            $weixin_config = Config::get('weixin_config');
            $appid = $weixin_config['appid'];
            $appsecret = $weixin_config['appsecret'];
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

    /**
     * 获取登录token
     */

    public function build_token($member_id)
    {
        $tokenCon = new Token();
        $is_token = db('member_token')->where('member_id', $member_id)->find();
        if ($is_token){
            if ($is_token['expiretime'] < time()){
                $token = $tokenCon->refresh($member_id);
            }else{
                $token = $is_token['token'];
            }
        } else {
            $token =$tokenCon->build($member_id);
        }
        if (empty($token)){
            return null;
        }
        return $token;
    }

    /**
     * 获取授权token
     * @param $code
     * @return bool|string
     */
    private function getUserAccessToken($code)
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$this->AppID&secret=$this->AppSecret&code=$code&grant_type=authorization_code";
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

    public function add_news(){
        $filename = "kfwechat.jpg";

//        file_put_contents($filename,file_get_contents($filename));
//        $filePath = '/www/wwwroot/computer-lease/public/'.$filename;
//        var_dump($filePath);exit();

        $url = "https://api.weixin.qq.com/cgi-bin/material/add_material?access_token=".$this->getAccessToken()."&type=image";
        $real_path = "{$_SERVER['DOCUMENT_ROOT']}/{$filename}";
        if (class_exists ( '\CURLFile' )) {//关键是判断curlfile,官网推荐php5.5或更高的版本使用curlfile来实例文件
            $real_path = new \CURLFile ( $real_path, mime_content_type($filename) );
        } else {
            $real_path = '@' . $real_path;
        }
        $file_info = array(
            'media' => $real_path,
            'type' => 'image',
            'filename' => $filename,
            'filelength' => filesize($filename),
            'content-type' => mime_content_type($filename)
        ); //素材

        $result = $this->curl_post($url,$file_info);
        if(!empty($result)){
            $userUnionjson = json_decode($result["data"]);
            if(isset($userUnionjson->{'errcode'})){
                $sErrCode = $userUnionjson->{'errcode'};

                if ($sErrCode == "40001" || $sErrCode == "41001"|| $sErrCode == "42001"){
                    $sAccessToken = $this->getAccessToken();
                    $url = "https://api.weixin.qq.com/cgi-bin/material/add_material?access_token=".$sAccessToken."&type=image";
                    $result = $this->curl_post($url,$file_info);
                }
            }
        }
        return json($result);
    }

//    public function post($url, $post_data)
//    {
//        $post_data = is_array($post_data) ? http_build_query($post_data) : $post_data;
//        $curl = curl_init();  //这里并没有带参数初始化
//        if (class_exists ( '\CURLFile' )) {//php5.5跟php5.6中的CURLOPT_SAFE_UPLOAD的默认值不同，php版本>=5.6后，这种写法就会导致文件无法进行上传到微信服务器
//            curl_setopt ( $curl, CURLOPT_SAFE_UPLOAD, true );
//        } else {
//            if (defined ( 'CURLOPT_SAFE_UPLOAD' )) {
//                curl_setopt ( $curl, CURLOPT_SAFE_UPLOAD, false );
//            }
//        }
//        curl_setopt($curl, CURLOPT_URL, $url);//这里传入ur
//        // curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
//        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);//对认证证书来源的检查，不开启次功能
//        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);//从证书中检测 SSL 加密算法
//        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);//自动设置referer
//        curl_setopt($curl, CURLOPT_POST, 1);//开启post
//        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);//要传送的数据
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//        $tmpInfo = curl_exec($curl);
//        if (curl_errno($curl)) {
//            echo 'Curl error: ' . curl_error($curl);
//            exit();
//        }
//        curl_close($curl);
//        return $tmpInfo;
//    }

    public function curl_post($url,$data = ''){ // 模拟提交数据函数
        $curl = curl_init(); // 启动一个CURL会话
        if (class_exists ( '\CURLFile' )) {//php5.5跟php5.6中的CURLOPT_SAFE_UPLOAD的默认值不同，php版本>=5.6后，这种写法就会导致文件无法进行上传到微信服务器
            curl_setopt ( $curl, CURLOPT_SAFE_UPLOAD, true );
        } else {
            if (defined ( 'CURLOPT_SAFE_UPLOAD' )) {
                curl_setopt ( $curl, CURLOPT_SAFE_UPLOAD, false );
            }
        }
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
//        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
//        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo['data'] = curl_exec($curl); // 执行操作
        $tmpInfo['errno'] = curl_errno($curl);//捕抓异常

        curl_close($curl); // 关闭CURL会话
        return $tmpInfo; // 返回数据
    }


//    用wechat.class.php
    /*
     微信第三方开发者模式 使用方法
     服务器地址    ： http://你的域名/index.php/Wx/wechat.html
     Token        ： 自己定义
     AppID        ： 微信公众账户后台的AppID
     AppSecret    ： 微信公众账户后台的AppSecret
     Crypt        ： 微信公众账户后台的EncodingAESKey
     */

    public function wechat()
    {
        // 第三方发送消息给公众平台
        $options = array(
            'token' => $this->Token,         // 填写你设定的key
            'encodingaeskey' => $this->Crypt,
            'appid' => $this->AppID,
            'appsecret' => $this->AppSecret,
            'debug' => false,
            'logcallback' => false,
        );

        Loader::import('wechat.wechat', EXTEND_PATH);
        //import("@.Library.wechat");
        $weObj = new \Wechat($options);
        //$weObj->valid();
        $type = $weObj->getRev()->getRevType();
        //设置菜单

        $newmenu = array(
            "button" =>
                array(
                    array('type' => 'view', 'name' => '小猪圈', 'url' => 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx5cb120cb7d1c9866&redirect_uri=http%3A%2F%2Fadmin.91xzq.com%2Fapi%2Fwelogin%2Fh5login&response_type=code&scope=snsapi_userinfo&state=state#wechat_redirect'),
                    array('type' => 'view', 'name' => '代理商中心', 'url' => 'http://h5.91xzq.com/agent/personal.html'),
                    array('type' => 'view', 'name' => '关于我们', 'url' => 'http://www.91xzq.com'),
                )
        );
        // 公众号菜单更新
        //$weObj->getMenu($newmenu);
        $weObj->createMenu($newmenu);
        //$weObj->deleteMenu($newmenu);

        //分解数据获得常用字段
        //$this->openid     = $weObj->getRev()->getRevFrom();
        $this->type = $weObj->getRev()->getRevType();
        $this->data = $weObj->getRev()->getRevData();
        $content = '[捂脸]' . $weObj->getRev()->getRevContent();

        switch ($type) {
            // 文本消息
            case \Wechat::MSGTYPE_TEXT:
                file_put_contents('test.txt', 'TextMessage', FILE_APPEND);
                $weObj->text($content)->reply();
                exit;
                break;
            // 图片消息
            case \Wechat::MSGTYPE_IMAGE:
                $weObj->text('图片？')->reply();
                break;
            // 位置消息
            case \Wechat::MSGTYPE_LOCATION:
                $weObj->text('what?GPS?')->reply();
                exit;
                break;
            // 连接消息
            case \Wechat::MSGTYPE_LINK:
                break;
            // 音乐消息
            case \Wechat::MSGTYPE_MUSIC:
                break;
            // 图文消息（推送过来的应该不存在这种类型，但是可以给用户回复该类型消息）
            case \Wechat::MSGTYPE_NEWS:
                break;
            // 音频消息
            case \Wechat::MSGTYPE_VOICE:
                break;
            // 视频消息
            case \Wechat::MSGTYPE_VIDEO:
                break;
            // 短视频
            case \Wechat::MSGTYPE_SHORTVIDEO:
                break;
            //事件消息:五种
            case \Wechat::MSGTYPE_EVENT:
                $event = $weObj->getRev()->getRevEvent();
                $reply = $this->messageEvent($event);
                break;
            default:
                $weObj->text("你好,我是微信小助手,很高兴为您服务~")->reply();
        }
    }

    // Wechat事件处理
    public function messageEvent($event)
    {
        switch ($event) {
            // 订阅
            case \Wechat::EVENT_SUBSCRIBE:
                $this->text("欢迎关注小猪圈公众号~")->reply();
                break;
            // 取消订阅
            case \Wechat::EVENT_UNSUBSCRIBE:
                break;
            // 扫描带参数二维码
            case \Wechat::EVENT_SCAN:
                echo 123;
                break;
            // 上报地理位置
            case \Wechat::EVENT_LOCATION:
                break;
            // 菜单 - 点击菜单跳转链接
            case \Wechat::EVENT_MENU_VIEW:
                break;
            // 菜单 - 点击菜单拉取消息
            case \Wechat::EVENT_MENU_CLICK:
                break;
            // 菜单 - 扫码推事件(客户端跳URL)
            case \Wechat::EVENT_MENU_SCAN_PUSH:
                break;
            // 菜单 - 扫码推事件(客户端不跳URL)
            case \Wechat::EVENT_MENU_SCAN_WAITMSG:
                break;
            // 菜单 - 弹出系统拍照发图
            case \Wechat::EVENT_MENU_PIC_SYS:
                break;
            // 菜单 - 弹出拍照或者相册发图
            case \Wechat::EVENT_MENU_PIC_PHOTO:
                break;
            // 菜单 - 弹出微信相册发图器
            case \Wechat::EVENT_MENU_PIC_WEIXIN:
                break;
            // 菜单 - 弹出地理位置选择器
            case \Wechat::EVENT_MENU_LOCATION:
                break;
            default:
                break;
        }
    }

    /* 微信登录验证，可以选择方法一（静默授权）或者方法二（用户手动授权）
       1、设置微信公众号的菜单，访问 http://你的域名/index.php/Wx/checkLogin.html
       2、返回redirect_uri的授权域名，获取$_GET['code']
       3、通过code换取网页授权access_token和openID
       4、function loginWx() 就是最终获取数据的方法
     */
    public function checkLogin()
    {
        // 方法一 Scope为snsapi_base基本授权
        //$result = $this->_baseAuth("http://www.kodjr.com/index.php/Wx/loginWx.html");

        // 方法二 Scope为snsapi_userinfo用户授权
        //$result = $this->_userInfoAuth("http://www.kodjr.com/index.php/Wap/loginWx.html");
    }

    // 验证是否邦定帐号了
    public function loginWx()
    {
        $code = $_GET['code'];
        // 方法一
        $tokenOpenID = $this->_baseToken($code);
        ///dump($tokenOpenID);
        // 设置session
        if (!session('user_wechat_auth')) {
            session('user_wechat_auth', $tokenOpenID);
        } else {

        }
        // 方法二
        //$userData = $this->_userInfoToken($code);
        //$userInfo = $this->_getUserInfo($userData['access_token'],$userData['openid']);
    }


    // 方法一：Scope为snsapi_base基本授权

    public function _baseToken($code)
    {
        // 3.通过code换取网页授权access_token和openID
        $curl = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $this->AppID . '&secret=' . $this->AppSecret . '&code=' . $code . '&grant_type=authorization_code';
        $content = $this->http_curl($curl);
        $result = json_decode($content[1], true);
        $result['state'] = $content[0];
        return $result;
    }

    public function http_curl($url, $method = 'POST', $postfields = null, $headers = array(), $debug = false)
    {
        $ci = curl_init();
        /* Curl settings */
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ci, CURLOPT_TIMEOUT, 30);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);

        switch ($method) {
            case 'POST':
                curl_setopt($ci, CURLOPT_POST, true);
                if (!empty($postfields)) {
                    curl_setopt($ci, CURLOPT_POSTFIELDS, http_build_query($postfields));
                    $this->postdata = $postfields;
                }
                break;
        }
        curl_setopt($ci, CURLOPT_URL, $url);
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ci, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($ci);
        $http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);

        if ($debug) {
            echo "=====post data======\r\n";
            var_dump($postfields);

            echo '=====info=====' . "\r\n";
            print_r(curl_getinfo($ci));

            echo '=====$response=====' . "\r\n";
            print_r($response);
        }
        curl_close($ci);
        return array($http_code, $response);
    }

    // 方法二：Scope为snsapi_userinfo用户授权

    public function _baseAuth($redirect_url)
    {
        // 1.准备scope为snsapi_base网页授权页面
        $baseurl = urlencode($redirect_url);
        $snsapi_base_url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->AppID . '&redirect_uri=' . $baseurl . '&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect';
        // 2.静默授权,获取code
        $code = $_GET['code'];
        if (!isset($code)) {
            header('Location:' . $snsapi_base_url);
        }
    }

    public function _userInfoAuth($redirect_url)
    {
        //1.准备scope为snsapi_userInfo网页授权页面
        $redirecturl = urlencode($redirect_url);
        $snsapi_userInfo_url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $this->AppID . '&redirect_uri=' . $redirecturl . '&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect';
        //2.用户手动同意授权,同意之后,获取code
        $code = $_GET['code'];
        if (!isset($code)) {
            header('Location:' . $snsapi_userInfo_url);
        }
    }

    public function _userInfoToken($code)
    {
        //3.通过code换取网页授权access_token
        $curl = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $this->AppID . '&secret=' . $this->AppSecret . '&code=' . $code . '&grant_type=authorization_code';
        $content = $this->http_curl($curl);
        $result = json_decode($content[1], true);
        $result['state'] = $content[0];
        //4.通过access_token和openid拉取用户信息
        $webAccess_token = $result->access_token;
        $openid = $result->openid;
        $userInfourl = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $webAccess_token . '&openid=' . $openid . '&lang=zh_CN ';
        $recontent = $this->http_curl($userInfourl);
        $userInfo = json_decode($recontent, true);
        return $userInfo;
    }
}
