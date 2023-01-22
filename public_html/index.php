<?php

require_once "../data/page.php";
require_once "../data/action.php";

function main() {
	include_header();
	
	if (array_key_exists("a", $_GET)) {
		switch ($_GET["a"]) {
			case "register":
				do_register();
				break;
			case "login":
				do_login();
				break;
			default:
				break;
		}
	}
	
	include_static_page();
	include_footer();
}

main();
