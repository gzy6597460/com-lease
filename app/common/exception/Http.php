<?php
namespace app\common\exception;

use Exception;
use think\exception\Handle;
class Http extends Handle
{
    public function render(\Exception $e){
        if(config('app_debug')){
            //如果开启debug则正常报错
            return parent::render($e);
        }else{
            return json(['code'=>500,'status'=>false,'msg'=>$e->getMessage(),'time'=>time()]);
        }
    }
}