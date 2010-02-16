<?php

	require ( 'database.inc.php' );
	if ( isset ( $_GET['show_source'] ) ) { die ( highlight_file ( basename ( $_SERVER['PHP_SELF'] ), 1 ) ); }

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

	function do_query ( $query ) {
		$res = mysql_query ( $query );
		if ( $res )
			return $res;
		else {
			echo ( "QUERY FAILED: $query\n" );
			die ( mysql_error () );
		}
	}

	function UserGetSalt ( $login ) {
		$query = sprintf ( "SELECT user_salt FROM users WHERE user_login = '%s'",
					mysql_real_escape_string ( $login )
				);

		$res = do_query ( $query );
		if ( mysql_num_rows ( $res ) > 0 ) {
			$salt = mysql_fetch_array ( $res );
			return $salt[0];
		} else
			return false;
	}

	function UserHashPassword ( $login, $password, $salt = NULL ) {
		if ( $salt == NULL ) {
			$salt = UserGetSalt ( $login );
			if ( $salt === FALSE )
				return FALSE;
		}

		$salted = sprintf ( "%s:%s:%s",
				_SITE_PASSWORD_SALT_,
				$password,
				$salt
			);

		return hash ( "sha512", $salted );
	}

	function UserValidateLogin ( $login, $password ) {
		$query = sprintf ( "SELECT * FROM users WHERE user_login = '%s' AND user_password = '%s'",
					mysql_real_escape_string ( $login ),
					mysql_real_escape_string ( UserHashPassword ( $login, $password ) )
				);
		$res = do_query ( $query );

		if ( mysql_num_rows ( $res ) == 1 ) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function UserCount () {
		$query = "SELECT COUNT(user_id) AS num_users FROM users";
		$res = do_query ( $query );
		$num = mysql_fetch_array ( $res );
		return $num[0];
	}

	function SaltGenerate ( $desired_length = NULL ) {
		if ( $desired_length == NULL ) {
			$desired_length = rand ( 5, 20 );
		}

		srand ( (double) microtime () * 1000000 );
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`~!@#$%^&*()-=_+[]{}\\|';\":/.,?><";
		$res = "";
		while ( strlen ( $res ) < $desired_length ) {
			$res .= $chars [ rand ( 0, strlen ( $chars ) ) ];
		}
		return $res;
	}

	function UserCreate ( $login, $password ) {
		// create salt
		$salt = SaltGenerate ();
		$password = UserHashPassword ( $login, $password, $salt );

		$query = sprintf ( "INSERT INTO users ( user_login, user_salt, user_password ) VALUES ( '%s', '%s', '%s' )",
					mysql_real_escape_string ( $login ),
					mysql_real_escape_string ( $salt ),
					mysql_real_escape_string ( $password )
				);

		$res = do_query ( $query );

		return ( $res == TRUE );
	}

	function ZoneGetOwner ( $zone ) {
		$query = sprintf ( "SELECT user_login FROM soa JOIN users ON users.user_id = soa.user_id WHERE origin = '%s'",
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
		$query = sprintf ( "SELECT id FROM soa WHERE origin = '%s'",
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
		$query = sprintf ( "SELECT serial FROM soa WHERE origin = '%s'",
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
		$query = sprintf ( "DELETE FROM rr WHERE zone = '%s'",
						mysql_real_escape_string ( $zone_id )
						);
		$res = do_query ( $query );
	}

	function ZoneCreateRR ( $zone_id, $rr ) {
		$query = sprintf ( "INSERT INTO rr ( zone, name, type, data, ttl ) VALUES ( '%s', '%s', '%s', '%s', 5 )",
							mysql_real_escape_string ( $zone_id ),
							mysql_real_escape_string ( $rr['name'] ),
							mysql_real_escape_string ( $rr['type'] ),
							mysql_real_escape_string ( $rr['data'] )
						);
		$res = do_query ( $query );
		return $res;
	}

	function ZoneSetSerial ( $zone, $serial ) {
		$query = sprintf ( "UPDATE soa SET serial='%s' WHERE origin = '%s' LIMIT 1",
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