<?php
// +----------------------------------------------------------------------
// | Tplay [ WE ONLY DO WHAT IS NECESSARY ]
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://tplay.pengyichen.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 听雨 < 389625819@qq.com >
// +----------------------------------------------------------------------


namespace app\admin\model;
use \think\Model;
class Order extends Model
{
    // 设置当前模型对应的数据表名称
    protected $name = 'order';
    // 设置返回数据集的对象名
    protected $resultSetType = 'order';
    // 自动写入时间戳
    protected $autoWriteTimestamp = 'datetime';

//    public function items() { //建立一对多关联
//        return $this->hasMany('Goods', 'goods_id', 'id'); //关联的模型，外键，当前模型的主键
//    }
//
//    public static function getGoodsByID($id)
//    {
//        $order = self::with('items')->find($id); // 通过 with 使用关联模型，参数为关联关系的方法名
//        return $order;
//    }

    public function goods()
    {
        //关联商品表
        return $this->belongsTo('Goods');
    }

    public function express()
    {
        //关联商品表
        return $this->belongsTo('Express');
    }

}
