<?php

namespace app\api\controller;

header('Access-Control-Allow-Origin:*');

use \think\Db;
use \think\Request;
use \think\Cache;
use \think\Cookie;
use \think\Session;
use \think\Log;
use \think\image;
use \think\Controller;
use think\Validate;
use OSS\Core\OssException;


class Store extends Controller
{
    /**
     * 商家入驻申请
     */
    public function storeApply()
    {
        $post = Request::instance()->post();
        //验证
        $validate = validate('Storeapply');
        $data = $post;
        if (!$validate->check($data)) {
            //dump($validate->getError());
            jsonOk(null,null,$validate->getError(),false);
        }
        $data['IDcard_validity'] = strtotime($data['IDcard_validity']);
        //入库
        $model = new \app\api\model\Storeapply;
        try {
            $result = $model->allowField(true)->save($data);
            if ($result !== false) {
                jsonOk($result,null,'申请成功!',true);
            } else {
                jsonOk($model->getError(),null,'申请失败',false);
            }
        } catch (\think\exception\PDOException $e) {
            jsonOk($e->getMessage(),null,'申请失败',false);
        } catch (\think\Exception $e) {
            jsonOk($e->getMessage(),null,'申请失败',false);
        }
        jsonOk(null,null,'无效请求',false);
    }

    /**
     * 审核照片上传
     */
    public function heard_upload()
    {
        // 获取表单上传文件
        $file = request()->file("certificate_img");
        if (empty($file)) {
            jsonOk(null, null, '请选择上传文件', false);
        }
        $info = $file->validate(['size' => 1048576, 'ext' => 'jpg,png,gif,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads');
        //var_dump($file);
        if (!$info) {// 上传错误提示错误信息
            //处理上传错误信息
            $msg = $file->getError();
            jsonOk(null, $msg, '请选择小于3M的图片', false);
            //echo $file->getError();
        } else {// 上传成功
            $savename = $info->getSaveName();
            $file_name = $info->getFilename();
            vendor('aliyuncs.autoload');
            $accessKeyId = "LTAI4ANIZP6RJaQu";//去阿里云后台获取秘钥
            $accessKeySecret = "NipIP9ERN8nx0oRxG0U4AeCI5JgZ9G";//去阿里云后台获取秘钥
            $endpoint = "oss-cn-shenzhen.aliyuncs.com";//你的阿里云OSS地址
            $ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $bucket = "xiaozhuquan";//oss中的文件上传空间
            $object = 'store_apply' . '/' . date('Y-m-d') . '/' . $file_name;// $info['imgfile']['savename'];
            ////想要保存文件的名称
            $file = './uploads/' . $savename;//文件路径，必须是本地的。
            try {
                $ossClient->uploadFile($bucket, $object, $file);
                //上传成功，自己编码
                //这里可以删除上传到本地的文件。unlink（$file）；
                $headimg_url = "http://{$bucket}.{$endpoint}/{$object}";
                if (empty($res)) {
                    jsonOk(null, null, '上传失败(oss)', true);
                }
                jsonOk($headimg_url, null, '上传成功！', true);
            } catch (OssException $e) {
                //上传失败，自己编码
//                printf($e->getMessage() . "\n");
                jsonOk(null, $e->getMessage(), '上传失败', false);
            }
        }
    }

}
