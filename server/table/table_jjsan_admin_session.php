<?php
include_once "table_common.php";
class table_jjsan_admin_session extends table_common
{

    static $_t = 'jjsan_admin_session';

    public function __construct()
    {
		$this->_table = 'jjsan_admin_session';
		$this->_pk    = 'id';
		parent::__construct();
	}

    public function updateSession($session)
    {
        return DB::query('UPDATE %t SET %i WHERE %i', [
            $this->_table,
            DB::field('update_time', date('Y-m-d H:i:s')),
            DB::field('session', $session)
        ]);
	}

}

