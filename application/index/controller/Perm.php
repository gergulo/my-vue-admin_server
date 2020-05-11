<?php
namespace app\index\controller;

use app\common\controller\Base;
use think\Controller;
use think\Db;
use think\Config;

class Perm extends Base {

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = ['getBtnPerms', 'getApiPerms', 'listAll'];

    protected $apiRoot = 'a:root';

    /**
     * 获取按钮权限列表
     */
    public function getBtnPerms(){
        $data = model('Permission')
            ->field('parent, permission_name, permission_value, permission_type')
            ->where('permission_type', 2)
            ->order('parent, permission_name')
            ->select();
        $data = $this->parseBtnPermMap($data);
        $data = array(
            'btnPermMap' => $data,
        );
        $this->success(__('Get data successful'), $data);
    }

    /**
     * 获取接口权限列表
     */
    public function getApiPerms(){
        $data = model('Permission')
            ->field('parent, permission_name, permission_value, permission_type')
            ->where('permission_type', 3)
            ->order('parent, permission_name')
            ->select();
        $data = $this->parseApiPermMap($data, $this->apiRoot);
        $data = array(
            'apiPermMap' => $data,
        );
        $this->success(__('Get data successful'), $data);
    }

    /**
     * 获取所有权限列表
     */
    public function listAll(){
        $data = model('Permission')
            ->field('parent, permission_name, permission_value, permission_type')
            ->where('permission_type', 'in', [1, 2, 3])
            ->order('parent, permission_name')
            ->select();
        $btnPermMap = $this->parseBtnPermMap($data);
        $permMap = [];
        $permMap[1] = $this->parsePermMap($data, 1);
        $permMap[3] = $this->parsePermMap($data, 3);
        $data = array(
            'btnPermMap' => $btnPermMap,
            'permMap' => $permMap,
        );
        $this->success(__('Get data successful'), $data);
    }

    /**
     * 同步菜单权限列表
     */
    public function syncMenuPerms(){
        $request_data = $this->request->param();
        $list = [];
        $count = count($request_data);
        for ($i = 0; $i < $count; $i++){
            $list[] = array(
                'parent' => $request_data[$i]['parent'],
                'permission_name' => $request_data[$i]['perm_name'],
                'permission_value' => $request_data[$i]['perm_value'],
                'permission_type' => $request_data[$i]['perm_type'],
                'leaf' => $request_data[$i]['leaf'] == 'true' ? '1' : '0',
            );
        }
        $result = false;
        // 启动事务
        Db::startTrans();
        try{
            $model = model('Permission');
            $model
                ->where(['permission_type' => $list[0]['permission_type']])
                ->delete(true);
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
            $this->success(__('Sync menu permisson successful'));
        } else {
            $this->error(__('Sync menu permisson failed'));
        }
    }

    /**
     * 添加权限
     */
    public function add(){
        $data['permission_name'] = $this->request->param('perm_name', '');
        $data['permission_type'] = $this->request->param('perm_type', '');
        $data['permission_value'] = $this->request->param('perm_value', '');
        $data['parent'] = $this->request->param('parent', '');
        $data['leaf'] = '1';
        $role = model('Permission');
        $result = $role->save($data);
        if ($result){
            $this->success(__('Add button permisson successful'));
        } else {
            $this->error(__('Add button permisson failed'));
        }
    }

    /**
     * 更新权限
     */
    public function update(){
        $data['permission_name'] = $this->request->param('perm_name', '');
        $data['permission_type'] = $this->request->param('perm_type', '');
        $data['permission_value'] = $this->request->param('perm_value', '');
        $data['parent'] = $this->request->param('parent', '');
        $data['leaf'] = '1';

        $role = model('Permission');
        $result = $role->save($data, ['permission_value' =>  $data['permission_value']]);
        if ($result){
            $this->success(__('Update button permisson successful'));
        } else {
            $this->error(__('Update button permisson failed'));
        }
    }

    /**
     * 删除权限
     */
    public function delete(){
        $where['permission_value'] = $this->request->param('perm_value', '');
        $result = model('Permission')
            ->where($where)
            ->delete();
        if ($result){
            $this->success(__('Delete button permisson successful'));
        } else {
            $this->error(__('Delete button permisson failed'));
        }
    }

    /**
     * 整理权限列表
     * @param $data 原始数据
     * @param $permission_type 权限类型
     * @return array 整理后数据
     */
    private function parsePermMap($data, $permission_type){
        $result = [];
        foreach ($data as $v){
            if ($v['permission_type'] == $permission_type) {
                $result[] = array(
                    'parent' => $v['parent'],
                    'perm_name' => $v['permission_name'],
                    'perm_value' => $v['permission_value'],
                    'perm_type' => $v['permission_type'],
                );
            }
        }
        return $result;
    }


    /**
     * 整理按钮权限列表
     * @param $data 原始数据
     * @return array 整理后数据
     */
    private function parseBtnPermMap($data){
        $result = [];
        foreach ($data as $v){
            if ($v['permission_type'] == '2') {
                $result[$v['parent']][] = array(
                    'parent' => $v['parent'],
                    'perm_name' => $v['permission_name'],
                    'perm_value' => $v['permission_value'],
                    'perm_type' => $v['permission_type'],
                );
            }
        }
        return $result;
    }

    /**
     * 整理接口权限列表
     * @param $data 原始数据
     * @return array 整理后数据
     */
    private function parseApiPermMap($data, $root){
        $result = [];
        foreach ($data as $v){
            if ($v['permission_type'] == '3') {
                if (strcasecmp($v['parent'], $root) == 0){
                    $result[] = array(
                        'parent' => $v['parent'],
                        'perm_name' => $v['permission_name'],
                        'perm_value' => $v['permission_value'],
                        'perm_type' => $v['permission_type'],
                        'children' => $this->parseApiPermMap($data, $v['permission_value']),
                    );
                }
            }
        }
        return $result;
    }
}