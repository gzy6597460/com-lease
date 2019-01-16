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


namespace app\api\model;

use \think\Model;
class Member extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'member';
    // 自动写入时间戳
    protected $autoWriteTimestamp = 'datetime';
    
}
