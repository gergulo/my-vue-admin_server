<?php
namespace app\index\controller;

use app\common\controller\Base;
use think\Controller;
use think\Db;
use think\Config;

class Role extends Base {

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = ['listall'];

    /**
     * 查询角色列表
     */
    public function query(){
        $txt = $this->request->param('txt', '');
        $current = intval($this->request->param('current', '1'));
        $size = intval($this->request->param('size', '10'));

        $where = function ($query) use ($txt) {
            if (!empty($txt)) {
                $query->where('role_name|role_value', 'LIKE', "%{$txt}%");
            }
        };
        $data =[];
        $model = model('Role');
        $total = $model
            ->where($where)
            ->count();
        if ($total > 0) {
            $data = $model
                ->where($where)
                ->field('id, role_name, role_value, remark, create_time, update_time')
                ->limit(($current - 1) * $size, $size)
                ->select();
        }
        $page = array(
            'size' => $size,
            'current' => $current,
            'pages' => ceil($total / $size),
            'total' =>$total,
            'records' => $data,
        );
        $data = array(
            'page'  => $page
        );
        $this->success(__('Get data successful'), $data);
    }

    /**
     * 创建角色
     */
    public function add(){
        $data['role_name'] = $this->request->param('role_name', '');
        $data['role_value'] = $this->request->param('role_value', '');
        $data['remark'] = $this->request->param('remark', '');

        $role = model('Role');
        $result = $role->save($data);
        if ($result){
            $result = array(
                'id' => $role['id'],
                'create_time' => $role['create_time'],
                'update_time' => $role['update_time'],
            );
            $this->success(__('Save successful'), $result);
        } else {
            $this->error(__('Save failed'));
        }
    }

    /**
     * 更新角色
     */
    public function update(){
        $id = $this->request->param('id', '');
        $data['role_name'] = $this->request->param('role_name', '');
        $data['role_value'] = $this->request->param('role_value', '');
        $data['remark'] = $this->request->param('remark', '');

        $role = model('Role');
        $result = $role->save($data, ['id' =>  $id]);
        if ($result){
            $result = array(
                'update_time' => $role['update_time'],
            );
            $this->success(__('Save successful'), $result);
        } else {
            $this->error(__('Save failed'));
        }
    }

    /**
     * 删除角色
     */
    public function delete(){
        $where['id'] = $this->request->param('id', '');
        $result = false;
        // 启动事务
        Db::startTrans();
        try{
            model('Role')
                ->where($where)
                ->delete();
            model('UserRole')
                ->where('role_id', $where['id'])
                ->delete();
            $result = true;
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
        if ($result){
            $this->success(__('Delete completed'));
        } else {
            $this->error(__('Delete failed'));
        }
    }

    /**
     * 获取角色权限
     */
    public function getroleperms(){
        $id = $this->request->param('id', '');
        $data = model('Role')
            ->with('permissions')
            ->where('id', $id)
            ->find();
        //整理权限信息
        $menu_perm_values = [];
        $btn_perm_values = [];
        $api_perm_values = [];
        $role = array(
            'role_name' => $data['role_name'],
            'role_value' => $data['role_value'],
        );
        if (!empty($data)){
            $permissions = $data['permissions'];
            foreach ($permissions as $v){
                switch($v['permission_type']){
                    case "1":
                        $menu_perm_values[] = $v['permission_value'];
                        break;
                    case "2":
                        $btn_perm_values[] = $v['permission_value'];
                        break;
                    case "3":
                        $api_perm_values[] = $v['permission_value'];
                        break;
                }
            }
        }
        $result = array(
            'role' => $role,
            'menu_perm_values' => $menu_perm_values,
            'btn_perm_values' => $btn_perm_values,
            'api_perm_values' => $api_perm_values,
        );
        $this->success(__('Get data successful'), $result);
    }

    /**
     * 更新角色权限
     */
    public function updateperms(){
        $role_id = $this->request->param('role_id', '');
        $perm_type = $this->request->param('perm_type', '');
        $perm_values = $this->request->param('perm_values/a', []);

        $list = [];
        foreach ($perm_values as $v){
            $list[] = array(
                'role_id' => $role_id,
                'permission_type' => $perm_type,
                'permission_value' => $v,
            );
        }
        $result = false;
        // 启动事务
        Db::startTrans();
        try{
            $model = model('RolePermission');
            $model
                ->where('role_id', $role_id)
                ->where('permission_type', $perm_type)
                ->delete();
            $model->saveAll($list, false);
            $result = true;
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            print_r($e);
            // 回滚事务
            Db::rollback();
        }
        if ($result){
            $this->success(__('Update role permission successful'));
        } else {
            $this->error(__('Update role permission failed'));
        }
    }

    /**
     * 添加角色权限
     */
    public function addperm(){
        $data['role_id'] = $this->request->param('role_id', '');
        $data['permission_type'] = $this->request->param('perm_type', '');
        $data['permission_value'] = $this->request->param('perm_value', []);

        $result = model('RolePermission')
            ->save($data);
        if ($result){
            $this->success(__('Add button permission successful'));
        } else {
            $this->error(__('Add button permission failed'));
        }
    }

    /**
     * 删除角色权限
     */
    public function deleteperm(){
        $data['role_id'] = $this->request->param('role_id', '');
        $data['permission_type'] = $this->request->param('perm_type', '');
        $data['permission_value'] = $this->request->param('perm_value', []);
        
        $result = model('RolePermission')
            ->where($data)
            ->delete();
        if ($result){
            $this->success(__('Delete button permission successful'));
        } else {
            $this->error(__('Delete button permission failed'));
        }
    }

    /**
     * 获取所有角色列表
     */
    public function listall() {
        $data = model('Role')
            ->field('id, role_name, role_value')
            ->select();
        $data = array(
            'roles'  => $data
        );
        $this->success(__('Get data successful'), $data);
    }
}