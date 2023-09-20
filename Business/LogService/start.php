<?php

use Workerman\Worker;
use Workerman\Business\LogService\LogWorker;
use Workerman\Config\LogService as Config;

// $worker进程，这里使用Text协议，可以用telnet测试
$worker = new LogWorker(Config::$protocol.'://'.Config::$address.':'.Config::$port);
// $worker名称，status方便查看
$worker->name = Config::$worker_name;
// $worker进程数
$worker->count = Config::$worker_count;
// 本机ip，分布式部署时使用内网ip
$worker->lanIp = Config::$address;