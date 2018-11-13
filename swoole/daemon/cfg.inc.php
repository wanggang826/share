<?php
// 加载env配置文件
if (file_exists(APPROOT .'/daemon/env_cfg.inc.php')) {
    require_once APPROOT.'/daemon/env_cfg.inc.php';
}


define( 'SERVICE_TIMEOUT', 0.1 );
define( 'HB_IDLE_TIME', 200 );
define( 'HB_CK_INTERVAL', 60 );
defined('URL_IP') || define( 'URL_IP', '127.0.0.1');
define( 'URL', URL_IP.'/sync.php' ); // 提供web服务的ip