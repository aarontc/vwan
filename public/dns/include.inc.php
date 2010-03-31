<?php

	require ( 'database.inc.php' );
	if ( isset ( $_GET['show_source'] ) ) { die ( highlight_file ( basename ( $_SERVER['PHP_SELF'] ), 1 ) ); }

	ob_start();
	session_start();
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache");

	if (!isset($_SESSION['Flash']))
		$_SESSION['Flash'] = array();

	if (!openlog("myvwan", LOG_ODELAY | LOG_PERROR | LOG_PID, LOG_USER))
		die("Unable to connect to log daemon");



	function mydie($message) {
		syslog(LOG_ALERT, $_SERVER['REMOTE_ADDR'] . " [" . $_SESSION['Username'] . "] die called [" . $message . "]");
		die($message);
	}


	function debug_query($query) {
		//print_r($query);
		return mysql_query($query);
	}

	function do_query ( $query ) {
		$res = debug_query ( $query );
		if ( $res )
			return $res;
		else {
			echo ( "QUERY FAILED: $query\n" );
			die ( mysql_error () );
		}
	}


	function get_script_name() {
		return (basename($_SERVER['PHP_SELF'], '.php'));
	}


	function myexec($cmd, &$output, &$return) {
		syslog(LOG_NOTICE, $_SERVER['REMOTE_ADDR'] . " [" . $_SESSION['Username'] . "] Executing [" . $cmd . "]");
		return exec($cmd, $output, $return);
	}


















	if ( ! function_exists('array_map_recursive') ) {
		function array_map_recursive($function, $data) {
			foreach ( $data as $i => $item ) {
				$data[$i] = is_array($item)
					? array_map_recursive($function, $item)
					: $function($item) ;
			}
			return $data ;
		}
	}


	function undo_magic_quotes( ) {
		if ( get_magic_quotes_gpc( ) ) {
			$_GET = array_map_recursive('stripslashes', $_GET) ;
			$_POST = array_map_recursive('stripslashes', $_POST) ;
			$_COOKIE = array_map_recursive('stripslashes', $_COOKIE) ;
			$_REQUEST = array_map_recursive('stripslashes', $_REQUEST) ;
		}
	}



	function UserValidateLogin($user, $password) {
		$query = sprintf("SELECT user FROM courier.users WHERE user='%s' AND clearpass='%s'",
			mysql_real_escape_string($user),
			mysql_real_escape_string($password)
		);

		$result = debug_query($query);
		if (mysql_num_rows($result) == 1)
			return true;

		return false;
	}


	function ZoneGetOwner ( $zone ) {
		$query = sprintf ( "SELECT owner FROM mydns.soa WHERE origin = '%s'",
					mysql_real_escape_string ( $zone )
				);
		$res = do_query ( $query );

		if ( mysql_num_rows ( $res ) > 0 ) {
			$owner = mysql_fetch_array ( $res );
			return $owner[0];
		} else {
			return FALSE;
		}
	}

	function ZoneGetID ( $zone ) {
		$query = sprintf ( "SELECT id FROM mydns.soa WHERE origin = '%s'",
						mysql_real_escape_string ( $zone )
						);
		$res = do_query ( $query );
		if ( $id = mysql_fetch_array ( $res ) ) {
			return $id[0];
		} else {
			return FALSE;
		}
	}

	function ZoneGetOriginByID ( $zone_id ) {
		$query = sprintf ( "SELECT origin FROM mydns.soa WHERE id = '%s'",
						mysql_real_escape_string ( $zone_id )
						);
		$res = do_query ( $query );
		if ( $id = mysql_fetch_array ( $res ) ) {
			return $id[0];
		} else {
			return FALSE;
		}
	}


	function ZoneGetSerial ( $zone ) {
		$query = sprintf ( "SELECT serial FROM mydns.soa WHERE origin = '%s'",
						mysql_real_escape_string ( $zone )
						);
		$res = do_query ( $query );
		if ( $serial = mysql_fetch_array ( $res ) ) {
			return $serial[0];
		} else {
			return FALSE;
		}
	}

	function ZoneIncrementSerial ( $zone ) {
		$serial = ZoneGetSerial($zone);
		$today = gmdate('Ymd');
		if(substr($serial, 0, 8) != $today) {
			if ( (int)substr($serial, 0, 8) > (int)$today) {
				die("Serial number in future on zone $zone");
			}

			$newserial = $today . '01';
		} else {
			$modcount = (int)substr($serial, 9, 2);
			if ($modcount >= 99) {
				die("Zone was changed more than 99 times today");
			} else {
				$modcount ++;
				$newserial = $today . sprintf("%02d", $modcount);
			}
		}

		ZoneSetSerial($zone, $newserial);
	}


	function ZoneIncrementSerialByID ( $zone_id ) {
		$zone = ZoneGetOriginByID ( $zone_id );
		ZoneIncrementSerial ( $zone );
	}

	function ZoneDeleteRR ( $zone_id, $name ) {
		$query = sprintf ( "DELETE FROM mydns.rr WHERE zone = '%s' AND name = '%s'",
							mysql_real_escape_string ( $zone_id ),
							mysql_real_escape_string ( $name )
						);
		$res = do_query ( $query );
		ZoneIncrementSerialByID ( $zone_id );

		return $res;
	}

	function PTRDelete ( $ip ) {
		$octets = explode('.', $ip);
		$zone = $octets[2] . '.' . $octets[1] . '.' . $octets[0] . '.in-addr.arpa.';
		$host = $octets[3];

		$zoneid = ZoneGetID($zone);
		if ( $zoneid !== false ) {
			ZoneDeleteRR($zoneid, $host);
		}
	}

	function PTRDeleteAllbyZone ( $zone_id ) {
		$query = sprintf ( "SELECT data FROM mydns.rr WHERE zone='%s' AND type='A'",
							mysql_real_escape_string ( $zone_id )
						);
		$res = do_query ( $query );
		while ( $row = mysql_fetch_array ( $res ) ) {
			PTRDelete ( $row[0] );
		}
	}

	function ZoneDeleteRRs ( $zone_id ) {
		PTRDeleteAllByZone ( $zone_id );
		$query = sprintf ( "DELETE FROM mydns.rr WHERE zone = '%s'",
						mysql_real_escape_string ( $zone_id )
						);
		$res = do_query ( $query );
	}

	function ZoneCreateRR ( $zone_id, $rr, $zonename="" ) {
		$query = sprintf ( "INSERT INTO mydns.rr ( zone, name, type, data, ttl ) VALUES ( '%s', '%s', '%s', '%s', 300 )",
							mysql_real_escape_string ( $zone_id ),
							mysql_real_escape_string ( $rr['name'] ),
							mysql_real_escape_string ( $rr['type'] ),
							mysql_real_escape_string ( $rr['data'] )
						);
		$res = do_query ( $query );

		// check if reverse dns zone exists
		if (strtolower($rr['type']) == 'a') {
			$reverse = explode('.', $rr['data']);
			$newzone = $reverse[2] . '.' . $reverse[1] . '.' . $reverse[0] . '.in-addr.arpa.';
			$zoneid = ZoneGetID($newzone);
			if ( $zoneid !== false ) {
				ZoneCreateRR($zoneid, array("name"=>$reverse[3], "type"=>"ptr", "data"=>$rr['name'] . '.' . $zonename));
			}
		}
		return $res;
	}

	function ZoneSetSerial ( $zone, $serial ) {
		$query = sprintf ( "UPDATE mydns.soa SET serial='%s' WHERE origin = '%s' LIMIT 1",
						mysql_real_escape_string ( $serial ),
						mysql_real_escape_string ( $zone )
						);
		$res = do_query ( $query );
		return ( $res );
	}


	function ZoneReplaceRRs ( $zone, $rr_list ) {
		// get SOA ID
		$id = ZoneGetID ( $zone );
		if ( $id === FALSE )
			return FALSE;

		// get serial
		$serial = ZoneGetSerial ( $zone );
		if ( $serial === FALSE )
			return FALSE;

		// drop RRs
		ZoneDeleteRRs ( $id );

		// insert RRs
		foreach ( $rr_list as $rr ) {
			ZoneCreateRR ( $id, $rr, $zone );
		}

		ZoneIncrementSerial ( $zone );
		return TRUE;
	}


	undo_magic_quotes ();




?>