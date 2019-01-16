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

// 应用公共文件
use think\Log;
use think\Db;

/**
 * 根据附件表的id返回url地址
 * @param  [type] $id [descripticon]
 * @return [type]     [description]
 */
function geturl($id)
{
	if ($id) {
		$geturl = \think\Db::name("attachment")->where(['id' => $id])->find();
		if($geturl['status'] == 1) {
			//审核通过
			return $geturl['filepath'];
		} elseif($geturl['status'] == 0) {
			//待审核
			return '/uploads/xitong/beiyong1.jpg';
		} else {
			//不通过
			return '/uploads/xitong/beiyong2.jpg';
		} 
    }
    return false;
}


/**
 * [SendMail 邮件发送]
 * @param [type] $address  [description]
 * @param [type] $title    [description]
 * @param [type] $message  [description]
 * @param [type] $from     [description]
 * @param [type] $fromname [description]
 * @param [type] $smtp     [description]
 * @param [type] $username [description]
 * @param [type] $password [description]
 */
function SendMail($address)
{
    vendor('phpmailer.PHPMailerAutoload');
    //vendor('PHPMailer.class#PHPMailer');
    $mail = new \PHPMailer();          
     // 设置PHPMailer使用SMTP服务器发送Email
    $mail->IsSMTP();                
    // 设置邮件的字符编码，若不指定，则为'UTF-8'
    $mail->CharSet='UTF-8';         
    // 添加收件人地址，可以多次使用来添加多个收件人
    $mail->AddAddress($address); 

    $data = \think\Db::name('emailconfig')->where('email','email')->find();
            $title = $data['title'];
            $message = $data['content'];
            $from = $data['from_email'];
            $fromname = $data['from_name'];
            $smtp = $data['smtp'];
            $username = $data['username'];
            $password = $data['password'];   
    // 设置邮件正文
    $mail->Body=$message;           
    // 设置邮件头的From字段。
    $mail->From=$from;  
    // 设置发件人名字
    $mail->FromName=$fromname;  
    // 设置邮件标题
    $mail->Subject=$title;          
    // 设置SMTP服务器。
    $mail->Host=$smtp;
    // 设置为"需要验证" ThinkPHP 的config方法读取配置文件
    $mail->SMTPAuth=true;
    //设置html发送格式
    $mail->isHTML(true);           
    // 设置用户名和密码。
    $mail->Username=$username;
    $mail->Password=$password; 
    // 发送邮件。
    return($mail->Send());
}


/**
 * 阿里大鱼短信发送
 * @param [type] $appkey    [description]
 * @param [type] $secretKey [description]
 * @param [type] $type      [description]
 * @param [type] $name      [description]
 * @param [type] $param     [description]
 * @param [type] $phone     [description]
 * @param [type] $code      [description]
 * @param [type] $data      [description]
 */
//function SendSms($param,$phone)
//{
//    // 配置信息
//    import('dayu.top.TopClient');
//    import('dayu.top.TopLogger');
//    import('dayu.top.request.AlibabaAliqinFcSmsNumSendRequest');
//    import('dayu.top.ResultSet');
//    import('dayu.top.RequestCheckUtil');
//
//    //获取短信配置
//    $data = \think\Db::name('smsconfig')->where('sms','sms')->find();
//            $appkey = $data['appkey'];
//            $secretkey = $data['secretkey'];
//            $type = $data['type'];
//            $name = $data['name'];
//            $code = $data['code'];
//
//    $c = new \TopClient();
//    $c ->appkey = $appkey;
//    $c ->secretKey = $secretkey;
//
//    $req = new \AlibabaAliqinFcSmsNumSendRequest();
//    //公共回传参数，在“消息返回”中会透传回该参数。非必须
//    $req ->setExtend("");
//    //短信类型，传入值请填写normal
//    $req ->setSmsType($type);
//    //短信签名，传入的短信签名必须是在阿里大于“管理中心-验证码/短信通知/推广短信-配置短信签名”中的可用签名。
//    $req ->setSmsFreeSignName($name);
//    //短信模板变量，传参规则{"key":"value"}，key的名字须和申请模板中的变量名一致，多个变量之间以逗号隔开。
//    $req ->setSmsParam($param);
//    //短信接收号码。支持单个或多个手机号码，传入号码为11位手机号码，不能加0或+86。群发短信需传入多个号码，以英文逗号分隔，一次调用最多传入200个号码。
//    $req ->setRecNum($phone);
//    //短信模板ID，传入的模板必须是在阿里大于“管理中心-短信模板管理”中的可用模板。
//    $req ->setSmsTemplateCode($code);
//    //发送
//
//
//    $resp = $c ->execute($req);
//}


/**
 * 替换手机号码中间四位数字
 * @param  [type] $str [description]
 * @return [type]      [description]
 */
function hide_phone($str){
    $resstr = substr_replace($str,'****',3,4);  
    return $resstr;  
}


/**
 * 阿里云短信发送
 * @param  [type] $str [description]
 * @return [type]      [description]
 */
//首先在函数顶部引入阿里云短信的命名空间，无需修改，官方sdk自带的命名空间
use Aliyun\Core\Config;
use Aliyun\Core\Profile\DefaultProfile;
use Aliyun\Core\DefaultAcsClient;
use Aliyun\Api\Sms\Request\V20170525\SendSmsRequest;

//阿里短信函数，$mobile为手机号码，$code为自定义随机数
function sendMsg($mobile,$code,$templateCode){

    //这里的路径EXTEND_PATH就是指tp5根目录下的extend目录，系统自带常量。alisms为我们复制api_sdk过来后更改的目录名称
    require_once EXTEND_PATH.'alisms/vendor/autoload.php';
    Config::load();             //加载区域结点配置

    $accessKeyId = 'LTAI4ANIZP6RJaQu';  //阿里云短信获取的accessKeyId

    $accessKeySecret = 'NipIP9ERN8nx0oRxG0U4AeCI5JgZ9G';    //阿里云短信获取的accessKeySecret

    //这个个是审核过的模板内容中的变量赋值，记住数组中字符串code要和模板内容中的保持一致
    //比如我们模板中的内容为：你的验证码为：${code}，该验证码5分钟内有效，请勿泄漏！
    $templateParam = array("code"=>$code);           //模板变量替换

    $signName = '朗汇科技'; //这个是短信签名，要审核通过

//    $templateCode = 'SMS_140075102';   //短信模板ID，记得要审核通过的

    //短信API产品名（短信产品名固定，无需修改）
    $product = "Dysmsapi";
    //短信API产品域名（接口地址固定，无需修改）
    $domain = "dysmsapi.aliyuncs.com";
    //暂时不支持多Region（目前仅支持cn-hangzhou请勿修改）
    $region = "cn-hangzhou";

    // 初始化用户Profile实例
    $profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
    // 增加服务结点
    DefaultProfile::addEndpoint("cn-hangzhou", "cn-hangzhou", $product, $domain);
    // 初始化AcsClient用于发起请求
    $acsClient= new DefaultAcsClient($profile);

    // 初始化SendSmsRequest实例用于设置发送短信的参数
    $request = new SendSmsRequest();
    // 必填，设置雉短信接收号码
    $request->setPhoneNumbers($mobile);

    // 必填，设置签名名称
    $request->setSignName($signName);

    // 必填，设置模板CODE
    $request->setTemplateCode($templateCode);

    // 可选，设置模板参数
    if($templateParam) {
        $request->setTemplateParam(json_encode($templateParam));
    }

    //发起访问请求
    $acsResponse = $acsClient->getAcsResponse($request);

    //返回请求结果
    $result = json_decode(json_encode($acsResponse),true);
    return $result;
}

//添加积分
function add_score($member_id='',$extra_score='',$channel = '默认'){
    if (empty($member_id)){
        Log::record('添加积分出错，用户ID为空[' . var_export($member_id, true) . ']！', 'info');
        return false;
    }
    $before_info = db('member_score')->where('member_id', $member_id)->find();
    $result = db('member_score')->where('member_id', $member_id)->setInc('get_score',$extra_score);
    $after_info = db('member_score')->where('member_id', $member_id)->find();
    db('score_history')->insert([
        'score'=>$extra_score,
        'member_id'=>$member_id,
        'channel'=>$channel,
        'before'=> $before_info['get_score'],
        'after'=> $after_info['get_score'],
        'create_time'=>date('Y-m-d H:i:s'),
    ]);
    if ($result){
        return true;
    }
}

//抵扣积分
function dec_score($member_id='',$dec_score='',$channel = '默认'){
    if (empty($member_id)){
        Log::record('抵扣积分出错，用户ID为空[' . var_export($member_id, true) . ']！', 'info');
        return false;
    }
    Db::startTrans();
    try {
        $before_score = Db::name('member_score')->where('member_id', $member_id)->value('get_score');
        Db::name('member_score')->where('member_id', $member_id)->setDec('get_score', $dec_score);
        $after_score = Db::name('member_score')->where('member_id', $member_id)->value('get_score');
        //添加积分日志
        Db::name('score_history')->insert([
            'channel' => $channel,
            'before' => $before_score,
            'after' => $after_score,
            'member_id' => $member_id,
            'score' => '-' . $dec_score,
            'create_time' => date('Y-m-d H:i:s')
        ]);
        // 提交事务
        Db::commit();
    } catch (\Exception $e) {
        // 回滚事务
        Log::record('抵扣积分失敗...' . var_export($member_id, true), 'info');
        Db::rollback();
        return false;
    }
    return true;

}

//验证银行卡
function check_bankCard($card_number){
    $arr_no = str_split($card_number);
    $last_n = $arr_no[count($arr_no)-1];
    krsort($arr_no);
    $i = 1;
    $total = 0;
    foreach ($arr_no as $n){
        if($i%2==0){
            $ix = $n*2;
            if($ix>=10){
                $nx = 1 + ($ix % 10);
                $total += $nx;
            }else{
                $total += $ix;
            }
        }else{
            $total += $n;
        }
        $i++;
    }
    $total -= $last_n;
    $x = 10 - ($total % 10);
    if($x == $last_n){
        return 'true';
    }else{
        return 'false';
    }
}
//验证是否是身份证
function is_idcard( $id )
{
    $id = strtoupper($id);
        $regx = "/(^\d{15}$)|(^\d{17}([0-9]|X)$)/";
    $arr_split = array();
    if(!preg_match($regx, $id))
    {
        return FALSE;
    }
    if(15==strlen($id)) //检查15位
    {
        $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";
        @preg_match($regx, $id, $arr_split);
        //检查生日日期是否正确
        $dtm_birth = "19".$arr_split[2] . '/' . $arr_split[3]. '/' .$arr_split[4];
        if(!strtotime($dtm_birth))
        {
            return FALSE;
        }
        else
        {
            return TRUE;
        }
    }
    else //检查18位
    {
        $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
        @preg_match($regx, $id, $arr_split);
        $dtm_birth = $arr_split[2] . '/' . $arr_split[3]. '/' .$arr_split[4];
        if(!strtotime($dtm_birth)) //检查生日日期是否正确
        {
            return FALSE;
        }
        else
        {
            //检验18位身份证的校验码是否正确。
            //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
            $arr_int = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
            $arr_ch = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
            $sign = 0;
            for ( $i = 0; $i < 17; $i++ )
            {
                $b = (int) $id{$i};
                $w = $arr_int[$i];
                $sign += $b * $w;
            }
            $n = $sign % 11;
            $val_num = $arr_ch[$n];
            if ($val_num != substr($id,17, 1))
            {
                return FALSE;
            }
            else
            {
                return TRUE;
            }
        }
    }
}
//多文件上传
function nFileUpload($file, $path, $saveName = false){            //函数会默认将同名文件覆盖
    if($file['heardimg']['error']){                                    //返回代码不为0是表示上传失败，为0则为成功
        $msg['statusCode'] = '300';
        $msg['message'] = '上传文件失败！';
    }else{
        if($saveName == false){                                    //如果保存文件名为空，则将上传的文件移动到相应目录
            move_uploaded_file($file['heardimg']['tmp_name'], $path.$file['heardimg']['name']);
        }else{
            $arr = explode(".", $file['file']['name']);            //如果保存文件名不为空，则将上传的文件移动到相应目录，并按指定文件名命名
            move_uploaded_file($file['heardimg']['tmp_name'], $path.$saveName.".".end($arr));
        }
        $msg['statusCode'] = '200';
        $msg['message'] = '上传文件成功！';
    }
    return $msg;
}

function object_array($array)
{
    if(is_object($array))
    {
        $array = (array)$array;
    }
    if(is_array($array))
    {
        foreach($array as $key=>$value)
        {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}

//获取用户信息
function get_memberinfo($token)
{
    $member_id = db('member_token')->where('token', $token)->value('member_id');
    if (empty($member_id)) {
        jsonOk(null, null, '登录状态失效,请重新登录。', false);
    }
    $member_info = db('member')->where('id', $member_id)->find();
    if (empty($member_info)) {
        jsonOk(null, null, '未获取到用户信息', false);
    }
    return $member_info;
}

