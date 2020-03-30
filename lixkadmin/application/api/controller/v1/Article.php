<?php
namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\model\Article as ArticleModel;
use http\Header;
use think\Db;
use think\Request;
use think\Cache;

/**
 * 文章接口
 */
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

    /**
     * 新增文章
     *
     * @param string $content  文章内容
     * @param array $content  图片路径数组
     * @param Header $token token
     */
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

    /**
     * 获取美食秀数据
     *
     * @param int $page 页码
     * @param Header $token token 可选
     */
    public function getfriend(){
        $page=$this->request->get('page');
        $page=empty($page)?1:$page;
        //Cache::clear();
        $returnFriendData=$this->redis->hget('friend_data',$page);
        $returnFriendData=json_decode($returnFriendData,1);
        $articleModel=new ArticleModel();
        if(empty($returnFriendData)){

            $returnFriendData=$articleModel->getFriendAll($page);
            if($returnFriendData){
                $this->redis->hset('friend_data',$page,json_encode($returnFriendData));
            }
        }
        eblog('myclick',$this->auth->getUser(),'notify');
        foreach($returnFriendData as &$v){
            $commenlen=$this->redis->hlen('comment:crecord'.':'.$v['post_id']);
            if($commenlen){
                $alllen=$commenlen;
                if(intval($commenlen)>10){
                    $commenlen=10;
                }
                $commvalue=array();
                $f=$alllen-$commenlen;
                for($i=$f;$i<$alllen;$i++){
                    array_push($commvalue,$i);
                }
                if(!empty($commvalue)){
                    $comments=$this->redis->hmget('comment:crecord'.':'.$v['post_id'],$commvalue);
                    $commentCon=array();
                    foreach($comments as $vv){
                        $commentCon[]=json_decode($vv,1);
                    }
                    $v['comments']=array(
                        "total"=>count($commentCon),
                        "comment"=>$commentCon
                    );
                }
            }else{
                $articleModel=new ArticleModel();
                $commentCon=$articleModel->getArticleComment($v['post_id']);
                $v['comments']=array(
                    "total"=>count($commentCon),
                    "comment"=>$commentCon
                );
            }
        }
        if($this->auth->id){
            foreach($returnFriendData as &$v){
                $myClickData=0;
                $myClickData=$this->redis->hget('click_data',$this->auth->id.':'.$v['post_id']);
                if(!($myClickData) && $myClickData!=0){
                    $articleModel=new ArticleModel();
                    $myClickData=$articleModel->getMyLick($this->auth->id,$v['post_id']);
                    if($myClickData){
                        $this->redis->hset('click_data',$this->auth->id.':'.$v['post_id'],($myClickData));
                    }
                }
                if($myClickData)
                    $v['mylike']=$myClickData;
                else
                    $v['mylike']=0;
                $likeData=$this->redis->hGet('comment:wholike',$v['post_id']);
                if(!($likeData)){
                    $articleModel=new ArticleModel();
                    $likeData=$articleModel->getArticleLike($v['post_id']);
                    if($likeData){
                        $this->redis->hset('comment:wholike',$v['post_id'],$likeData);
                    }
                }
                $who=explode(",",$likeData);
                $likeDatas=array();
                if(!empty($likeData)){
                    foreach($who as $vid){
                        $username=Db::name('user')->where('id',$vid)->value('username');
                        $likeDatas[]=array(
                            'uid'=>$vid,
                            'username'=>$username
                        );
                    }
                }
                $v['islike']=count($likeDatas);
                $v['like']=$likeDatas;
            }
        }
        $this->success('获取成功',$returnFriendData);
    }
    //
    /**
     * 点赞
     *
     * @param int $article 文章id
     * @param int $type 点赞（类型,固定传1）
     */
    public function dolike(){
        $artId=$this->request->post('article');
        $type=$this->request->post('type');
        $article=Db::name('article')->where('id',$artId)->find();
        if($article && $this->auth->id){
            $articleModel=new ArticleModel();
            $res=$articleModel->dolixk($artId,$type,$this->auth->id);
            if($res){
                $this->success('成功');
            }else{
                eblog('article.dolike','失败','notify');
                $this->error('失败');
            }
        }else{
            $this->error('找不到此文章,或者未登陆');
        }
    }

    /**
     * 评论
     *
     * @param string $article 文章id
     * @param string $content 评论内容
     * @param Header $token token
     */
    public function docomment(){
        $pid=$this->request->post('pid');
        $artId=$this->request->post('article');
        $content=$this->request->post('content');
        $article=Db::name('article')->where('id',$artId)->find();

        if(empty($pid))
            $pid=0;
        if($article && $this->auth->id){
            $articleModel=new ArticleModel();
            $res=$articleModel->docomment($artId,$content,$this->auth->id,intval($pid));
            if($res){
                $this->success('成功');
            }else{
                eblog('article.docomment','失败','notify');
                $this->error('失败');
            }
        }else{
            $this->error('找不到此文章,或者未登陆');
        }
    }
}