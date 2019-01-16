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
use app\admin\model\Order as orderModel;
class Order extends Permissions
{
    public function index($out_excel = 0)
    {
        $model = new orderModel();
        $post = $this->request->param();
        $where = [];
        $goodswhere = [];
        if (isset($post['inside_order_no']) and !empty($post['inside_order_no'])) {
            $where['inside_order_no'] = ['like', '%' . $post['inside_order_no'] . '%'];
        }
        if (isset($post['buy_name']) and !empty($post['buy_name'])) {
            $where['buy_name'] = ['like', '%' . $post['buy_name'] . '%'];
        }
        if (isset($post['goods_name']) and !empty($post['goods_name'])) {
            $goodswhere['goods_name'] = ['like', '%' . $post['goods_name'] . '%'];
        }
        if (isset($post['order_type']) and !empty($post['order_type'])) {
            $where['order_type'] = $post['order_type'];
        }
        if (isset($post['status'])and !empty($post['status'])) {
            $where['status'] = $post['status'];
        }
        if (isset($post['lease_months']) and !empty($post['lease_months'])) {
            $where['lease_months'] = $post['lease_months'];
        }
        if(isset($post['create_time']) and !empty($post['create_time'])) {
            $where['order.create_time'] = ['like',$post['create_time'].'%'];
        }

        $order_list = (empty($where)&&empty($goodswhere)) ? $model->order('create_time desc')->paginate(20) : $model->hasWhere('goods',$goodswhere)->where($where)->order('create_time desc')->paginate(20,false,['query'=>$this->request->param()]);
        //return json($order_list);exit;
        //$articles = $article->toArray();
        //        //添加最后修改人的name
        //        foreach ($articles as $key => $value) {
        //            $articles[$key]['edit_admin'] = Db::name('admin')->where('id',$value['edit_admin_id'])->value('nickname');
        //        }
//        $join = [
//            ['goods g', 'o.goods_id = g.id']
//        ];
//        $excel_list = (empty($where)&&empty($goodswhere)) ? \db('order')->alias('o')
//            ->join($join)
//            ->order('o.create_time desc')
//            ->select()
//            : \db('order')->alias('o')->join($join)->where($where)->select();

        //        if($out_excel==1){
        //            $this->out2excel($excel_list);
        //            exit;
        //        }
        //        var_dump($excel_list);
//        $this->assign('excel_list',json_encode($excel_list));
        $this->assign('order',$order_list);
        $info['status'] = Db::name('order')->field('status')->group('status')->select();
        $info['type'] = Db::name('order')->field('order_type')->group('order_type')->select();
        $info['lease_months'] = Db::name('order')->field('lease_months')->group('lease_months')->select();
        //        $info['admin'] = Db::name('admin')->select();
        $this->assign('info',$info);
        return $this->fetch();
    }

    public function orderinfo($id=''){
        $model = new orderModel();
//        $post = $this->request->post();
//        var_dump($post);
        if ($this->request->isPost()) {
            $old_info = $model->where('id',$id)->find();

            $post = $this->request->post();
            $data['update_time'] = date('Y-m-d H:i:s',time());
            $data['status'] = $post['status'];
            $data['buy_name'] = $post['buy_name'];
            $data['phone'] = $post['phone'];
            $data['address'] = $post['address'];
            $data['buy_num'] = $post['buy_num'];
            $data['total_fee'] = $post['total_fee']*100;
            $data['deduction_score'] = $post['deduction_score'];
            $data['lease_months'] = $post['lease_months'];
            if ($post['start_time'] != null){
                $data['start_time'] = $post['start_time'];
                if ($post['end_time']== null){
                    $data['end_time'] = date("Y-m-d", strtotime("+".$post['lease_months']." months", strtotime($post['start_time'])));
                }else{
                    $data['end_time'] = $post['end_time'];
                }
            }
//            快递修改
            $express_data['express_num'] = $post['express_num'];
            $express_data['express_channel'] = $post['express_channel'];
            if (!empty($post['express.confirm_date'])){
                $express_data['confirm_date'] = $post['confirm_date'];
            }
            if (!empty($post['express.express_date'])){
                $express_data['express_date'] = $post['express_date'];
            }
            if (empty($old_info['express_id'])&&($post['express_num'])){
               $data['express_id']=db('order_express')->insertGetId([
                   'order_id'=>$id,
                   'express_num'=>$post['express_num'],
                   'express_channel'=>$post['express_channel'],
                   'express_date'=>date('Y-m-d H:i:s',time())
               ]);
            }else if($post['express_num']){
                db('order_express')->where('id',$old_info['express_id'])->update($express_data);
            }
            $data['remark'] = $post['remark'];

            if (!$model->where('id',$post['id'])->update($data)) {
                return json(['success'=>false ,'message'=>'修改失败']);
            }
            if (($old_info['status']!=$post['status'])&&($post['status']=='关闭订单')&&($old_info['status']=='未付款')){
                if ($old_info['deduction_score']>0){
                    add_score($old_info['member_id'],$old_info['deduction_score'],'订单号：'.$old_info['inside_order_no'].'未支付退回积分');
                    return json(['success'=>true ,'message'=>'修改成功,退还积分'.$old_info['deduction_score']]);
                }
            }
            return json(['success'=>true ,'message'=>'修改成功']);
        }
        //获取菜单id
//        $id = $this->request->has('id') ? $this->request->param('id', 0, 'intval') : 0;

        $info = empty($id)? null : $model->where('id',$id)->find();
        $this->assign('data_info',$info);
        return $this->fetch();
    }

    public function  out2excel(){
        //        $post = $this->request->post();
        // $innerdata = Db::table('inner')
        //     ->whereTime('add_date', 'between', [$start_date,$end_date])
        //     ->where('inner.depart_id',session('depart_id'))
        //     ->join('goods','inner.goods_id = goods.id')
        //     ->join('storage','storage.id = inner.storage_id')
        //     ->join('supplier','supplier.id = inner.supplier_id')
        //     ->join('user','user.id = inner.user_id')
        //     //->limit(5)
        //     ->order('inner.id desc')
        //     ->field('goods_name,add_date,storage_name,supplier_name,real_name,num,inner.price')
        //     ->select();

        //        $excel_list = (empty($where)&&empty($goodswhere)) ? \db('order')->alias('o')
        //            ->join($join)
        //            ->order('o.create_time desc')
        //            ->select()
        //            : \db('order')->alias('o')->join($join)->where($where)->select();

        $join = [
            ['goods g', 'o.goods_id = g.id']
        ];
        $innerdata = \db('order')->alias('o')
            ->join($join)
            ->field('o.id,o.inside_order_no,o.out_trade_no,o.old_order_no,
            o.status,o.member_id,o.buy_name,o.phone,g.goods_name,o.lease_months,o.total_fee,o.real_pay,o.deduction_score,o.create_time,o.order_type,o.address,o.buy_num')
            ->order('o.create_time desc')
            ->where('o.status','not in',['关闭订单','未付款'])
            ->select();
        $table = '';
        $table .= "<table>
            <thead>
                <tr>
                    <th class='name'>订单id</th>
                    <th class='name'>内部订单号</th>
                    <th class='name'>微信商户订单号</th>
                    <th class='name'>续租订单号</th>   
                    <th class='name'>订单状态</th>              
                    <th class='name'>用户ID</th>
                    <th class='name'>收货人</th>
                    <th class='name'>收货电话</th>
                    <th class='name'>收货地址</th>
                    <th class='name'>商品名称</th>
                    <th class='name'>租赁数量</th>
                    <th class='name'>租赁月数</th>                   
                    <th class='name'>订单金额</th>                    
                    <th class='name'>实际支付</th>
                    <th class='name'>积分抵扣</th>
                    <th class='name'>创建时间</th>
                    <th class='name'>订单类型</th>
                </tr>
            </thead>
            <tbody>";
        foreach ($innerdata as $v) {
            $table .= "<tr>
                    <td class='name'>{$v['id']}</td>
                    <td class='name'>{$v['inside_order_no']}</td>
                    <td class='name'>{$v['out_trade_no']}</td>    
                    <td class='name'>{$v['old_order_no']}</td>
                    <td class='name'>{$v['status']}</td>
                    <td class='name'>{$v['member_id']}</td>
                    <td class='name'>{$v['buy_name']}</td>
                    <td class='name'>{$v['phone']}</td>
                    <td class='name'>{$v['address']}</td>
                    <td class='name'>{$v['goods_name']}</td>                   
                    <td class='name'>{$v['buy_num']}</td>
                    <td class='name'>{$v['lease_months']}</td>                    
                    <td class='name'>{$v['total_fee']}</td>
                    <td class='name'>{$v['real_pay']}</td>
                    <td class='name'>{$v['deduction_score']}</td>
                    <td class='name'>{$v['create_time']}</td>
                    <td class='name'>{$v['order_type']}</td>
                </tr>";
        }
        $table .= "</tbody>
        </table>";
        //通过header头控制输出excel表格
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename="导出结果.xls"');
        header("Content-Transfer-Encoding:binary");
        echo $table;
    }
//    public function order_list()
//    {
//        //获取分页page和limit参数
//        $page=input("get.page")?input("get.page"):1;
//        $page=intval($page);
//        $limit=input("get.limit")?input("get.limit"):1;
//        $limit=intval($limit);
//        $start=$limit*($page-1);
//
//        //搜索数据 id=" + id + "&memberID=" + memberID + "&name=" + name + "&nick_name=" + nick_name + "&mobile=" + mobile + "&create_time=" + create_time,
//        $where = [];
//        $id=input("get.id");
//        if($id){
//            $where['id'] = $id;
//        }
//        $phone=input("get.phone");
//        if($phone){
//            $where['phone'] = $phone;
//        }
//        $name=input("get.name");
//        if($name){
//            $where['name'] = $name;
//        }
//
//        $order = new orderModel();
//        $data_list = $order->where($where)->order('id desc')->limit($start,$limit)->select();
//        foreach ($data_list as $value) {
//            $value -> goods_name = $value -> goods -> goods_name;
//            $value -> total_fee = ($value -> total_fee/100).'元';
//        }
//        $count = $order->where($where)->count();
//        $list["msg"]="";
//        $list["code"]=0;
//        $list["count"]=$count;
//        $list["data"]=$data_list;
//        if(empty($data_list)){
//            $list["msg"]="暂无数据";
//        }
//        return json($list);
//    }


}
