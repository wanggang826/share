<?php
if (PHP_SAPI !== 'cli') {
    exit('need cli');
}

ini_set('date.timezone', 'PRC');

define( 'APPROOT', dirname( dirname( __FILE__ ) ) );

require_once APPROOT . '/daemon/cfg.inc.php';

if( isset( $argv[1] ) && is_numeric($argv[1]) ) {
	define( 'SERVICE_PORT', $argv[1] );
} else {
	exit( "No port Or Port is not a number\n" );
}

require APPROOT . "/lib/log.class.php";

class G{
	public static $developer_list = array(
		'lysc' => 1,
	);
}

$logHandler = new CLogFileHandler( APPROOT . "/logs/" . date('Y-m-d-H') . "_" . SERVICE_PORT . ".txt" );
Log::Init( $logHandler, 15 );

$mgr_cli = new swoole_client( SWOOLE_SOCK_TCP );

$isNotWorking = @!$mgr_cli->connect('127.0.0.1', SERVICE_PORT, SERVICE_TIMEOUT );

if ( $isNotWorking ) {
	Log::INFO("Connect Failed! Daemon not running, starting daemon now.\n");
	echo "Connect Failed! Daemon not running, starting daemon now.\n";

	$serv = new swoole_server("0.0.0.0", SERVICE_PORT); 

	$serv->set(array(
		"reactor_num" => 4,
		'daemonize' => 1,
		'log_file' => APPROOT . '/daemon/swoole.php',
		'log_level' => 4, // log level >= WARNING
		'package_eof' => "\r\n",
		'open_eof_split' => 1,
		'heartbeat_idle_time' => HB_IDLE_TIME,
		'heartbeat_check_interval' => HB_CK_INTERVAL,
		'open_tcp_keepalive' => 1,
		'tcp_keepidle' => 10,
		'tcp_keepcount' => 3,
		'tcp_keepinterval' => 5,
		"task_worker_num" => 6,
		"task_ipc_mode" => 2,
		"message_queue_key" => 8888,
		"task_tmpdir" => APPROOT . "/daemon/swoole.tmp",
		"worker_num" => 1,
	));

	$sid_table = new swoole_table( 8192 );
	$sid_table->column( "fd", swoole_table::TYPE_INT, 8 );
	$sid_table->create();

	$fd_table = new swoole_table( 8192 );
	$fd_table->column( "runlevel", swoole_table::TYPE_INT, 1 );
	$fd_table->column( "sid", swoole_table::TYPE_INT, 4 );
	$fd_table->column( "time", swoole_table::TYPE_STRING, 20 );
	$fd_table->create();

	$serv->fd = $fd_table;
	$serv->sid = $sid_table;

	//$serv->client = array();

	$serv->on( 'WorkerStart', 'WorkerStart' );

	$serv->on( 'Connect', "Connect" );

	$serv->on( 'Receive', "Receive" );

	$serv->on( 'Close', "Close" );

	$serv->on( "Start", "Start" );

	$serv->on( "WorkerStop", "WorkerStop" );

	$serv->on( "WorkerError", "WorkerError" );

	$serv->on( "ManagerStart", "ManagerStart" );

	$serv->on( "Finish", "Finish" );

	$serv->on( "Task", "Task" );

	$serv->start();

} else {
	switch ( @$argv[2] ) {
		case 'stop':
			# Stoping Service...
			Log::INFO("Service is going down by CMD STOP.");
			$mgr_cli->send( "stop\r\n" );
			$data = $mgr_cli->recv();

			if ( !$data ) {
				die( "swoole server error" );
			} else {
				echo $data;
			}
			break;

		case 'reload':
			$mgr_cli->send( "reload\r\n" );
			$data = $mgr_cli->recv();

			if ( !$data ) {
				die( "swoole server error" );
			} else {
				echo $data;
			}
			break;

		default:
			# code...
			echo "Server is running or parameter error.\r\n";
			break;
	}
}

function Task( $serv, $task_id, $src_worker_id, $data ) {
	Log::INFO( "#{$serv->worker_id}#{$task_id}#{$src_worker_id} Get message:" . print_r( $data, 1 ) );
	switch ( $data['type'] ) {
		case 'curl':
			$request = json_encode( $data['request'] );
			$fd = $data['fd'];
			$sid = $data['sid'];

			$response = https_request( URL, $request );
			Log::INFO( print_r( $response, 1 ) );

			if ( @isset( $response['stationid'] ) ) {
				$sid = $response['stationid'];
				$serv->fd->set( $fd, array( "sid" => $sid ) );

				$serv->sid->set( $sid, array( "fd" => $fd ) );
			}

			$ack = "";
			foreach ( $response as $key => $value ) {
				$ack .= strtoupper( $key ) . ":" . $value . ";";
			}

			$ack .= "CHKSUM:";
			$crc = cal_crc( $ack );
			$ack .= "$crc\r\n";

			if ( $serv->send( $fd, $ack ) ) {
				Log::INFO( "Send " . $ack . " to " . "sid: $sid - fd: $fd." );
			} else {
				$errcode = $serv->getLastError();
				Log::INFO( "#{$serv->worker_id}#{$task_id}#{$src_worker_id} Send failed; errcode: $errcode" );
			}
			break;

		case 'cmd':
			$fd = $data['fd'];
			switch ( $data['cmd'] ) {
				case 'list':
					$serv->send( $fd, "FD\tSID\tFROM\tLOGIN_TIME\r\n" );

					foreach( $serv->connections as $connection ) {
						$fdinfo = $serv->connection_info( $connection );
						$time = $serv->fd->get( $connection )['time'];
						$ip = $fdinfo["remote_ip"];
						$port = $fdinfo['remote_port'];
						$address = "$ip:$port";
						//$sid = $serv->client[$connection]['sid'];
						$conn_sid = $serv->fd->get( $connection )['sid'];

						$serv->send( $fd, "$connection\t$conn_sid\t$address\t$time\r\n" );
					}
					$serv->send( $fd, "total: " . ( count( $serv->connections ) ) . "\r\n" );
					break;

				case 'list_sid':
					$serv->send( $fd, "SID\tFD\r\n" );

					foreach( $serv->sid as $sid => $em ) {
						$serv->send( $fd, "$sid\t{$em['fd']}\r\n" );
					}
					break;

				default:
					break;
			}
			break;

		default:
			break;
	}
}

function Finish( $serv, $task_id, $data ) {

}

function Start( $serv ) {
	cli_set_process_title( "master_" . SERVICE_PORT );
}

function ManagerStart( $serv ) {
	cli_set_process_title( "manager_" . SERVICE_PORT );
}

function WorkerStop( $serv, $worker_id ) {
	Log::INFO( "Worker $worker_id : stop." );
}

function WorkerError( $serv, $worker_id, $worker_pid, $exit_code ) {
	Log::WARN( "Worker $worker_id : error. pid : $worker_pid, exitcode : $exit_code ." );
}

function WorkerStart( $serv, $worker_id ) {
	// Log time change must be first line
	Log::ChangeHandle( APPROOT . '/logs/' . date('Y-m-d-H') . "_" . SERVICE_PORT . ".txt" );

	Log::INFO( "worker {$worker_id} start." );
	cli_set_process_title( "worker{$worker_id}_" . SERVICE_PORT );

	$next_hour_diff = strtotime(date("Y-m-d H:00:00")) + 3600 - time();
	// Log::WARN("xxstart $worker_id");
	$serv->after($next_hour_diff*1000, function() use($serv, $worker_id) {
		// first time
		Log::ChangeHandle( APPROOT . '/logs/' . date('Y-m-d-H') . "_" . SERVICE_PORT . ".txt" );
		// Log::WARN("xxfirst $worker_id");
		$serv->tick( 3600*1000, function() use($serv, $worker_id) {
			Log::ChangeHandle( APPROOT . '/logs/' . date('Y-m-d-H') . "_" . SERVICE_PORT . ".txt" );
			// Log::WARN("xxchange $worker_id");
		});
	});
}

function Connect( $serv, $fd ) {
	$connection_info = $serv->connection_info($fd);

	Log::INFO( date( 'Y-m-d H:i:s' ) . sprintf(" A Client( %s - %s# %s:%s ) Connected.",
		$serv->worker_id,
		$fd, 
		$connection_info['remote_ip'], 
		$connection_info['remote_port']
	));

	//$serv->client[$fd]['runlevel'] = 0;
	//$serv->client[$fd]['time'] = date( 'Y-m-d H:i:s' );
	$serv->fd->set( $fd, array( "time" => date( 'Y-m-d H:i:s' ), "runlevel" => 0 ) );
}

function Receive( $serv, $fd, $from_id, $data ) {
	$data = trim( $data );
	$sid = $serv->fd->get( $fd )['sid'];

	$connection_info = $serv->connection_info($fd);

	if ( $connection_info['remote_ip'] == '127.0.0.1' ) {

		switch ( $data ) {

			case 'stop':

				Log::INFO( " Got Command From MGR Client : Shutdown.\n" );
				$serv->send( $fd, "After swoole server will stop.\n" );

				foreach ($serv->connections as $fd) {
					$serv->close($fd);
				}

				$serv->shutdown();
				break;

			case 'reload':
				Log::INFO( " Got Command From MGR Client : Reload.\n" );
				if( $serv->reload() ) {
					$serv->send( $fd, "reload succeed.\n" );
				} else {
					$serv->send( $fd, "reload failed.\n" );
				}
				break;

			case 'check':
				$serv->send( $fd, "OK" );
				break;

			case 'clean':
				$serv->task( 'clean' );
				break;

			default:
				# code...
				break;
		}

        //LOG::INFO('|'.$fd.'|'.$from_id.'|'.$data);
	}

	$info = dataParse( $data );
	Log::INFO( "Get message from $from_id - $fd " . print_r( $info, 1 ) );

	switch ( $info['type'] ) {
		case 'CMD':
			switch ( $serv->fd->get( $fd )['runlevel'] ) {
				case "0" :
						switch ( $info['param'][0] ) {
							case 'login':
								if ( G::$developer_list[$info['param'][1]] ) {
									$serv->fd->set( $fd, array( "runlevel" => 1, ) );

									$serv->send( $fd, "\r\nServer: Welcome! What Can I Do For You:)\r\n" );

									Log::INFO( sprintf( "Developer %s has login. ( %s# %s:%s )", 
										$info['param'][1], 
										$fd,
										$connection_info['remote_ip'], 
										$connection_info['remote_port']
									));
								}
								break;

							case 'isonline':
								if( $serv->sid->exist( $info['param'][1] ) ) {
									$serv->send( $fd, "1" );
								} else {
									$serv->send( $fd, "0" );
								}
								break;

							case 'close':
								$sid = $info['param'][1];

								if( $serv->sid->exist( $info['param'][1] ) ) {
									$sid_fd = $serv->sid->get( $sid )['fd'];
									$serv->close( $sid_fd );
									$serv->send( $fd, "1" );
								} else {
									$serv->send( $fd, "0" );
								}

								break;

							default:
								Log::INFO( "[#".  $fd . "]Client data: " . $data );
								$serv->send( $fd, "$data\r\n" );
								break;
						}
					break;

				case "1" :
					$data = array();
					$data['fd'] = $fd;
					$data['type'] = "cmd";

					switch ( $info['param'][0] ) {
						case 'close':
							$serv->close( $info['param'][1] );
							break;

						case 'list':
							$data['cmd'] = "list";
							$serv->task( $data );
							break;

						case 'list_sid':
							$data['cmd'] = "list_sid";
							$serv->task( $data );
							break;

						case 'stats':
							foreach ( $serv->stats() as $key => $value ) {
								$serv->send( $fd, "$key: $value\r\n" );
							}
							break;

						default:
							$serv->send( $fd, "Server: Unknown Command.\r\n" );
							break;
					}

					break;

				default:
					# code...
					break;
			}

			break;

		case 'DATA':
			@$chksum = $info['param']['CHKSUM'];
			$crc = cal_crc( $info['original'] );
			if( $crc == $chksum ) {

				$request = array(
					'data' => $info['param'],
					'stationid' => $sid
					);

				$taskData = array();
				$taskData['type'] = "curl";
				$taskData['request'] = $request;
				$taskData['fd'] = $fd;
				$taskData['sid'] = $sid;

				$task_id = $serv->task( $taskData );
				Log::INFO( "#{$serv->worker_id}#{$fd} task_id: $task_id. {$info['param']['ACT']}" );
			} else {
				Log::INFO( "Crc error. crc: $crc" );
			}
			break;

		case 'SERVER':
			$cmd = $info['param']['data'] . ";CHKSUM:";
			$crc = cal_crc( $cmd );
			$cmd .= "$crc\r\n";

			$sid = $info['param']['stationid'];

			if ( $serv->sid->exist( $sid ) ) {
				$fd = $serv->sid->get( $sid )['fd'];
				Log::INFO( "Send $cmd to sid: $sid - fd: $fd." );
				$serv->send( $fd, $cmd );
			} else {
				Log::INFO( "$sid in not online" );
				$serv->send( $fd, "$sid in not online" );
			}
			break;

		default:
			# code...
			break;
	}
}

function Close( $serv, $fd ) {
	Log::INFO( "Client $fd : Closed.\n" );

	$sid = $serv->fd->get( $fd )['sid'];
	$sid_fd = $serv->sid->get( $sid )['fd'];

	$serv->fd->del( $fd );

	if( $sid != "0" && $fd == $sid_fd ) {
		$serv->sid->del( $sid );
	}
}

function dataParse( $data ) {
	if ( is_object( json_decode( $data ) ) ) {
		$data = json_decode( $data, true );

		$info = array(
			'type' => 'SERVER',
			'param' => $data,
			);

	} elseif ( strpos( $data, ';' ) ) {
		$groups = explode( ';', $data );
		$time = null;

		foreach ( $groups as $group ) {
			$key_value = explode( ':', $group );
			@$params[$key_value[0]] = $key_value[1];
			if( $key_value[0] == "TIME" ) {
				$time = $key_value;
			}
		} 

		$info = array(
			'type' => 'DATA',
			'param' => $params,
			'time' => $time,
			'original' => substr( $data, 0, strripos( $data, ':' ) + 1 ),
			);
	} else {
		$info = array(
			'type' => "CMD",
			'param' => explode( " ", $data ),
			);
	}

	return $info;
}

function https_request( $url, $data = null ) {
	$curl = curl_init();
	curl_setopt( $curl, CURLOPT_URL, $url );
	curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, FALSE );
	curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, FALSE );

	if( !empty( $data ) ) {
		curl_setopt( $curl, CURLOPT_POST, 1 );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
	}

	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );

	$output = curl_exec( $curl );

	return json_decode( $output, 1);
}

function cal_crc ( $str, $crc = 0x385da3ca, $poly = 0x9cb5e37d ) {

	$len = strlen($str);

	$arr = str_split($str);

	$mod = $len % 4; 

	if ( $mod > 0 ) {
		$mod = 4 - $mod;
	}

	$len = $len + $mod; $i = 0; 

	while ( $len-- ) {
		$xbit = 0b10000000; 

		if ( $len < $mod ) {
			$data = 0;
		} else {
			$data = ord($arr[$i++]); 
		}

		for ( $bits = 0; $bits < 8; $bits++ ) { 
			if ( $crc & 0x80000000 ) {
				$crc = $crc << 1; 
				$crc = $crc ^ $poly;
			} else {
				$crc = $crc << 1; 
			}

			if ( $data & $xbit ) {
				$crc = $crc ^ $poly;
			}
			$xbit = $xbit >> 1; 
		}
	}
	return sprintf( "%08x", $crc & 0xFFFFFFFF );
}
