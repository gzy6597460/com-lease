<?php
namespace app\api\validate;

use think\Validate;
use think\Db;

class Refuelnow extends Validate
{
    protected $rule = [
        'token' => 'require',
        'order_id' => 'require|checkOrder:1|checkCount:1',
    ];

    protected $message = [
        'token.require' => '请登录后再进行加油!',
        'order_id.require' => '未获取到订单信息',
        'order_id.checkOrder' => '订单信息异常,不能发起加油!',
        'order_id.checkCount' => '该订单油已加满!',
    ];

    //验证订单
    protected function checkOrder($value)
    {
        $result = \db('Order')->where('id',$value)->find();
        if (empty($result)){
            return false;
        }
        if ($result['status'] != "租赁中"){
            return false;
        }
        return true;
    }
    //验证订单
    protected function checkCount($value)
    {
        $result = db('order_refuel')->where('order_id', $value)->count();
        if ($result > 14) {
            return false;
        }
        return true;
    }
}