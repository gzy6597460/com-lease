<?php

namespace app\api\validate;

use think\Validate;
use think\Db;

class Order extends Validate
{
    protected $rule = [
        'token' => 'require',
        'order_id' => 'require|checkOrder:1',
        'goods_id' => 'require|checkGood:1',
        'meal_id' => 'require|checkMeal:1',
        'addr_id' => 'require|isAddr:1',
        'deduction_score' => 'require|number',
        'buy_num' => 'require',
        'is_pledge' => 'require',

        //售后
        'change_id' => 'require',
        'express_id' => 'require',
        'remark' => 'require',

    ];

    protected $message = [
        'token.require' => '您还未登录,请重新登录',

        'order_id.require' => '请选择订单',
        'order_id.checkOrder' => '订单非发货状态',
        'goods_id.require' => '未选择商品',
        'goods_id.checkGood' => '该商品已下架',
        'meal_id.require' => '未选择套餐',
        'meal_id.checkMeal' => '未找到该套餐',
        'addr_id.require' => '未选择收货地址',
        'addr_id.isAddr' => '收货地址信息异常',
        'deduction_score.require' => '未选择抵扣积分',
        'deduction_score.number' => '抵扣积分请填写数字',
        'buy_num.require.require' => '未选择购买数量',
        'is_pledge.require' => '未选择是否免押',

        //取消订单
        'order_id.checkCancelOrder' => '订单状态异常或已取消',
        //删除订单
        'order_id.checkDelOrder' => '请先关闭订单',
        //售后
        'change_id.require' => '请选择售后类型',
        'order_id.checkChangeOrder' => '订单状态异常或已发起申请',
        'express_id.require' => '请填写快递单号',
        'remark.require' => '请填写备注信息',

    ];

    protected $scene = [
        'add'       => ['goods_id', 'meal_id', 'addr_id', 'deduction_score', 'buy_num', 'is_pledge'],
        'confirm'   => ['token', 'order_id' => 'require|checkOrder:1'],
        'cancel'    => ['token', 'order_id' => 'require|checkCancelOrder:1'],
        'change'    => ['token', 'order_id' => 'require|checkChangeOrder:1'],
        'del'       => ['token', 'order_id' => 'require|checkDelOrder:1']
    ];

    //验证删除订单状态
    protected function checkDelOrder($value)
    {
        $result = \db('Order')->where('id', $value)->find();
        if ($result['status'] != '关闭订单') {
            return false;
        }
        return true;
    }

    //验证售后订单状态
    protected function checkChangeOrder($value)
    {
        $result = \db('Order')->where('id', $value)->find();
        if ($result['status'] != '租赁中' and $result['status'] != '已发货') {
            return false;
        }
        return true;
    }

    //验证取消订单状态
    protected function checkCancelOrder($value)
    {
        $result = \db('Order')->where('id', $value)->find();
        if ($result['status'] !== '未付款') {
            return false;
        }
        return true;
    }

    //验证订单状态
    protected function checkOrder($value)
    {
        $result = \db('Order')->where('id', $value)->find();
        if ($result['status'] !== '已发货') {
            return false;
        }
        return true;
    }


    //验证商品状态
    protected function checkGood($value)
    {
        $result = \db('goods')->where('id', $value)->value('is_onshelf');
        if (empty($result)) {
            return false;
        }
        return true;
    }

    //验证套餐状态
    protected function checkMeal($value)
    {
        $result = \db('goods_meal')->where('id', $value)->find();
        if (empty($result)) {
            return false;
        }
        return true;
    }

    //验证套餐状态
    protected function isAddr($value)
    {
        $result = db('member_addr')->where('id', $value)->find();
        if (empty($result)) {
            return false;
        }
        return true;
    }

}