<?php

use Workerman\Worker;
use Workerman\Business\GlobalData\Server;
use Workerman\Config\GlobalData;

// 监听端口
$worker = new Server(
    GlobalData::$address,
    GlobalData::$port,
    GlobalData::$persistence,
    GlobalData::$datapath
);
