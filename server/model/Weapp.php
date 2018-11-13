<?php
namespace model;

use \C;
use \Exception;
use \LOG;
use \DB;
use model\Api;
/**
 *
 */

class Weapp
{

	function __construct()
	{

	}

	public static function valid($isfirsttime = false) {
		$echoStr = $_GET["echostr"];

		//valid signature , option
		if(self::checkSignature()){
			if ( $isfirsttime ) {
				echo $echoStr;    	exit;
			} else {
				return true;
			}
		}
	}

	private function checkSignature()
	{
	    $signature = $_GET["signature"];
	    $timestamp = $_GET["timestamp"];
	    $nonce = $_GET["nonce"];

	    $token = TOKEN;
	    $tmpArr = array($token, $timestamp, $nonce);
	    sort($tmpArr, SORT_STRING);
	    $tmpStr = implode( $tmpArr );
	    $tmpStr = sha1( $tmpStr );

	    if( $tmpStr == $signature ){
	        return true;
	    }else{
	        return false;
	    }
	}
}
