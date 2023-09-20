<?php

namespace Workerman\Config;

class Message
{
	/**
	 * 消息服务进程名
	 * @var number
	 */
	public static $worker_name = 'MessageService';
	
	/**
	 * 消息服务进程数
	 * @var number
	 */
	public static $worker_count = 8;
}