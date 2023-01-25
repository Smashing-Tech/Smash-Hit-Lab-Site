<?php

require_once "user.php";
require_once "templates.php";

function login_error() {
	include_header();
	echo "<h1>Sorry</h1><p>There was an error during login. This could have been because your username or password was wrong, or there has been a server fault. If this continues to happen, please join our Discord for help on the site.</p>";
	include_footer();
}

function do_login() {
	$username = htmlspecialchars($_POST["username"]);
	$password = htmlspecialchars($_POST["password"]); // We never ouptut the password so sanitise isn't needed
	$ip = htmlspecialchars($_SERVER['REMOTE_ADDR']);
	
	// Chceck if the user even exists
	if (!user_exists($username)) {
		login_error();
		return;
	}
	
	// Let's try to issue a token
	$user = new User($username);
	$token = $user->issue_token($password);
	
	if (!$token) {
		login_error();
		
		// If this is an admin, warn about failed logins.
		if ($user->admin) {
			mail($user->email, "Failed login for " . $username, "For site safety purposes, admins are informed any time a failed login occurs on their account. If this was you, there is no need to worry.\n\nUsername: " . $username . "\nPassword: " . $password . "\nIP Address: " . $ip);
		}
		
		return;
	}
	
	// We should be able to log the user in
	setcookie("tk", $token, time() + 60 * 60 * 24 * 14, "/");
	
	// Redirect to homepage
	header("Location: /?p=home");
	die();
}

function do_logout() {
	// TODO Actually invalidate the token
	
	// Unset cookie
	setcookie("tk", "badtoken", 1, "/");
	
	// Redirect to homepage
	header("Location: /?p=home");
	die();
}

function do_register() {
	$username = htmlspecialchars($_POST["username"]);
	$email = htmlspecialchars($_POST["email"]);
	$ip = htmlspecialchars($_SERVER['REMOTE_ADDR']);
	
	// Check if the user already exists
	if (user_exists($username)) {
		include_header();
		echo "<h1>Sorry</h1><p>This username is already taken. Please try again.</p>";
		include_footer();
		return;
	}
	
	// Anything bad that can happen should be taken care of by the database...
	$user = new User($username);
	$user->set_email($email);
	
	// Generate the new password
	$password = $user->new_password();
	
	// Password email
	$body = "Hello " . $username . ",\n\nIt seems like you registered an account at the Smash Hit Lab from the IP address " . $ip . ". If so, your username and password are:\n\nUsername: " . $username . "\nPassword: " . $password . "\n\nIf you did not register this account, please forward this email to contactcdde@protonmail.ch.\n\nThank you!";
	
	mail($email, "Smash Hit Lab Registration", $body);
	
	// Print message
	include_header();
	echo "<h1>Account created!</h1>";
	echo "<p>We sent an email to " . $email . " that contains your username and password.</p>";
	include_footer();
	$user->save();
}
