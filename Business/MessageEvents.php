<?php
namespace Workerman\Business;
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
use Workerman\GatewayWorker\Lib\Gateway as LGateway;
use Workerman\Config\Gateway;
use Workerman\Library\Log;
use Workerman\Crontab\Crontab;
use Workerman\Business\Wool\Grab;
use Workerman\Business\Wool\GrabNewest;
use Workerman\Business\Wool\GrabRank;
use Workerman\Business\Wool\GrabContent;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class MessageEvents
{
    /**
     * 对象实例
     * @var array
     */
    private static $instances = array();

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        LGateway::sendToClient($client_id, "Hello $client_id");
        LGateway::sendToAll("$client_id login");
    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param string $message 具体消息
     */
    public static function onMessage($client_id, $message)
    {
        $call_id = md5($message);
        $data = json_decode($message, true);
        // 判断数据是否正确
        if(empty($data['class']) || empty($data['method']) || !isset($data['params'])){
            Log::add('参数异常: ' . $message);
            // 发送数据给客户端，请求包错误
            return self::response($client_id, array('code'=>400,'msg'=>'bad request','call_id'=>$call_id,'data'=>'参数异常'));
        }
        
        if($data['sign'] != md5($data['class'] . $data['method'] . json_encode($data['params']) . Gateway::$client_sign[$data['client']])){
            Log::add('签名错误: ' . $message);
            // 签名错误
            return self::response($client_id, array('code'=>401,'msg'=>'invalid sign','call_id'=>$call_id,'data'=>'签名错误'));
        }

        $class = 'Workerman\\'.$data['class'];
        // 判断类对应文件是否载入
        if(!class_exists($class)){
            Log::add('无此业务: ' . $message);
            $code = 404;
            $msg = "class {$data['class']} not found";
            // 发送数据给客户端 类不存在
            return self::response($client_id, array('code'=>$code,'msg'=>$msg,'call_id'=>$call_id,'data'=>'无此业务'));
        }
        
        // 调用类的方法
        try{
            // if(isset(self::$instances[$data['class']])){
            //     $instance = self::$instances[$data['class']];
            // }else{
            //     $instance = new $data['class']();
            //     self::$instances[$data['class']] = $instance;
            // }
            // $ret = call_user_func(array($instance,$data['method']), $data['params']);
            if($data['client'] == 'croner'){
                $ps = $data['params'];
                $tid = $ps['taskid'];
                $interval = intval($ps['interval'] ?? 5);

                $log = $ps['name'].' => '.date('Y-m-d H:i:s').' : '.date('Y-m-d H:i:s', time()+$interval).' 执行 '.':'.$interval."\n";
                if($ps['type'] === 0){
                    echo $log;
                    Log::add($log);
                    new Crontab('*/'.$interval.' * * * * *', function() use ($tid, $ps, $interval, $client_id, $call_id) {
                        echo date('Y-m-d H:i:s').' '.$ps['name'].':'.$interval."\n";
                        $a = new GrabNewest();
                        $ret = $a->run(array_merge($ps, ['taskid'=>$tid]));
                        
                        // 发送数据给客户端，调用成功，data下标对应的元素即为调用结果
                        return self::response($client_id, array('code'=>0,'msg'=>'ok','call_id'=>$call_id,'data'=>$ret));
                    });
                }else if($ps['type'] > 1000){
                    echo $log;
                    Log::add($log);
                    new Crontab('*/'.$interval.' * * * * *', function() use ($tid, $ps, $interval, $client_id, $call_id) {
                        echo date('Y-m-d H:i:s').' '.$ps['name'].':'.$interval."\n";
                        $a = new GrabRank();
                        $ret = $a->run(array_merge($ps, ['taskid'=>$tid]));
                        
                        // 发送数据给客户端，调用成功，data下标对应的元素即为调用结果
                        return self::response($client_id, array('code'=>0,'msg'=>'ok','call_id'=>$call_id,'data'=>$ret));
                    });
                }else{
                    echo $log;
                    Log::add($log);
                    new Crontab('*/'.$interval.' * * * * *', function() use ($tid, $ps, $interval, $client_id, $call_id) {
                        echo date('Y-m-d H:i:s').' '.$ps['name'].':'.$interval."\n";
                        $a = new Grab();
                        $ret = $a->run(array_merge($ps, ['taskid'=>$tid]));

                        // 发送数据给客户端，调用成功，data下标对应的元素即为调用结果
                        return self::response($client_id, array('code'=>0,'msg'=>'ok','call_id'=>$call_id,'data'=>$ret));
                    });
                }
            }else if($data['client'] == 'business'){
                $ps = $data['params'];
                $id = $ps['id'];

                echo date('Y-m-d H:i:s').' 实例:'.$class."\n";
                if(isset(self::$instances[$class])){
                    $instance = self::$instances[$class];
                }else{
                    $instance = new $class();
                    self::$instances[$class] = $instance;
                }
                $ret = $instance->run($ps);
                
                // 发送数据给客户端，调用成功，data下标对应的元素即为调用结果
                return self::response($client_id, array('code'=>0,'msg'=>'ok','call_id'=>$call_id,'data'=>$ret));
            }

        }        // 有异常
        catch(Exception $e){
            Log::add('处理异常: ' . $message);
            // 发送数据给客户端，发生异常，调用失败
            $code = $e->getCode() ? $e->getCode() : 500;
            Log::add('ERROR CODE [' . $code . ']: ' . $e->getMessage());
            return self::response($client_id, array('code'=>$code,'msg'=>$e->getMessage(),'call_id'=>$call_id,'data'=>'处理异常'));
        }
    }

    private static function response($client_id, $data)
    {
        LGateway::sendToClient($client_id, json_encode($data));
        return 0 == $data['code'] ? true : false;
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        LGateway::sendToAll("$client_id logout");
    }
}
