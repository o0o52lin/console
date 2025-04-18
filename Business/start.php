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
use Workerman\Business\Business\Base as BusinessBase;
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
    // 触发任务
    // $croner = CronerBase::getInstance($className = 'Workerman\Business\Croner\TaskCroner');
    // Timer::add(0.01, array($croner, 'trigger'), array(), false);

    $croner = BusinessBase::getInstance($className = 'Workerman\Business\Business\Tasker');
    Timer::add(0.01, array($croner, 'trigger'), array(), false);

    // $gn = new GrabNewest();
    // new Crontab('*/6 * * * * *', function() use ($gn) {
    //     $data = $gn->run([
    //         'url'=>'http://new.xianbao.fun/plus/json/push.json',
    //         'name'=>'最新10条',
    //         'taskid'=>0,
    //         'type'=>0
    //     ]);
    //     echo date('Y-m-d H:i:s').' => 获取到'.count($data).'条数据，第一条ID：'.($data[0]['id'] ?? 0);
    // });
};