<?php
namespace model;

use \Exception;

class Model{

	public function __construct()
	{
	}

	/*
		检查传参数组是否包含必须有的键值对
	*/
	public static function _check_must_key($con,$key_arr)
	{
		foreach($key_arr as $key){
			if(!isset($con[$key])){
				throw new Exception("少传了必须传的参数");
				return ;
			}
		}
		return true;
	}
	
	
	/*
	 * 获取特定键值 的数组
	 * $arr1 = ['key1','key2'];
	 * $arr2 = ['key1'=>'aaa','key3'=>'asdf','key4'=>'asdf232'];
	 * return ['key1'=>'aaa']
	 * */
	public function get_key_selected_array($arr1,$arr2)
	{
		$data = [];
		foreach($arr1 as $key){
			if(isset($arr2[$key])){
				$data[$key] = $arr2[$key];
			}
		}
		return $data;
	}

}
//    end of file
