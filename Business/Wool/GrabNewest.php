<?php

namespace Workerman\Business\Wool;

use Workerman\Library\Http;
use Workerman\Business\Base;

/**
 * 最新10条线报
 * @since 2020-09-10
 */
class GrabNewest extends Base
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

        // if (!$this->checkTaskTimer($taskid, $params)) {
        //     return false;
        // }

        $http = new Http();

        $data_count = 0;
        $json = [];
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
                $first_id = $json[0]['id'] ?? '';

                foreach ($json as $key => $value) {
                    if(mb_strlen($value['title'] ?? '', 'UTF-8') <= 3) continue;

                    $chk = $this->db('master')->select('*')->from('zbp_post')->where([
                        'log_XbkID'=>$value['id']
                    ])->limit(1)->forUpdate()->row();
                    $url = str_replace('`','', $value['yuanurl'] ?? '');
                    /*
                    https://m.weibo.cn/detail/5037145058969377
                    http://www.zuanke8.com/thread-9299034-1-1.html
                    https://v1.xianbao.net/thread-222825-1-1.html
                    https://www.douban.com/group/topic/306281833/
                    https://www.coolapk.com/feed/56099957
                    https://app.xiaodigu.cn/mag/circle/v1/show/wapShowView?content_id=303802
                    https://www.x6g.com/i-wz-26140.html
                    https://yyok.cc/46147.html
                    https://www.dir28.com/205250.html
                    https://www.mf927.com/post/7841.html
                    */
                    preg_match('/(?:detail\/|thread-|\?content_id=|topic\/|feed\/|i-wz-|dir28\.com\/|post\/)(\d+)/', $url, $match);
                    $oid = trim($match[1] ?? '');
                    $intro = preg_replace('/( ?qita=\{.*)$/', '', $value['content']);
                    $content = '';
                    if($chk['log_ID'] ?? 0){
                        $content = trim($value['content'] ?? '');
                        $rs = $this->db('master')
                            ->update('zbp_post')
                            ->set('log_Title', $value['title'])
                            ->set('log_Intro', $intro)
                            ->set('log_CateID', $value['cateid'])
                            ->set('log_CommNums', $value['comments'])
                            ->set('log_Uname', $value['louzhu'])
                            ->set('log_Url', $value['url'])
                            ->set('log_OID', $oid)
                            ->set('log_Ourl', $url)
                            ->set('log_UpdateTime', $value['shijianchuo'])
                            ->where('log_ID', $chk['log_ID'])
                            ->query();
                        if($rs < 1){
                            throw new \Exception($name.' 抓取失败1');
                        }
                    }else{
                        $data = [
                            'log_XbkID' => $value['id'],
                            'log_Title' => $value['title'],
                            'log_Intro' => $intro,
                            'log_CateID' => $value['cateid'],
                            'log_CommNums' => $value['comments'],
                            'log_Uname' => $value['louzhu'],
                            'log_OID' => $oid,
                            'log_Ourl' => $url,
                            'log_Url' => $value['url'],
                            'log_CreateTime' => $value['shijianchuo'],
                            'log_PostTime' => $value['shijianchuo'],
                        ];
                        $rs = $this->db('master')
                            ->insert('zbp_post')
                            ->cols($data)->query();
                        if($rs < 1){
                            throw new \Exception($name.' 抓取失败2');
                        }
                    }
                    // if($content == ''){
                    //     $cps = [
                    //         'log_ID' => $value['log_ID'],
                    //         'type' => $type,
                    //         'name' => $name,
                    //         'url' => $value['url'],
                    //     ];
                    //     $this->asyncCall('Business\\Wool\\GrabContent', $cps, function($ps, $ret){
                    //         // $this->log('GrabContent from GrabNewest:' . json_encode($ret));
                    //     }); 
                    // }
                }
                $this->log(date('Y-m-d H:i:s').' => '.$data_count.'条数据，第一条ID：'.$first_id);
            }
        }catch (Exception $e) {
            $this->log('出错了：' . $e->getMessage());
        }
        // $this->reRunTaskTimer($taskid, time()+$interval);
        // $param_str = str_replace([' => ', ' ( '], ['=>', '('], preg_replace('/\s+/', ' ', var_export($params, true)));
        // $this->log('处理(编号:' . $taskid  . ')业务结束 => '.$data_count.'条数据 => 参数：' . $param_str);
        return $json;
    }
    
}