<?php
define( "MAX", 10 );
define( 'APPROOT', dirname( dirname( __FILE__ ) ) );

require APPROOT . "/lib/log.class.php";
require APPROOT . "/bin/ports.php";

$logHandler = new CLogFileHandler( APPROOT . "/bin/check.txt" );
Log::Init( $logHandler, 15 );

foreach( G::$ports as $port ) {
	Log::INFO( "==========" );
	$count = 0;
	for ( $i = 0; $i < MAX; $i++ ) { 
		@$client = new swoole_client( SWOOLE_SOCK_TCP );
		if( @!$client->connect( "127.0.0.1", $port, 0.1 ) ) {
			$count++;
			Log::WARN( "port : $port. Connect failed $count " );
		} elseif ( !$client->send( "check\r\n" ) ) {
			$count++;
			Log::WARN( "port : $port. Send failed $count " );
		} elseif( !$client->recv() ) {
			$count++;
			Log::WARN( "port : $port. Recv failed $count " );
		} else {
			$client->close();
			break;
		}
		$client->close();
		sleep( 2 );
	}

	if( $count == MAX ) {
		$cmd = "php " . APPROOT . "/bin/swoole_server.php stop $port";
		Log::INFO( "Already kill swoole process" );

		$output = array();
		$cmd = "php " . APPROOT . "/bin/swoole_server.php start $port";
		exec( $cmd, $output, $return_var );
		Log::INFO( "port : $port . {$output[0]}" );
	}
}

