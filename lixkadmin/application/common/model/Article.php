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
            $comments=Db::name('comments')
                ->alias("c")
                ->join('user u','c.uid=u.id','LEFT')
                ->where('c.to',$v['id'])
                ->where('c.pid',0)
                ->field('u.username,u.id as uid,c.content')
                ->limit(10)
                ->select();

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
    public function dolixk($comment_id,$type,$user_id){
        //判断redis是否已经缓存了该文章数据
        //使用：分隔符对redis管理是友好的
        //这里使用redis zset-> zscore()方法
        $this->redis = new \Redis();
        $this->redis->connect(config('cache.host'), config('cache.post'));
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
}