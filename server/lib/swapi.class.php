<?php
class swAPI {
    const SERVER_IP = SWOOLE_SERVER_IP;
    const PORT      = SWOOLE_SERVER_PORT;

    const SWOOLE_SLOT_INTERVAL = UMBRELLA_SLOT_INTERVAL; // 用于开关锁命令中, 因为终端是从4号开始的, 服务器是从1号开始的

    private static $_receive = '';

    private function client($host, $port, $data) {
        $client = new swoole_client( SWOOLE_SOCK_TCP );
        //连接到服务器
        if ( !$client->connect( $host, $port, 0.5 ) ) {
            LOG::WARN("swoole client connect server failed: " . self::SERVER_IP . ":" . self::PORT);
            return false;
        }
        //向服务器发送数据
        if ( !$client->send( $data . "\r\n" )) {
            LOG::WARN("swoole client send data failed: " . $data);
            return false;
        }
        self::$_receive = $client->recv();
        $client->close();
    }

    private function send($data) {
        LOG::DEBUG('swoole client send data ' .print_r($data, 1));
        self::client(self::SERVER_IP, self::PORT, json_encode($data, true));
    }

    public static function isStationOnline($stationId) {
        if (empty($stationId)) return false;
        $data = 'isonline ' . $stationId;
        self::client(self::SERVER_IP, self::PORT, $data);
        // 在线返回1 不在线返回0
        $result = (self::$_receive == 1);
        LOG::DEBUG("check station status, stationid: $stationId , online: " . ($result + 0));
        return $result;
    }

    public static function sendStatus($statusData)
    {
        self::send($statusData);
    }

	public static function borrowUmbrella($stationid, $orderid){
        $data['stationid'] = $stationid;
        $content = [
            'EVENT_CODE' => 1,
            'ORDERID' => $orderid,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
		self::send($data);
	}

    public static function query_info($station_id, $slot_num, $all = false){
        if($all){
            for($i = $slot_num; $i; $i--){
                $data['stationid'] = $station_id;
                $content = [
                    'EVENT_CODE' => 52,
                    'SLOT' => $i + self::SWOOLE_SLOT_INTERVAL,
                    'MSG_ID' => time(),
                ];
                $tmp = '';
                foreach($content as $k => $v) {
                    $tmp .= $k . ':' . $v . ';';
                }
                $tmp = rtrim($tmp, ';');
                $data['data'] = $tmp;
                self::send($data);
                LOG::DEBUG('query slot : ' . $i);
                sleep(5);
            }
        }else{
            $data['stationid'] = $station_id;
            $content = [
                'EVENT_CODE' => 52,
                'SLOT' => $slot_num + self::SWOOLE_SLOT_INTERVAL,
                'MSG_ID' => time(),
            ];
            $tmp = '';
            foreach($content as $k => $v) {
                $tmp .= $k . ':' . $v . ';';
            }
            $tmp = rtrim($tmp, ';');
            $data['data'] = $tmp;
            self::send($data);
        }
    }

    public static function slotLock($station_id, $slot_num, $all = false){
        if($all){
            for($i = $slot_num; $i; $i--){
                $data['stationid'] = $station_id;
                $content = [
                    'EVENT_CODE' => 53,
                    'SLOT' => $i + self::SWOOLE_SLOT_INTERVAL,
                    'MSG_ID' => time(),
                ];
                $tmp = '';
                foreach($content as $k => $v) {
                    $tmp .= $k . ':' . $v . ';';
                }
                $tmp = rtrim($tmp, ';');
                $data['data'] = $tmp;
                self::send($data);
                sleep(5);
            }
        }else{
            $data['stationid'] = $station_id;
            $content = [
                'EVENT_CODE' => 53,
                'SLOT' => $slot_num + self::SWOOLE_SLOT_INTERVAL,
                'MSG_ID' => time(),
            ];
            $tmp = '';
            foreach($content as $k => $v) {
                $tmp .= $k . ':' . $v . ';';
            }
            $tmp = rtrim($tmp, ';');
            $data['data'] = $tmp;
            self::send($data);
        }
    }

    public static function slotUnlock($station_id, $slot_num, $all = false){
        if($all){
            for($i = $slot_num; $i; $i--){
                $data['stationid'] = $station_id;
                $content = [
                    'EVENT_CODE' => 54,
                    'SLOT' => $i + self::SWOOLE_SLOT_INTERVAL,
                    'MSG_ID' => time(),
                ];
                $tmp = '';
                foreach($content as $k => $v) {
                    $tmp .= $k . ':' . $v . ';';
                }
                $tmp = rtrim($tmp, ';');
                $data['data'] = $tmp;
                self::send($data);
                sleep(5);
            }
        }else{
            $data['stationid'] = $station_id;
            $content = [
                'EVENT_CODE' => 54,
                'SLOT' => $slot_num + self::SWOOLE_SLOT_INTERVAL,
                'MSG_ID' => time(),
            ];
            $tmp = '';
            foreach($content as $k => $v) {
                $tmp .= $k . ':' . $v . ';';
            }
            $tmp = rtrim($tmp, ';');
            $data['data'] = $tmp;
            self::send($data);
        }
    }

    public static function manually_lend($station_id, $slot_num, $all = false){
        if($all){
            for($i = $slot_num; $i; $i--){
                $data['stationid'] = $station_id;
                $content = [
                    'EVENT_CODE' => 55,
                    'SLOT' => $i + self::SWOOLE_SLOT_INTERVAL,
                    'MSG_ID' => time(),
                ];
                $tmp = '';
                foreach($content as $k => $v) {
                    $tmp .= $k . ':' . $v . ';';
                }
                $tmp = rtrim($tmp, ';');
                $data['data'] = $tmp;
                self::send($data);
                sleep(7);
            }
        }else{
            $data['stationid'] = $station_id;
            $content = [
                'EVENT_CODE' => 55,
                'SLOT' => $slot_num + self::SWOOLE_SLOT_INTERVAL,
                'MSG_ID' => time(),
            ];
            $tmp = '';
            foreach($content as $k => $v) {
                $tmp .= $k . ':' . $v . ';';
            }
            $tmp = rtrim($tmp, ';');
            $data['data'] = $tmp;
            self::send($data);
        }
    }

    public static function reboot($station_id){
        $data['stationid'] = $station_id;
        $content = [
            'EVENT_CODE' => 56,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::send($data);
    }

    public static function upgrade($station_id, $file_name, $file_size){
        $data['stationid'] = $station_id;
        $content = [
            'EVENT_CODE' => 57,
            'FILE_NAME' => $file_name,
            'FILE_SIZE' => $file_size
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::send($data);
    }

    public static function sync_umbrella($station_id){
        $data['stationid'] = $station_id;
        $content = [
            'EVENT_CODE' => 58,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::send($data);
    }

    public static function module_num($station_id, $module_num){
        $data['stationid'] = $station_id;
        $content = [
            'EVENT_CODE' => 59,
            'MODULE' => $module_num,
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::send($data);
    }

    public static function initSet($stationId)
    {
        $data['stationid'] = $stationId;
        $content = [
            'EVENT_CODE' => 60,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::send($data);
    }


    public static function moduleOpen($station_id, $moduleType){
        $data['stationid'] = $station_id;
        $content = [
            'EVENT_CODE' => 61,
            'MODULE' => $moduleType,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::send($data);
    }


    public static function moduleClose($station_id, $moduleType){
        $data['stationid'] = $station_id;
        $content = [
            'EVENT_CODE' => 62,
            'MODULE' => $moduleType,
            'MSG_ID' => time(),
        ];
        $tmp = '';
        foreach($content as $k => $v) {
            $tmp .= $k . ':' . $v . ';';
        }
        $tmp = rtrim($tmp, ';');
        $data['data'] = $tmp;
        self::send($data);
    }

    // 单元模组启动
    public static function elementModuleOpen($station_id)
    {
        self::moduleOpen($station_id, 1);
    }

    // 单元模组休眠
    public static function elementModuleClose($station_id)
    {
        self::moduleClose($station_id, 1);
    }

    // 语音功能启动
    public static function voiceModuleOpen($station_id)
    {
        self::moduleOpen($station_id, 2);
    }

    // 语音功能休眠
    public static function voiceModuleClose($station_id)
    {
        self::moduleClose($station_id, 2);
    }

}
