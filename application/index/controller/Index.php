<?php
namespace app\index\controller;

use app\common\controller\Base;
use think\Controller;

class Index extends Base {

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = ['login'];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = ['login', 'logout', 'info', 'pwd'];

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 登录
     */
    public function login() {
        $user_name = $this->request->param('u', '');
        $password = $this->request->param('p', '');

        if (!$user_name) {
            $this->error(__('Username can not be empty'));
        }
        if (!$password) {
            $this->error(__('Password can not be empty'));
        }
        $result = $this->auth->login($user_name, $password);
        if ($result) {
            $this->success(__('Logged in successful'), $this->auth->getTokenInfo());
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注销
     */
    public function logout(){
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 获取用户信息
     */
    public function info(){
        $user_info = $this->auth->getUserAllInfo();
        $this->success(__('Get user infomation successful'), $user_info);
    }

    /**
     * 修改密码
     */
    public function pwd(){
        $old_password = $this->request->param('old_password', '');
        $new_password = $this->request->param('new_password', '');
        $id = $this->auth->__get('id');
        //用户登录验证
        $user = model('User')
            ->where('id', $id)
            ->find();
        if (!empty($user)) {
            if ($user['status'] == 1) {
                $this->error(__('User is locked'));
            }
            if (strcasecmp($user['password'], $this->auth->getEncryptPassword($old_password, $user['salt'])) <> 0) {
                $this->error(__('Password is incorrect'));
            }
            $user->password = $this->auth->getEncryptPassword($new_password, $user['salt']);
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
            $this->error(__('Change password failed'));
        }
    }
}