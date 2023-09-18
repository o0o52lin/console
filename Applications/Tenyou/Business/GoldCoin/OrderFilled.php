<?php

namespace Business\GoldCoin;

/**
 * 填写订单后获得金币业务
 * @author minch<yeah@minch.me>
 * @since 2020-09-10
 */
class OrderFilled extends Common
{
    /**
     * 触发处理
     * @param array $params 任务参数
     * @return boolean
     */
    public function run($params)
    {
        $taskid = intval($params['taskid']);
        $this->period = intval($params['period']);
        if(!$this->checkDate()){
            $this->delTaskTimer($taskid);
            return false;
        }
        if (!$this->checkTaskTimer($taskid, $params)) {
            return false;
        }

        return $this->runPeriod($params);
    }

    /**
     * Period_20230308 2023女神节业务
     */
    protected function _runPeriod_20230308($params)
    {
        $taskid = intval($params['taskid']);

        $uid = intval($params['uid']);

        // 判断用户是否开启星星账户
        $goldcoin = $this->goldcoin($uid);
        if(!isset($goldcoin['uid']) || $goldcoin['uid'] != $uid){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '用户未开启'.$this->credit_name.'账户');
        }

        // 获取相关配置，4:抢购填单
        $config = $this->getGoldCoinTask(4);
        if(empty($config)){
            $this->delTaskTimer($taskid);
            $this->error(1000, '当期(period:'.$this->period.')无抢购填单'.$this->credit_name.'任务');
            return true;
        }

        $oid = intval($params['oid']);
        $uid = intval($params['uid']);

        $tid = $config['id'] ?? 0;
        $goldcoin_order_filled = $config['coin_per_time'];
        $goldcoin_order_filled_everyday = $config['times_per_day'];
        if(!$goldcoin_order_filled || !$goldcoin_order_filled_everyday){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '当期(period:'.$this->period.')填单任务配置错误'.var_export($config, true));
        }

        // 判断当日已经获得星星次数
        if($goldcoin['daily_order_filled'] >= $goldcoin_order_filled_everyday){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '当日填单已经获得'.$this->credit_name.'次数上限');
        }
        // 判断订单是否满足条件
        $order = $this->db('slave')
            ->select('oid,state')
            ->from('zbp_order')
            ->where('oid', $oid)
            ->where('buyer_uid', $uid)
            ->row();
        if(!isset($order['state']) || $order['state'] < 3){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '订单状态未满足获得'.$this->credit_name.'业务');
        }
        // 更新用户获得星星业务进度
        if(!$this->gain($uid, 'order_filled', $goldcoin_order_filled, $goldcoin_order_filled_everyday, $tid)){
            $this->reRunTaskTimer($taskid);
            return $this->error(1000, '处理用户获得'.$this->credit_name.'业务失败');
        }
        $this->delTaskTimer($taskid);
        return true;
    }

    /**
     * Period_20220516 业务
     */
    protected function _runPeriod_20220516($params)
    {
        $taskid = intval($params['taskid']);

        $uid = intval($params['uid']);

        // 判断用户是否开启金币账户
        $goldcoin = $this->goldcoin($uid);
        if(!isset($goldcoin['uid']) || $goldcoin['uid'] != $uid){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '用户未开启'.$this->credit_name.'账户');
        }

        // 获取相关配置，4:抢购填单
        $config = $this->getGoldCoinTask(4);
        if(empty($config)){
            $this->delTaskTimer($taskid);
            $this->error(1000, '当期(period:'.$this->period.')无抢购填单'.$this->credit_name.'任务');
            return true;
        }

        $oid = intval($params['oid']);
        $uid = intval($params['uid']);

        $tid = $config['id'] ?? 0;
        $goldcoin_order_filled = $config['coin_per_time'];
        $goldcoin_order_filled_everyday = $config['times_per_day'];
        if(!$goldcoin_order_filled || !$goldcoin_order_filled_everyday){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '当期(period:'.$this->period.')填单任务配置错误'.var_export($config, true));
        }

        // 判断当日已经获得金币次数
        if($goldcoin['daily_order_filled'] >= $goldcoin_order_filled_everyday){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '当日填单已经获得'.$this->credit_name.'次数上限');
        }
        // 判断订单是否满足条件
        $order = $this->db('slave')
            ->select('oid,state')
            ->from('zbp_order')
            ->where('oid', $oid)
            ->where('buyer_uid', $uid)
            ->row();
        if(!isset($order['state']) || $order['state'] < 3){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '订单状态未满足获得'.$this->credit_name.'业务');
        }
        // 更新用户获得金币、业务进度
        if(!$this->gain($uid, 'order_filled', $goldcoin_order_filled, $goldcoin_order_filled_everyday, $tid)){
            $this->reRunTaskTimer($taskid);
            return $this->error(1000, '处理用户获得'.$this->credit_name.'业务失败');
        }
        $this->delTaskTimer($taskid);
        return true;
    }
    
    protected function _runPeriod_0($params)
    {
        $taskid = intval($params['taskid']);
     
        $oid = intval($params['oid']);
        $uid = intval($params['uid']);
        // 获取系统相关配置
        $conf_keys = ['goldcoin_order_filled', 'goldcoin_order_filled_everyday'];
        $config = $this->getSystemConfig($conf_keys);
        if(!isset($config['goldcoin_order_filled']) || !isset($config['goldcoin_order_filled_everyday'])){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '未配置'.$this->credit_name.'相关业务');
        }
        // 判断用户是否开启金币账户
        $goldcoin = $this->goldcoin($uid);
        if(!isset($goldcoin['uid']) || $goldcoin['uid'] != $uid){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '用户未开启'.$this->credit_name.'账户');
        }
        // 判断当日已经获得金币次数
        if($goldcoin['daily_order_filled'] >= $config['goldcoin_order_filled_everyday']){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '当日填单已经获得'.$this->credit_name.'次数上限');
        }
        // 判断订单是否满足条件
        $order = $this->db('slave')
                    ->select('oid,state')
                    ->from('zbp_order')
                    ->where('oid', $oid)
                    ->where('buyer_uid', $uid)
                    ->row();
        if(!isset($order['state']) || $order['state'] < 3){
            $this->delTaskTimer($taskid);
            return $this->error(1000, '订单状态未满足获得'.$this->credit_name.'业务');
        }
        // 处理获得金币业务
        if(!$this->gain($uid, 'order_filled', $config['goldcoin_order_filled'], $config['goldcoin_order_filled_everyday'])){
            $this->reRunTaskTimer($taskid);
            return $this->error(1000, '处理用户获得'.$this->credit_name.'业务失败');
        }
        $this->delTaskTimer($taskid);
        return true;
    }
    
}