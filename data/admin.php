<?php
/**
 * Regular and completely not at all evil admin functions
 */

require_once "database.php";
require_once "config.php";
require_once "templates.php";
require_once "user.php";

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
		include_header();
		echo "<h1>Sorry</h1><p>The action you have requested is not currently implemented.</p>";
		include_footer();
	}
}

function do_site_config() {
	/**
	 * This is *only* for Knot126 to use :)
	 */
	
	if (get_name_if_admin_authed()) {
		if (!array_key_exists("submit", $_GET)) {
			include_header();
			echo "<h1>Site configuration</h1>";
			echo "<form action=\"./?a=site_config&submit=1\" method=\"post\">";
			edit_feild("commenting", "select", "Comments", "If comments should be enabled, disabled or closed. Closed will disable new comments but still show old ones, while disabled will stop showing them entirely.", get_config("commenting", "enabled"), true, array("enabled" => "Enabled", "disabled" => "Disabled", "closed" => "Closed"));
			edit_feild("register", "select", "Enable registering", "Weather registering of new accounts should be limited or not.", get_config("register", "anyone"), true, array("anyone" => "Anyone can register", "users" => "Only users can register", "admins" => "Only admins can register", "closed" => "Registering is disabled"));
			edit_feild("enable_login", "select", "Enable logins", "Allow users to log in to the stie.</p><p><b>Warning:</b> If you set this to completely disabled and all admins are logged out, then you need to wait for Knot126 to fix the site.", get_config("enable_login", "users"), true, array("users" => "All users can log in", "admins" => "Only admins can log in", "closed" => "Logging in is disabled"));
			echo "<input type=\"submit\" value=\"Save settings\"/>";
			echo "</form>";
			include_footer();
		}
		else {
			set_config("commenting", $_POST["commenting"], array("enabled", "disabled", "closed"));
			set_config("register", $_POST["register"], array("anyone", "users", "admins", "closed"));
			set_config("enable_login", $_POST["enable_login"], array("users", "admins", "closed"));
			redirect("./?a=site_config");
		}
	}
	else {
		include_header();
		echo "<h1>Sorry</h1><p>The action you have requested is not currently implemented.</p>";
		include_footer();
	}
}
