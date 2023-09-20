<?php 
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
use Workerman\Worker;
use Workerman\GatewayWorker\BusinessWorker;
use Workerman\Config\Business;
use Workerman\Config\Gateway;


// bussinessWorker 进程
$worker = new BusinessWorker();
// worker名称
$worker->name = Business::$worker_name;
// bussinessWorker进程数量
$worker->count = Business::$worker_count;
// 服务注册地址
$worker->registerAddress = Gateway::$register_address.':'.Gateway::$register_port;


