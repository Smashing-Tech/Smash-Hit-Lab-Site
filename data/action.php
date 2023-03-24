<?php

require_once "user.php";
require_once "templates.php";
require_once "config.php";

function login_error() {
	include_header();
	echo "<h1>Sorry</h1><p>There was an error during login. This could have been because your username or password was wrong, or there has been a server fault. If this continues to happen, please join our Discord for help on the site.</p>";
	include_footer();
}

function handle_login_availability(string $username) {
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
}

function do_login() {
	inform("Login changes", "We are updating the site codebase, and part is that the login location has changed slightly, please click the button below to start and change bookmarks to the link!<br/><br/><a href=\"./?a=auth-login\"><button>Begin login</button></a>");
	
	$username = htmlspecialchars($_POST["username"]);
	$password = $_POST["password"]; // We should not sanitise the password, bad things happen
	$ip = htmlspecialchars($_SERVER['REMOTE_ADDR']);
	
	if (!isset($_POST["username"]) || !isset($_POST["password"]) || !$_POST["username"] || !$_POST["password"]) {
		sorry("You did not fill out the entire form. Please try again.");
	}
	
	// Check if logins are enabled
	handle_login_availability($username);
	
	// Check if the username is valid
	if (!validate_username($username)) {
		sorry("Your handle isn't valid. Handles can be at most 24 characters and must only use upper and lower case A - Z as well as underscores (_), dashes (-) and fullstops (.).");
	}
	
	// Chceck if the user even exists
	if (!user_exists($username)) {
		login_error();
		return;
	}
	
	// Finally open the user file
	$user = new User($username);
	
	// Check if they are banned first. If so then we don't do the token.
	// Note that admins can bypass blocks.
	if (!$user->is_admin() && $user->is_banned()) {
		$until = $user->unban_date();
		
		if ($until == "forever") {
			sorry("You have been banned from the Smash Hit Lab.");
		}
		
		sorry("You have been banned from the Smash Hit Lab until $until.");
	}
	
	// We also check if this IP has been blocked, assuming the user trying
	// to sign in isn't an admin
	if (!$user->is_admin() && is_ip_blocked($ip)) {
		sorry("You cannot log in from this location.");
	}
	
	// Let's try to issue a token
	$token = $user->issue_token($password);
	
	if (!$token) {
		login_error();
		
		// If this is an admin, warn about failed logins.
		if ($user->admin) {
			mail($user->email, "Failed login for " . $username, "For site safety purposes, admins are informed any time a failed login occurs on their account. If this was you, there is no need to worry.\n\nUsername: " . $username . "\nPassword: " . htmlspecialchars($password) . "\nIP Address: " . $ip);
			
			// Also set a notification for the admin
			notify($user->name, "Login failed from $ip", "/");
		}
		
		return;
	}
	
	// We should be able to log the user in
	setcookie("tk", $token->get_id(), time() + 60 * 60 * 24 * 14, "/");
	setcookie("lb", $token->make_lockbox(), time() + 60 * 60 * 24 * 14, "/");
	
	// Redirect to homepage
	redirect("/?p=home");
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

function handle_register_availability() {
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
}

function handle_register() {
	$email_required = get_config("email_required", true);
	
	if (!isset($_POST["username"]) || (!isset($_POST["email"]) && $email_required) || !isset($_POST["day"]) || !isset($_POST["month"]) || !isset($_POST["year"])) {
		sorry("You did not fill out the entire form. Please try again.");
	}
	
	$username = htmlspecialchars($_POST["username"]);
	$email = (array_key_exists("email", $_POST)) ? htmlspecialchars($_POST["email"]) : "example@example.com";
	$ip = htmlspecialchars($_SERVER['REMOTE_ADDR']);
	$birthdate = strtotime($_POST["day"] . "-" . $_POST["month"] . "-" . $_POST["year"]);
	
	// Check if registering is enabled.
	handle_register_availability();
	
	// Check if the IP has been blocked
	if (is_ip_blocked($ip)) {
		sorry("You cannot create an account from this location.");
	}
	
	// Check if the username is valid
	if (!validate_username($username)) {
		sorry("Your handle isn't valid. Handles can be at most 24 characters and must only use upper and lower case A - Z as well as underscores (_), dashes (-) and fullstops (.).");
	}
	
	// Check if the user already exists
	if (user_exists($username)) {
		include_header();
		echo "<h1>Sorry</h1><p>This username is already taken. Please try again.</p>";
		include_footer();
		return;
	}
	
	// Check if the user is of age
	if ($birthdate > (time() - 60 * 60 * 24 * 365 * 16)) {
		include_header();
		echo "<h1>Sorry</h1><p>You are not old enough to use this website.</p>";
		include_footer();
		return;
	}
	
	// Anything bad that can happen should be taken care of by the database...
	$user = new User($username);
	$user->set_email($email);
	
	// Generate the new password
	$password = $user->new_password();
	
	// Password email
	// Yes there is a more readable version of this available as the original
	// HTML file. :)
	$body = "<!DOCTYPE html>\n<html>\n\t<head>\n\t\t<title>Smash Hit Lab Account Details</title>\n\t\t<style>\n\t\t\t@import url('https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;700&display=swap');\n\t\t\t\n\t\t\t.body {\n\t\t\t\tfont-family: \"Titillium Web\", monospace, sans-serif;\n\t\t\t}\n\t\t\t\n\t\t\t.main {\n\t\t\t\tmargin: 1em auto;\n\t\t\t\tpadding: 0.5em;\n\t\t\t\tborder-radius: 0.5em;\n\t\t\t\tmax-width: min(75%, 50em);\n\t\t\t}\n\t\t\t\n\t\t\tp {\n\t\t\t\tfont-size: 14pt;\n\t\t\t}\n\t\t\t\n\t\t\t.box {\n\t\t\t\tdisplay: grid;\n\t\t\t\tgrid-template-columns: 150px auto;\n\t\t\t}\n\t\t\t\n\t\t\t.box-key {\n\t\t\t\tgrid-column: 1;\n\t\t\t\tgrid-row: 1;\n\t\t\t}\n\t\t\t\n\t\t\t.box-value {\n\t\t\t\tgrid-column: 2;\n\t\t\t\tgrid-row: 1;\n\t\t\t}\n\t\t</style>\n\t</head>\n\t<body class=\"body\">\n\t\t<div class=\"main\">\n\t\t\t<p>Hello $username!</p>\n\t\t\t<p>It seems like you registered an account at the <a href=\"https://smashhitlab.000webhostapp.com/?p=home\">Smash Hit Lab</a> from the IP address <a href=\"https://www.shodan.io/host/$ip\">$ip</a>. If it wasn't you, please report this email to <a href=\"mailto:contactcdde@protonmail.ch\">contactcdde@protonmail.ch</a> and do not mark it as spam.</p>\n\t\t\t<p>Assuming this was you, the username and password for your account is:</p>\n\t\t\t<div class=\"box\">\n\t\t\t\t<div class=\"box-key\"><p><b>Username</b></p></div>\n\t\t\t\t<div class=\"box-value\"><p>$username</p></div>\n\t\t\t</div>\n\t\t\t<div class=\"box\">\n\t\t\t\t<div class=\"box-key\"><p><b>Password</b></p></div>\n\t\t\t\t<div class=\"box-value\"><p>$password</p></div>\n\t\t\t</div>\n\t\t\t<p>You can <a href=\"https://smashhitlab.000webhostapp.com/?p=login\">log in here</a>.</p>\n\t\t\t<p>Thank you!</p>\n\t\t</div>\n\t</body>\n</html>\n";
	
	if ($email_required) {
		mail($email, "Smash Hit Lab Registration", $body, array("MIME-Version" => "1.0", "Content-Type" => "text/html; charset=utf-8"));
	}
	
	// Alert the admins of the new account
	alert("New user account $username was registered", "./?u=$username");
	
	// Save the user data
	$user->save();
	
	// Print message
	if ($email_required) {
		include_header();
		echo "<h1>Account created!</h1>";
		echo "<p>We sent an email to " . $email . " that contains your username and password.</p>";
		include_footer();
	}
	else {
		inform("Account created!", "Your account was created successfully!</p><p>Your password is: " . htmlspecialchars($password));
	}
}

function do_register() {
	if (!array_key_exists("submit", $_GET)) {
		include_header();
		
		form_start("./?a=register&submit=1");
		form_textbox("handle", "Handle", "This is the name that you will be identified by. It should be between 3 and 24 characters of only A-Z, 0-9 and dashes (<code>-</code>) or underscores (<code>_</code>).");
		form_textbox("email", "Email", "Please give your email address. We will send an email containing your login details, so make sure it is the right address!");
		form_textbox("day", "day", "TODO: DESC.");
		form_textbox("month", "month", "TODO: DESC.");
		form_textbox("year", "year", "TODO: DESC.");
		form_end("Create account");
		
		include_footer();
	}
	else {
		handle_register();
	}
}
