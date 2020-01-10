<?php
namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\model\Article as ArticleModel;
use think\Db;

class Article extends Api{
    protected $noNeedLogin = ['addarticle'];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();

    }
    public function addarticle(){
        $content=$this->request->post('content');
        $all_path=$this->request->post('all_path/a');
        $uid=$this->auth->id;
        $params=array(
            'content'=>$content,
            'all_path'=>serialize($all_path),
            'user_id'=>$uid
        );
        Db::startTrans();
        try {
            $article = ArticleModel::create($params, true);
            Db::commit();
            $this->success('添加成功');
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        //show_json(1,array('url' => mobileUrl('xcshop/applys.levelchange', array('id' => $id))));
    }
}