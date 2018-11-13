<?php
define( 'APPROOT', dirname( dirname( __FILE__ ) ) );

require APPROOT . "/bin/ports.php";

if( isset( $argv[2] ) ) {
	G::$ports = array( $argv[2], );
}

switch ( @$argv[1] ) {
	case 'start':
		foreach( G::$ports as $port ) {
			$return_value = start( $port );
			echo( "port : $port. $return_value" ) . "\n";
		}
		break;

	case 'restart':
		foreach( G::$ports as $port ) {
			kill( $port );
			$return_value = start( $port );
			echo( "port : $port. $return_value" ) . "\n";
		}
		break;

	case 'stop':
		foreach( G::$ports as $port ) {
			kill( $port );
		}
		break;
	
	default:
		# code...
		break;
}

function kill( $port ) {
	$output = array();
	$cmd = "ps -aux | grep _$port | grep -v grep | awk '{print $2}'";
	exec( $cmd, $output, $return_var );
	sort( $output );
	foreach ( $output as $pid ) {
		exec( "kill -9 $pid" );
	}
}

function start( $port ) {
	$output = array();
	$cmd = "php " . APPROOT . "/daemon/ddzh.php $port";
	exec( $cmd, $output, $return_var );
	return $output[0];
}
