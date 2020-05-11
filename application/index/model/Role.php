<?php

namespace app\index\model;

use think\Model;

/**
 * 角色模型
 * @package app\index\model
 */
class Role extends Model {
    // 表名
    protected $name = 'role';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    public function permissions()
    {
        //return $this->hasMany('RolePermission');
        //return $this->belongsToMany('Permission', 'role_permission', 'permission_id', 'role_id');
        return $this->belongsToMany('Permission', 'role_permission', 'permission_value', 'role_id');
    }
}