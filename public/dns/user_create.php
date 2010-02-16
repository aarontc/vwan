<?php
	require_once ( 'include.inc.php' );

	if ( isset ( $_POST['new_login'] ) && strlen ( $_POST['new_login'] ) > 1 ) {
		if ( UserCount() < 1 || UserValidateLogin ( $_POST['login'], $_POST['password'] ) == TRUE ) {

			if ( $_POST['new_password'] == $_POST['new_password_confirm'] ) {
				if ( UserCreate ( $_POST['new_login'], $_POST['new_password'] ) ) {
					echo "Success!";
				} else {
					echo "Failed to create new user";
				}
			} else {
				echo ( "New password not confirmed correctly." );
			}
		} else {
			echo ( "Invalid credentials submitted" );
		}
	}
?>
	<form method="post">
<?php
	if ( UserCount() < 1 ) {
		echo "You are the first user to sign up, so no existing authentication is required.";
	} else {
?>
<fieldset>
	<legend>Authentication</legend>

	<label for="login">Login:</label>
	<input type="text" id="login" name="login" value="" /><br />

	<label for="password">Password:</label>
	<input type="password" id="password" name="password" value="" /><br />
</fieldset>
<?php	}


?>
<fieldset>
	<legend>New User</legend>

	<label for="new_login">Login:</label>
	<input type="text" id="new_login" name="new_login" value="" /><br />

	<label for="new_password">Password:</label>
	<input type="password" id="new_password" name="new_password" value="" /><br />

	<label for="new_password_confirm">Confirm Password:</label>
	<input type="password" id="new_password_confirm" name="new_password_confirm" value="" /><br />
</fieldset>
<input type="submit" />
</form>