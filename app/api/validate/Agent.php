<?php
namespace app\api\validate;

use think\Validate;
use think\Db;

class Agent extends Validate
{
    protected $rule = [
        'name' => 'require',
        'cardBank' => 'require',
        'cardBankType' => 'require',
        'cardSubBank' => 'require',
        'idCardNo' => 'require|isIDcard:1',
        'cardNo' => 'require|check_bankCard:1|hasCard:1',
        'phone' => 'require',
        'smsCode' => 'require',
        'cardProvince' => 'require',
        'cardCity' => 'require',
        'cardArea' => 'require',
    ];

    protected $message = [
        'name.require' => '请填写姓名',
        'cardBank.require' => '请填写银行',
        'cardBankType.require' => '请填写银行类型',
        'cardSubBank.require' => '请填写银行详细名称',
        'idCardNo.require' => '请填写身份证号',
        'cardNo.require' => '请填写银行卡号',
        'phone.require' => '请填写手机号',
        'smsCode.require' => '请填写验证码',
        'cardProvince.require' => '请选择省份',
        'cardCity.require' => '请选择城市',
        'cardArea.require' => '请选择地区',
        'idCardNo.isIDcard' => '请填写正确的身份证号',
        'smsCode.hasCode' => '请填写验证码',
        'cardNo.check_bankCard' => '请输入正确的银行卡号',
        'cardNo.hasCard' => '该银行卡已被提现使用',
    ];

    protected $scene = [
        'addBank'  =>  ['name','cardBank','cardBankType','cardSubBank','idCardNo','cardNo','phone','smsCode','cardProvince','cardCity','cardArea'],
        'token'  =>  ['token','order_id'],
    ];

    //验证是否是身份证
    public function isIDcard($value)
    {
        $id = strtoupper($value);
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
    //验证是否是银行卡
    public function check_bankCard($value){
        $arr_no = str_split($value);
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

    //验证银行卡号是否注册
    public function hasCard($value)
    {
        $result = \db('agent_bank')->where('card_no', $value)->find();
        if ($result) {
            return false;
        }
        return true;
    }

}