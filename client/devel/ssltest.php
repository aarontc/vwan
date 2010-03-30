<?php
define('USERNAME', 'dummy');
define('PASSWORD', 'dummy');
$host = 'vpn.hyperbbq.com';
$port = 443;
$path = '/dns/client_update.php';
$type = 'ssl'; // this can be ether 'ssl' for https or '' for http
$timeout = 30; // connection timeout

// $xml = file_get_contents('boxes.xml');
$data = "login=".urlencode(USERNAME);
$data .= "&password=".urlencode(PASSWORD);
// $data .= "&dnsxml=".urlencode($xml);

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