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
use Workerman\GatewayWorker\Register;
use Workerman\Config\Gateway;

$register = new Register('Text://'.Gateway::$register_address.':'.Gateway::$register_port);
