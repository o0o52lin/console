<?php

namespace Business\Wool;

use Library\Http;
use Business\Base;

/**
 * 填写订单后获得金币业务
 * @author minch<yeah@minch.me>
 * @since 2020-09-10
 */
class Grab extends Base
{
    /**
     * 触发处理
     * @param array $params 任务参数
     * @return boolean
     */
    public function run($params)
    {
        $taskid = intval($params['taskid']);
        $type = intval($params['type']);
        $url = trim($params['url'] ?? '');
        $name = trim($params['name']);
        $interval = intval($params['interval'] ?? 30);
        if (!$this->checkTaskTimer($taskid, $params)) {
            return false;
        }

        $http = new Http();

        if($url){
            $res = $http->get($url);
            $json = json_decode($res, true);
            $json = is_array($json) ? $json : [];
            if($type >= 1001){
                $tid = $type-1000;
                $this->db->where('`type`='.$tid)
                    ->delete('zbp_xianbao_collect')
                    ->query();
            }
            foreach ($json as $key => $value) {
                if(mb_strlen($value['title'] ?? '', 'UTF-8') <= 3) continue;

                $chk = $this->db('master')->select('*')->from('zbp_xianbao')->where([
                    'id'=>$value['id']
                ])->limit(1)->forUpdate()->row();
                if($chk['id'] ?? 0){
                    $rs = $this->db('master')
                        ->update('zbp_xianbao')
                        ->set('title', $value['title'])
                        ->set('intro', $value['content'])
                        ->set('cateid', $value['cateid'])
                        ->set('catename', $value['catename'])
                        ->set('comments', $value['comments'])
                        ->set('uname', $value['louzhu'])
                        ->set('origin_url', $value['yuanurl'])
                        ->where('id', $chk['id'])
                        ->query();
                    if($rs < 1){
                        throw new \Exception($name.' 抓取失败1');
                    }
                }else{
                    $data = [
                        'id' => $value['id'],
                        'title' => $value['title'],
                        'intro' => $value['content'],
                        'cateid' => $value['cateid'],
                        'catename' => $value['catename'],
                        'comments' => $value['comments'],
                        'uname' => $value['louzhu'],
                        'origin_url' => $value['yuanurl'],
                        'dateline' => $value['shijianchuo'],
                    ];
                    $rs = $this->db('master')
                        ->insert('zbp_xianbao')
                        ->cols($data)->query();
                    if($rs < 1){
                        throw new \Exception($name.' 抓取失败2');
                    }
                }
                if($type >= 1001){
                    $rs = $this->db('master')
                        ->insert('zbp_xianbao_collect')
                        ->cols([
                            'type'=>$tid,
                            'xbid'=>$value['id']
                        ])->query();

                    if($rs < 1){
                        throw new \Exception($name.' 抓取失败2');
                    }
                }
            }
        }
        $this->reRunTaskTimer($taskid, time()+$interval);
        return true;
    }
    
}