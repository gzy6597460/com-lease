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
use \app\api\model\Good as GoodModel;

class Index
{
    /** 搜索*/
    public function search()
    {
        $post = Request::instance()->post();
        if ((isset($post['keyword']) == false) || (empty($post['keyword']))) {
            jsonOk(null, null, '未填写关键字', false);
            exit();
        }
        $keyword = $post['keyword'];
        $list = db('goods')->field('id,goods_name,goods_path,parameter,reference_price,minimum_price,is_hot')->where('is_onshelf', 1)->where('goods_name|parameter', 'like', "%{$keyword}%")->select();
        if ($list) {
            jsonOk($list, null, '获取成功', true);
        }
        return jsonOk(null, null, '暂无商品', false);
    }

    /** 首页轮播图*/
    public function index_banner()
    {
        //缓存
        $list = Cache::get('index_banner');
        if (empty($list)) {
            $list = db('banner')->field('id,banner_img,url,sort')->where('is_show', 1)->order('sort', 'desc')->select();
            foreach ($list as $key => $value) {
                if (empty($value['url'])) {
                    $list[$key]['url'] = "javascript:;";
                }
            }
            if ($list) {
                Cache::set('index_banner', $list, 7200);
            }
        }
        if ($list) {
            jsonOk($list, null, '获取成功', true);
        }
        return jsonOk(null, null, '暂无轮播图', false);
    }

    /** 快速入口*/
    public function fast_track()
    {
        $category_id = input("get.category_id");
        if (empty($category_id)) {
            return jsonOk(null, null, '未选择分类', false);
        }
        $list = db('goods')->where('is_onshelf', 1)->where('category_id', $category_id)->select();
        if ($list) {
            jsonOk($list, null, '获取成功', true);
        }
        return jsonOk(null, null, '暂无商品', false);
    }

    /** 热门推荐*/
    public function hot_goods()
    {
        $list = Cache::get('hot_goods');
        $join = [
            ['goods_series s', 's.id = g.series_id']
        ];
        if (empty($list)) {
            $list = db('goods')->alias('g')->join($join)->field('g.id,g.goods_path,s.series_name')->where('is_onshelf', 1)->where('is_hot', 1)->select();
            if ($list) {
                Cache::set('hot_goods', $list, 7200);
            }
        }
        if ($list) {
            jsonOk($list, null, '获取成功', true);
        }
        return jsonOk(null, null, '暂无热门推荐', false);
    }

    /** 猜你喜欢*/
    public function whatYouLike()
    {
        //缓存
        $list = Cache::get('what_like');
        if (empty($list)) {
            $join = [
                ['goods_series s', 's.id = g.series_id']
            ];
            $list = db('goods')->alias('g')->join($join)->field('g.id,g.parameter,g.goods_path,s.series_name')
                ->where('is_onshelf', 1)->where('g.id','not in',[22])->select();
            if ($list) {
                Cache::set('what_like', $list, 7200);
            }
        }
        shuffle($list);
        if ($list) {
            jsonOk($list, null, '获取成功', true);
        }
        return jsonOk(null, null, '暂无数据', false);
    }
}