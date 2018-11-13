<?php
use model\Api;

switch($act){

	// 普通接口
	case "common":
		include_once "act/api/common.php";
		break;

    // 商铺站点接口
    case "shop_station":
        include "act/api/shop_station.php";
        break;

    // 商铺接口
    case "shop":
        include "act/api/shop.php";
        break;

    // 微信小程序接口
    case "weapp":
        include "act/api/weapp.php";
        break;

    // 用户端接口（微信公众号，支付宝生活号）
    case "platform":
        include "act/api/platform.php";
        break;

    default:
		Api::output([],Api::API_NOT_EXISTS);
		break;

}
