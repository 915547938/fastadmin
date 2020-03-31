<?php

namespace app\api\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;


class Crontab extends Command
{

    protected function configure()
    {
        file_put_contents('notify.txt',date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        $this->setName('Crontab')->setDescription("每天统计数据");//这里的setName和php文件名一致,setDescription随意
    }

    /*
     * 报表-全局统计
     */
    protected function execute(Input $input, Output $output)
    {
        //这里写业务逻辑
        eblog('crontab.execute',PHP_EOL,'like_notify');
        eblog('crontab.execute','-------like start---------','like_notify');
        $redis=new \redis();
        $redis->connect(config('cache.host'),config('cache.port'));
        for($i=0;$i<500;$i++){
            $like=$redis->lPop('comment:uploadlist');
            if($like){
                $likeArray=json_decode($like,1);
                $likeData=$likeArray;
                unset($likeData['type']);
                $likeData['isdelete']=$likeArray['type'];
                $oldLike=Db::name('like')->where('article',$likeData['article'])->where('user_id',$likeData['user_id'])->find();
                if($oldLike){
                    $likeData['isdelete']=$oldLike['isdelete']>0?0:1;
                    $res=Db::name('like')->where('article',$likeData['article'])->where('user_id',$likeData['user_id'])->update($likeData);
                }else{
                    $res=Db::name('like')->insert($likeData);
                }
                if(!$res){
                    eblog('crontab.execute',$likeData,'like_notify');
                }
                eblog('crontab.execute','-------like '.$i.'---------','like_notify');
            }
        }
        $redis->del('friend_data');
        eblog('crontab.execute','-------like end---------','like_notify');
    }
}