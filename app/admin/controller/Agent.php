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
use \think\Cookie;
use \think\Session;
use app\admin\model\Agent as agentModel;//管理员模型
use app\admin\model\AdminMenu;
use app\admin\controller\Permissions;
class Agent extends Permissions
{
    public function index()
    {
        return $this->fetch();
    }
    // 获取用户列表
    public function agent_list()
    {
        //获取分页page和limit参数
        $page=input("get.page")?input("get.page"):1;
        $page=intval($page);
        $limit=input("get.limit")?input("get.limit"):1;
        $limit=intval($limit);
        $start=$limit*($page-1);

        //搜索数据 id=" + id + "&memberID=" + memberID + "&name=" + name + "&nick_name=" + nick_name + "&mobile=" + mobile + "&create_time=" + create_time,
        $where = [];
        $id=input("get.id");
        if($id){
            $where['id'] = $id;
        }
        $memberID=input("get.memberID");
        if($memberID){
            $where['memberID'] = $memberID;
        }
        $name=input("get.name");
        if($name){
            $where['name'] = $name;
        }
        $nick_name=input("get.nick_name");
        if($nick_name){
            $where['nick_name'] = $nick_name;
        }
        $mobile=input("get.mobile");
        if($mobile){
            $where['mobile'] = $mobile;
        }
        $create_time=input("get.create_time");
        if($create_time){
            if ($create_time=="undefined") {

            }else{
                $where['create_time'] = ['like',$create_time.'%'];
            }
        }
        $member = new agentModel();
        $data_list = $member->where($where)->limit($start,$limit)->select();
        $count = count($data_list);
        $list["msg"]="";
        $list["code"]=0;
        $list["count"]=$count;
        $list["data"]=$data_list;
        if(empty($data_list)){
            $list["msg"]="暂无数据";
        }
        return json($list);
    }
}