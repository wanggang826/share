<?php
include_once "table_common.php";
class table_jjsan_common_setting extends table_common
{

	public function __construct() {
		$this->_table = substr(__CLASS__, 6);
		self::$_t = $this->_table;
		$this->_pk    = 'skey';

		parent::__construct();
	}

    public function getCacheName($cacheName)
    {
        return $cacheName . '_cache';
	}

    public function getCacheData($cacheName)
    {
        $rst = $this->fetch($this->getCacheName($cacheName));
        if (!$rst) return false;
        $rst = json_decode($rst['svalue'], true);
        if (isset($rst['expire_time']) && $rst['expire_time'] > time()) {
            return false;
        } else {
            return $rst['data'];
        }
	}


	public function updateCacheData($cacheName, $data, $expireTime = 3600) {
        $rst = $this->fetch($this->getCacheName($cacheName));
        if (!$rst) {
            return $this->insert([
                'skey' => $this->getCacheName($cacheName),
                'svalue' => json_encode(['data' => $data, 'expire_time' => time() + $expireTime])
            ]);
        } else {
            return $this->update($this->getCacheName($cacheName), [
               'svalue' => json_encode(['data' => $data, 'expire_time' => time() + $expireTime])
            ]);
        }
    }

    public function deleteCache($cacheName)
    {
        return $this->delete($this->getCacheName($cacheName));
    }
}
