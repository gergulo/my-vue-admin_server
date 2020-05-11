<?php

namespace app\index\model;

use think\Model;

/**
 * 用户模型
 * @package app\index\model
 */
class User extends Model {
    // 表名
    protected $name = 'user';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    public function roles()
    {
        return $this->belongsToMany('Role', 'user_role', 'role_id', 'user_id');
    }
}