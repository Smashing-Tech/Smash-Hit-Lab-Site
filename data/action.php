<?php

require_once "user.php";

function do_register() {
	// Anything bad that can happen should be taken care of by the database...
	$user = new User($_POST["username"]);
	$user->set_email($_POST["email"]);
	echo "PASSWORD IS: " . $user->new_password(); // TODO Just how we do this for now...
	$user->save();
}
