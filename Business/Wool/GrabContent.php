<?php

namespace Workerman\Business\Wool;

use Workerman\Library\Http;
use Workerman\Business\Base;

/**
 * 获取内容
 */
class GrabContent extends Base
{
    /**
     * 触发处理
     * @param array $params 任务参数
     * @return boolean
     */
    public function run($params)
    {
        $id = intval($params['id']);
        $type = intval($params['type']);
        $name = trim($params['name']);
        $url = trim($params['url']);

        $http = new Http();

        $content = '';
        try{
            if($url){
                $url = 'http://new.xianbao.fun/'.ltrim($url, '/');
                $http->timeout = 10;
                $cookie = 'night=0; __51cke__=; timezone=8; __tins__21467067=%7B%22sid%22%3A%201695085348334%2C%20%22vd%22%3A%201%2C%20%22expires%22%3A%201695087148334%7D; __51laig__=11';
                $res = $http->header([
                    'Accept'=>'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'User-Agent'=>'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
                ])->get($url, $cookie);
                preg_match('/(<article[^>]+>.*<\/article>)/', $res, $match);

                $content = trim($match[0] ?? '');
                
                $content != '' && $this->db('master')
                    ->update('zbp_xianbao')
                    ->set('content', $content)
                    ->where('id', $id)
                    ->query();
            }
        }catch (Exception $e) {
            $this->log('GrabContent 出错了：id:'.$id."\n" . $e->getMessage());
        }
        // $this->reRunTaskTimer($taskid, time()+$interval);
        // $param_str = str_replace([' => ', ' ( '], ['=>', '('], preg_replace('/\s+/', ' ', var_export($params, true)));
        // $this->log('处理(编号:' . $taskid  . ')业务结束 => '.$data_count.'条数据 => 参数：' . $param_str);
        return $content;
    }
    
}