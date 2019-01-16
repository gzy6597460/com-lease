<?php
namespace app\api\validate;

use think\Validate;
use think\Db;

class Refuel extends Validate
{
    protected $rule = [
        'order_id' => 'require|checkOrder:1',
    ];

    protected $message = [
        'order_id.require' => '未获取到订单信息',
        'order_id.checkOrder' => '订单信息异常,不能发起加油!',
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


}