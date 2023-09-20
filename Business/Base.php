<?php

namespace Workerman\Business;

use Workerman\Library\DbConnection;
use Workerman\Library\Db;
use Workerman\Library\Log;
use Workerman\Config\Database;
use Workerman\Config\Gateway;
use Workerman\Connection\AsyncTcpConnection;

/**
 * 业务处理基类
 * @author Minch<yeah@minch.me>
 * @since 2015-01-27
 */
abstract class Base
{
    /**
     * 业务处理实例
     * @var array
     */
    protected static $instance = array();
    
    /**
     * 数据库连接
     * @var DbConnection
     */
    protected $db;
    
    /**
     * 网关地址
     * @var string
     */
    protected $gateway_address = '';
    
    /**
     * 网关签名
     * @var string
     */
    protected $gateway_sign = '';
    
    /**
     * 网关链接
     * @var AsyncTcpConnection
     */
    protected $gateway_conn = null;
    
    /**
     * 错误代码
     * @var number
     */
    protected $errCode = 0;
    
    /**
     * 错误信息
     * @var string
     */
    protected $errMsg = '';
    
    /**
     * 异步请求 array(call_id=>array(callback,params))
     * @var array
     */
    protected static $async_calling = array();
    
    /**
     * 变量共享组件
     * @var GlobalDataClient
     */
    protected $globaldata = null;
    
    /**
     * 业务定时器表(通用)
     * @var tasktimerTable
     */
    protected $tasktimerTable = 'zbp_tasktimer';
    
    /**
     * reRunTaskCallback
     * @var callable
     */
    protected $reRunTaskCallback = null;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->db = Db::instance(Database::$master);
        $this->gateway_address = 'tcp://'.Gateway::$address.':'.Gateway::$port;
    }

    abstract public function run($params);
    
    /**
     * 数据库连接
     * @param $conf string \Config\Database属性名
     * @return \Library\DbConnection
     */
    protected function db($conf = 'default')
    {
        if (!isset(Database::${$conf})) {
            return false;
        }
        return Db::instance(Database::${$conf});
    }

    /**
     * 获取业务处理器实例
     * @param string $name
     * @return multitype:
     */
    public static function getInstance($name)
    {
        if(!isset(self::$instance[$name]) OR !self::$instance[$name]){
            self::$instance[$name] = new $name();
        }
        return self::$instance[$name];
    }

    /**
     * 业务处理器之间相互调用（同步）
     * @param string $class 类名
     * @param string $method 方法名
     * @param array $params 参数
     * @return boolean
     */
    protected static function call($class, $method, $params)
    {
        $res = call_user_func(array(self::getInstance($class),$method), $params);
        unset($class, $method, $params);
        return $res;
    }

    /**
     * 异步请求业务处理
     * @param string $class 业务类名(带命名空间，如：Webservice\User\GetMoney)
     * @param string $method 业务方法名
     * @param array $params 请求参数
     * @param mix $callback 成功后回调函数
     */
    protected function asyncCall($class, $params, $callback = null)
    {
        $sign = md5($class.'run'.json_encode($params).Gateway::$client_sign['business']);
        // 请求业务处理参数
        $dataString = json_encode(array('class'=>$class, 'method'=>'run','params'=>$params,'client'=>'business','sign'=>$sign ));
        
        // 判断业务是否正在处理中
        $call_id = md5($dataString);
        if(array_key_exists($call_id, self::$async_calling)){
            return false;
        }
        if( $callback != null ){
            // 添加到业务处理列表
            self::$async_calling[$call_id] = array('callback'=>$callback, 'params'=>$params);
        }
        
        // 建立异步链接
        if(!$this->gateway_conn){
            // 建立异步链接
            $this->gateway_conn = new AsyncTcpConnection($this->gateway_address);
            $this->gateway_conn->connect();
        }
        // 发送数据
        $this->gateway_conn->send($dataString . "\n");
        // 异步获得结果
        $this->gateway_conn->onMessage = function ($conn, $result)
        {
            // 处理结果
            $res = explode("\n", trim($result));
            foreach ($res as $re){
                $val = json_decode($re, true);
                if (!isset($val['call_id']) || !isset(self::$async_calling[$val['call_id']])) continue;
                call_user_func(self::$async_calling[$val['call_id']]['callback'], self::$async_calling[$val['call_id']]['params'], $val);
                // 解除正在进行中的任务
                unset(self::$async_calling[$val['call_id']], $val);
            }
            // 解除正在进行中的任务
            unset($result,$res);
        };
        unset($dataString, $service, $params, $call_id, $conn, $callback);
        return true;
    }

    /**
     * 获取下次触发时间间隔
     * @param number $error_num 错误次数
     * @return number
     */
    protected function getNextTriggerTime($error_num = 0)
    {
        $maps = array('0'=>30,'1'=>180,'2'=>600,'3'=>1800);
        return isset($maps[$error_num]) ? $maps[$error_num] : 0;
    }

    /**
     * 记录日志
     * @param string $msg
     * @return void
     */
    protected function log($msg = '')
    {
        return Log::add($msg);
    }

    /**
     * 设置错误信息
     * @param number $errCode 错误编码(1000:数据不存在或状态异常,2000:数据更新失败)
     * @param string $errMsg 错误信息
     * @return boolean false
     */
    protected function error($errCode = 0, $errMsg = '')
    {
        $this->errCode = $errCode;
        $this->errMsg = $errMsg;
        Log::add('ERROR[' . $errCode . '] ' . $errMsg);
        return false;
    }

    /**
     * 获取错误代码
     * @return number
     */
    public function getErrorCode()
    {
        return $this->errCode;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errMsg;
    }
    
    /**
     * 增加业务处理任务
     * @param string $type   业务类型
     * @param array $params  参数
     * @param int $time   执行时间(时间戳)
     * @param number $is_open 是否开启
     * @param number $step   步骤
     * @return boolean
     */
    protected function addTaskTimer($type, $params, $time = 0, $is_open = 1, $step = 0)
    {
        if (!is_string($type) || empty($type)) {
            return false;
        }
        if(!is_array($params)){
            return false;
        }
        ksort($params);
        $trigger_time = intval($time);
        $now = time();
        $data = array();
        $data['type'] = trim(strval($type));
        $data['params'] = json_encode($params);
        $data['uuid'] = md5($data['type'].$data['params']);
        $data['trigger_time'] = $trigger_time < $now ? $now : $trigger_time;
        $data['is_open'] = intval($is_open);
        $data['is_run'] = 0;
        $data['step'] = intval($step);
        
        $task = $this->db->select('id')->from('zbp_tasktimer')
                    ->where('`type`=\''.addslashes($data['type']).'\' AND `uuid`="'.$data['uuid'].'"')
                    ->row();
        if(isset($task['id']) && $task['id'] > 0){
            // 已经存在相同的任务,确保任务开启
            $this->db->update('zbp_tasktimer')
                    ->set('is_open', 1)
                    ->where('`id`='.$task['id'].' AND `uuid`="'.$data['uuid'].'"')
                    ->query();
            return true;
        }
        $rs = $this->db->insert('zbp_tasktimer')->cols($data)->query();
        if ($rs) {
            return true;
        }
        return false;
    }
    
    /**
     * 删除业务处理任务
     * @param int $taskid 任务ID
     * @return boolean
     */
    protected function delTaskTimer($taskid)
    {
        $taskid = intval($taskid);
        if( $taskid <= 0 ){
            return false;
        }
        
        $rs = $this->db->delete($this->tasktimerTable)->where('`id`='.$taskid)->query();
        if($rs){
            return true;
        }
        return false;
    }
    
    /**
     * 检测定时任务数据是否存在和执行锁
     * @param int $taskid
     * @param int $params
     * @return boolean
     */
    protected function checkTaskTimer($taskid, $params = null)
    {
        //处理的业务名称，父类类名
        $type = get_called_class();
        $param_str = str_replace([' => ', ' ( '], ['=>', '('], preg_replace('/\s+/', ' ', var_export($params, true)));
        $this->log('开始处理'. $this->tasktimerTable . '业务:' . $type . ',任务编号:' . $taskid . ',参数：' . $param_str);
        $now = time();
        // 查询 任务
        $timer = $this->db->select('id,trigger_time,err_num')
                        ->from($this->tasktimerTable)
                        ->where('id', $taskid)
                        ->where('type="' . addslashes($type). '"')
                        ->where('is_open', 1)
                        ->where('is_run', 0)
                        ->row();
        if(!is_array($timer) or empty($timer)){
            unset($timer);
            return $this->error(1000, $this->tasktimerTable . '定时任务(' . $taskid . ')不存在或已完成|关闭');
        }
        if($timer['trigger_time'] > $now){
            unset($timer);
            return $this->error(1000, $this->tasktimerTable . '定时任务(' . $taskid . ')未到执行时间');
        }
        // 获取执行锁
        $lock = $this->db->update($this->tasktimerTable)
                        ->set('is_run',1)
                        ->where('id', $taskid)
                        ->where('type="' . addslashes($type). '"')
                        ->where('is_open', 1)
                        ->where('is_run', 0)
                        ->query();
        if($lock !== 1){
            return $this->error(1000, $this->tasktimerTable . '定时任务(' . $taskid . ')获取执行锁失败' . $this->db->lastSQL() . $this->db->getError());
        }
        return true;
    }
    
    /**
     * 设置下次重新执行时间
     * @param int $taskid 任务ID
     * @param int $trigger_time 触发时间
     * @param int $step 步骤|进度
     * @param int $callable 回调函数
     * @return boolean
     */
    protected function reRunTaskTimer($taskid, $trigger_time = 0, $step = 0, $callable = null)
    {
        //处理的业务名称，父类类名
        $type = get_called_class();
        $trigger_time = intval($trigger_time);
        
        //默认自动设置下次执行之间，执行5次错误则停止任务
        if( $trigger_time == 0 )
        {
            $timer = $this->db->select('id,trigger_time,err_num')
                            ->from($this->tasktimerTable)
                            ->where('id', $taskid)
                            ->where('type="' . addslashes($type). '"')
                            ->where('is_open', 1)
                            ->where('is_run', 1)
                            ->row();
            
            $ret = $this->db->update($this->tasktimerTable)
                            ->set('is_run', 0)
                            ->set('err_num', $timer['err_num'] + 1)
                            ->set('trigger_time', time() + $this->getNextTriggerTime($timer['err_num']))
                            ->where('id', $taskid)
                            ->where('type="' . addslashes($type). '"')
                            ->where('is_open', 1)
                            ->where('is_run', 1)
                            ->query();

            is_callable($callable) ? $callable($taskid, $timer['err_num'] + 1) : (is_callable($this->reRunTaskCallback) && $this->reRunTaskCallback($taskid, $timer['err_num'] + 1));
            return $ret;
        }
        
        //手动设置下次执行时间和进度
        return $this->db->update($this->tasktimerTable)
                        ->set('is_run', 0)
                        ->set('err_num', 0)
                        ->set('trigger_time', $trigger_time)
                        ->set('step', $step)
                        ->where('id', $taskid)
                        ->where('type="' . addslashes($type). '"')
                        ->where('is_open', 1)
                        ->where('is_run', 1)
                        ->query();
    }
}