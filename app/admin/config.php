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
    'TMPL_ACTION_ERROR'     =>  'View/Public/error.html', // 默认错误跳转对应的模板文件
    'TMPL_ACTION_SUCCESS'   =>  'View/Public/success.html', // 默认成功跳转对应的模板文件
    'TMPL_EXCEPTION_FILE'   =>  'View/Public/exception.html',// 异常页面的模板文件
];