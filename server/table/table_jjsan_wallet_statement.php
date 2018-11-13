<?php
include_once "table_common.php";
class table_jjsan_wallet_statement extends table_common
{
    static $_t = 'jjsan_wallet_statement';

	public function __construct() {

		$this->_table = 'jjsan_wallet_statement';
		$this->_pk    = 'id';

		parent::__construct();
	}

    public function fetch_by_field($k,$v) {
        return DB::fetch_first('SELECT * FROM %t WHERE %i ', array($this->_table, DB::field($k, $v)));
    }

    public function get_statement($uid, $page, $page_size){
        return DB::fetch_all('SELECT `type`, `amount`, `time` FROM %t WHERE %i ORDER BY `time` DESC LIMIT %d, %d',
            array(
                $this->_table,
                DB::field('uid', $uid),
                $page,
                $page_size
            )
        );
    }

    public function updateTypeByRelatedId($relatedId, $data)
    {
        $tmp = '';
        foreach ($data as $k => $v) {
            $tmp .= DB::field($k, $v) . ',';
        }
        $cond = trim($tmp, ',');
        return DB::query('UPDATE %t SET %i WHERE %i', [
           $this->_table,
           $cond,
           DB::field('related_id', $relatedId)
        ]);
    }
}
