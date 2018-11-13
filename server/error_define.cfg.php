<?php
// 错误代码, 供返回接口使用
define(	"ERR_NORMAL", 0 );
define(	"ERR_PARAMS_INVALID", 1000 ); // 1001 弃用, 改为1000 表示参数错误
define(	"ERR_REQUEST_FAIL", 1002 ); // 请求其他服务器失败
define(	"ERR_SERVER_DB_FAIL", 1003 );
define(	"ERR_REQUEST_FAIL_FROM_CLOUD", 1004 ); // 请求云服务器失败, 可能与配置有关
define(	"ERR_LOCAL_CONFIG", 1005 ); // 本地配置错误
define(	"ERR_TERMINAL_SEAT_QUERY_FAIL", 1006 ); // 终端定位失败, 需与交换机同步mac信息
define(	"ERR_SID_NOT_FOUND", 1007 ); //没有对应的sid
define(	"ERR_UMBRELLA_NOT_FOUND_OR_NOT_ORDER", 1008 ); //没有对应的雨伞id，或雨伞没有对应订单
define(	"ERR_FILE_OPT_FAIL", 1009 );
define(	"ERR_FEE_STRATEGY_DIFF", 1010 ); //收费策略不一致
define(	"ERR_SERVER_BUSINESS_ERROR", 1011 ); //服务器业务逻辑错误

define(	"ERR_LBS_UPDATE_POI_FAIL", 2001 ); // 百度LBS更新POI失败
define(	"ERR_DESPOSIT_NOT_ENOUGH", 3000 ); //押金不足




define( "ERR_STATION_NEED_LOGIN", 6001 );  //站点未登录,需要重新登录
define( "ERR_STATION_NEED_SYNC_LOCAL_TIME", 6002 );  //终端需要校时
define( "ERR_STATION_UPGRADE_FILENAME_NOT_EXISTED", 6061 );  //升级软件时,请求缺少文件名或者文件名不存在
define( "ERR_STATION_UPGRADE_SERVER_FILE_NOT_EXISTED", 6062 );  //升级软件时,服务器上的文件不存在
define( "ERR_STATION_UPGRADE_BYTE_NUMBER_NOT_EXISTED", 6063 );  //升级软件时,缺少字节数量(长度)
define( "ERR_STATION_UPGRADE_SOFT_VERSION_MISMATCH", 6063 );  //升级软件时,软件版本不匹配
define( "ERR_STATION_UPGRADE_FILENAME_MISMATCH", 6064 );  //升级软件时,文件名不匹配
