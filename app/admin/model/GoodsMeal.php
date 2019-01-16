<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/28
 * Time: 15:03
 */

namespace app\admin\model;
use \think\Model;

class GoodsMeal extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'goods_meal';

    public function goods()
    {
        return $this->belongsTo('Goods');
    }
}