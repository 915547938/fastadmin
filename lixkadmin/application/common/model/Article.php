<?php
namespace app\common\model;

use think\model;

class Article extends Model{
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
}