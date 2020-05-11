<?php

namespace app\common\library;

use app\index\model\User;
use fast\Random;
use think\Cache;
use think\Config;
use think\Db;
use think\Hook;
use think\Request;
use think\Validate;

class Auth {

    protected static $instance = null;
    protected $_error = '';
    protected $_logined = FALSE;
    protected $_user = NULL;
    protected $_token = '';
    //Token默认有效时长
    protected $keep_time = 2592000;
    protected $requestUri = '';
    protected $perms = [];
    //默认配置
    protected $config = [];
    protected $options = [];
    protected $allowFields = ['id', 'user_name', 'nick_name', 'remark', 'login_ip', 'login_time', 'prev_login_time'];

    public function __construct($options = []) {
        if ($config = Config::get('user')) {
            $this->options = array_merge($this->config, $config);
        }
        $this->options = array_merge($this->config, $options);
    }

    /**
     * 
     * @param array $options 参数
     * @return Auth
     */
    public static function instance($options = []) {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }
        return self::$instance;
    }

    /**
     * 获取User模型
     * @return User
     */
    public function getUser() {
        return $this->_user;
    }

    /**
     * 兼容调用user模型的属性
     * 
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        return $this->_user ? $this->_user->$name : NULL;
    }

    /**
     * 根据Token初始化
     * @param string       $token    Token
     * @return boolean
     */
    public function init($token) {
        if ($this->_logined) {
            return true;
        }
        if ($this->_error)
            return false;
        $user = Token::get($token);
        if (!$user) {
            $this->setError(__('You are not logged in'));
            return false;
        }
        $user_id = intval($user['id']);
        if ($user_id > 0) {
            $user = User::get($user_id);
            if (!$user) {
                $this->setError(__('User not exist'));
                return false;
            }
            if ($user['status'] != 0) {
                $this->setError(__('User is locked'));
                return false;
            }
            $this->_user = $user;
            $this->_token = $token;
            $this->_logined = true;

            //初始化成功的事件
            Hook::listen("user_init_successed", $this->_user);

            return true;
        } else {
            $this->setError(__('You are not logged in'));
            return false;
        }
    }

    /**
     * 用户登录
     * @param string    $user_name    账号
     * @param string    $password   密码
     * @return boolean
     */
    public function login($user_name, $password) {
        $user = User::get(['user_name' => $user_name]);
        if (!$user) {
            $this->setError(__('User not exist'));
            return false;
        }
        if ($user['status'] != 0) {
            $this->setError(__('User is locked'));
            return false;
        }
        if ($user->password != $this->getEncryptPassword($password, $user->salt)) {
            $this->setError(__('Password is incorrect'));
            return false;
        }
        $ip = request()->ip();
        $time = date('Y-m-d H:i:s');

        $user->prev_login_time = $user->login_time;
        //记录本次登录的IP和时间
        $user->login_ip = $ip;
        $user->login_time = $time;

        $user->save();
        
        $this->_user = $user;
        $this->_token = Token::create();
        Token::set($this->_token, $user->id, $this->keep_time);

        $this->_logined = true;

        //登录成功的事件
        Hook::listen("user_login_successed", $this->_user);

        return true;
    }

    /**
     * 注销
     * 
     * @return boolean
     */
    public function logout() {
        if (!$this->_logined) {
            $this->setError(__('You are not logged in'));
            return false;
        }
        //设置登录标识
        $this->_logined = false;
        //删除Token
        Token::delete($this->_token);
        //注销成功的事件
        Hook::listen("user_logout_successed", $this->_user);
        return true;
    }

    /**
     * 检测是否是否有对应权限
     * @param string $path      控制器/方法
     * @param string $module    模块 默认为当前模块
     * @return boolean
     */
    public function check($path = NULL, $module = NULL) {
        if (!$this->_logined)
            return false;

        $perms = $this->getPermList();
        if (in_array('*', $perms)) {
            return true;
        }
        $url = 'a:' . (is_null($path) ? $this->getRequestUri() : $path);
        $url = str_replace('/', ':', $url);
        foreach ($perms as $v) {
            $result = stripos($url, $v);
            if ($result !== false && $result >= 0){
                return true;
            }
        }
        return false;
    }

    /**
     * 判断是否登录
     * @return boolean
     */
    public function isLogin(){
        if ($this->_logined) {
            return true;
        }
        return false;
    }

    /**
     * 获取当前Token信息
     * @return array
     */
    public function getTokenInfo(){
        return ['token' => $this->_token, 'expire' => $this->keep_time];
    }

    /**
     * 获取用户基本信息
     * @return array
     */
    public function getUserInfo(){
        $data = $this->_user->toArray();
        $allowFields = $this->getAllowFields();
        $user_info = array_intersect_key($data, array_flip($allowFields));
        $user_info = array_merge($user_info, Token::get($this->_token));
        return $user_info;
    }

    /**
     * 获取用户所有信息
     * @return array
     */
    public function getUserAllInfo(){
        $user_info = $this->getUserInfo();
        $result = model('User')
            ->with('roles')
            ->where('id', $user_info['id'])
            ->find();
        //复制用户信息
        list($result, $api_permissions) = $this->parseUserInfo($result);
        //设置用户权限
        $this->setPermList($api_permissions);
        $user_info = array_merge($user_info, $result);
        return $user_info;
    }

    /**
     * 获取用户的权限列表
     * @return array
     */
    public function getPermList(){
        if ($this->perms)
            return $this->perms;

        $this->perms = Cache::get('up:' . $this->_user['id']);
        if ($this->perms)
            return $this->perms;

        $this->getUserAllInfo();

        $this->perms = Cache::get('up:' . $this->_user['id']);
        return $this->perms;
    }

    /**
     * 设置用户的权限列表
     * @param $data
     */
    public  function setPermList($data){
        Cache::set('up:' . $this->_user['id'] , $data);
    }

    /**
     * 获取当前请求的URI
     * @return string
     */
    public function getRequestUri(){
        return $this->requestUri;
    }

    /**
     * 设置当前请求的URI
     * @param string $uri
     */
    public function setRequestUri($uri){
        $this->requestUri = $uri;
    }

    /**
     * 获取允许输出的字段
     * @return array
     */
    public function getAllowFields(){
        return $this->allowFields;
    }

    /**
     * 设置允许输出的字段
     * @param array $fields
     */
    public function setAllowFields($fields){
        $this->allowFields = $fields;
    }

    /**
     * 获取密码加密后的字符串
     * @param string $password  密码
     * @param string $salt      密码盐
     * @return string
     */
    public function getEncryptPassword($password, $salt = ''){
        return md5(md5($password) . $salt);
    }

    /**
     * 检测当前控制器和方法是否匹配传递的数组
     *
     * @param array $arr 需要验证权限的数组
     * @return boolean
     */
    public function match($arr = []){
        $request = Request::instance();
        $arr = is_array($arr) ? $arr : explode(',', $arr);
        if (!$arr) {
            return false;
        }
        $arr = array_map('strtolower', $arr);
        // 是否存在
        if (in_array(strtolower($request->action()), $arr) || in_array('*', $arr)) {
            return true;
        }

        // 没找到匹配
        return false;
    }

    /**
     * 设置会话有效时间
     * @param int $keep_time 默认为永久
     */
    public function setKeepTime($keep_time = 0){
        $this->keep_time = $keep_time;
    }

    /**
     * 设置错误信息
     *
     * @param $error 错误信息
     * @return Auth
     */
    public function setError($error){
        $this->_error = $error;
        return $this;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError(){
        return $this->_error ? __($this->_error) : '';
    }

    /**
     * 整理用户信息
     * @param $data
     * @return mixed
     */
    private function parseUserInfo($data){
        if (empty($data)){
            return $data;
        }
        $new_roles = [];
        $new_permissions = [];
        $api_permissions = [];
        $roles = $data['roles'];
        if (!empty($roles)){
            //整理角色信息
            $count = count($roles);
            $role_ids = [];
            for($i = 0; $i < $count; $i++){
                $temp = [];
                $temp['role_name'] = $roles[$i]['role_name'];
                $temp['role_value'] = $roles[$i]['role_value'];
                $new_roles[] = $temp;
                $role_ids[] = $roles[$i]['id'];
            }
            //整理权限信息
            $role_details = model('Role')
                ->with('permissions')
                ->where('id','in', $role_ids)
                ->find();
            if (!empty($role_details)){
                $permissions = $role_details['permissions'];
                $count = count($permissions);
                for($i = 0; $i < $count; $i++){
                    $temp = [];
                    $temp['perm_name'] = $permissions[$i]['permission_name'];
                    $temp['perm_value'] = $permissions[$i]['permission_value'];
                    $new_permissions[] = $temp;
                    if ($permissions[$i]['permission_type'] == '3') {
                        $api_permissions[] = $permissions[$i]['permission_value'];
                    }
                    if ($permissions[$i]['permission_value'] == "*") {
                        $api_permissions[] = $permissions[$i]['permission_value'];
                    }
                }
            }
        }
        $result['roles'] = $new_roles;
        $result['perms'] = $new_permissions;
        return [$result, $api_permissions];
    }
}
