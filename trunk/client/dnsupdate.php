<?php
	define('DEBUG', TRUE);
	define('USERNAME', 'dummy');
	define('PASSWORD', 'dummy');
	$host = 'www.myvwan.com';
	$port = 443;
	$path = '/dns/client_update.php';
	$use_ssl = true;
	$timeout = 30; // connection timeout
	$useragent = 'MyVWAN DNS Updater/0.1';

	$boxes = array();
	// get the domain name
	$f[] = explode("\n",file_get_contents("/etc/dnsmasq.conf"));
	// collect dynamically leased IP computers name and IP
	$f[] = explode("\n",file_get_contents("/etc/dhcp.leases"));
	// collect statically mapped IP computers name and IP
	$f[] = explode("\n",file_get_contents("/etc/hosts"));
if(defined('DEBUG')) echo "Parsing dnsmasq.conf...";
	foreach($f[0] as $fl) { // parse dnsmasq.conf
		if(preg_match("/^domain=(.*)/", $fl, $domain)) {
			$domain = $domain[1];
			break;
		}
	}
if(defined('DEBUG')) echo "done.\n";
	$a = count($f[1]);
	$b = count($f[2]);

	$doc = new DOMDocument();
	$doc->formatOutput = true;
	$r = $doc->createElement( "zone" );
	$r->setAttribute("name", "$domain");
	$doc->appendChild( $r );
if(defined('DEBUG')) echo "Parsing dhcp.leases...";
	for($i = 0; $i < $a; $i++) {
		if(strlen($f[1][$i]) > 0) { // parse dhcp.leases
			$p = preg_split("/[\s]+/", $f[1][$i], -1, PREG_SPLIT_NO_EMPTY);
			// build XML here
			if($p[3] != "*") appendToXmlDoc($doc, $r, "a", $p[3], $p[2]);
		}
	}
if(defined('DEBUG')) echo "done.\n";
if(defined('DEBUG')) echo "Parsing hosts...";
	for($i = 0; $i < $b; $i++) {
		if(strlen($f[2][$i]) > 0 && !preg_match("/^[\s]*#/", $f[2][$i])) { // parse hosts
			$p = preg_split("/[\s]+/", $f[2][$i], -1, PREG_SPLIT_NO_EMPTY);
			if(!preg_match("/^127.*|^\:\:1$/", $p[0])) { // make sure we haven't just found the loopback entry
				$found_host_name = false;
				for($j = 1; $j < count($p); $j++) { // go through the names associated with this IP
					if(!preg_match("/[\.]|localhost/", $p[$j])) { // make sure that we didn't just find a fully qualified domain name or 'localhost'
						if(!$found_host_name) { // have we found the host name?
							$found_host_name = true;
							// build XML here
							appendToXmlDoc($doc, $r, "a", $p[$j], $p[0]);
						} else { // ...if so, the rest are 'cname' entries
							// build XML here
							appendToXmlDoc($doc, $r, "cname", $p[$j], $p[0]);
						}
					}
				}
			}
		}
	}
if(defined('DEBUG')) echo "done.\n";
	$data = "login=".urlencode(USERNAME);
	$data .= "&password=".urlencode(PASSWORD);
	$data .= "&dnsxml=".urlencode($doc->saveXML());
if(defined('DEBUG')) echo "Sending info...";
	$sslhost = $use_ssl ? "ssl://$host" : $host;
	$fp = fsockopen($sslhost,$port,$errno,$errstr,$timeout) or die("Error: ".$errstr.$errno);
	fputs($fp, "POST $path HTTP/1.1\r\n");
	fputs($fp, "Host: $host\r\n");
	fputs($fp, "User-Agent: $useragent\r\n");
	fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
	fputs($fp, "Content-length: ".strlen($data)."\r\n");
	fputs($fp, "Connection: close\r\n\r\n");
	fputs($fp, $data);
if(defined('DEBUG')) echo "done.\n";
	while(!feof($fp)) $d .= fgets($fp,4096);
// 	while(!feof($fp)) {
// 		$d = fgets($fp,4096);
// 		if(preg_match("/^SUCCESS|^ERROR/i", $d)) { // only display the line that starts with 'SUCCESS' or 'ERROR'
// 			echo $d;
// 		}
// 	}
	fclose($fp);
if(!defined('DEBUG') || strlen($d) > 0) {
	echo "got it.\n", $d;
} else {
	echo "no response from server!\n";
}
	
	function appendToXmlDoc($doc, $r, $type, $name, $data) {
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
