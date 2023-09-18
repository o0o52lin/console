<?php

namespace Business\GoldCoin;

use Business\Base;

/**
 * 金币公共业务类
 * @author minch<yeah@minch.me>
 * @since 2020-09-10
 */
class Common extends Base
{
    /**
     * goldcoin prepared type map
     * @var array
     */
    protected static $typemap = [
        'zhongwa_gift' => 1,
        'invite' => 2,
        'support' => 3,
        'order_filled' => 4,
        'order_finished' => 5,
        'app_view' => 6,
        'goods_view' => 7,
        'link_view' => 8,
        'share' => 9,
        'signin' => 10,
        'secret' => 11
    ];

    /**
     * 开始日期
     * @var string
     */
    protected $start_date = 20201012;

    /**
     * 结束日期
     * @var string
     */
    protected $end_date = 20201122;

    /**
     * 期号
     * @var string
     */
    protected $period = 0;

    /**
     * 积分名称
     * @var string
     */
    protected $credit_name = '金币';

    public function run($params){}

    public function runPeriod($params)
    {
        $method = '_runPeriod_'.$this->period;
        $this->credit_name = ($params['credit_name'] ?? '') != '' ? $params['credit_name'] : $this->credit_name;
        if(method_exists($this, $method)){
            return $this->{$method}($params);
        }else{
            return $this->error(1000, '找不到方法：'.$method);
        }
    }

    /**
     * 判断是否在活动期间
     * @return [type] [description]
     */
    protected function checkDate()
    {
        if($this->period == 20220516){
            // 2022十周年庆典-金币游乐园
            $this->start_date = 20220516;
            $this->end_date = 20220531;
        }else if($this->period == 20230308){
            // 2023女神节
            $timeRange = $this->getActivityTimeRange($this->period);
            $this->start_date = $timeRange['starttime'] > 0 ? date('Ymd', $timeRange['starttime']) : 0;
            $this->end_date = $timeRange['endtime'] > 0 ? date('Ymd', $timeRange['endtime']) : 0;
        }
        if(date('Ymd') < $this->start_date || date('Ymd') > $this->end_date){
            return false;
        }
        return true;
    }

    /**
     * 2023女神节获取时间配置
     */
    protected function getActivityTimeRange($period)
    {
        $config = $this->db('master')
                    ->select('value')
                    ->from('zbp_system_config')
                    ->where('`key`', 'activity_time_ranges_'.$period)
                    ->row();

        $config = json_decode(trim($config['value'] ?? ''), true);
        $config = is_array($config) ? $config : [];

        return ['starttime'=>$config['starttime'] ?? 0,'endtime'=>$config['endtime'] ?? 0];
    }

    /**
     * 获取用户金币账户
     * @param int $uid 用户ID
     * @return boolean
     */
    protected function goldcoin($uid)
    {
        return $this->db('master')
                    ->select('*')
                    ->from('zbp_goldcoin')
                    ->where('uid', $uid)
                    ->where('period', $this->period)
                    ->row();
    }


    /**
     * 用户获得(临时)金币，并更新任务进度
     * @param  int $uid  用户ID
     * @param  int $type 金币获得方式
     * @param  int $amount 获得金币数量
     * @param  int $daily_limit 每日完成任务次数
     * @param  int $tid 金币任务id
     * @return boolean
     */
    protected function gain($uid, $type, $amount, $daily_limit, $tid = 0)
    {
        if(!$uid || !$type || !$amount){
            $this->error(1000, '更新'.$this->credit_name.'任务进度参数错误');
        }
        
        $step = 1;
        $done = 0;
        try{
            // 开始事务
            $this->db('master')->beginTransaction();
            
            $user_goldcoin = $this->db('master')->select('uid,amount')->from('zbp_goldcoin')->where([
                'uid'=>$uid,
                'period'=>$this->period
            ])->limit(1)->forUpdate()->row();

            $user_task = $this->db('master')->select('*')->from('zbp_goldcoin_task')->where([
                'uid'=>$uid,
                'period'=>$this->period,
                'task_id'=>$tid,
                'date'=>date('Ymd')
            ])->limit(1)->forUpdate()->row();

            $gold_prepared = $this->db('master')->select('*')->from('zbp_goldcoin_prepared')->where([
                'uid'=>$uid,
                'period'=>$this->period,
                'task_id'=>$tid
            ])->limit(1)->forUpdate()->row();

            if($user_task['id'] ?? 0){
                $step = $user_task['step'] + 1;
                if($step > $daily_limit){
                    $rs = 1;
                }else if($step == $daily_limit){
                    $step = $daily_limit;
                    $rs = $this->db('master')
                        ->update('zbp_goldcoin_task')
                        ->where('id', $user_task['id'])
                        ->set('step', $step)
                        ->set('state', 2)
                        ->set('finish_time', time())
                        ->query();
                    $done = 1;
                }else{
                    $rs = $this->db('master')
                        ->update('zbp_goldcoin_task')
                        ->where('id', $user_task['id'])
                        ->set('step', $step)
                        ->query();
                }
                if($rs < 1){
                    throw new \Exception($this->credit_name.'任务更新失败1');
                }
            }else{
                $rs = $this->db('master')
                    ->insert('zbp_goldcoin_task')
                    ->cols([
                        'uid'=>$uid,
                        'task_id'=>$tid,
                        'period'=>$this->period,
                        'date'=>date('Ymd'),
                        'step'=>$step,
                        'state'=>$step >= $daily_limit ? 2 : 1,
                        'finish_time'=> $step >= $daily_limit ? time() : 0,
                        'dateline'=>time()
                    ])->query();
                if($rs < 1){
                    throw new \Exception($this->credit_name.'任务更新失败2');
                }
            }

            // 更新金币为可领取
            $state = in_array($type, ['invite', 'order_filled', 'order_finished']) ? 1 : ($step >= $daily_limit ? 1 : 0);
            $rs = $this->db('master')
                ->update('zbp_goldcoin_prepared')
                ->where('id', $gold_prepared['id'])
                ->setCols([
                    'amount'=>['op'=>'+', 'val'=>$amount],
                    'state'=>$state,
                    'update_time'=>time()
                ])
                ->query();
            if($rs < 1){
                throw new \Exception($this->credit_name.'任务更新失败3');
            }
            if(in_array($type, ['invite', 'order_filled', 'order_finished'])){
                $rs = $this->db('master')
                    ->update('zbp_goldcoin')
                    ->set('temp_amount', ['op'=>'+', 'val'=>$amount])
                    ->set('daily_'.$type, ['op'=>'+', 'val'=>$amount])
                    ->set('update_time', time())
                    ->where([
                        'uid'=>$uid,
                        'period'=>$this->period
                    ])->query();
            }else{
                $rs = $this->db('master')
                    ->update('zbp_goldcoin')
                    ->set('temp_amount', ['op'=>'+', 'val'=>$amount])
                    ->set('update_time', time())
                    ->where([
                        'uid'=>$uid,
                        'period'=>$this->period
                    ])->query();
            }

            if($rs < 1){
                throw new \Exception($this->credit_name.'任务更新失败4');
            }
            
            $this->db('master')->commit();

            return true;
        
        }catch(\Exception $e){
            // 数据回滚
            $this->db('master')->rollBack();
            $this->error(1000, $e->getMessage());
            return false;
        }
    }

    /**
     * 获取金币任务配置
     * @param  int $type 金币任务类型
     * @return array
     */
    protected function getGoldCoinTask($type)
    {
        $row = $this->db('slave')
            ->select('*')
            ->from('zbp_goldcoin_task_config')
            ->where('`period` = "' . $this->period. '"')
            ->where('`type` = "' . $type. '"')
            ->row();

        $row = is_array($row) ? $row : [];
        if(!empty($row)){
            $row['content'] = trim($row['content']) != '' ? json_decode(trim($row['content']), true) : [];
        }
        return $row;
    }
}