<?php

/**
 * 业务处理器配置
 */
namespace Workerman\Config;

class Business
{
    /**
     * 进程名
     *
     * @var string
     */
    public static $worker_name = 'BusinessWorker';
    
    /**
     * 开启进程数
     *
     * @var number
     */
    public static $worker_count = 2;
    
    /**
     * 向网关发送数据时使用些签名加密，以便业务端验证来源合法性
     * @var string
     */
    public static $gateway_sign = 'UL9HAOht0evxa45M6G9TESEZGwcB0TNl';

}
