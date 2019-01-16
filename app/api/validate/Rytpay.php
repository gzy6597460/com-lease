<?php

namespace app\api\validate;

use think\Validate;
use think\Db;

class Rytpay extends Validate
{
    protected $rule = [
        'token' => 'require',
        'realName' => 'require',

        //绑定银行卡
        'bankNo' => 'require',
        'cardType' => 'require',
        'phoneNo' => 'require',
        'certNo' => 'require',
        'idType' => 'require',
        'accProp' => 'require',
        'province' => 'require',
        'city' => 'require',
        'branchName' => 'require',

        //签约确认
        'smsCode' => 'require',
        'protocolReqNo' => 'require',

        //单笔代扣
        'orderNo' => 'require',
        'txnAmt' => 'require',
        'accNo' => 'require',

        //支付
        'order_id'=>'require',
        'bankcard_id'=>'require',

    ];

    protected $message = [
        'realName.require' => '请输入姓名',
        //签约申请
        'bankNo.require' => '请输入银行卡号',
        'cardType.require' => '请输入银行卡类型',
        'phoneNo.require' => '请输入手机号码',
        'certNo.require' => '请输入证件号',
        'idType.require' => '请输入证件类型',
        'accProp.require' => '账号属性',


        //签约确认
        'smsCode.require' => '请输入验证码',
        'protocolReqNo.require' => '请输入签约流水号[reqNo]',

        //单笔代扣
        'orderNo.require' => '请输入订单号',
        'txnAmt.require' => '请输入金额',
        'accNo.require' => '请输入银行卡号',
        'province.require' => '请输入省份',
        'city.require' => '请输入城市',
        'branchName.require' => '请输入支行详细名称',

        //银行卡支付
        'order_id.require'=>'请输入订单号',
        'bankcard_id.require'=>'请选择银行卡',
    ];

    protected $scene = [
        'sign' => ['token','bankNo', 'cardType', 'phoneNo', 'certNo', 'idType', 'accProp','realName', 'province', 'city', 'branchName'],
        'signConfirm' => ['smsCode', 'protocolReqNo'],
        'payOne' => ['realName', 'orderNo', 'txnAmt', 'accNo', 'province', 'city', 'branchName'],
        'bankPay' => ['token', 'order_id', 'bankcard_id'],
    ];

}