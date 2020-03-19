<?php
namespace app\common\model;

use think\model;
use think\Db;
use think\Request;
use think\Cache;

class Article extends Model{
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    public static function getFriendall($page){
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
            $islike=Db::name('like')
                ->alias("l")
                ->join('user u','l.user_id=u.id','LEFT')
                ->where('l.article',$v['id'])
                ->field('u.username,u.id  as uid')
                ->select();
            $comments=Db::name('comments')
                ->alias("c")
                ->join('user u','c.uid=u.id','LEFT')
                ->where('c.to',$v['id'])
                ->where('c.pid',0)
                ->field('u.username,u.id as uid,c.content')
                ->select();
            $oneArticle=array(
                "post_id"=>$v['id'],
                "uid"=> $v['user_id'],
                "username"=>$user['username'],
                "header_image"=> $domain.$user['avatar'],
                "content"=>array(
                    "text"=>$v['content'],
                    "images"=>$images ,
                ),
                "islike"=>$v['count_like'],
                "like"=>$islike,
                "comments"=>array(
                    "total"=>count($comments),
                    "comment"=>$comments
                ) ,
                "timestamp"=>dateline($v['create_time'])
            );
            $returnFriendData[]=$oneArticle;
            //$v['imgs']=$images;
        }
        Cache::set('friend_data'.$page,$returnFriendData,0);
        //cache('friend_data',$returnFriendData,)
        return $returnFriendData;
    }
}