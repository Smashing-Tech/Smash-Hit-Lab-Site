<?php

require_once "user.php";
require_once "templates.php";
require_once "config.php";

function do_login() {
	redirect("./?a=auth-login");
}

function do_logout() {
	// Delete the token on the server
	$db = new Database("token");
	$db->delete($_COOKIE["tk"]);
	
	// TODO Remove the token from the user
	
	// Unset cookie
	setcookie("tk", "badtoken", 1, "/");
	setcookie("lb", "badtoken", 1, "/");
	
	// Redirect to homepage
	redirect("/?p=home");
}

function handle_register() {
	redirect("./?a=auth-register");
}

function do_register() {
	redirect("./?a=auth-register");
}
