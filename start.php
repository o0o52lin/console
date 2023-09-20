<?php

// 标记是全局启动
define('GLOBAL_START', 1);
define('ROOT_DIR', __DIR__);

require ROOT_DIR . '/Autoloader.php';

use Workerman\Worker;
use Workerman\Crontab\Crontab;

ini_set('display_errors', 'on');

$start_files = array_merge(glob(ROOT_DIR.'/Business/*/start*.php'), glob(ROOT_DIR.'/Business/start*.php'));;

// 加载所有Applications/*/start.php，以便启动所有服务
foreach($start_files as $start_file)
{
    require_once $start_file;
}

Worker::runAll();