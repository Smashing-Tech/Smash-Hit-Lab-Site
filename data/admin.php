<?php
/**
 * Regular and completely not at all evil admin functions
 */

function do_site_config() {
	/**
	 * Site config form
	 */
	
	$user = get_name_if_admin_authed();
	
	if ($user) {
		if (!array_key_exists("submit", $_GET)) {
			include_header();
			echo "<h1>Site configuration</h1>";
			echo "<form action=\"./?a=site_config&submit=1\" method=\"post\">";
			
			echo "<h3>Community settings</h3>";
			edit_feild("sitename", "text", "Site name", "The name of your community.", get_config("sitename", "My New Community"));
			
			echo "<h3>Features</h3>";
			edit_feild("enable_news", "select", "News", "Enable or disabled the news articles feature.", get_config("enable_news", "enabled"), true, ["enabled" => "Enabled", "disabled" => "Disabled"]);
			edit_feild("enable_wiki", "select", "Wiki", "Enable or disabled the wiki features.", get_config("enable_wiki", "enabled"), true, ["enabled" => "Enabled", "disabled" => "Disabled"]);
			edit_feild("enable_forum", "select", "Forums", "Enable or disabled the forum feature.", get_config("enable_forum", "disabled"), true, ["disabled" => "Disabled"]);
			edit_feild("enable_discussions", "select", "Discussions", "If discussions should be enabled, disabled or closed sitewide. Closed will disable new comments but still show old ones, while disabled will stop showing them entirely. Comments can still be marked as hidden when closed, but cannot when disabled.", get_config("enable_discussions", "enabled"), true, array("enabled" => "Enabled", "disabled" => "Disabled", "closed" => "Closed"));
			
			echo "<h3>Connections</h3>";
			edit_feild("discord_webhook", "text", "Discord webhook", "The discord webhook that will be used for alerts.", get_config("discord_webhook", ""));
			
			echo "<h4>Labbyware Native Auth</h4>";
			edit_feild("register", "select", "Enable registering", "Weather registering of new accounts should be limited or not.", get_config("register", "anyone"), true, array("anyone" => "Anyone can register", "users" => "Only users can register", "admins" => "Only admins can register", "closed" => "Registering is disabled"));
			edit_feild("enable_login", "select", "Enable logins", "Allow users to log in to the stie.</p><p><b>Warning:</b> If you set this to completely disabled and all admins are logged out, then you need to wait for Knot126 to fix the site.", get_config("enable_login", "users"), true, array("users" => "All users can log in", "verified" => "Verified users and admins can log in", "admins" => "Only admins can log in", "closed" => "Logging in is disabled"));
			
			echo "<input type=\"submit\" value=\"Save settings\"/>";
			echo "</form>";
			include_footer();
		}
		else {
			// Community
			set_config("sitename", htmlspecialchars($_POST["sitename"]));
			
			// Features
			set_config("enable_news", $_POST["enable_news"], array("enabled", "disabled"));
			set_config("enable_wiki", $_POST["enable_wiki"], array("enabled", "disabled"));
			set_config("enable_forum", $_POST["enable_forum"], array("enabled", "disabled"));
			set_config("enable_discussions", $_POST["enable_discussions"], array("enabled", "disabled", "closed"));
			
			// Security
			set_config("register", $_POST["register"], array("anyone", "users", "admins", "closed"));
			set_config("enable_login", $_POST["enable_login"], array("users", "verified", "admins", "closed"));
			
			set_config("discord_webhook", $_POST["discord_webhook"]);
			
			alert("Site config was updated by @$user", "./?a=site_config");
			redirect("./?a=site_config");
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function admin_action_item(string $url, string $icon, string $title) {
	echo "<div style=\"display: inline-block; width: 150px; height: 125px; text-align: center; padding: 0.75em; margin: 0.5em; background: var(--colour-background-light); border-radius: 0.5em;\">";
		echo "<a href=\"$url\">";
			echo "<p style=\"font-size: 24pt;\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px; font-size: 32pt;\">$icon</span></p>";
			echo "<p>$title</p>";
		echo "</a>";
	echo "</div>";
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
		
		echo "<h4>Site and maintanance</h4>";
		admin_action_item("./?a=site_config", "settings", "Settings");
		admin_action_item("./?a=site-styles", "style", "Site styles");
		admin_action_item("./?a=send_notification", "notifications_active", "Send notification");
		admin_action_item("./?a=backup_db", "backup", "Create backup");
		admin_action_item("./?a=storage_list", "inventory", "Site storage");
		admin_action_item("./?a=alerts", "new_releases", "Alerts");
		
		echo "<h4>Users and contributed content</h4>";
		admin_action_item("./?a=user_ban", "gavel", "Ban user");
		admin_action_item("./?a=user_delete", "person_off", "Delete user");
		admin_action_item("./?a=user_roles", "security", "Edit roles");
		admin_action_item("./?a=delete_mod", "delete", "Delete mod page");
		admin_action_item("./?a=admin-impersonate&handle=smashhitlab", "business", "Brand account");
		
		include_footer();
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function do_admin_alerts() {
	/**
	 * ADMINNNNNNNNNNNNNNNNNNNNNNNN!!!!!!!!!
	 */
	
	$user = new User(get_name_if_authed());
	
	if ($user->is_mod()) {
		include_header();
		echo "<h1>Alerts</h1>";
		echo "<div class=\"comment-card\"><p>Alerts will be removed on 7 May 2023 in favour of the Discord webhook.</p></div>";
		
		$un = new UserNotifications($user->name, "alert");
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
			echo "<h1>Ban or unban user</h1>";
			
			$have_handle = false;
			
			if (array_key_exists("handle", $_GET)) {
				$have_handle = true;
			}
			
			form_start("./?a=user_ban");
			edit_feild("handle", "text", "Handle", "Handle or username of the user to ban.", $have_handle ? $_GET["handle"] : "", !$have_handle);
			edit_feild("duration", "select", "Duration", "How long to ban this user.", "1w", true, array("21600" => "6 Hours", "86400" => "1 Day", "604800" => "1 Week", "2678400" => "1 Month", "31536000" => "1 Year", "-1" => "Forever", "1" => "Remove ban"));
			edit_feild("reason", "text", "Reason", "Type a short reason why you want to ban this user (optional). <b>This message is not shown to the user at the moment and is for audit logs only.</b>", "");
			echo "<p><b>Note:</b> Any IP addresses assocaited with this user will be blocked for the set duration, up to 3 months. We do not block IPs for longer as they can change periodically.</p>";
			form_end("Set ban status");
			
			include_footer();
		}
		else {
			$handle = htmlspecialchars($_POST["handle"]);
			$duration = intval($_POST["duration"]);
			$reason = htmlspecialchars($_POST["reason"]);
			
			$user = new User($handle);
			
			// Check if the user is admin
			if ($user->is_admin() || $user->is_mod()) {
				alert("@$banner tried to ban @$user->name", "./?u=$banner");
				sorry("You cannot ban a staff member. This action has been reported.");
			}
			
			$user->set_ban($duration);
			
			$until = $user->unban_date();
			
			// Unbanning
			if ($duration === 0 || $duration === 1) {
				alert("User $user->name unbanned by $banner\n\nReason: $reason", "./?u=$user->name");
				
				// Display success page
				include_header();
				echo "<h1>Account unbanned</h1><p>The account $handle was successfully unbanned.</p>";
				include_footer();
			}
			// Banning
			else {
				alert("User $user->name banned by $banner\n\nReason: $reason", "./?u=$user->name");
				
				// Display success page
				include_header();
				echo "<h1>Account banned</h1><p>The account $handle was successfully banned until $until.</p>";
				include_footer();
			}
		}
		
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function do_user_delete() {
	$banner = get_name_if_admin_authed();
	
	if ($banner) {
		if (!array_key_exists("handle", $_POST)) {
			include_header();
			echo "<h1>Delete user</h1>";
			
			$have_handle = false;
			
			if (array_key_exists("handle", $_GET)) {
				$have_handle = true;
			}
			
			form_start("./?a=user_delete");
			edit_feild("handle", "text", "Handle", "Handle or username of the user to delete.", $have_handle ? $_GET["handle"] : "", !$have_handle);
			edit_feild("reason", "text", "Reason", "Type a short reason why you want to delete this user (required). <b>This message is not shown to the user at the moment and is for audit logs only.</b>", "");
			form_end("Delete this user");
			
			include_footer();
		}
		else {
			$handle = htmlspecialchars($_POST["handle"]);
			$reason = htmlspecialchars($_POST["reason"]);
			
			$user = new User($handle);
			
			if (strlen($reason) < 3) {
				sorry("Please type a better ban reason.");
			}
			
			if ($user->is_admin()) {
				alert("Admin @$banner tried to delete staff member @$user->name\n\nReason: $reason", "./?u=$banner");
				sorry("You cannot delete a staff member. This action has been reported.");
			}
			
			if ($user->is_verified()) {
				alert("Admin @$banner tried to delete verified user @$user->name\n\nReason: $reason", "./?u=$banner");
				sorry("You cannot delete a verified member. This action has been reported.");
			}
			
			$user->delete();
			
			alert("Admin @$banner deleted user @$user->name\n\nReason: $reason", "./?u=$banner");
			
			include_header();
			echo "<h1>Account deleted</h1><p>The account $handle was successfully deleted.</p>";
			include_footer();
		}
		
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function do_send_notification() {
	/**
	 * Send a notification to everyone who uses the website.
	 */
	
	$user = get_name_if_admin_authed();
	
	if ($user) {
		if (!array_key_exists("submit", $_GET)) {
			include_header();
			echo "<h1>Send notification</h1>";
			form_start("./?a=send_notification&submit=1");
			edit_feild("title", "text", "Title", "Title of the notification to send to users.", "");
			edit_feild("url", "text", "Link", "The URL that the notification should lead to.", "");
			echo "<p><b>Warning:</b> This notification will be sent to everyone who has an account! Please think carefully before using this feature.</p>";
			form_end("Send notification");
			include_footer();
		}
		else {
			$db = new Database("user");
			$users = $db->enumerate();
			
			$title = $_POST["title"];
			$link = $_POST["url"];
			
			notify_many($users, $title, $link);
			
			alert("Global notification sent by @$user\n\nTitle: $title\nLink: $link", "./?u=$user");
			redirect("./?a=notifications");
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

$gEndMan->add("notifications-send-multicast", function(Page $page) {
	$handle = get_name_if_authed();
	$user = $handle ? (new User($handle)) : null;
	
	if ($user && ($user->has_role('multicast') || $user->is_admin())) {
		if (!$page->has("submit")) {
			$form = new Form("./?a=notifications-send-multicast&submit=1");
			$form->textbox("title", "Title", "Title of the notifiaction");
			$form->textbox("link", "Link", "The link the notifiaction leads to");
			$form->textbox("users", "Users", "A comma seperated list of users' handles this notification will be sent to.");
			$form->submit("Send notifiaction");
			
			$page->heading(1, "Send multicast notifiaction");
			$page->add($form);
		}
		else {
			$title = $page->get("title");
			$link = $page->get("link");
			$handles = $page->get("users");
			
			notify_many(parse_comma_array($handles), $title, $link);
			alert("Multicast notifiaction sent by $handle to " . htmlspecialchars($handles), "./?u=$handle");
			
			$page->redirect("./?n=home");
		}
	}
	else {
		$page->info("Sorry!", "You cannot send multicast notifications: not authed as a admin or missing 'multicast' privleges.");
	}
});

function do_backup_db() {
	/**
	 * Back up the site database.
	 */
	
	$user = get_name_if_admin_authed();
	
	if ($user) {
		if (!array_key_exists("submit", $_GET)) {
			include_header();
			echo "<h1>Backup database</h1>";
			form_start("./?a=backup_db&submit=1");
			echo "<p><b>Note:</b> This operation might take a long time to preform.</p>";
			form_end("Backup database");
			include_footer();
		}
		else {
			$path = htmlspecialchars(basename(backup_database()));
			
			include_header();
			echo "<h1>Backup is done</h1><p>The database was backed up to <code>$path</code>.</p><p><a href=\"./?a=storage_download&file=$path\">Click here to download the backup</a></p>";
			include_footer();
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function do_storage_download() {
	/**
	 * Download a file from the site storage.
	 */
	
	$user = get_name_if_admin_authed();
	
	if ($user) {
		if (array_key_exists("file", $_GET)) {
			download_file("../data/store/" . str_replace("/", ".", $_GET["file"]));
		}
		else {
			sorry("You didn't specify what file you wanted to download...");
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function do_storage_list() {
	/**
	 * Download a file from the site storage.
	 */
	
	$user = get_name_if_admin_authed();
	
	if ($user) {
		include_header();
		
		echo "<h1>Site storage</h1>";
		
		$files = list_folder("../data/store/");
		
		for ($i = 0; $i < sizeof($files); $i++) {
			$name = $files[$i];
			$name_url = urlencode($name);
			
			echo "<p><a href=\"./?a=storage_download&file=$name_url\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">description</span> $name</a></p>";
		}
		
		include_footer();
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function do_user_roles() {
	$actor = get_name_if_admin_authed();
	
	if ($actor) {
		if (!array_key_exists("submit", $_GET)) {
			include_header();
			echo "<h1>Edit user roles</h1>";
			
			$have_handle = false;
			
			if (array_key_exists("handle", $_GET)) {
				$have_handle = true;
			}
			
			form_start("./?a=user_roles&submit=1");
			edit_feild("handle", "text", "Handle", "Handle or username of the user to update.", $have_handle ? $_GET["handle"] : "", !$have_handle);
			edit_feild("role", "select", "Role", "Which role to set this user to.", "1w", true, [
				"headmaster" => "Headmaster",
				"admin" => "Administrator",
				"mod" => "Moderator",
				"none" => "None"
			]);
			edit_feild("reason", "text", "Reason", "Type a short reason why you want to change this user's role. (required)", "");
			form_end("Set role");
			
			include_footer();
		}
		else {
			$handle = htmlspecialchars($_POST["handle"]);
			$reason = htmlspecialchars($_POST["reason"]);
			$role = htmlspecialchars($_POST["role"]);
			
			$user = new User($handle);
			$actor = new User($actor);
			
			if (strlen($reason) < 3) {
				sorry("Please type a better ban reason.");
			}
			
			if ($role == "headmaster" && !$actor->has_role("headmaster")) {
				sorry("Only another headmaster can grant the headmaster role.");
			}
			
			if ($user->has_role("headmaster") && ($actor->name !== $user->name)) {
				sorry("Only the person who has the headmaster role can demote themselves.");
			}
			
			if ($user->has_role("impersonateable")) {
				sorry("This user has the impersonateable role and must be manually updated in the database.");
			}
			
			if ($user->get_role_score() > $actor->get_role_score()) {
				sorry("You cannot change the role of $user->name because you do not have a role that is at least equal to that role.");
			}
			
			if (!$actor->has_role("admin")) {
				sorry("You must be an admin to change roles!");
			}
			
			switch ($role) {
				case "headmaster": {
					$user->set_roles(array("headmaster", "admin", "staff"));
					break;
				}
				case "admin": {
					$user->set_roles(array("admin", "staff"));
					break;
				}
				case "mod": {
					$user->set_roles(array("mod", "staff"));
					break;
				}
				case "none": {
					$user->set_roles(array());
					break;
				}
				default: {
					sorry("Invalid role type: $role.");
				}
			}
			
			alert("User @$user->name has role set to \"$role\" by @$actor->name\n\nReason: $reason");
			
			include_header();
			echo "<h1>Roles updated</h1><p>The role for $handle was set to $role!</p>";
			include_footer();
		}
		
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

$gEndMan->add("admin-impersonate", function(Page $page) {
	$actor = get_name_if_admin_authed();
	
	if ($actor) {
		$handle = $page->get("handle");
		
		if (!user_exists($handle)) {
			$page->info("No such user", "You cannot impersonate this user becuase they do not exist.");
		}
		
		$user = new User($handle);
		
		if (!$user->has_role("impersonateable")) {
			$page->info("Not impersonateable", "You cannot impersonate this user becuase they lack the impersonateable role.");
		}
		
		// Make the token without consent
		$token = $user->make_token();
		
		// Move old cookies
		$page->cookie("tk1", $page->get_cookie("tk"));
		$page->cookie("lb1", $page->get_cookie("lb"));
		
		// Set the cookies
		$page->cookie("tk", $token->get_id(), 86400);
		$page->cookie("lb", $token->make_lockbox(), 86400);
		
		// Redirect to user page
		$page->redirect("./?u=$handle");
	}
	else {
		$page->info("Sorry", "The action you have requested is not currently implemented.");
	}
});

$gEndMan->add("admin-return", function(Page $page) {
	$tk = $page->get_cookie("tk1");
	
	if ($tk) {
		$page->cookie("tk", $page->get_cookie("tk1"));
		$page->cookie("lb", $page->get_cookie("lb1"));
		$page->cookie("tk1", "invalid", 0);
		$page->cookie("lb1", "invalid", 0);
	}
	
	$page->redirect("./?n=home");
});

$gEndMan->add("admin-migrate-users", function(Page $page) {
	$user = get_name_if_admin_authed();
	
	if ($user) {
		
	}
	else {
		$page->info("You can't fucking do that !!!!");
	}
});
