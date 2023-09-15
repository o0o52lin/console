<?php
namespace Library;

/**
 * 通用函数
 */
class CommFunction
{
	/**
	 * Create a "Random" String
	 *
	 * @param	string	type of random string.  basic, alpha, alnum, numeric, nozero, unique, md5, encrypt and sha1
	 * @param	int	number of characters
	 * @return	string
	 */
	public static function random_string($type = 'alnum', $len = 8)
	{
	    mt_srand();//重新播种下
	    
	    switch ($type)
	    {
	        case 'basic':
	            return mt_rand();
	        case 'alnum':
	        case 'numeric':
	        case 'nozero':
	        case 'alpha':
	            switch ($type)
	            {
	                case 'alpha':
	                    $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	                    break;
	                case 'alnum':
	                    $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	                    break;
	                case 'numeric':
	                    $pool = '0123456789';
	                    break;
	                case 'nozero':
	                    $pool = '123456789';
	                    break;
	            }
	            return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
	        case 'unique': // todo: remove in 3.1+
	        case 'md5':
	            return md5(uniqid(mt_rand()));
	        case 'encrypt': // todo: remove in 3.1+
	        case 'sha1':
	            return sha1(uniqid(mt_rand(), TRUE));
	    }
	}

	/**
     * 全局缓存方法 - 默认为GlobalDataClient缓存，仅限于常见的小数据缓存
     *
     * @param $key 缓存key值
     * @param $data 默认为NULL，表示读取；若为FALSE，表示删除；其它表示设置缓存
     * @param $expire 缓存时间。单位：秒，默认1800秒。
     */
    public static function cache($key, $data = NULL, $expire = 1800) 
    {
        if(empty($key)){
        	return false;
        }

        //获取key
        if ($data === NULL) {
            return \Library\GlobalDataClient::getInstance()->$key;
        }
        //删除key
        if ($data === FALSE) {
            unset(\Library\GlobalDataClient::getInstance()->$key);
            return true;
        }
        //保存key
        return \Library\GlobalDataClient::getInstance()->add($key, $data, $expire);
    }
}
