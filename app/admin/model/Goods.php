<?php

namespace app\admin\model;
use \think\Model;
class Goods extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'goods';

    public function goods() {
        return $this->belongsTo('Order', 'id', 'id'); //关联模型名，外键名，关联模型的主键
    }

    //关联套餐表
    public function meal()
    {
        return $this->hasMany('GoodsMeal','goods_id','id');
    }

    //关联图片表
    public function picture()
    {
        return $this->hasMany('GoodsPicture','goods_id','id');
    }
}