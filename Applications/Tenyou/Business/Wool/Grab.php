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
        $url = trim($params['url']);
        $name = trim($params['name']);

        if (!$this->checkTaskTimer($taskid, $params)) {
            return false;
        }

        $http = new Http();

        if($type && $url){
            $res = $http->get($url);
            $json = json_decode($res, true);
            foreach ($json as $key => $value) {
                if(mb_strlen($value['title'], 'UTF-8') <= 3) continue;

                $chk = $this->db('master')->select('*')->from('zbp_xianbao')->where([
                    'id'=>$value['id']
                ])->limit(1)->forUpdate()->row();
                $data = [
                    'title' => $value['title'],
                    'intro' => $value['content'],
                    'catename' => $value['catename'],
                    'comments' => $value['comments'],
                    'uname' => $value['louzhu'],
                    'origin_url' => $value['yuanurl'],
                    'dateline' => $value['shijianchuo'],
                ];
                if($chk['id'] ?? 0){
                    $rs = $this->db('master')
                        ->update('zbp_xianbao')
                        ->where('id', $chk['id'])
                        ->cols($data)
                        ->query();
                    if($rs < 1){
                        throw new \Exception($name.' 抓取失败1');
                    }
                }else{
                    $data['id'] = $value['id'];
                    $data['cateid'] = $value['cateid'];
                    $data['dateline'] = $value['shijianchuo'];
                    $rs = $this->db('master')
                        ->insert('zbp_xianbao')
                        ->cols($data)->query();
                    if($rs < 1){
                        throw new \Exception($name.' 抓取失败2');
                    }
                }
            }
        }
        $this->reRunTaskTimer($taskid, time()+6);
        return true;
    }
    
}