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

	function ZoneDeleteRRs ( $zone_id ) {
		$query = sprintf ( "DELETE FROM mydns.rr WHERE zone = '%s'",
						mysql_real_escape_string ( $zone_id )
						);
		$res = do_query ( $query );
	}

	function ZoneCreateRR ( $zone_id, $rr ) {
		$query = sprintf ( "INSERT INTO mydns.rr ( zone, name, type, data, ttl ) VALUES ( '%s', '%s', '%s', '%s', 5 )",
							mysql_real_escape_string ( $zone_id ),
							mysql_real_escape_string ( $rr['name'] ),
							mysql_real_escape_string ( $rr['type'] ),
							mysql_real_escape_string ( $rr['data'] )
						);
		$res = do_query ( $query );
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
			ZoneCreateRR ( $id, $rr );
		}

		ZoneSetSerial ( $zone, (int)$serial + 1 );
		return TRUE;
	}


	undo_magic_quotes ();




?>