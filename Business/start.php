<?php

use Workerman\Worker;
use Workerman\Crontab\Crontab;
use Workerman\Business\Wool\Grab;
use Workerman\Business\Wool\GrabNewest;
use Workerman\Business\Wool\GrabRank;
use Workerman\Library\Db;
use Workerman\Library\Log;
use Workerman\Config\Croner;
use Workerman\Config\Gateway;
use Workerman\Config\Database;
use Workerman\Config\GlobalData;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Library\GlobalDataClient;
use Workerman\Lib\Timer;
use Workerman\Business\Croner\Base as CronerBase;
use Workerman\Library\Http;

$worker = new Worker();
$worker->count = Croner::$worker_count;
$worker->name = Croner::$worker_name;

// 设置时区，避免运行结果与预期不一致
date_default_timezone_set('PRC');

$worker->onWorkerStart = function () use ($worker) {
    echo 'wait 1 s...'.date('Y-m-d H:i:s')."\n";
    // 延迟1秒，等待网关、共享数据组件等初始化完成
    sleep(1);
    echo 'start...'.date('Y-m-d H:i:s')."\n";
    $globaldata = new GlobalDataClient(GlobalData::$address . ':' . GlobalData::$port);
    $globaldata->add($className, array('interval'=>$interval, 'last_time'=>time(), 'pid'=>0));
    // 触发任务
    $croner = CronerBase::getInstance($className = 'Workerman\Business\Croner\TaskCroner');
    Timer::add(0.01, array($croner, 'trigger'), array(), false);
};