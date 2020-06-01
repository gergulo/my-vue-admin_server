<?php
namespace app\index\controller;

use app\common\controller\Base;
use think\Controller;
use think\Db;
use think\Config;
use fast\Random;

class User extends Base {

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = [];

    /**
     * 查询用户列表
     */
    public function query(){
        $txt = $this->request->param('txt', '');
		$status = intval($this->request->param('status', '-1'));
        $current = intval($this->request->param('current', '1'));
        $size = intval($this->request->param('size', '10'));

        $where = function ($query) use ($txt, $status) {
            if (!empty($txt)) {
                $query->where('user_name|nick_name', 'LIKE', "%{$txt}%");
            }
			if ($status != -1) {
				$query->where('status', $status);
			}
        };
        $data =[];
        $model = model('User');
        $total = $model
            ->where($where)
            ->count();
        if ($total > 0) {
            $data = $model
                ->with('roles')
                ->where($where)
                ->field('id, user_name, nick_name, remark, status, create_time, update_time')
                ->limit(($current - 1) * $size, $size)
                ->select();
            $data = $this->parseUserInfoList($data);
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
     * 创建用户
     */
    public function add(){
        $data['user_name'] = $this->request->param('user_name', '');
        $data['nick_name'] = $this->request->param('nick_name', '');
        $data['remark'] = $this->request->param('remark', '');
        $data['status'] = intval($this->request->param('status', 0));
        $data['salt'] = Random::alnum();
        $default_password = Config::get('site.default_password') ? : '12345678';
        $data['password'] = $this->auth->getEncryptPassword($default_password, $data['salt']);
        
        $user = model('User');
        $result = $user->save($data);
        if ($result){
            $result = array(
                'id' => $user['id'],
                'create_time' => $user['create_time'],
                'update_time' => $user['update_time'],
            );
            $this->success(__('Save successful'), $result);
        } else {
            $this->error(__('Save failed'));
        }
    }

    /**
     * 更新用户
     */
    public function update(){
        $id = $this->request->param('id', '');
        $data['user_name'] = $this->request->param('user_name', '');
        $data['nick_name'] = $this->request->param('nick_name', '');
        $data['remark'] = $this->request->param('remark', '');
        $data['status'] = intval($this->request->param('status', 0));

        $user = model('User');
        $result = $user->save($data, ['id' =>  $id]);
        if ($result){
            $result = array(
                'update_time' => $user['update_time'],
            );
            $this->success(__('Save successful'), $result);
        } else {
            $this->error(__('Save failed'));
        }
    }

    /**
     * 删除用户
     */
    public function delete(){
        $where['id'] = $this->request->param('id', '');
        $result = false;
        // 启动事务
        Db::startTrans();
        try{
            model('User')
                ->where($where)
                ->delete();
            model('UserRole')
                ->where('user_id', $where['id'])
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
     * 修改密码
     */
    public function pwd(){
        $id = $this->request->param('id', '');
        $new_password = $this->request->param('new_password', '');
        if (empty($id)) {
            $id = $this->auth->__get('id');
        }
        //用户登录验证
        $user = model('User')
            ->where('id', $id)
            ->find();
        if (!empty($user)) {
            if ($user['status'] == 1) {
                $this->error(__('User is locked'));
            }
            $new_password = $this->auth->getEncryptPassword($new_password, $user['salt']);
            if ($new_password != $user->password) {
                $user->password = $new_password;
                $result = $user->save();
                if ($result){
                    $result = array(
                        'update_time' => $user['update_time'],
                    );
                    $this->success(__('Change password successful'), $result);
                } else {
                    $this->error(__('User not exist'));
                }
            } else {
                $this->error(__('New and old passwords can\'t be the same'));
            }
        } else {
            $this->error(__('Change password failed'));
        }
    }

    /**
     * 修改用户角色
     */
    public function role(){
        $id = $this->request->param('user_id', '');
        $role_ids = $this->request->param('role_ids/a', []);

        $list = [];
        foreach ($role_ids as $v){
            $list[] = array(
                'user_id' => $id,
                'role_id' => $v
            );
        }
        $result = false;
        // 启动事务
        Db::startTrans();
        try{
            $model = model('UserRole');
            $model->where('user_id', $id)
                ->delete();
            $model->saveAll($list, false);
            $result = true;
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
        if ($result){
            $this->success(__('Save role successful'));
        } else {
            $this->error(__('Save role failed'));
        }
    }
    
    /**
     * 整理用户列表
     * @param $data 原始数据
     * @return array 整理后数据
     */
    private function parseUserInfoList($data){
        foreach ($data as $k=>$v){
            $roles = [];
            foreach ($v['roles'] as $v2){
                $roles[] =array(
                    'id' => $v2['id'],
                    'role_name' => $v2['role_name'],
                    'role_value' => $v2['role_value'],
                );
            }
            $v['roleList'] = $roles;
            unset($v['roles']);
        }
        return $data;
    }
}