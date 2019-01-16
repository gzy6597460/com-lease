<?php
namespace app\api\controller;

header('Access-Control-Allow-Origin:*');

use app\api\controller\Wechat;
use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use think\Log;
use think\config;

class Promotion
{

    /** 0元租机活动*/
    public function zeroPromotion()
    {
        $where['id']  = ['in','23'];//活动商品id(3个)
        $where['is_onshelf']  = 1;//是否上架
        $list = db('goods')
            ->field('id,goods_name,goods_path,minimum_price,keywords')
            ->where($where)
            ->order('sort','desc')
            ->select();
        foreach ($list as $key =>$value){
            $list[$key]['goods_name'] = '联想 M710s 商用台式电脑主机';
            $list[$key]['keywords'] = explode("/", $value['keywords']);

        }
        if ($list) {
            jsonOk($list, null, '获取成功', true);
        }
        return jsonOk(null, null, '暂无活动商品', false);
    }

}