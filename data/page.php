<?php

require_once "user.php";

function get_page_name() {
	return str_replace("/", ",", str_replace(".", ",", $_GET["p"]));
}

function include_header() {
	require_once("../data/_header.html");
}

function include_static_page() {
	// If we have no static page then don't do anything.
	if (!array_key_exists("p", $_GET)) {
		echo "<h1>Sorry</h1><p>That page does not exist!</p>";
		return;
	}
	
	$page_name = get_page_name();
	$path = "../data/pages/static/" . $page_name . ".html";
	
	if (file_exists($path)) {
		readfile($path);
	}
	else {
		echo "<h1>Sorry</h1><p>That page does not exist!</p>";
	}
}

function include_footer() {
	readfile("../data/_footer.html");
}
