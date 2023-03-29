<?php

require_once "endpoint.php";

require_once "action.php";
require_once "admin.php";
require_once "auth.php";
require_once "block.php";
require_once "config.php";
require_once "database.php";
require_once "discussion.php";
require_once "form.php";
require_once "mod.php";
require_once "news.php";
require_once "notifications.php";
require_once "page.php";
require_once "setup.php";
require_once "site.php";
require_once "styles.php";
require_once "templates.php";
require_once "user.php";

function handle_action(string $action, Page $page) {
	switch ($action) {
	// ---- USER ACCOUNTS ---- //
		case "register": do_register(); break; // DEPRECATED
		case "login": do_login(); break; // DEPRECATED
		case "logout": do_logout(); break; // DEPRECATED
	// ---- MOD PAGES ---- //
		case "mod_update":
		/*DEPRECATED*/ case "edit_mod": edit_mod(); break;
		case "save_mod": save_mod(); break;
		case "mod_history": mod_history(); break;
		case "mod_delete":
		case "delete_mod": delete_mod(); break;
		case "list_mods": list_mods(); break;
	// ---- MORE USER ACCOUNT STUFF ---- //
		case "edit_account": edit_account(); break;
		case "save_account": save_account(); break;
		case "account_delete": account_delete(); break;
		case "user_email": user_email(); break;
	// ---- NEWS STUFF ---- //
		case "update_news": update_news(); break;
		case "save_news": save_news(); break;
	// ---- DISCUSSIONS ---- //
		case "discussion_update": discussion_update(); break;
		case "discussion_hide": discussion_hide(); break;
		case "discussion_delete": discussion_delete(); break;
		case "discussion_follow": discussion_follow(); break;
		case "discussion_lock": discussion_lock(); break;
		case "discussion_view": discussion_view(); break;
		case "discussion_poll": discussion_poll(); break;
	// ---- MISC USER PAGES ---- //
		case "notifications": check_notifications(); break;
	// ---- ADMIN ACTION PAGES ---- //
		case "site_config": do_site_config(); break;
		case "backup_db": do_backup_db(); break;
		case "storage_download": do_storage_download(); break;
		case "storage_list": do_storage_list(); break;
		case "user_roles": do_user_roles(); break;
		case "user_ban": do_user_ban(); break;
		case "user_delete": do_user_delete(); break;
		case "user_verify": user_verify(); break;
		case "admin_dashboard": do_admin_dashboard(); break;
		case "alerts": do_admin_alerts(); break;
		case "send_notification": do_send_notification(); break;
		// Transitioning to using Endpoint Manager
		default: {
			global $gEndMan; $okay = $gEndMan->run($action, $page);
			
			if (!$okay) {
				$page->info("Sorry", "The action you have requested is not currently implemented.");
			}
			/// @hack This is here for now b/c we can't have it elsewhere right now
			else {
				$page->send();
			}
			
			break;
		}
	}
}

function main() {
	/**
	 * Called in the index.php script
	 */
	
	$page = new Page();
	
	if (array_key_exists("a", $_GET)) {
		handle_action($_GET["a"], $page);
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
	// DEPRECATED: Static pages are deprecated, should use news articles now!
	else if (array_key_exists("p", $_GET)) {
		include_header();
		include_static_page();
		include_footer();
	}
	else {
		// Redirect to home page
		header("Location: /?n=home");
		die();
	}
}
