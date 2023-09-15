<?php 

use \GatewayWorker\BusinessWorker;
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

// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';

// 比较耗时的业务另起进程专门处理
// 进程名
$worker = new BusinessWorker();
// worker名称
$worker->name = Config\Message::$worker_name;
// bussinessWorker进程数量
$worker->count = Config\Message::$worker_count;
// 设置处理业务类名
$worker->eventHandler = 'MessageEvents';
// 服务注册地址
$worker->registerAddress = Config\Gateway::$register_address.':'.Config\Gateway::$register_port;

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
	\Workerman\Worker::runAll();
}
