<?php

namespace Workerman\Business\Business;

use Workerman\Library\DbConnection;
use Workerman\Library\Db;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Library\Log;
use Workerman\Library\GlobalDataClient;
use Workerman\Config\Database;
use Workerman\Config\Gateway;
use Workerman\Config\Business;
use Workerman\Config\GlobalData;
use Workerman\Lib\Timer;

/**
 * 定时器基类
 */
abstract class Base
{
    /**
     * 定时器实例
     * @var array
     */
    protected static $instance = array();
    
    /**
     * 异步连接实例
     * @var \Workerman\Connection\AsyncTcpConnection
     */
    protected $conn = null;
    
    /**
     * 数据库连接
     * @var \Library\DbConnection
     */
    protected $db;
    
    /**
     * 网关地址
     * @var array
     */
    protected $gateway = array();
    
    /**
     * 正在进行的业务处理
     * @var array
     */
    public static $calling = array();
    
    /**
     * 网关签名密钥
     * @var string
     */
    protected $sign;
    
    /**
     * 变量共享组件
     * @var GlobalDataClient
     */
    protected $globaldata = null;

    /**
     * 构造函数
     */
    protected function __construct()
    {
        $this->db = Db::instance(Database::$slave);
        // 链接业务网关
        $this->sign = Business::$gateway_sign;
        $this->gateway = 'tcp://' . Gateway::$address . ':' . Gateway::$port;
        $this->conn = new AsyncTcpConnection($this->gateway);
        $this->conn->connect();
        // 共享组件初始化
        $this->globaldata = new GlobalDataClient(GlobalData::$address . ':' . GlobalData::$port);
    }

    /**
     * 获取定时器实例
     * @param string $name
     * @param TimerWorker $worker
     * @return multitype:
     */
    public static function getInstance($name)
    {
        if(!isset(self::$instance[$name]) or !self::$instance[$name]){
            self::$instance[$name] = new $name();
        }
        return self::$instance[$name];
    }

    /**
     * 请求业务处理
     * @param string $business 业务名,如果 Order\Timeout
     * @param array $params 参数
     * @return boolean
     */
    protected function call($class, $params)
    {
        $now = time();
        // 请求业务处理参数
        $dataString = json_encode(array('class'=>$class,'method'=>'run','params'=>$params,'client'=>'business','sign'=>md5($class . 'run' . json_encode($params) . $this->sign)));
        // 判断业务是否正在处理中
        $call_id = md5($dataString);
        // if(!$this->globaldata->add($call_id, $now)){
        //     $last_time = $this->globaldata->$call_id;
        //     // 判断是否超时(5分钟)
        //     if(($now - $last_time) < 300){ // 未超时当作重复请求处理
        //         $this->log('ERROR 重复请求业务处理 '.$dataString);
        //         return false;
        //     }
        //     $this->log('WARNING 执行业务超时,重新请求业务处理 '.$dataString);
        // }
        
        if(!$this->conn){
            // 建立异步链接
            $this->conn = new AsyncTcpConnection($this->gateway);
            $this->conn->connect();
        }
        // 发送数据
        $this->conn->send($dataString . "\n");
        // 异步获得结果
        $this->conn->onMessage = function ($conn, $result)
        {
            // 处理结果
            $res = explode("\n", trim($result));
            foreach($res as $re){
                print_r($re, true);
                // $this->log('返回内容 '.print_r($re, true));
                // $val = json_decode($re, true);
                // $call_id = $val['call_id'];
                // // 解除正在进行中的任务
                // unset($this->globaldata->$call_id);
            }
        };
    }

    /**
     * 异步请求业务处理
     * @param string $class 业务类名(带命名空间，如：Webservice\User\GetMoney)
     * @param array $params 请求参数
     * @param mix $callback 成功后回调函数
     */
    protected function asyncCall($class, $params, $callback = null)
    {
        $now = time();
        // 请求业务处理参数
        $dataString = json_encode(array('class'=>$class,'method'=>'run','params'=>$params,'client'=>'croner','sign'=>md5($class . 'run' . json_encode($params) . $this->sign)));
        
        // 判断业务是否正在处理中
        $call_id = md5($dataString);
        if(!$this->globaldata->add($call_id, $now)){
            $last_time = $this->globaldata->$call_id;
            // 判断是否超时(5分钟)
            if(($now - $last_time) < 300){ // 未超时当作重复请求处理
                //$this->log('ERROR 重复请求业务处理 '.$dataString);
                return false;
            }
            $this->log('WARNING 执行业务超时,重新请求业务处理 '.$dataString);
        }
        
        // 建立异步链接
        if(!$this->conn){
            // 建立异步链接
            $this->conn = new AsyncTcpConnection($this->gateway);
            $this->conn->connect();
        }
        // 发送数据
        $this->conn->send($dataString . "\n");
        // 异步获得结果
        $this->conn->onMessage = function ($conn, $result)
        {
            // 处理结果
            $res = explode("\n", trim($result));
            foreach($res as $re){
                // $this->log('返回内容 '.print_r($re, true));
                $val = json_decode($re, true);
                $call_id = $val['call_id'];
                // 解除正在进行中的任务
                unset($this->globaldata->$call_id);
            }
        };
        unset($dataString, $params, $call_id, $callback);
        return true;
    }

    /**
     * 数据库连接
     * @param $conf string Database属性名
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
     * 记录日志
     * @param string $msg
     * @return void
     */
    protected function log($msg = '')
    {
        return Log::add($msg);
    }

    /**
     * 业务触发器，所有定时业务都需要实现此方法
     */
    abstract public function trigger();

    /**
     * 抢资源锁
     * @return boolean
     */
    protected function getlock()
    {
        $now = time();
        $gkey = get_class($this);
        $gdata = $ndata = $this->globaldata->$gkey;
        $pid = posix_getpid();
        $nexttime = isset($gdata['interval']) && $gdata['last_time'] ? $gdata['interval'] + $gdata['last_time'] : $now;
        if(isset($gdata['end_time']) && $gdata['end_time'] > 0 && $nexttime > $gdata['end_time']){
            $nexttime =  $gdata['end_time'];
        }
        
        // echo 'getlock_' . $pid . '_' . $nexttime . ':' . $now . '_' . $gkey . PHP_EOL;
        // 未到执行时间
        if($now < $nexttime){
            $this->wait($nexttime - $now);
            return false;
        }
        // 执行超时(5分钟),释放资源
        if(($now - $nexttime) > 300){
            $this->log('定时触发任务超时释放资源 '.$gkey);
            $ndata['pid'] = 0;
            $ndata['last_time'] = $now;
            $this->globaldata->$gkey = $ndata;
            $this->wait();
            return false;
        }
        if($gdata['pid'] != 0 || $gdata['pid'] == $pid){
            $this->wait();
            return false;
        }
        if(isset($gdata['once']) && $gdata['once'] && $gdata['last_time']>0){
            return false;
        }
        $ndata['last_time'] = $now;
        $ndata['pid'] = $pid;

        print_r([$gkey, $gdata, $ndata]);
        // 抢资源锁
        if(!$this->globaldata->cas($gkey, $gdata, $ndata)){
            $this->wait();
            return false;
        }
        return true;
    }

    /**
     * 释放资源
     * @return boolean
     */
    protected function unlock()
    {
        $key = get_class($this);
        $gdata = $ndata = $this->globaldata->$key;
        if($gdata['pid'] == 0){
            return true;
        }
        // 只能本进程解锁
        $pid = posix_getpid();
        if($pid != $gdata['pid']){
            return false;
        }
        $ndata['last_time'] = time();
        $ndata['pid'] = 0;
        // 释放资源锁
        if(!$this->globaldata->cas($key, $gdata, $ndata)){
            return false;
        }
    }

    /**
     * 等待资源释放
     */
    protected function wait($second = 0)
    {
        $continue = true;
        if($second>0){
            $interval = $second;
        }else{
            $now = time();
            $key = get_class($this);
            $gdata = $this->globaldata->$key;
            $interval = isset($gdata['interval']) ? $gdata['interval'] : 0;
            if(isset($gdata['end_time']) && $gdata['end_time'] != 0){
                if($gdata['end_time'] < $now){
                    $continue = false;
                }elseif (($now + $interval) > $gdata['end_time']){
                    $interval = $gdata['end_time'] - $now;
                }
            }
            if(isset($gdata['once']) && $gdata['once'] && $gdata['last_time'] > 0){
                $continue = false;
            }
            unset($key, $gdata);
        }
        if($continue && $interval > 0){
            Timer::add($interval, array(self::getInstance(get_class($this)),'trigger'), array(), false);
        }
        unset($second, $interval);
    }
}