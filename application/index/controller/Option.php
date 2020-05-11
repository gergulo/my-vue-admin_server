<?php
namespace app\index\controller;

use app\common\controller\Base;
use think\Controller;
use think\Db;

class Option extends Base {

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = [''];

}