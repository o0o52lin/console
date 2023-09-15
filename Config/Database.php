<?php 
namespace Config;

/**
 * MySQL数据库配置
 * @author minch<yeah@minch.me>
 */
class Database
{
	/**
	 * 数据库的一个实例配置，则使用时像下面这样使用
	 * $user_array = Db::instance('one_demo')->select('name,age')->from('user')->where('age>12')->query();
	 * 等价于
	 * $user_array = Db::instance('one_demo')->query('SELECT `name`,`age` FROM `one_demo` WHERE `age`>12');
	 * @var array
	 */
	
	/**
	 * 主库配置(读写)
	 * @var array
	 */
	public static $master = array(
		'host'		=> '192.168.0.216',
		'port'		=> '3306',
		'user'		=> 'root',
		'password'	=> '111111',
		'dbname'	=> 'mall',
		'charset'	=> 'utf8',
	);
	
	/**
	 * 从库配置(只读)
	 * @var array
	 */
	public static $slave = array(
		'host'		=> '192.168.0.216',
		'port'		=> '3306',
		'user'		=> 'root',
		'password'	=> '111111',
		'dbname'	=> 'mall',
		'charset'	=> 'utf8',
	);
}