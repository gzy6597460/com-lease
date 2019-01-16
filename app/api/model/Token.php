<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/16
 * Time: 15:09
 */
namespace app\api\model;

use \think\Model;
class Token extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'member_token';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

}