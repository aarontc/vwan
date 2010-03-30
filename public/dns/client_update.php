<?php

	require_once ( 'include.inc.php' );

	if ( UserValidateLogin ( $_POST['login'], $_POST['password'] ) === TRUE ) {
		if ( isset ( $_POST['dnsxml'] ) ) {
			$doc = new DOMDocument();
			$doc -> loadXML ( $_POST['dnsxml'] );
			$zones = $doc -> getElementsByTagName ( "zone" );
			foreach ( $zones as $zone ) {
				$name = $zone -> getAttributeNode ( "name" );
				$zone_name = $name->value;
				if ( substr ( $zone_name, -1 ) != "." )
					$zone_name .= ".";

				if ( $_POST['login'] == ZoneGetOwner ( $zone_name ) ) {

					$rrs = $zone -> getElementsByTagName ( "rr" );
					$rr_list = array ();
					foreach ( $rrs as $rr ) {
						$type = $rr->getAttributeNode( "type" );
						$type = $type->value;

						$name = $rr->getElementsByTagName( "name" );
						$name = $name->item(0)->nodeValue;

						$data = $rr->getElementsByTagName( "data" );
						$data = $data->item(0)->nodeValue;

						$rr_list[] = array ( 'type' => $type,
											'name' => $name,
											'data' => $data
											);
					}
					if ( ZoneReplaceRRs ( $zone_name, $rr_list ) ) {
						echo "SUCCESS: Zone '$zone_name' replaced succesfully. New serial number: " . ZoneGetSerial ( $zone_name ) . "\n";
					}
				} else {
					echo ( "ERROR: Zone '$zone_name' is not owned by '" .  $_POST['login'] . "'\n" );
				}
			}
		} else {
			echo "ERROR: No DNS-XML data posted\n";
		}
	} else {
		if ( isset ( $_POST['login'] ) ) {
			echo "ERROR: Invalid credentials\n";
		}
	}

	if ( strpos ( $_SERVER['HTTP_USER_AGENT'], "MyVWAN DNS Updater" ) === FALSE ) {
?>
<form method="post">
<fieldset>
	<legend>Authentication</legend>

	<label for="login">Login:</label>
	<input type="text" id="login" name="login" value="<?= htmlentities ( $_POST['login'] ) ?>" /> (Case sensitive!) <br/>

	<label for="password">Password:</label>
	<input type="password" id="password" name="password" value="" /> <br/>
</fieldset>
<fieldset>
	<legend>Update Data</legend>
	<label for="dnsxml">DNS-XML Data:</label>
	<textarea id="dnsxml" name="dnsxml" rows="10" cols="50"><?= htmlentities ( $_POST['dnsxml'] ) ?></textarea>
</fieldset>
<input type="submit" />
</form>
<?php
	}
?>