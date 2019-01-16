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
use app\api\controller\Wechat;

class Foradmin
{
    protected $ticket = 'xzq1234321!';

    public function send_wechatmessage(){
        $post = Request::instance()->post();
        $post_ticket = $post['ticket'];

        if ($post_ticket != $this->ticket){
            jsonOk(null,null,'密钥错误',false);
        }
        if (!isset($post['data'])){
            jsonOk(null,null,'未传模板数据',false);
        }
        $data = $post['data'];
        exit;
        $Wechat = new Wechat();
        $result = $Wechat->sendTemplateMessage($data);
        if (empty($result)){
            jsonOk($result,null,'发送失败',false);
        }
        jsonOk($result,null,'发送成功',true);

    }

}