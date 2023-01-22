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
			default:
				break;
		}
	}
	else if (array_key_exists("m", $_GET)) {
		include_header();
		display_mod($_GET["m"]);
		include_footer();
	}
	else {
		include_header();
		include_static_page();
		include_footer();
	}
}

main();
