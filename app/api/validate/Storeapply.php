<?php

namespace app\api\validate;

use think\Validate;

class Storeapply extends Validate
{
    protected $rule = [
        'legal_representative' => 'require',
        'representative_phone' => 'require|isPhone:1',
        'representative_IDcard' => 'require|is_idcard:1',
        'IDcard_validity' => 'require|date',
        'IDcard_front' => 'require|url',
        'IDcard_reverse' => 'require|url',
        'business_name' => 'require',
        'business_license' => 'require|url',
        'certificate_tax' => 'require|url',
        'certificate_organizationCode' => 'require|url',
        'certificate_bank' => 'require|url',
        'certificate_3C' => 'require|url',
    ];

    protected $message = [
        'business_name' => '请输入企业名称',
        'legal_representative.require' => '法定代表人必须',
        'representative_phone.require' => '法定代表人手机号必须',
        'representative_phone.isPhone' => '请输入正确的手机号',
        'representative_IDcard.require' => '法定代表人身份证号必须',
        'representative_IDcard.is_idcard' => '请输入正确的身份证号',
        'IDcard_validity.require' => '身份证有效期必须',
        'IDcard_validity.date' => '身份证有效期格式错误',
        'IDcard_front.require' => '身份证正面照必须',
        'IDcard_front.url' => '身份证正面照错误,请重新上传',
        'IDcard_reverse.require' => '身份证反面照必须',
        'IDcard_reverse.url' => '身份证反面照错误,请重新上传',
        'business_license.require' => '营业执照必须',
        'business_license.url' => '营业执照错误,请重新上传',
        'certificate_tax.require' => '税务登记证必须',
        'certificate_tax.url' => '税务登记证错误,请重新上传',
        'certificate_organizationCode.require' => '组织结构代码证必须',
        'certificate_organizationCode.url' => '组织结构代码证错误,请重新上传',
        'certificate_bank.require' => '银行开户许可证必须',
        'certificate_bank.url' => '银行开户许可证错误,请重新上传',
        'certificate_3C.require' => '3C认证资质照必须',
        'certificate_3C.url' => '3C认证资质照错误,请重新上传',
    ];

    //验证是否是正确的手机号
    protected function isPhone($value)
    {
        $rule = '/^1[3456789]{1}\d{9}$/';
        $result = preg_match($rule, $value);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }
    //验证是否是身份证
    function is_idcard($value)
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
}