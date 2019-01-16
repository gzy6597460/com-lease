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

//admin模块公共函数

/**
 * 管理员密码加密方式
 * @param $password  密码
 * @param $password_code 密码额外加密字符
 * @return string
 */
function password($password, $password_code = 'lshi4AsSUrUOwWV')
{
    return md5(md5($password) . md5($password_code));
}

/**
 * 管理员操作日志
 * @param  [type] $data [description]
 * @return [type]       [description]
 */
function addlog($operation_id = '')
{
    //获取网站配置
    $web_config = \think\Db::name('webconfig')->where('web', 'web')->find();
    if ($web_config['is_log'] == 1) {
        $data['operation_id'] = $operation_id;
        $data['admin_id'] = \think\Session::get('admin');//管理员id
        $request = \think\Request::instance();
        $data['ip'] = $request->ip();//操作ip
        $data['create_time'] = time();//操作时间
        $url['module'] = $request->module();
        $url['controller'] = $request->controller();
        $url['function'] = $request->action();
        //获取url参数
        $parameter = $request->path() ? $request->path() : null;
        //将字符串转化为数组
        $parameter = explode('/', $parameter);
        //剔除url中的模块、控制器和方法
        foreach ($parameter as $key => $value) {
            if ($value != $url['module'] and $value != $url['controller'] and $value != $url['function']) {
                $param[] = $value;
            }
        }

        if (isset($param) and !empty($param)) {
            //确定有参数
            $string = '';
            foreach ($param as $key => $value) {
                //奇数为参数的参数名，偶数为参数的值
                if ($key % 2 !== 0) {
                    //过滤只有一个参数和最后一个参数的情况
                    if (count($param) > 2 and $key < count($param) - 1) {
                        $string .= $value . '&';
                    } else {
                        $string .= $value;
                    }
                } else {
                    $string .= $value . '=';
                }
            }
        } else {
            //ajax请求方式，传递的参数path()接收不到，所以只能param()
            $string = [];
            $param = $request->param();
            foreach ($param as $key => $value) {
                if (!is_array($value)) {
                    //这里过滤掉值为数组的参数
                    $string[] = $key . '=' . $value;
                }
            }
            $string = implode('&', $string);
        }
        $data['admin_menu_id'] = empty(\think\Db::name('admin_menu')->where($url)->where('parameter', $string)->value('id')) ? \think\Db::name('admin_menu')->where($url)->value('id') : \think\Db::name('admin_menu')->where($url)->where('parameter', $string)->value('id');

        //return $data;
        \think\Db::name('admin_log')->insert($data);
    } else {
        //关闭了日志
        return true;
    }

}


/**
 * 格式化字节大小
 * @param  number $size 字节数
 * @param  string $delimiter 数字和单位分隔符
 * @return string            格式化后的带单位的大小
 */
function format_bytes($size, $delimiter = '')
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
    return round($size, 2) . $delimiter . $units[$i];
}

/**
 * 发送短信
 * @param  number $size 字节数
 * @param  string $delimiter 数字和单位分隔符
 * @return string            格式化后的带单位的大小
 */


/**
 * 封装生成二维码函数
 *
 */

function getQrcode($url){
    /*生成二维码*/
    vendor("phpqrcode.phpqrcode");
    $data =$url;
    $level = 'L';// 纠错级别：L、M、Q、H
    $size =4;// 点的大小：1到10,用于手机端4就可以了
    $QRcode = new \QRcode();
    ob_start();
    $QRcode->png($data,false,$level,$size,2);
    $imageString = base64_encode(ob_get_contents());
    ob_end_clean();
    return "data:image/jpg;base64,".$imageString;
}


function httpGet($url) {
    //echo "url = ".$url;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);
    return $res;
}

function httpPost($url,$data){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

    $res = curl_exec($curl);
    curl_close($curl);
    return $res;
}

//* 正确时返回数据
//* @param null $data
//* @param null $msg
//* @param int $err
//*/
function jsonOk($data=null,$extra=null,$msg=null,$status=true,$token=null,$code=200){
    $res = [
        'status' => $status,
        'code'  => $code,
        'message' => $msg,
        'token' => $token,
        'data' => $data,
        'extra' => $extra,
    ];
//        dump($res);
    die(json_encode($res));
}



/**
 * 正确时返回数据
 * @param null $data
 * @param null $msg
 * @param int $err
 */
function jsonErr($msg='出错了或者数据为空',$data=null,$extra=null,$status=false){
    $res = [
        'status' => $status,
        'code'  => 200,
        'message' => $msg,
        'token' => null,
        'data' => $data,
        'extra' => $extra,
    ];
//        dump($res);
    die(json_encode($res));
}

//隐藏手机中间号码
function hidephone($phone){
    $p = substr($phone,0,3)."****".substr($phone,7,4);
    return $p;
}

//隐藏身份证
function hideIDcard($idcard){
    $p = substr($idcard,0,4)."****".substr($idcard,14,4);
    return $p;
}

//阿里云市场快递查询api
function queryExpress($express_id){
    $host = "https://cexpress.market.alicloudapi.com";
    $path = "/cexpress";
    $method = "GET";
    $appcode = "188d6cc1c5db4a7aba8de63f17b8a98c";
    $headers = array();
    array_push($headers, "Authorization:APPCODE " . $appcode);
    $querys = "no=".$express_id;
    $bodys = "";
    $url = $host . $path . "?" . $querys;

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
    $res = curl_exec($curl);
    return $res;
}

function settoken($member_id){
    $str = md5(uniqid(md5(microtime(true)),true));  //生成一个不会重复的字符串
    $str = 'xzq_'.md5($member_id).'_'.sha1($str);  //加密
    return $str;
}

function http_post($url,$param,$post_file=false){
    $oCurl = curl_init();
    if(stripos($url,"https://")!==FALSE){
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
    }
    if (PHP_VERSION_ID >= 50500 && class_exists('\CURLFile')) {
        $is_curlFile = true;
    } else {
        $is_curlFile = false;
        if (defined('CURLOPT_SAFE_UPLOAD')) {
            curl_setopt($oCurl, CURLOPT_SAFE_UPLOAD, false);
        }
    }
    if (is_string($param)) {
        $strPOST = $param;
    }elseif($post_file) {
        if($is_curlFile) {
            foreach ($param as $key => $val) {
                if (substr($val, 0, 1) == '@') {
                    $param[$key] = new \CURLFile(realpath(substr($val,1)));
                }
            }
        }
        $strPOST = $param;
    } else {
        $aPOST = array();
        foreach($param as $key=>$val){
            $aPOST[] = $key."=".urlencode($val);
        }
        $strPOST =  join("&", $aPOST);
    }
    curl_setopt($oCurl, CURLOPT_URL, $url);
    curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt($oCurl, CURLOPT_POST,true);
    curl_setopt($oCurl, CURLOPT_POSTFIELDS,$strPOST);
    $sContent = curl_exec($oCurl);
    $aStatus = curl_getinfo($oCurl);
    curl_close($oCurl);
    if(intval($aStatus["http_code"])==200){
        return $sContent;
    }else{
        return false;
    }
}