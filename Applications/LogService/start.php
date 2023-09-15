<?php

use \Workerman\Worker;

// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';
require_once __DIR__ . '/LogWorker.php';

// $worker进程，这里使用Text协议，可以用telnet测试
$worker = new LogWorker(Config\LogService::$protocol.'://'.Config\LogService::$address.':'.Config\LogService::$port);
// $worker名称，status方便查看
$worker->name = Config\LogService::$worker_name;
// $worker进程数
$worker->count = Config\LogService::$worker_count;
// 本机ip，分布式部署时使用内网ip
$worker->lanIp = Config\LogService::$address;

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
