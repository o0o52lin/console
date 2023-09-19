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

        $data_count = 0;
        try{
            if($url){
                $http->timeout = 10;
                $cookie = 'night=0; __51cke__=; timezone=8; __tins__21467067=%7B%22sid%22%3A%201695085348334%2C%20%22vd%22%3A%201%2C%20%22expires%22%3A%201695087148334%7D; __51laig__=11';
                $res = $http->header([
                    'Accept'=>'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'User-Agent'=>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
                ])->get($url, $cookie);
                $json = json_decode($res, true);
                $json = is_array($json) ? $json : [];
                $data_count = count($json);
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
                    $url = str_replace('`','', $value['yuanurl']);
                    if($chk['id'] ?? 0){
                        $rs = $this->db('master')
                            ->update('zbp_xianbao')
                            ->set('title', $value['title'])
                            ->set('intro', $value['content'])
                            ->set('cateid', $value['cateid'])
                            ->set('catename', $value['catename'])
                            ->set('comments', $value['comments'])
                            ->set('uname', $value['louzhu'])
                            ->set('origin_url', $url)
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
                            'origin_url' => $url,
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
        }catch (Exception $e) {
            $this->log('出错了：' . $e->getMessage());
        }
        $this->reRunTaskTimer($taskid, time()+$interval);
        $param_str = str_replace([' => ', ' ( '], ['=>', '('], preg_replace('/\s+/', ' ', var_export($params, true)));
        $this->log('处理(编号:' . $taskid  . ')业务结束 => '.$data_count.'条数据 => 参数：' . $param_str);
        return true;
    }
    
}