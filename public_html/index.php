<?php

require_once "../data/page.php";
require_once "../data/action.php";
require_once "../data/mod.php";

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
			case "edit_account":
				edit_account();
				break;
			default:
				break;
		}
	}
	else if (array_key_exists("m", $_GET)) {
		include_header();
		display_mod($_GET["m"]);
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

main();
