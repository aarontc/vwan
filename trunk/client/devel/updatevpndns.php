<?php
	define('USERNAME', 'dummy');
	define('PASSWORD', 'dummy');
	$host = 'vpn.hyperbbq.com';
	$port = 443;
	$path = '/dns/client_update.php';
	$type = 'ssl'; // this can be ether 'ssl' for https or '' for http
	$timeout = 30; // connection timeout

	// get the domain name
	$f = explode("\n",file_get_contents("/etc/dnsmasq.conf"));
	foreach($f as $fl) {
		if(preg_match("/^domain=(.*)/", $fl, $domain)) {
			$domain = $domain[1];
			break;
		}
	}

	$boxes = array();
	// collect dynamically leased IP computers name and IP
	$f = explode("\n",file_get_contents("/etc/dhcp.leases"));
	foreach($f as $fl) {
		$fl = trim($fl);
		if(strlen($fl) < 1) continue;
		$t = explode(" ",$fl);
		$boxes [] = array(
			'type' => "a",
			'name' => "$t[3]",
			'data' => "$t[2]"
		);
	}

	// collect statically mapped IP computers name and IP
	$f = explode("\n",file_get_contents("/etc/hosts"));
	foreach($f as $fl) {
		$fl = trim($fl);
		if(strlen($fl) < 1 || preg_match("/^[\s]*#/", $fl)) continue;
		$t = preg_split("/[\s]+/", $fl, -1, PREG_SPLIT_NO_EMPTY);
		if(preg_match("/^127.*|^\:\:1$/", trim($t[0]))) continue;
		$t[1] = trim($t[1]);
		if(preg_match("/(.*).$domain$/i", $t[1], $m)) $t[1] = $m[1];
		$boxes [] = array(
			'type' => "a",
			'name' => $t[1],
			'data' => trim($t[0])
		);
		for($i = 2; $i < count($t); $i++) {
			$t[$i] = trim($t[$i]);
			if(preg_match("/(.*)\.$domain$/i", $t[$i], $m)) {
				$t[$i] = $m[1];
			}
			if(strlen($t[$i]) > 0 && strcasecmp($t[$i], "localhost") && strcasecmp($t[$i], $t[1])) {
				$boxes[] = array(
					'type' => "cname",
					'name' => "$t[$i]",
					'data' => "$t[1]"
				);
			}
		}
	}
	
// create and output XML
	$doc = new DOMDocument();
	$doc->formatOutput = true;
	$r = $doc->createElement( "zone" );
	$r->setAttribute("name", "$domain");
	$doc->appendChild( $r );
	foreach( $boxes as $box )
	{
		$b = $doc->createElement( "rr" );
		$b->setAttribute("type", $box['type']);
		foreach( $box as $key => $val )
		{
			if($key == 'type') continue;
			$element = $doc->createElement( $key );
			$element->appendChild(
				$doc->createTextNode( $val )
			);
			$b->appendChild( $element );
		}
		$r->appendChild( $b );
	}
	
	$data = "login=".urlencode(USERNAME);
	$data .= "&password=".urlencode(PASSWORD);
	$data .= "&dnsxml=".urlencode($doc->saveXML());

	$sslhost = !strcasecmp($type,"ssl") ? "ssl://$host" : $host;
	$fp = fsockopen($sslhost,$port,$errno,$errstr,$timeout) or die("Error: ".$errstr.$errno);
	fputs($fp, "POST $path HTTP/1.1\r\n");
	fputs($fp, "Host: $host\r\n");
	fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
	fputs($fp, "Content-length: ".strlen($data)."\r\n");
	fputs($fp, "Connection: close\r\n\r\n");
	fputs($fp, $data);

	while(!feof($fp)) $d .= fgets($fp,4096);
	fclose($fp);
	echo $d;
?>
