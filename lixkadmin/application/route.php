<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;
//注册
Route::rule('register','api/v1.User/registereasy','POST');
//登录
Route::rule('login','api/v1.User/login',"POST");
//新增文章
Route::rule('article','api/v1.Article/addarticle',"POST");
//上传文件
Route::rule('upload','api/v1.Common/upload',"POST");
Route::rule('mangyupload','api/v1.Common/uploads',"POST");
//获取朋友圈
Route::rule('friend','api/v1.Article/getfriend',"get");
//修改个人信息
Route::rule('profile','api/v1.User/profile',"POST");
Route::rule('myinit','api/v1.User/myinit',"get");
Route::rule('like','api/v1.Article/dolike',"post");
/*return [
    //别名配置,别名只能是映射到控制器且访问时必须加上请求的方法
    '__alias__'   => [
    ],
    //变量规则
    '__pattern__' => [
    ],
//        域名绑定到模块
//        '__domain__'  => [
//            'admin' => 'admin',
//            'api'   => 'api',
//        ],
];*/
