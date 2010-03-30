<?php
	define('USERNAME', 'dummy');
	define('PASSWORD', 'dummy');
	$host = 'vpn.hyperbbq.com';
	$port = 443;
	$path = '/dns/client_update.php';
	$use_ssl = true;
	$timeout = 30; // connection timeout
	$useragent = 'HyperBBQ DNS Updater/0.1';

	$boxes = array();
	// get the domain name
	$f[] = explode("\n",file_get_contents("/etc/dnsmasq.conf"));
	// collect dynamically leased IP computers name and IP
	$f[] = explode("\n",file_get_contents("/etc/dhcp.leases"));
	// collect statically mapped IP computers name and IP
	$f[] = explode("\n",file_get_contents("/etc/hosts"));

	foreach($f[0] as $fl) {
		if(preg_match("/^domain=(.*)/", $fl, $domain)) {
			$domain = $domain[1];
			break;
		}
	}

	$a = count($f[1]); $b = count($f[2]);
	$l = $a > $b ? $a : $b;

	$doc = new DOMDocument();
	$doc->formatOutput = true;
	$r = $doc->createElement( "zone" );
	$r->setAttribute("name", "$domain");
	$doc->appendChild( $r );

	for($i = 0; $i < $l; $i++) {
		if($i < $a && strlen($f[1][$i]) > 0) { // dhcp.leases
			$p = preg_split("/[\s]+/", $f[1][$i], -1, PREG_SPLIT_NO_EMPTY);
			// build XML here
			appendToXmlDoc($doc, "a", $p[3], $p[2]);
		}
		if($i < $b && strlen($f[2][$i]) > 0 && !preg_match("/^[\s]*#/", $f[2][$i])) { // hosts
			$p = preg_split("/[\s]+/", $f[2][$i], -1, PREG_SPLIT_NO_EMPTY);
			if(!preg_match("/^127.*|^\:\:1$/", $p[0])) {
				if(preg_match("/(.*).$domain$/i", $p[1], $m)) $p[1] = $m[1]; // remove the local domain from then host string
				// build XML here
				appendToXmlDoc($doc, "a", $p[1], $p[0]);
				for($j = 2; $j < count($p); $j++) {
					if(preg_match("/(.*)\.$domain$/i", $p[$i], $m)) $p[$i] = $m[1]; // remove the local domain from then host string
					if(strlen($t[$i]) > 0 && strcasecmp($t[$i], "localhost") && strcasecmp($t[$i], $t[1])) {
						// build XML here
						appendToXmlDoc($doc, "cname", $p[$i], $p[1]);	
					}
			}
		}
	}

	$data = "login=".urlencode(USERNAME);
	$data .= "&password=".urlencode(PASSWORD);
	$data .= "&dnsxml=".urlencode($doc->saveXML());

	$sslhost = $use_ssl ? "ssl://$host" : $host;
	$fp = fsockopen($sslhost,$port,$errno,$errstr,$timeout) or die("Error: ".$errstr.$errno);
	fputs($fp, "POST $path HTTP/1.1\r\n");
	fputs($fp, "Host: $host\r\n");
	fputs($fp, "User-Agent: $useragent\r\n");
	fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
	fputs($fp, "Content-length: ".strlen($data)."\r\n");
	fputs($fp, "Connection: close\r\n\r\n");
	fputs($fp, $data);

	while(!feof($fp)) $d .= fgets($fp,4096);
// 	while(!feof($fp)) {
// 		$d = fgets($fp,4096);
// 		if(preg_match("/^SUCCESS|^ERROR/i", $d)) { // only display the line that starts with 'SUCCESS' or 'ERROR'
// 			echo $d;
// 		}
// 	}
	fclose($fp);
	echo $d;

	function appendToXmlDoc($doc, $type, $name, $data) {
		$b = $doc->createElement( "rr" );
		$b->setAttribute("type", $type);
		$element = $doc->createElement( "name" );
		$element->appendChild( $doc->createTextNode( $name ) );
		$b->appendChild( $element );
		$element = $doc->createElement( "data" );
		$element->appendChild( $doc->createTextNode( $data ) );
		$b->appendChild( $element );
		$r->appendChild( $b );
	}
?>
