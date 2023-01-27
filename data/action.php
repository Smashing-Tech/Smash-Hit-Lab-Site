<?php

require_once "user.php";
require_once "templates.php";
require_once "config.php";

function login_error() {
	include_header();
	echo "<h1>Sorry</h1><p>There was an error during login. This could have been because your username or password was wrong, or there has been a server fault. If this continues to happen, please join our Discord for help on the site.</p>";
	include_footer();
}

function do_login() {
	$username = htmlspecialchars($_POST["username"]);
	$password = $_POST["password"]; // We should not sanitise the password, bad things happen
	$ip = htmlspecialchars($_SERVER['REMOTE_ADDR']);
	
	// Check if logins are enabled
	switch (get_config("enable_login", "users")) {
		case "closed":
			sorry("Logging in has been disabled. Please check Discord for updates.");
			break;
		case "admins":
			$u = new User($username);
			
			if (!$u->is_admin()) {
				sorry("We have disabled logging in for most users at the moment. Please join our Discord for any updates.");
			}
			break;
		case "users":
			break;
		default:
			sorry("The site operator has not configured the site corrently. To be safe, no one is allowed to log in. Please have the hosting party delete the invalid file at \"data/db/site/settings\", then logins will be enabled again.");
			break;
	}
	
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
			mail($user->email, "Failed login for " . $username, "For site safety purposes, admins are informed any time a failed login occurs on their account. If this was you, there is no need to worry.\n\nUsername: " . $username . "\nPassword: " . htmlspecialchars($password) . "\nIP Address: " . $ip);
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
	
	// Check if registering is enabled.
	switch (get_config("register", "anyone")) {
		case "closed":
			sorry("User account registration has been disabled for the moment. Please try again later and make sure to join the Discord for updates.");
			break;
		case "admins":
			if (!get_name_if_admin_authed()) {
				sorry("We have disabled new account creation for most users at the moment. Please join our Discord and contact an admin to have them create an account for you.");
			}
			break;
		case "users":
			if (!get_name_if_authed()) {
				sorry("Only existing users can create new accounts at the moment. If you have a friend who uses this site, have them enter your desired username and email for you. Otherwise, please ask staff to create an account for you.");
			}
			break;
		case "anyone":
			break;
		default:
			sorry("The site operator has not configured the site corrently. To be safe, accounts will not be created. Please have the hosting party delete the invalid file at \"data/db/site/settings\", then user account creation will be enabled again.");
			break;
	}
	
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
