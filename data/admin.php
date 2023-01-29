<?php
/**
 * Regular and completely not at all evil admin functions
 */

require_once "database.php";
require_once "config.php";
require_once "templates.php";
require_once "user.php";
require_once "block.php";

function do_evaluate() {
	/**
	 * This is *only* for Knot126 to use :)
	 */
	
	if (get_name_if_authed() === "knot126") {
		if (!array_key_exists("command", $_POST)) {
			include_header();
			echo "<h1>Run code</h1>";
			echo "<form action=\"./?a=eval\" method=\"post\">";
			edit_feild("command", "textarea", "Code", "The code that you would like to run.", "echo 'Hello, world!';");
			echo "<input type=\"submit\" value=\"Execute\"/>";
			echo "</form>";
			include_footer();
		}
		else {
			include_header();
			echo "<h1>Code result</h1>";
			echo "<pre>";
			eval($_POST["command"]);
			echo "</pre>";
			include_footer();
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function do_site_config() {
	/**
	 * This is *only* for Knot126 to use :)
	 */
	
	$user = get_name_if_admin_authed();
	
	if ($user) {
		if (!array_key_exists("submit", $_GET)) {
			include_header();
			echo "<h1>Site configuration</h1>";
			echo "<form action=\"./?a=site_config&submit=1\" method=\"post\">";
			edit_feild("enable_discussions", "select", "Discussions", "If discussions should be enabled, disabled or closed sitewide. Closed will disable new comments but still show old ones, while disabled will stop showing them entirely. Comments can still be marked as hidden when closed, but cannot when disabled.", get_config("enable_discussions", "enabled"), true, array("enabled" => "Enabled", "disabled" => "Disabled", "closed" => "Closed"));
			edit_feild("register", "select", "Enable registering", "Weather registering of new accounts should be limited or not.", get_config("register", "anyone"), true, array("anyone" => "Anyone can register", "users" => "Only users can register", "admins" => "Only admins can register", "closed" => "Registering is disabled"));
			edit_feild("enable_login", "select", "Enable logins", "Allow users to log in to the stie.</p><p><b>Warning:</b> If you set this to completely disabled and all admins are logged out, then you need to wait for Knot126 to fix the site.", get_config("enable_login", "users"), true, array("users" => "All users can log in", "admins" => "Only admins can log in", "closed" => "Logging in is disabled"));
			echo "<input type=\"submit\" value=\"Save settings\"/>";
			echo "</form>";
			include_footer();
		}
		else {
			set_config("enable_discussions", $_POST["enable_discussions"], array("enabled", "disabled", "closed"));
			set_config("register", $_POST["register"], array("anyone", "users", "admins", "closed"));
			set_config("enable_login", $_POST["enable_login"], array("users", "admins", "closed"));
			alert("Site config was updated by $user", "./?a=site_config");
			redirect("./?a=site_config");
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function do_admin_dashboard() {
	/**
	 * Our really lovely admin dashboard!
	 */
	
	$user = get_name_if_admin_authed();
	
	if ($user) {
		include_header();
		echo "<h1>Admin dashboard</h1>";
		
		echo "<h3>Actions</h3>";
		echo "<ul><li><a href=\"./?a=site_config\">Site configuration</a> &mdash; very basic site options</li><li><a href=\"./?a=user_ban\">Ban user</a> &mdash; user banning form</li><li><a href=\"./?a=delete_mod\">Delete mod page</a> &mdash; remove a mod page from the site</li></ul>";
		
		echo "<h3>Alerts</h3>";
		$un = new UserNotifications($user, "alert");
		$un->display("");
		$un->clear();
		
		include_footer();
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function do_user_ban() {
	$banner = get_name_if_admin_authed();
	
	if ($banner) {
		if (!array_key_exists("handle", $_POST)) {
			include_header();
			echo "<h1>Ban user</h1>";
			
			form_start("./?a=user_ban");
			edit_feild("handle", "text", "Handle", "Handle or username of the user to ban.", "");
			edit_feild("duration", "select", "Duration", "How long to ban this user.", "1w", true, array("21600" => "6 Hours", "86400" => "1 Day", "604800" => "1 Week", "2678400" => "1 Month", "31536000" => "1 Year", "-1" => "Forever", "1" => "Remove ban"));
			edit_feild("reason", "text", "Reason", "Type a short reason why you want to ban this user (optional). <b>This message is not shown to the user at the moment and is for audit logs only.</b>", "");
			echo "<p><b>Note:</b> Any IP addresses assocaited with this user will be blocked for the set duration, up to 3 months.</p>";
			form_end("Ban user");
			
			include_footer();
		}
		else {
			$handle = htmlspecialchars($_POST["handle"]);
			$duration = intval($_POST["duration"]);
			$reason = htmlspecialchars($_POST["reason"]);
			
			$user = new User($handle);
			$user->set_ban($duration);
			
			$duration = $user->unban_date();
			
			alert("User $user->name banned by $banner: $reason", "./?u=$user->name");
			
			// Display success page
			include_header();
			echo "<h1>Account banned</h1><p>The account $handle was successfully banned until $duration.</p>";
			include_footer();
		}
		
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}
