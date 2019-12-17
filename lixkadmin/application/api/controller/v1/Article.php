<?php
namespace app\api\controller\v1;

use app\common\controller\Api;

class Article extends Api{
    protected $noNeedLogin = ['addarticle'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }
    public function addarticle(){
        $content=$this->request->post('content');
        $file=$this->request->file('imgpath');
        dump($file);exit;
    }
}