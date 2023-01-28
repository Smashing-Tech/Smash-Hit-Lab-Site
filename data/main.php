<?php

require_once "page.php";
require_once "action.php";
require_once "mod.php";
require_once "news.php";
require_once "admin.php";

function main() {
	if (array_key_exists("a", $_GET)) {
		switch ($_GET["a"]) {
			case "register":
				do_register();
				break;
			case "login":
				do_login();
				break;
			case "logout":
				do_logout();
				break;
			case "edit_mod":
				edit_mod();
				break;
			case "save_mod":
				save_mod();
				break;
			case "delete_mod":
				delete_mod();
				break;
			case "edit_account":
				edit_account();
				break;
			case "save_account":
				save_account();
				break;
			case "update_news":
				update_news();
				break;
			case "save_news":
				save_news();
				break;
			case "eval":
				do_evaluate();
				break;
			case "site_config":
				do_site_config();
				break;
			case "admin_dashboard":
				do_admin_dashboard();
				break;
			case "discussion_update":
				discussion_update();
				break;
			case "discussion_hide":
				discussion_hide();
				break;
			case "discussion_follow":
				discussion_follow();
				break;
			case "notifications":
				check_notifications();
				break;
			default:
				sorry("The action you have requested is not currently implemented.");
				break;
		}
	}
	else if (array_key_exists("m", $_GET)) {
		include_header();
		display_mod($_GET["m"]);
		include_footer();
	}
	else if (array_key_exists("u", $_GET)) {
		include_header();
		display_user($_GET["u"]);
		include_footer();
	}
	else if (array_key_exists("n", $_GET)) {
		include_header();
		display_news($_GET["n"]);
		include_footer();
	}
	else if (array_key_exists("p", $_GET)) {
		include_header();
		include_static_page();
		include_footer();
	}
	else {
		// Redirect to home page
		header("Location: /?p=home");
		die();
	}
}
