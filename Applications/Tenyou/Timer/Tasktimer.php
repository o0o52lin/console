<?php

namespace Timer;

use Timer\Base;

/**
 * 业务通用定时器
 * @author 关小龙
 * @since 2017-05-27
 */
class Tasktimer extends Base
{
	/**
	 * 定时任务表名
	 * @var string
	 */
	private $table = 'zbp_tasktimer';
	
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
		$sec = $now % 3600;
		do{
			$this->db('slave')->select('id,type,params,uuid,trigger_time')->from($this->table);
			$this->db('slave')->where('`id`>'.$minid.' AND `is_open`=1 AND `is_run`=0 AND err_num<5 AND `trigger_time`<='.$now);

			$timers = $this->db('slave')->orderBy(array('id ASC'))->limit(200)->query();
			if(!is_array($timers) OR empty($timers)){
				break;
			}
			foreach ($timers as $v){
				$minid = $v['id'];
				$ps = json_decode($v['params'],TRUE);
				if(($ps['type'] ?? -1) === 0){
					$this->call('Business\\Wool\\GrabNewest', array_merge($ps, array('taskid'=>$v['id']) ));
				}else if(($ps['type'] ?? -1) > 1000){
					$this->call('Business\\Wool\\GrabRank', array_merge($ps, array('taskid'=>$v['id']) ));
				}else{
					$this->call($v['type'], array_merge($ps, array('taskid'=>$v['id']) ));
				}
				
			}
			$j += 1;
		}while ($j < 100);
		$this->unlock();
		$this->wait();
	}
}
