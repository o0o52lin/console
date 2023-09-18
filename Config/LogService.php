<?php
namespace Config;

/**
 * 众划算日志业务相关配置
 */
class LogService
{
    /**
     * 进程名称，status方便查看
     * @var string
     */
    public static $worker_name = 'LogWorker';
    
    /**
     * 进程数
     * @var string
     */
    public static $worker_count = 1;
    
    /**
     * 网关协议
     * @var string
     */
    public static $protocol = 'Text';
    
    /**
     * 进程监听的ip地址，分布式部署时使用内网ip
     * @var string
     */
    public static $address = '127.0.0.1';
    
    /**
     * 进程监听端口
     * @var number
     */
    public static $port = 56789;
    
    /**
     * 数据存储路径
     * @var string
     */
    public static $dataPath = ROOT_DIR.'/data/logs/';
	
}