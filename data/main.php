<?php

require_once "page.php";
require_once "action.php";
require_once "mod.php";
require_once "news.php";
require_once "wiki.php";
require_once "admin.php";

function handle_action($action) {
	switch ($action) {
	// ---- USER ACCOUNTS ---- //
		case "register":
			do_register();
			break;
		case "login":
			do_login();
			break;
		case "logout":
			do_logout();
			break;
	// ---- MOD PAGES ---- //
		case "mod_update":
		case "edit_mod":
			edit_mod();
			break;
		case "save_mod":
			save_mod();
			break;
		case "mod_history":
			mod_history();
			break;
		case "mod_delete":
		case "delete_mod":
			delete_mod();
			break;
		case "list_mods":
			list_mods();
			break;
	// ---- WIKI ---- //
		case "wiki_display": wiki_display(); break;
		case "wiki_update": wiki_update(); break;
		case "wiki_history": wiki_history(); break;
		case "wiki_delete": wiki_delete(); break;
	// ---- MORE USER ACCOUNT STUFF ---- //
		case "edit_account":
			edit_account();
			break;
		case "save_account":
			save_account();
			break;
	// ---- NEWS STUFF ---- //
		case "update_news":
			update_news();
			break;
		case "save_news":
			save_news();
			break;
	// ---- DISCUSSIONS ---- //
		case "discussion_update":
			discussion_update();
			break;
		case "discussion_hide":
			discussion_hide();
			break;
		case "discussion_delete":
			discussion_delete();
			break;
		case "discussion_follow":
			discussion_follow();
			break;
	// ---- MISC USER PAGES ---- //
		case "notifications":
			check_notifications();
			break;
	// ---- ADMIN ACTION PAGES ---- //
		case "site_config":
			do_site_config();
			break;
		case "backup_db":
			do_backup_db();
			break;
		case "storage_download":
			do_storage_download();
			break;
		case "user_ban":
			do_user_ban();
			break;
		case "user_verify":
			user_verify();
			break;
		case "admin_dashboard":
			do_admin_dashboard();
			break;
		case "send_notification":
			do_send_notification();
			break;
		default:
			sorry("The action you have requested is not currently implemented.");
			break;
	}
}

function main() {
	if (array_key_exists("a", $_GET)) {
		handle_action($_GET["a"]);
	}
	else if (array_key_exists("m", $_GET)) {
		display_mod();
	}
	else if (array_key_exists("u", $_GET)) {
		display_user($_GET["u"]);
	}
	else if (array_key_exists("n", $_GET)) {
		display_news($_GET["n"]);
	}
	else if (array_key_exists("w", $_GET)) {
		wiki_display();
	}
	// DEPRECATED: Static pages are deprecated, should use news articles now!
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
