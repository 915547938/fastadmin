<?php

namespace app\api\controller\v1;

use app\common\controller\Api;

/**
 * 首页接口
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */
    public function index()
    {

        $con=file_get_contents("http://101.132.32.32:8088/common/echo?msg=hello");
        $path = 'H:\ceshi\f.txt';  //文件路径和文件名
        file_put_contents($path, $con);
        dump(json_decode(file_get_contents($path),1));exit;
        echo 1;exit;

        $this->success('请求成功');
    }
}
