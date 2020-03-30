<?php
namespace app\common\model;

use app\common\model\BaseModel;
use think\Collection;
use think\model;
use think\Db;
use think\Request;
use think\Cache;

class Article extends BaseModel{
    // 开启自动写入时间戳字段

    protected $autoWriteTimestamp = 'int';
    public $redis=null;
    public $num=1;
    public $score = 4320;
    public $liketype=0;
    public function __construct($data = [])
    {
        parent::__construct($data);
        $this->redis = new \Redis();
        $this->redis->connect(config('cache.host'), config('cache.post'));
        //eblog('redis',1111,'notify');
    }

    public function getFriendall($page){
        $request = Request::instance();
        $domain = $request->domain();
        $article=self::all(function($query)use($page){
            $num=2;
            $page=empty($page)?1:$page;
            $start=($page-1)*$num;
            $query->where('status',1)->where('isdelete',0)->limit($start,$num);
        });
        $returnFriendData=array();
        foreach($article as &$v){
            $user=Db::name('user')->where('id',$v['user_id'])->find();
            $images=unserialize($v['all_path']);
            foreach($images as &$v1){
                $v1=$domain.$v1;
            }
            $islike=array();
            $comments=array();
                /*Db::name('comments')
                ->alias("c")
                ->join('user u','c.uid=u.id','LEFT')
                ->where('c.to',$v['id'])
                ->where('c.pid',0)
                ->field('u.username,u.id as uid,c.content')
                ->limit(10)
                ->select();*/

            $oneArticle=array(
                "post_id"=>$v['id'],
                "uid"=> $v['user_id'],
                "username"=>$user['username'],
                "header_image"=> $user['avatar'],
                "content"=>array(
                    "text"=>$v['content'],
                    "images"=>$images ,
                ),
                "islike"=>count($islike),
                "like"=>$islike,
                'mylike'=>0,
                "comments"=>array(
                    "total"=>count($comments),
                    "comment"=>$comments
                ) ,
                "timestamp"=>dateline($v['create_time'])
            );
            $returnFriendData[]=$oneArticle;
            //$v['imgs']=$images;
        }
        //Cache::set('friend_data'.$page,$returnFriendData,0);
        //cache('friend_data',$returnFriendData,)
        return $returnFriendData;
    }
    public function getMyLick($uid,$articleid){
        $article=Db::name('like')->where(['user_id'=>$uid,'article'=>$articleid])->find();
        if($article)
            return 1;
        else
            return 0;
    }
    public function getArticleLike($id){
        $islike=Db::name('like')
            ->where('article',$id)
            ->where('isdelete',0)
            ->field('user_id')
            ->limit(10)
            ->select();
        $returnData="";
        foreach($islike as $v){
            $returnData=join(",",$v);
        }
        return $returnData;
    }
    public function getArticleComment($artId,$type=0){
        $con=Db::name('comments')
            ->alias('c')
            ->join('user u','u.id=c.uid','left')
            ->where('c.to',$artId)
            ->order('c.id','desc')
            ->limit(10)
            ->field('c.id,u.id as uid,u.username,c.content,c.pid')
            ->select();
        $returnCon=array_reverse($con);
        foreach(array_reverse($con) as $v){
            //缓存评论数量
            $v['pname']=$v['username'];
            $this->redis->zincrby('comment:ccount',$this->num,$artId);
            //缓存最新10条数据
            $len=$this->redis->hLen('comment:crecord'.':'.$artId);

            $this->redis->hset('comment:crecord'.':'.$artId,$len,json_encode($v));
        }
        return $returnCon;
    }
    public function dolixk($comment_id,$type,$user_id){
        //判断redis是否已经缓存了该文章数据
        //使用：分隔符对redis管理是友好的
        //这里使用redis zset-> zscore()方法
        //$this->redis = new \Redis();
        //$this->redis->connect(config('cache.host'), config('cache.post'));
        if($this->redis->zscore("comment:like",$comment_id)){
            $rel=$this->redis->hGet("comment:record",$user_id.":".$comment_id);
            if(!$rel){
                //没点过
                //点赞加1
                $this->redis->zincrby('comment:like',$this->num,$comment_id);
                //增加记录
                $this->redis->hset('comment:record',$user_id.":".$comment_id,$type.":".time().":1");
                $who=$this->redis->hGet('comment:wholike',$comment_id);
                if($who!="")
                    $this->redis->hset('comment:wholike',$comment_id,$who.",".$user_id);
                else
                    $this->redis->hset('comment:wholike',$comment_id,$user_id);

            }else{
                $this->liketype=1;
                //点过赞
                //点赞减一
                $this->redis->zincrby('comment:like',-$this->num,$comment_id);
                //增加记录
                $this->redis->hDel('comment:record',$user_id.":".$comment_id);
                $who=$this->redis->hGet('comment:wholike',$comment_id);
                $who=explode(",",$who);
                unset($who[count($who)-1]);
                $who=implode(",",$who);
                $this->redis->hset('comment:wholike',$comment_id,$who);
            }
        }else{
            $allnum=Db::name('like')->where('id',$comment_id)->where('isdelete',0)->count();
            $this->num=$allnum+$this->num;
            //没点过
            //点赞加1
            $this->redis->zincrby('comment:like',$this->num,$comment_id);
            //增加记录
            $this->redis->hset('comment:record',$user_id.":".$comment_id,$type.":".time().":1");
            $who=$this->redis->hGet('comment:wholike',$comment_id);
            if($who!="")
                $this->redis->hset('comment:wholike',$comment_id,$who.",".$user_id);
            else
                $this->redis->hset('comment:wholike',$comment_id,$user_id);
        }
        //判断是否需要更新数据
        $this->UploadList($comment_id,$user_id);

        if($user_id){
            $myClickData=$this->redis->hget('click_data',$user_id.':'.$comment_id);
            if(empty($myClickData) && $myClickData!=0){
                $myClickData=$this->getMyLick($user_id,$myClickData);
                if($myClickData){
                    $this->redis->hset('click_data',$user_id.':'.$comment_id,($myClickData));
                }
            }
            if($myClickData==1){
                $this->redis->hset('click_data',$user_id.':'.$comment_id,0);
            }else{
                $this->redis->hset('click_data',$user_id.':'.$comment_id,1);
            }
        }
        return true;
    }
    public function UploadList($comment_id,$user_id)
    {
        date_default_timezone_set("Asia/Shanghai");
        $time = time();

        $this->redis->sadd("comment:uploadset",$comment_id);
        //更新到队列
        $data = array(
            "article" => $comment_id,
            "time" => $time,
            "user_id"=>$user_id,
            'type'=>$this->liketype
        );
        $json = json_encode($data);
        $this->redis->lpush("comment:uploadlist",$json);
    }
    public function docomment($article_id,$content,$user_id,$pid){
        $data=array(
            'uid'=>$user_id,
            'what'=>'评论文章id'.$article_id,
            'to'=>$article_id,
            'content'=>$content,
            'pid'=>$pid,
            'create_time'=>time()
        );
        $commId=Db::name('comments')->insertGetId($data);
        $username=Db::name('user')->where('id',$user_id)->value('username');
        $coomData=array(
            'id'=>$commId,
            'uid'=>$user_id,
            'username'=>$username,
            'pid'=>$pid,
            'pname'=>$username,
            'content'=>$content,
        );
        if($commId){
            //缓存评论数量
            $this->redis->zincrby('comment:ccount',$this->num,$article_id);
            //缓存最新10条数据
            $len=$this->redis->hLen('comment:crecord'.':'.$article_id);
            $this->redis->hset('comment:crecord'.':'.$article_id,$len,json_encode($coomData));
            return true;
        }else{
            return false;
        }
    }
}