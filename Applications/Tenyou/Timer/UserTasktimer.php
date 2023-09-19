<?php

namespace Timer;

use Timer\Base;

/**
 * 最新10条线报
 * @since 2017-05-27
 */
class NewestTasktimer extends Base
{
    /**
     * 定时任务表名
     * @var string
     */
    private $table = 'zbp_user_tasktimer';
    
    /**
     * 触发业务请求
     */
    public function trigger()
    {
        if(!$this->getlock()){
            return false;
        }
        $minid = $j = 0;
        $now = time();
        $hour = $now % 3600;
        $day = $now % 86400;

        do{
            $this->db('slave')
                ->select('id,type,params,uid')
                ->from($this->table)
                ->where('id>', $minid)
                ->where('is_open', 1)
                ->where('is_run', 0)
                ->where('err_num<', 5)
                ->where('trigger_time<=', $now)
                ->where('instr(`type`, "Business")')
                ->orderBy(array('id ASC'));
            $timers = $this->db('slave')->limit(200)->query();
            if(!is_array($timers) OR empty($timers)){
                break;
            }
            foreach ($timers as $v){
                $minid = $v['id'];
                $params = json_decode($v['params'], true);
                if(!is_array($params)) $params = [];
                $this->call($v['type'], array_merge($params , array('uid'=>$v['uid'],'taskid'=>$v['id']) ));
            }
            $j += 1;
        }while ($j < 100);
        $this->unlock();
        $this->wait();
    }
}
