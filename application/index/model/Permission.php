<?php

namespace app\index\model;

use think\Model;

/**
 * 权限模型
 * @package app\index\model
 */
class Permission extends Model {
    // 表名
    protected $name = 'permission';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'datetime';
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
}