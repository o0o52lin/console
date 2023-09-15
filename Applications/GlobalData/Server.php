<?php

namespace GlobalData;

use Workerman\Worker;

/**
 * Global data server.
 */
class Server
{
    /**
     * Worker instance.
     * @var worker
     */
    protected $_worker = null;

    /**
     * All data.
     * @var array
     */
    protected $_dataArray = array();

    /**
     * expire key time maper  [key=>time]
     * @var array
     */
    protected $_expireArray = array();

    /**
     * 数据持久化
     * @var boolean
     */
    protected $persistence = false;

    /**
     * 数据持久化存储路径
     * @var string
     */
    protected $datapath = '';

    /**
     * Construct.
     * @param string $ip
     * @param int $port
     */
    public function __construct($ip = '0.0.0.0', $port = 2207, $persistence = false, $datapath = '')
    {
        $worker = new Worker("frame://$ip:$port");
        $worker->count = 1;
        $worker->name = 'GlobalDataServer';
        $worker->onWorkerStart = array($this, 'onStart');
        $worker->onMessage = array($this, 'onMessage');
        $worker->onWorkerStop = array($this, 'onStop');
        $worker->reloadable = false;
        $this->_worker = $worker;
        $this->persistence = $persistence;
        $this->datapath = $datapath;
    }

    /**
     * onStart
     * @return mixed 
     */
    public function onStart()
    {
        \Workerman\Lib\Timer::add(1, array($this, 'onExpire'));
        $this->loadCache();
    }

    /**
     * onStop
     * @return mixed 
     */
    public function onStop()
    {
        $this->saveCache();
    }

    /**
     * onMessage.
     * @param TcpConnection $connection
     * @param string $buffer
     */
    public function onMessage($connection, $buffer)
    {
        if($buffer === 'ping')
        {
            return;
        }
        $data = unserialize($buffer);
        if(!$buffer || !isset($data['cmd']) || !isset($data['key']))
        {
            return $connection->close(serialize('bad request'));
        }
        $cmd = $data['cmd'];
        $key = $data['key'];
        switch($cmd) 
        {
            case 'get':
                if(!isset($this->_dataArray[$key]))
                {
                   return $connection->send('N;');
                }
                return $connection->send(serialize($this->_dataArray[$key]));
                break;
            case 'set':
                $this->_dataArray[$key] = $data['value'];
                $connection->send('b:1;');
                break;
            case 'add':
                if(isset($this->_dataArray[$key]))
                {
                    return $connection->send('b:0;');
                }
                if(isset($data['expire'])){
                    $this->setExpire($key, $data['expire']);
                }
                $this->_dataArray[$key] = $data['value'];
                return $connection->send('b:1;');
                break;
            case 'increment':
                if(!isset($this->_dataArray[$key]))
                {
                    return $connection->send('b:0;');
                }
                if(!is_numeric($this->_dataArray[$key]))
                {
                    $this->_dataArray[$key] = 0;
                }
                $this->_dataArray[$key] = $this->_dataArray[$key]+$data['step'];
                return $connection->send(serialize($this->_dataArray[$key]));
                break;
            case 'cas':
                if(isset($this->_dataArray[$key]) && md5(serialize($this->_dataArray[$key])) === $data['md5'])
                {
                    $this->_dataArray[$key] = $data['value'];
                    return $connection->send('b:1;');
                }
                $connection->send('b:0;');
                break;
            case 'expire':
                if(!isset($this->_dataArray[$key]) || !isset($data['expire']))
                {
                    return $connection->send('b:0;');
                }
                $this->setExpire($key, $data['expire']);
                $connection->send('b:1;');
                break;
            case 'delete':
                unset($this->_dataArray[$key]);
                $connection->send('b:1;');
                break;
            default:
                $connection->close(serialize('bad cmd '. $cmd));
        }
    }

    /**
     * setExpire
     * @param mixed $key 
     * @param mixed $expire 
     * @return mixed 
     */
    public function setExpire($key, $expire = 0)
    {
        $expire = intval($expire);
        $now = time();
        if($expire > 0 && $expire < ($now-864000)){
            $expire = $now + $expire;
            $this->_expireArray[$key] = $expire;
        }elseif($expire > $now){
            $this->_expireArray[$key] = $expire;
        }
    }

    /**
     * onExpire
     * @return mixed 
     */
    public function onExpire()
    {
        if(is_array($this->_expireArray)){
            $now = time();
            foreach($this->_expireArray as $key=>$time){
                if($time < $now){
                    unset($this->_dataArray[$key]);
                    unset($this->_expireArray[$key]);
                }
            }
        }
    }

    /**
     * saveCache
     * @return mixed 
     */
    protected function saveCache()
    {
        if(!$this->persistence){
            return;
        }
        if(!is_file($this->datapath)){
            touch($this->datapath);
        }
        if(is_file($this->datapath) && is_writable($this->datapath)){
            $cache_string = '<?php'.PHP_EOL.PHP_EOL;
            $cache_string .= '$data = '.var_export($this->_dataArray, true).';'.PHP_EOL;
            $cache_string .= '$expire = '.var_export($this->_expireArray, true).';'.PHP_EOL;
            file_put_contents($this->datapath, $cache_string);
        }
    }

    /**
     * loadCache
     * @return mixed 
     */
    protected function loadCache()
    {
        if($this->persistence && is_file($this->datapath) && is_readable($this->datapath)){
            include $this->datapath;
            $this->_dataArray = $data;
            $this->_expireArray = $expire;
        }
    }
}
