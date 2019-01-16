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


namespace app\admin\controller;

use \think\Db;
use \think\Time;
use \think\Cookie;
use app\admin\controller\Permissions;
use app\admin\model\Goods as goodsModel;
class Goods extends Permissions
{
    public function index()
    {
        $model = new goodsModel();
        $post = $this->request->param();
        $where = [];
        if (isset($post['goods_num']) and !empty($post['goods_num'])) {
            $where['g.goods_num'] = $post['goods_num'];
        }
        if (isset($post['goods_name']) and !empty($post['goods_name'])) {
            $where['g.goods_name'] = ['like', '%' . $post['goods_name'] . '%'];
        }

        if (isset($post['parameter'])and !empty($post['parameter'])) {
            $where['g.parameter'] =['like', '%' . $post['parameter'] . '%'];
        }
        if (isset($post['brand']) and !empty($post['brand'])) {
            $where['g.goods_brand'] = ['like', '%' . $post['brand'] . '%'];
        }
        if (isset($post['remark']) and !empty($post['remark'])) {
            $where['g.remark'] = ['like', '%' . $post['remark'] . '%'];
        }

        $join = [
            ['goods_series s', 's.id = g.series_id']
        ];
        $goods_list = empty($where) ? $model->alias('g')->join($join)
            ->field('g.id,g.goods_num,g.goods_name,g.goods_brand,g.goods_path,g.parameter,g.reference_price,g.remark,s.series_name')
            ->paginate(20) :
            $model->alias('g')->field('g.id,g.goods_num,g.goods_name,g.goods_brand,g.goods_path,g.parameter,g.reference_price,g.remark,s.series_name')
                ->join($join)
                ->where($where)
                ->paginate(20,false,['query'=>$this->request->param()]);

        //$articles = $article->toArray();
        //        //添加最后修改人的name
        //        foreach ($articles as $key => $value) {
        //            $articles[$key]['edit_admin'] = Db::name('admin')->where('id',$value['edit_admin_id'])->value('nickname');
        //        }
        $this->assign('goods',$goods_list);
        //        $info['admin'] = Db::name('admin')->select();
        //        $this->assign('info',$info);a
        return $this->fetch();
    }

    public function goodsinfo($id=''){
        $model = new goodsModel();
        //post修改
        if ($this->request->isPost()) {
            $post = $this->request->post();
            //var_dump($post);exit;
            switch ($post['is_onshelf']){
                case 'on':
                    $data['is_onshelf'] = 1;
                    break;
                case 'off':
                    $data['is_onshelf'] = 0;
                    break;
            }
            $data['goods_brand'] = $post['goods_brand'];
            $data['goods_name'] = $post['goods_name'];
            $data['goods_num'] = $post['goods_num'];
            $data['goods_path'] = $post['goods_path'];
            $data['minimum_price'] = $post['minimum_price'];
            $data['parameter'] = $post['parameter'];
            $data['reference_price'] = $post['reference_price'];
            $data['remark'] = $post['remark'];
            $data['series_id'] = $post['series_id'];
            $data['good_pic'] = $post['good_pic'];
            //var_dump($data);exit;
            if(isset($post['id']) and !empty($post['id'])) {
                if (!$model->save($data,['id' => $post['id']])) {
                    return json(['success'=>false ,'message'=>'保存失败']);
                }
                return json(['success'=>true ,'message'=>'保存成功']);
            }
            if (!$model->save($data)) {
                return json(['success'=>false ,'message'=>'保存失败']);
            }
            return json(['success'=>true ,'message'=>'保存成功']);
        }
        //获取菜单id
        //$id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
        //$info = empty($id)? null : $model->where('id',$id)->select();
        $join = [
            ['goods_series s', 's.id = g.series_id']
        ];
        $info = empty($id)? null : $model->alias('g')->join($join)->where('g.id',$id)->find();
        $series_list = db('goods_series')->select();
        $this->assign('data_info',$info);
        $this->assign('series_list',$series_list);
        return $this->fetch();
    }

    public function mealinfo(){
        $model = new goodsModel();
        $goods_id = input('get.goods_id');
        //post修改
        if ($this->request->isPost()) {
            $post = $this->request->post();
//            var_dump($post);
            foreach ($post as $key=>$value){
                $is_meal = db('goods_meal')->where('id',$post[$key]['id'])->find();
                $data['meal_name'] =  $post[$key]['meal_name'];
                $data['meal_days'] =  $post[$key]['meal_days'];
                $data['meal_param'] =  $post[$key]['meal_param'];
                $data['meal_months'] =  $post[$key]['meal_months'];
                $data['meal_discount'] =  $post[$key]['meal_discount'];
                $data['insurance_cost'] =  $post[$key]['insurance_cost'];
                $data['service_cost'] =  $post[$key]['service_cost'];
                $data['meal_price'] =  $post[$key]['meal_price'];
                $data['total_price'] =  $post[$key]['total_price'];
                if ($is_meal){
                    $result = db('goods_meal')->where('id',$post[$key]['id'])->update($data);
                }
            }
            return json(['success'=>true ,'message'=>'保存成功']);
        }

        $info = empty($goods_id)? null : $model->meal()->where('goods_id',$goods_id)->select();
        $this->assign('data_info',$info);
        return $this->fetch();
    }


    public function delete()
    {
        if($this->request->isAjax()) {
            $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;
            if(false == db('goods')->where('id',$id)->delete()) {
                return $this->error('删除失败');
            } else {
                return $this->success('删除成功','admin/goods/index');
            }
        }
    }


    public function add_meal(){
        $model = new goodsModel();
        //post修改
        if ($this->request->isPost()) {
            $post = $this->request->post();
//            var_dump($post);
            $data['meal_name'] =  $post['meal_name'];
            $data['meal_days'] =  $post['meal_days'];
            $data['meal_param'] =  $post['meal_param'];
            $data['meal_months'] =  $post['meal_months'];
            $data['meal_discount'] =  $post['meal_discount'];
            $data['insurance_cost'] =  $post['insurance_cost'];
            $data['service_cost'] =  $post['service_cost'];
            $data['meal_price'] =  $post['meal_price'];
            $data['total_price'] =  $post['total_price'];
            $data['goods_id'] =  $post['goods_id'];
            $data['good_pic'] =  $post['good_pic'];
            $result = db('goods_meal')->insert($data);
            if (!$result){
                return json(['success'=>false ,'message'=>'新增失败']);
            }
            return json(['success'=>true ,'message'=>'新增成功']);
        }
        $goods_list = $model->field('id,goods_name')->select();

        $this->assign('goods_list',$goods_list);
        return $this->fetch();
    }

    public function del_meal(){
        $model = new goodsModel();
        if($this->request->isAjax()) {
            $meal_id = $this->request->has('meal_id') ? $this->request->param('meal_id', 0, 'intval') : 0;
//            var_dump($meal_id);exit;
            if(false == db('goods_meal')->where('id',$meal_id)->delete()) {
                return json(['success'=>false ,'message'=>'删除失败']);
//                return $this->error('删除失败');
            } else {
                return json(['success'=>true ,'message'=>'删除成功']);
//                return $this->success('删除成功','admin/goods/index');
            }
        }
        $goods_list = $model->field('id,goods_name')->select();
        $this->assign('goods_list',$goods_list);
        return $this->fetch();
    }
}
