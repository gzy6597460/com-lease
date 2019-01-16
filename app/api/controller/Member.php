<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use think\Db;
use think\Log;
use app\api\controller\Token;
header('Access-Control-Allow-Origin:*');

class Member extends Controller
{
    /**
     * 个人资料-实名
     */
    public function personal_manage()
    {
        $token = input('get.token');
        $member_info = get_memberinfo($token);
        $member_id = $member_info['id'];
        $data = db('member')->field('id,nick_name,phone,id_card')->where('id', $member_id)->find();
        if (empty($data['id_card'])||($data['id_card']==null)) {
            $data['certify'] = 0;
        } else {
            $data['certify'] = 1;
            $data['phone'] = hidephone($data['phone']);
            $data['id_card'] = hideIDcard($data['id_card']);
        }
        jsonOk($data);
    }

    //信用认证
    public function personal_certify()
    {
        $token = input('get.token');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $data = db('member')->field('verify_idcard,verify_info,verify_operator,verify_sesame,verify_bank')->where('id', $member_id)->find();
        jsonOk($data);
    }

    /**
     * 个人中心资料
     * @params member_id
     */
    public function personal_center()
    {
        $token = input('get.token');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $join = [
            ['member_score s', 'm.id = s.member_id']
        ];
        $data = db('member')->alias('m')->field('m.id,m.phone,m.name,m.headimgurl,m.credit_score,s.get_score as score')->join($join)->where('m.id', $member_id)->find();
        return json($data);
    }

    /**
     * 获取全部订单
     * @params member_id
     */
    public function getorder_all()
    {
        $token = input('get.token');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $join = [
            ['goods g', 'a.goods_id=g.id']
        ];
        $data = db('order')->alias('a')
            ->field('g.goods_name,a.id as order_id,inside_order_no,out_trade_no,g.id as good_id,goods_path,parameter,total_fee,status,start_time,end_time,a.buy_num,a.extra_day')
            ->where('member_id', $member_id)
            ->where('is_del', 0)
            ->join($join)
            ->order('status','asc')
            ->select();
        return json($data);
    }

    /** 用户租赁中的订单*/
    public function getorder_using(){
        //验证用户token
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);
        $join = [
            ['goods g', 'a.goods_id=g.id']
        ];
        $order_list = db('order')->alias('a')->field('g.goods_name,a.id as order_id,inside_order_no,out_trade_no,g.id as good_id,goods_path,parameter,total_fee,status,start_time,end_time,a.buy_num')
            ->join($join)
            ->where('member_id', $member_info['id'])
            ->where('a.status',"租赁中")
            ->select();

        if (empty($order_list)){
            jsonOk(null, null, '暂无使用中的租赁订单', false);
        }
        jsonOk($order_list, null, '获取成功', true);
    }

    /**
     * 获取待付款订单
     * * @params member_id
     */
    public function getorder_nopay()
    {
        $token = input('get.token');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $join = [
            ['goods g', 'a.goods_id=g.id']
        ];
        $data = db('order')->alias('a')->field('g.goods_name,a.id as order_id,member_id,inside_order_no,out_trade_no,g.id as good_id,g.minimum_price,buy_name,deduction_score,lease_months,buy_num,goods_path,parameter,total_fee,real_pay,status,start_time,end_time,a.create_time,address')
            ->where('member_id', $member_id)->where('status', '未付款')->order('a.create_time desc')->join($join)->select();
        foreach ( $data as $key => $value ){
            $data[$key]['remain_time']=strtotime("+1 day",strtotime($data[$key]['create_time']))-time();
            //超时关闭订单
            if($data[$key]['remain_time'] < 0){
                if ($data[$key]['deduction_score']>0){
                    add_score($data[$key]['member_id'],$data[$key]['deduction_score'],'未支付退回积分');
                    db('score_history')->insert(['channel'=>'订单号-'.$data[$key]['inside_order_no'].'未支付退还积分','member_id'=>$data[$key]['member_id'],'score'=>'-'.$data[$key]['deduction_score'],'create_time'=>date('Y-m-d H:i:s')]);
                }
                \db('order')->where('id',$data[$key]['order_id'])->update(['status' => '关闭订单']);
                unset($data[$key]);
            }
        }
        return json($data);
    }

    /**
     * 获取待收货订单
     * * @params member_id
     */
    public function getorder_collect()
    {
        $token = input('get.token');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];

        $join = [
            ['goods g', 'a.goods_id=g.id'],
        ];
        $data = db('order')->alias('a')->field('g.goods_name,a.id as order_id,a.inside_order_no,a.out_trade_no,g.minimum_price,buy_name,lease_months,buy_num,g.id as good_id,goods_path,parameter,total_fee,status,start_time,end_time,express_id')
            ->where('member_id', $member_id)->where('a.status', '已发货')->join($join)->select();
        return json($data);
    }

    /**
     * 获取更换设备订单
     * * @params member_id
     */
    public function getorder_changedevice()
    {
        $token = input('get.token');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $join = [
            ['goods g', 'a.goods_id=g.id']
        ];
        $data = db('order')->alias('a')->field('g.goods_name,a.id as order_id,inside_order_no,out_trade_no,g.id as good_id,g.minimum_price,buy_name,lease_months,buy_num,goods_path,parameter,total_fee,status,start_time,end_time,real_pay')
            ->where('member_id', $member_id)->where('status','in',['更换设备','申请更换中'])->join($join)->select();
        return json($data);
    }

    /**
     * 获取签到数据
     */
    public function get_signdata()
    {
        $signCon = new Sign();
        $signCon->get_signdata();
    }


    /**
     * 签到加积分
     */
    public function sign()
    {
        $signCon = new Sign();
        $signCon->sign();
    }

    //获取默认收货地址
    public function get_default_address()
    {
        $token = input('get.token');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $result = db('member_account')
            ->alias('m')
            ->join('member_addr a', 'm.addr_id = a.id')
            ->field('a.id as addr_id,receiver_name,receiver_phone,street as address')
            ->where('m.member_id', $member_id)
            ->find();
        if (empty($result)) {
            jsonOk(null, null, '暂无默认收货地址', false);
        }
        jsonOk($result, null, '查询成功');
    }

    //获取收货地址
    public function get_address()
    {
        $token = input('get.token');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $default_id = db('member_account')->where('member_id',$member_id)->value('addr_id');
        $result = db('member_addr')->field('id as addr_id,receiver_name,receiver_phone,street as address')->where('member_id', $member_id)->order('create_time desc')->select();
        foreach ($result as $key => $value) {
            $result[$key]['default'] = 0;
            if ($result[$key]['addr_id']==$default_id){
                $result[$key]['default'] = 1;
            }
        }
        if (empty($result)) {
            jsonOk(null, null, '暂无收货地址', false);
        }
        jsonOk($result, null, '查询成功');
    }

    //添加收货地址
    public function add_address()
    {
        $post = Request::instance()->post();
//        $token = input('get.token');
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        if (empty($post['address'] && $post['receiver_name'] && $post['receiver_phone'])) {
            jsonOk(null, null, '请填写完整收货信息。', false);
        }
        $data = [
            'create_time' => date('Y-m-d H:i:s'),
            'member_id' => $member_id,
            'receiver_name' => $post['receiver_name'],
            'receiver_phone' => $post['receiver_phone'],
            'street' => $post['address'],
        ];
        $result = db('member_addr')->insertGetId($data);
        $count= db('member_addr')->where('member_id',$member_id)->count();
        //修改默认地址
        if ($post['is_default']||($count==1)){
            $this->change_default_addr($member_id,$result);
        }
        if (empty($result)) {
            jsonOk(null, null, '添加失败', false);
        } else {
            jsonOk(null, null, '添加成功');
        }
    }


    //更新收货地址
    public function up_address()
    {
        $post = Request::instance()->post();
        $token = $post['token'];
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        if (empty($post['address'] && $post['receiver_name'] && $post['receiver_phone'])) {
            jsonOk(null, null, '请填写完整收货信息。');
        }
        $data = [
            'create_time' => date('Y-m-d H:i:s'),
            'member_id' => $member_id,
            'receiver_name' => $post['receiver_name'],
            'receiver_phone' => $post['receiver_phone'],
            'street' => $post['address']
        ];
        $result = db('member_addr')->where('id', $post['addr_id'])->update($data);
        if ($post['is_default']){
            $this->change_default_addr($member_id,$post['addr_id']);
        }
        if (empty($result)) {
            jsonOk(null, null, '更新失败', false);
        } else {
            jsonOk(null, null, '更新成功');
        }
    }
    //    删除收货地址
    public function del_address()
    {
        $token = input('get.token');
        $addr_id =input('get.addr_id');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $result = db('member_addr')->where('member_id',$member_id)->where('id', $addr_id)->delete();
        $is_default = db('member_account')->where('member_id', $member_id)->value('addr_id');
        //是否为默认地址
        if ($addr_id==$is_default){
            $new_addr =db('member_addr')->where('member_id', $member_id)->find();
            if ($new_addr){
                $this->change_default_addr($member_id,$new_addr['id']);
            }
        }
        if (empty($result)) {
            jsonOk(null, null, '删除失败', false);
        } else {
            jsonOk(null, null, '删除成功');
        }
    }

//    更新默认地址
    public function up_default_addr()
    {
        $token = input('get.token');
        $addr_id = input('get.addr_id');
        $member_info = $this->get_memberinfo($token);
        $member_id = $member_info['id'];
        $result = db('member_account')->where('member_id',$member_id)->update(['addr_id'=>$addr_id]);
        return $result;
    }

    //更换默认地址方法
    private function change_default_addr($member_id,$addr_id)
    {
        $result = db('member_account')->where('member_id',$member_id)->update(['addr_id'=>$addr_id]);
        return $result;
    }


    //支付密码验证接口
    public function verify_paypassword(){

    }

    //设置支付密码接口
    public function set_paypassword(){
        $pass=password('123456');
        var_dump($pass);
    }

    //获取用户信息
    private function get_memberinfo($token){
        $member_id = db('member_token')->where('token',$token)->value('member_id');
        if (empty($member_id)){
            jsonOk(null,null,'登录状态失效,请重新登录。',false);
        }
        $member_info = db('member')->where('id',$member_id)->find();
        if (empty($member_info)){
            jsonOk(null,null,'未获取到用户信息',false);
        }
        return $member_info;
    }

    public function member_login($UserInfo,$referee_type,$referee_id,$jump_url='http://h5.91xzq.com/index.html'){
        if ($UserInfo) {
            Log::record('微信用户登录___' . var_export($UserInfo, true), 'info');
            $member_info = db('member')->where('weixin_id', $UserInfo['openid'])->find();
            if ($member_info) {
                Log::record('老用户登录系统...', 'info');
                if ($member_info['headimgurl'] != $UserInfo['headimgurl']) {
                    db('member')->where('id', $member_info['id'])->update([
                        'headimgurl' => $UserInfo['headimgurl'],
                    ]);
                }
                $token = $this->build_token($member_info['id']);
                $this->redirect($jump_url.'?token='.$token);
            } else {
                Log::record('新用户进行注册...', 'info');
                switch ($referee_type) {
                    case 'user':
                        Db::startTrans();
                        try {
                            //微信信息录入
                            $member_id =Db::name('member')->insertGetId([
                                'name' => $UserInfo['nickname'],
                                'sex' => $UserInfo['sex'],
                                'weixin_id' => $UserInfo['openid'],
                                'headimgurl' => $UserInfo['headimgurl'],
                                'create_time' => date("Y-m-d H:i:s"),
                                'referee' => $referee_id ? $referee_id : 0,
                            ]);
                            Db::name('member_account')->insert(['member_id' => $member_id, 'addr_id' => 0]);//地址信息创建
                            $referee_count = \db('score_history')->where('member_id', $referee_id)->where('channel', '推荐新用户奖励')->count();//老用户推广次数
                            Log::record('老用户___' . var_export($referee_id, true) . '推荐次数' . var_export($referee_count, true), 'info');
                            //发放积分奖励
                            if ($referee_count < 8) {
                                add_score($referee_id, 100, '推荐新用户奖励');
                            }
                            Log::record('用户推广—新用户登录...' . var_export($member_id, true), 'info');
                            $token = $this->build_token($member_id);
                            $this->redirect($jump_url.'?token='.$token);
                            // 提交事务
                            Db::commit();
                        } catch (\Exception $e) {
                            Log::record('用户推广—新用户注册出错...', 'info');
                            // 回滚事务
                            Db::rollback();
                        }
                        break;
                    case 'agent':
                        //微信信息录入
                        $re_agent_id = $referee_id;//代理商id
                        $find_agent = \db('agent')->where('id', $re_agent_id)->find();
                        $agent_level = $find_agent['level'];
                        Log::record('代理商链接推广——推广ID' . var_export($re_agent_id, true) . '代理商存在：' . var_export($find_agent, true), 'info');
                        $agent_data = [
                            'name' => $UserInfo['nickname'],
                            'sex' => $UserInfo['sex'],
                            'weixin_id' => $UserInfo['openid'],
                            'headimgurl' => $UserInfo['headimgurl'],
                            'create_time' => date("Y-m-d H:i:s"),
                            'agent_super_id' => $find_agent['agent_super_id'],
                            'agent_one_id' => $find_agent['agent_one_id'],
                            'agent_two_id' => $find_agent['agent_two_id'],
                            'agent_three_id' => 0,
                            'is_agent_share' => 1,
                        ];
                        switch ($agent_level){
                            case 3:
                                $agent_data['agent_three_id'] = $find_agent['id'];
                                break;
                            case 2:
                                $agent_data['agent_two_id'] = $find_agent['id'];
                                break;
                            case 1:
                                $agent_data['agent_one_id'] = $find_agent['id'];
                                break;
                            case 0:
                                $agent_data['agent_super_id'] = $find_agent['id'];
                                break;
                        }
                        $member_id =Db::name('member')->insertGetId($agent_data);
                        Db::name('member_account')->insert(['member_id' => $member_id, 'addr_id' => 0]);//地址信息创建
                        Log::record('代理商链接推广步骤完成.', 'info');
                        Log::record('代理商链接推广—新用户登录...' . var_export($member_id, true), 'info');
                        $token = $this->build_token($member_id);
                        $this->redirect($jump_url.'?token='.$token);
                        Db::startTrans();
                        try {
                            //微信信息录入
                            $re_agent_id = $referee_id;//代理商id
                            $find_agent = \db('agent')->where('id', $re_agent_id)->find();
                            $agent_level = $find_agent['level'];
                            Log::record('代理商链接推广——推广ID' . var_export($re_agent_id, true) . '代理商存在：' . var_export($find_agent, true), 'info');
                            $agent_data = [
                                'name' => $UserInfo['nickname'],
                                'sex' => $UserInfo['sex'],
                                'weixin_id' => $UserInfo['openid'],
                                'headimgurl' => $UserInfo['headimgurl'],
                                'create_time' => date("Y-m-d H:i:s"),
                                'agent_super_id' => $find_agent['agent_super_id'],
                                'agent_one_id' => $find_agent['agent_one_id'],
                                'agent_two_id' => $find_agent['agent_two_id'],
                                'agent_three_id' => 0,
                                'is_agent_share' => 1,
                            ];
                            if ($agent_level == 3) {
                                $agent_data['agent_three_id'] = $find_agent['id'];
                            }
                            $member_id =Db::name('member')->insertGetId($agent_data);
                            Db::name('member_account')->insert(['member_i' => $member_id, 'addr_id' => 0]);//地址信息创建
                            Log::record('代理商链接推广步骤完成.', 'info');
                            Log::record('代理商链接推广—新用户登录...' . var_export($member_id, true), 'info');
                            $token = $this->build_token($member_id);
                            $this->redirect($jump_url.'?token='.$token);
                            // 提交事务
                            Db::commit();
                        } catch (\Exception $e) {
                            Log::record('代理商链接推广—新用户注册出错...', 'info');
                            // 回滚事务
                            Db::rollback();
                        }
                        break;
                    case 'system':
                        Db::startTrans();
                        try {
                            //微信信息录入
                            $member_id =Db::name('member')->insertGetId([
                                'name' => $UserInfo['nickname'],
                                'sex' => $UserInfo['sex'],
                                'weixin_id' => $UserInfo['openid'],
                                'headimgurl' => $UserInfo['headimgurl'],
                                'create_time' => date("Y-m-d H:i:s"),
                            ]);
                            Db::name('member_account')->insert(['member_id' => $member_id, 'addr_id' => 0]);//地址信息创建
                            Log::record('微信公众号入口—新用户登录...' . var_export($member_id, true), 'info');
                            $token = $this->build_token($member_id);
                            $this->redirect($jump_url.'?token='.$token);
                            // 提交事务
                            Db::commit();
                        } catch (\Exception $e) {
                            Log::record('微信公众号入口—新用户注册出错...', 'info');
                            // 回滚事务
                            Db::rollback();
                        }
                        break;
                    default:
                        Db::startTrans();
                        try {
                            //微信信息录入
                            $member_id =Db::name('member')->insertGetId([
                                'name' => $UserInfo['nickname'],
                                'sex' => $UserInfo['sex'],
                                'weixin_id' => $UserInfo['openid'],
                                'headimgurl' => $UserInfo['headimgurl'],
                                'create_time' => date("Y-m-d H:i:s"),
                            ]);
                            Db::name('member_account')->insert(['member_id' => $member_id, 'addr_id' => 0]);//地址信息创建
                            Log::record('其他入口—新用户登录...' . var_export($member_id, true), 'info');
                            $token = $this->build_token($member_id);
                            $this->redirect($jump_url.'?token='.$token);
                            // 提交事务
                            Db::commit();
                        } catch (\Exception $e) {
                            Log::record('其他入口—新用户注册出错...', 'info');
                            // 回滚事务
                            Db::rollback();
                        }
                }
            }
        }
    }

    //生成token
    public function build_token($member_id)
    {
        $tokenCon = new Token();
        $is_token = db('member_token')->where('member_id', $member_id)->find();
        if ($is_token){
            if ($is_token['expiretime'] < time()){
                $token = $tokenCon->refresh($member_id);
            }else{
                $token = $is_token['token'];
            }
        } else {
            $token =$tokenCon->build($member_id);
        }
        if (empty($token)){
            return null;
        }
        return $token;
    }
}
