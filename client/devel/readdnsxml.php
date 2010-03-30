<?php
$doc = new DOMDocument();
$doc->load( 'boxes.xml' );

$rrs = $doc->getElementsByTagName( "rr" );
foreach( $rrs as $rr )
{
	$type = $rr->getAttributeNode( "type" );
	$type = $type->value;

	$name = $rr->getElementsByTagName( "name" );
	$name = $name->item(0)->nodeValue;

	$data = $rr->getElementsByTagName( "data" );
	$data = $data->item(0)->nodeValue;

	echo "$type - $name - $data\n";
}
?>
