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


//配置文件
return [
	'view_replace_str' => [
		'__CSS__'      => '/static/admin/css',
		'__PUBLIC__'   => '/static/public',
		'__JS__'       => '/static/admin/js'
	],
    'lucky_draw' => [
        'need_score' =>  100,  //抽奖所需要的积分
        'daily_time' =>  3,    //每日抽奖次数上限
        //奖品对应转盘角度
        'angle'=>[
            //电脑一台
            1 =>[
                [289,323],
            ],
            //免租3个月
            2 =>[
                [181,215],
            ],
            //免租1个月
            3 =>[
                [1,35],
            ],
            //键鼠一套
            4 =>[
                [217,251],
            ],
            //免租7天
            5 =>[
                [73,107],
                [145,179],
            ],
            //免租1天
            6 =>[
                [37,71],
                [109,143],
                [253,287],
                [325,359],
            ],
        ]
    ],
    'weixin_config' => [
        'appid'      => 'wx5cb120cb7d1c9866',
        'appsecret'   => '1f26b36c2792e2cb61dd8c00419b7a08',
    ],
    'nopay_message'=>[
        "touser" => "{openid}",
        "template_id" => "gEcitkMc0tcRNtF5xpaZk_B38f5QLOWpAkRkhUOQlbQ",
        "topcolor" => "#FF0000",
        "data" => [
            'first' => [
                "value" => "客官，您好！您的订单未支付，即将关闭。",
                "color" => "#173177"
            ],
            'ordertape' => [
                "value" => "{time}",
                "color" => "#173177"
            ],
            'ordeID' => [
                "value" => "{orderid}",
                "color" => "#173177"
            ],
            'remark' => [
                "value" => "还未付款，未付款订单24时内关闭，请及时付款。感谢您对小猪圈的青睐！",
                "color" => "#173177"
            ],
        ]
    ],
];