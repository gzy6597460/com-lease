<?php
namespace app\api\service;

use fast\Arr;
use think\Controller;
use \think\config;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use \think\Log;
use \think\Db;
use app\api\model\Bank as rytModel;
use app\api\controller\Bankinfo as bankInfo;

class Rytpay extends Controller
{
    public function _initialize()
    {
        $config = Config::get('ryt_pay');
        $this->public_key = $config['public_key'];
        $this->appId = $config['appId'];
        $this->platMerNo = $config['platMerNo'];
        $this->singleLimit = $config['singleLimit'];
        $this->limitPeriodUnit = $config['limitPeriodUnit'];
        $this->maxCntLimit = $config['maxCntLimit'];
        $this->businessCode = $config['businessCode'];
        $this->host = $config['host'];
    }

    //获取已绑定银行卡
    public function getSignCard($member_id)
    {
        $model = new rytModel();
        $result = $model->field('id,bankName,bankAbbr,bankNo,status')->where('member_id',$member_id)->where('status','签约成功')->select();
        if (empty($result)){
            return array('status'=>false,'msg'=>'未绑定银行卡','data'=>null,'extra'=>null);
        }
        foreach ($result as $key =>$value){
            $ex_name =explode("·",$value['bankName']);
            $value['bankName'] = $ex_name[0];
            $value['bankNo'] = hideIDcard($value['bankNo']);
        }
        return array('status'=>true,'msg'=>'获取成功','data'=>$result,'extra'=>null);
    }

    //签约申请
    public function signUp($data)
    {
        $reqNo = $this->createNum();
        $info = [
            'reqNo' => $reqNo,//流水号
            'platMerNo' => $this->platMerNo,//商户号
            'disableDate' => $this->sign_date(),
            'singleLimit' => $this->singleLimit,
            'limitPeriodUnit' => $this->limitPeriodUnit,
            'maxCntLimit' => $this->maxCntLimit,
            'businessCode' => $this->businessCode,
        ];
        $data = array_merge($data,$info);
        $pu_key = openssl_pkey_get_public($this->public_key);//这个函数可用来判断公钥是否是可用的
        $jdata = json_encode($data);
        $data_rsa = $this->encrypt_public($jdata,$pu_key);//加密
        $url = $this->host.'/rytpay-business/dfpay/api/hyl.do?merchant/regist';
        $params = [
            'appId' => $this->appId,
            'data' =>$data_rsa
        ];
        $res = http_post($url, $params);
        $res = object_array(json_decode($res));
        Log::record('url' . var_export($url, true), 'info');
        Log::record('发送数据' . var_export($data, true), 'info');
        Log::record('接收数据' . var_export($res, true), 'info');
        \db('api_log')->insert([
            'create_time'=>time(),
            'way'=>'银行卡_签约申请',
            'post'=>json_encode($data),
            'return'=>json_encode($res),
        ]);
        if (empty($res)){
            return array('status'=>false,'msg'=>'银行卡支付系统异常','data'=>'支付平台未返回参数','extra'=>null);
        }
        switch ($res['respCode']){
            case '0000':
                switch ($res['tranStatus']){
                    case '0000':
                        $data['protocolNo'] = $res['protocolNo'];
                        $model = new rytModel();
                        $model->allowField(true)->save($data);
                        Log::record('正确返回流水号' . var_export($reqNo, true), 'info');
                        return array('status'=>true,'msg'=>$res['tranInfo'],'data'=>$res['tranStatus'],'extra'=>$reqNo);
                        break;
                    default:
                        Log::record('报错返回流水号' . var_export($reqNo, true), 'info');
                        return array('status'=>false,'msg'=>'银行卡系统异常('.$res['tranInfo'].')','data'=>$res['tranStatus'],'extra'=>null);
                }
                break;
            case 'D0003':
                return array('status'=>false,'msg'=>$res['respMsg'],'data'=>$res['respCode'],'extra'=>null);
                break;
            default:
                return array('status'=>false,'msg'=>$res['respMsg'],'data'=>$res['respCode'],'extra'=>null);
        }
    }


    //签约确认
    public function confirm($data)
    {
        $pu_key = openssl_pkey_get_public($this->public_key);//这个函数可用来判断公钥是否是可用的
        $info =[
            'reqNo' => $this->createNum(),
            'platMerNo' => $this->platMerNo,
        ];
        $data = array_merge($data,$info);
        $jdata = json_encode($data);
        $data_rsa = $this->encrypt_public($jdata,$pu_key);
        $url = $this->host.'/rytpay-business/dfpay/api/hyl.do?merchant/confirm';
        $params = [
            'appId' => $this->appId,
            'data' =>$data_rsa
        ];
        $res = http_post($url, $params);
        $res = object_array(json_decode($res));
        Log::record('url' . var_export($url, true), 'info');
        Log::record('发送数据' . var_export($data, true), 'info');
        Log::record('接收数据' . var_export($res, true), 'info');
        \db('api_log')->insert([
            'create_time'=>time(),
            'way'=>'银行卡_签约确认',
            'post'=>json_encode($data),
            'return'=>json_encode($res),
        ]);
        if (empty($res)){
            return array('status'=>false,'msg'=>'银行卡支付系统异常','data'=>'支付平台未返回参数','extra'=>null);
        }
        switch ($res['respCode']){
            case 0000:
                switch ($res['tranStatus']){
                    case 0000:
                        $info =Db::name('member_bank')->where('reqNo',$data['protocolReqNo'])->find();
                        $bank = new bankInfo();
                        $bank_name = $bank->getname($info['bankNo']);
                        $bank_shortname = $this->get_shortname($info['bankNo']);
                        Db::name('member_bank')->where('reqNo',$data['protocolReqNo'])->update([
                            'bankAbbr'=>$bank_shortname,
                            'bankName'=>$bank_name,
                            'protocolNo'=>$res['protocolNo']?$res['protocolNo']:0,
                            'status'=>'签约成功',
                            'update_time'=>time(),
                        ]);
                        Log::record('签约成功:' . var_export($res['tranInfo'], true), 'info');
                        return array('status'=>true,'msg'=>$res['tranInfo'],'data'=>$res['tranStatus'],'extra'=>null);
                        break;
                    default:
                        return array('status'=>false,'msg'=>$res['tranInfo'],'data'=>$res['tranStatus'],'extra'=>null);
                }
                break;
            case 'DERROR':
                return array('status'=>false,'msg'=>$res['respMsg'],'data'=>$res['respCode'],'extra'=>null);
            default:
                return array('status'=>false,'msg'=>$res['respMsg'],'data'=>$res['respCode'],'extra'=>null);
        }
    }

    //解约
    public function release($data)
    {
        $reqNo = $this->createNum();
        $info = [
            'reqNo' => $reqNo,//流水号
            'platMerNo' => $this->platMerNo,//商户号
        ];
        $data = array_merge($data,$info);
        $pu_key = openssl_pkey_get_public($this->public_key);//这个函数可用来判断公钥是否是可用的
        $jdata = json_encode($data);
        $data_rsa = $this->encrypt_public($jdata,$pu_key);//加密
        $url = $this->host.'/rytpay-business/dfpay/api/hyl.do?merchant/release';
        $params = [
            'appId' => $this->appId,
            'data' =>$data_rsa
        ];
        $res = http_post($url, $params);
        $res = object_array(json_decode($res));
        return $res;
    }

    //单笔代扣
    public function pay_one($data){
        $info = [
            'reqNo' => $this->createNum(),//流水号
            'platMerNo' => $this->platMerNo,//商户号
        ];
        $data = array_merge($data,$info);
        $pu_key = openssl_pkey_get_public($this->public_key);//这个函数可用来判断公钥是否是可用的
        $jdata = json_encode($data);
        $data_rsa = $this->encrypt_public($jdata,$pu_key);
        $url = $this->host.'/rytpay-business/dfpay/api/hyl.do?trade/collect';
        $params = [
            'appId' => $this->appId,
            'data' =>$data_rsa
        ];
        $res = http_post($url, $params);
        $res = json_decode($res);
        return $res;
    }

    //签约查询
    public function querySign()
    {
        $pu_key = openssl_pkey_get_public($this->public_key);//这个函数可用来判断公钥是否是可用的
        $data =[
            'reqNo' => $this->createNum(),//流水号
            'platMerNo' => $this->platMerNo,
            'protocolNo' => "105201811220611290508",//协议号
        ];
        $jdata = json_encode($data);//转json格式
        $data_rsa = $this->encrypt_public($jdata,$pu_key);
        $url = $this->host.'/rytpay-business/dfpay/api/hyl.do?merchant/query';
        $params = [
            'appId' => $this->appId,
            'data' =>$data_rsa
        ];
        $res = http_post($url, $params);
        dump($res);
    }

    //交易查询
    public function queryPay($data){
        $info = [
            'reqNo' => $this->createNum(),//流水号
            'platMerNo' => $this->platMerNo,//商户号
            'orderNo' => "20181123",
            'txnAmt' => "100",
            'businessCode' => "10702",
        ];

        $data = array_merge($data,$info);
        $pu_key = openssl_pkey_get_public($this->public_key);//这个函数可用来判断公钥是否是可用的
        $jdata = json_encode($data);
        $data_rsa = $this->encrypt_public($jdata,$pu_key);
        $url = $this->host.'/rytpay-business/dfpay/api/hyl.do?trade/collect';
        $params = [
            'appId' => $this->appId,
            'data' =>$data_rsa
        ];
        $res = http_post($url, $params);
        dump($res);
    }

    //支付
    public function bank_pay($data){
        $info = [
            'reqNo' => $this->createNum(),//流水号
            'platMerNo' => $this->platMerNo,//商户号
        ];
        $data = array_merge($data,$info);
        $pu_key = openssl_pkey_get_public($this->public_key);//这个函数可用来判断公钥是否是可用的
        $jdata = json_encode($data);
        $data_rsa = $this->encrypt_public($jdata,$pu_key);
        $url = $this->host.'/rytpay-business/dfpay/api/hyl.do?trade/collect';
        $params = [
            'appId' => $this->appId,
            'data' =>$data_rsa
        ];
        $res = http_post($url, $params);
        $res = object_array(json_decode($res));
        Log::record('url' . var_export($url, true), 'info');
        Log::record('发送数据' . var_export($data, true), 'info');
        Log::record('接收数据' . var_export($res, true), 'info');
        \db('api_log')->insert([
            'create_time'=>time(),
            'way'=>'银行卡_支付',
            'post'=>json_encode($data),
            'return'=>json_encode($res),
        ]);
        if (empty($res)){
            return array('status'=>false,'msg'=>'银行卡支付系统异常','data'=>'支付平台未返回参数','extra'=>null);
        }
        switch ($res['respCode']){
            case 0000:
                if (isset($res['tranStatus'])){
                    switch ($res['tranStatus']){
                        case 0000:
                            return array('status'=>true,'msg'=>$res['tranInfo'],'data'=>$res['tranStatus'],'extra'=>null);
                            break;
                        default:
                            return array('status'=>false,'msg'=>$res['tranInfo'],'data'=>$res['tranStatus'],'extra'=>null);
                    }
                }else{
                    return array('status'=>false,'msg'=>$res['respMsg'],'data'=>$res['respMsg'],'extra'=>null);
                }
                break;
            default:
                return array('status'=>false,'msg'=>$res['respMsg'],'data'=>$res['respCode'],'extra'=>null);
        }
    }

    private function sign_date(){
        return date('Ymd', strtotime("+1 year"));
    }

    private function encrypt_public($data,$pub_key){
        $original_arr=str_split($data,117);//折分
        foreach($original_arr as $o)
        {
            $sub_enc=null;
            openssl_public_encrypt($o,$sub_enc,$pub_key);
            $original_enc_arr[]=$sub_enc;
        }
        openssl_free_key($pub_key);
        $data_rsa=base64_encode(implode('',$original_enc_arr));//最终网络传的密文
        return $data_rsa;
    }

    private function createNum(){
        $order_num = date('ymd').substr(time(),-5).substr(microtime(),2,5);
        return $order_num;
    }

    public function get_shortname($card)
    {
        $res = object_array(json_decode(httpGet('https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?_input_charset=utf-8&cardNo='.$card.'&cardBinCheck=true')));
        if (empty($res)){
            return null;
        }
        return $res['bank'];
    }
}