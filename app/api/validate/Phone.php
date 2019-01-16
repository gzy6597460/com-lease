<?php

namespace app\api\validate;

use think\Validate;
use think\Db;

class Phone extends Validate
{
    protected $rule = [
        'phone' => 'require|isPhone:1|hasPhone:1',
    ];

    protected $message = [
        'phone.require' => '请输入手机号',
        'phone.isPhone' => '请输入正确的手机号',
        'phone.hasPhone' => '该手机号码已被注册',
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

    //验证是否存在手机号
    protected function hasPhone($value)
    {
        $result = db('member')->field('phone')->where('phone', $value)->find();
        if ($result) {
            return false;
        } else {
            return true;
        }
    }
}