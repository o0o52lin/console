<?php

use Workerman\Worker;
use Workerman\Lib\Timer;
use Config\LogService as Config;

/**
 * 日志收集业务，方便查找和排除故障
 * @author minch <yeah@minch.me>
 */
class LogWorker extends Worker
{
    /**
     *  最大日志buffer，大于这个值就写磁盘
     * @var integer
     */
    const MAX_LOG_BUFFER_SIZE = 1024000;
    
    /**
     * 多长时间写一次数据到磁盘(秒)
     * @var integer
     */
    const WRITE_PERIOD_LENGTH = 60;
    
    /**
     * 多长时间清理一次老的磁盘数据(秒)
     * @var integer
     */
    const CLEAR_PERIOD_LENGTH = 86400;
    
    /**
     * 数据多长时间过期(秒)
     * @var integer
     */
    const EXPIRED_TIME = 2592000;
    
    /**
     * 日志的buffer
     * @var array
     */
    protected $logBuffer = array();
    
    /**
     * 存放统计日志的目录
     * @var string
     */
    protected $logDir = '';
    
    /**
     * 构造函数
     * @param unknown $socket_name
     */
    public function __construct($socket_name)
    {
        parent::__construct($socket_name);
        $this->onWorkerStart = array($this, 'onStart');
        $this->onMessage = array($this, 'onMessage');
        $this->onWorkerStop = array($this, 'onStop');
    }
    
    /**
     * 业务处理
     * @see Man\Core.SocketWorker::dealProcess()
     */
    public function onMessage($connection, $bin_data)
    {
        $data = json_decode($bin_data, true);
        // 解码
        $site = $data['site'] ? $data['site'] : 'no-site';
        $cost_time = $data['cost_time'];
        $time = $data['time'];
        $msg = $data['msg'];
        $ip = $connection->getRemoteIp();
        // 记录日志
        if (!isset($this->logBuffer[$site])) {
            $this->logBuffer[$site] = '';
        }
        $this->logBuffer[$site] .= "server_name:".$site."\t server_ip:".$ip."\t cost_time:".$cost_time."\n".$msg."\n";
        if(strlen($this->logBuffer[$site]) >= self::MAX_LOG_BUFFER_SIZE){
            $this->saveSiteLog($site);
        }
    }
    
    /**
     * 将所有日志数据写入磁盘
     * @return void
     */
    public function saveLog()
    {
        foreach ($this->logBuffer as $site=>$log){
            // 没有统计数据则返回
            if(empty($log)){
                continue;
            }
            unset($log);
            $this->saveSiteLog($site);
        }
    }
    
    /**
     * 将指定站点日志写入磁盘
     * @param string $site 站点
     * @return void
     */
    protected function saveSiteLog($site)
    {
        // 创建日志目录
        umask(0);
        $log_dir = $this->getLogDir() . $site;
        if(!is_dir($log_dir)){
            mkdir($log_dir, 0777, true);
        }
        // 写入磁盘
        file_put_contents($log_dir .'/'. date('Y-m-d'), $this->logBuffer[$site], FILE_APPEND | LOCK_EX);
        $this->logBuffer[$site] = '';
    }
    
    /**
     * 获取日志保存路径
     * @return string
     */
    protected function getLogDir()
    {
        $this->logDir = Config::$dataPath;
        if('/' != substr($this->logDir, -1)){
            $this->logDir .= '/';
        }
        return $this->logDir;
    }
    
    /**
     * 初始化
     * 统计目录检查
     * 初始化任务
     * @see Man\Core.SocketWorker::onStart()
     */
    protected function onStart()
    {
        // 初始化目录
        umask(0);
        $log_dir = $this->getLogDir();
        if(!is_dir($log_dir)){
            mkdir($log_dir, 0777, true);
        }
        // 定时保存数据
        Timer::add(self::WRITE_PERIOD_LENGTH, array($this, 'saveLog'));
        // 定时清理不用的数据
        Timer::add(self::CLEAR_PERIOD_LENGTH, array($this, 'clearExpried'), array($this->logDir, self::EXPIRED_TIME));
    }
    
    /**
     * 进程停止时需要将数据写入磁盘
     */
    protected function onStop()
    {
        $this->saveLog();
    }
    
    /**
     * 清除过期数据
     * @param string $file
     * @param int $exp_time
     */
    public function clearExpried($file = null, $exp_time = 86400)
    {
        $time_now = time();
        if(is_file($file)){
            $mtime = filemtime($file);
            if(!$mtime){
                $this->notice("filemtime $file fail");
                return;
            }
            if($time_now - $mtime > $exp_time){
                unlink($file);
            }
            return;
        }
        foreach (glob($file."/*") as $file_name){
            $this->clearExpried($file_name, $exp_time);
        }
    }
}
