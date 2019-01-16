<?php
namespace app\api\service;

use think\Controller;
use think\Db;
use think\Log;
use app\api\controller\Token;

class Member extends Controller
{
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
                Log::record('登录token...'.var_export($token, true), 'info');
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
                            Log::record('登录token...'.var_export($token, true), 'info');
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
                            Log::record('登录token...'.var_export($token, true), 'info');
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
                            Log::record('登录token...'.var_export($token, true), 'info');
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