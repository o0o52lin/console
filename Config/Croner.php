<?php

/**
 * 定时器配置
 */
namespace Workerman\Config;

class Croner
{
    /**
     * 进程名
     * @var string
     */
    public static $worker_name = 'Cron-Worker';
    
    /**
     * 进程数
     * @var string
     */
    public static $worker_count = 1;
    
    /**
     * 向网关发送数据时使用些签名加密，以便业务端验证来源合法性
     * @var string
     */
    public static $gateway_sign = 'dnRI26saAWPL0OeZm6JGJEy7DSn5X5VC';
}