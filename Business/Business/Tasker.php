<?php

namespace Workerman\Business\Business;

use Workerman\Business\Business\Base;

/**
 * 业务通用定时器
 */
class Tasker extends Base
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
	    // if(!$this->getlock()){
	    //     return false;
	    // }
		// $minid = $j = 0;
		// $now = time();
		// $sec = $now % 3600;
		// do{
		// 	$this->db('slave')->select('id,type,params,uuid,trigger_time')->from($this->table);
		// 	$this->db('slave')->where('`id`>'.$minid.' AND `is_open`=1 AND `is_run`=0 AND err_num<5 AND `trigger_time`<='.$now);

		// 	$timers = $this->db('slave')->orderBy(array('id ASC'))->limit(200)->query();
		// 	if(!is_array($timers) OR empty($timers)){
		// 		break;
		// 	}
		// 	foreach ($timers as $v){
		// 		$minid = $v['id'];
		// 		$this->call($v['type'], array_merge( json_decode($v['params'],TRUE), array('taskid'=>$v['id']) )); //把taskid数组放在后面，避免被设置参数覆盖
		// 	}
		// 	$j += 1;
		// }while ($j < 100);

	    $this->db('slave')->select('id,type,params,uuid,trigger_time')->from('zbp_tasktimer');
	    $this->db('slave')->where('`is_open`=1 AND `is_run`=0');
	    $list = $this->db('slave')->orderBy(array('id ASC'))->limit(50)->query();
	    foreach ($list as $item) {
	        $tid = $item['id'];
	        $ps = json_decode($item['params'], TRUE);
	        $interval = intval($ps['interval'] ?? 5);
	        $this->call($item['type'], array_merge($ps, array('taskid'=>$tid) )); 
	    }
	    
		// $this->unlock();
		// $this->wait();
	}
}
