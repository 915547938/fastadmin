<?php
namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\model\Article as ArticleModel;
use think\Db;
use think\Request;
use think\Cache;

class Article extends Api{
    protected $noNeedLogin = ['getfriend'];
    protected $noNeedRight = '*';
    public $redis=null;
    public function _initialize()
    {
        parent::_initialize();
        $this->redis=new \redis();
        $this->redis->connect(config('cache.host'),config('cache.port'));
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
        $avatar=Db::name('user')->where('id',$uid)->value('avatar');
        if(empty($avatar)){
            $this->error("请先上传头像");
        }
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
    //获取美食秀数据
    public function getfriend(){
        $page=$this->request->get('page');
        $page=empty($page)?1:$page;
        //Cache::clear();
        $returnFriendData=$this->redis->hget('friend_data',$page);
        $returnFriendData=json_decode($returnFriendData,1);
        if(empty($returnFriendData)){
            $articleModel=new ArticleModel();
            $returnFriendData=$articleModel->getFriendAll($page);
            if($returnFriendData){
                $this->redis->hset('friend_data',$page,json_encode($returnFriendData));
            }
        }
        $this->success('获取成功',$returnFriendData);
    }
    //点赞
    public function dolike(){
        $artId=$this->request->post('article');
        $type=$this->request->post('type');
        $article=Db::name('article')->where('id',$artId)->find();
        if($article){
            $articleModel=new ArticleModel();
            $res=$articleModel->dolixk($artId,$type,$this->auth->id);
            if($res){
                $this->success('成功');
            }else{
                eblog('article.dolike','失败','notify');
                $this->error('失败');
            }
        }else{
            $this->error('找不到此文章');
        }
    }

}