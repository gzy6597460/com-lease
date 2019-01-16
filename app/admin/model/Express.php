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
class Express extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'order_express';

    public function express() {
        return $this->belongsTo('Order', 'id', 'id'); //关联模型名，外键名，关联模型的主键
    }


}
