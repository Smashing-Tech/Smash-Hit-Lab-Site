<?php

class Page {
	public $name;
	public $type;
	public $path;
	public $title;
	
	private $data;
	
	function __construct(string $name) {
		
	}
}

function get_page_name() {
	return str_replace("/", ",", str_replace(".", ",", $_GET["page"]));
}

function include_header() {
	readfile("../data/_header.html");
}

function include_static_page() {
	$page_name = get_page_name();
	
	readfile("../data/pages/static/" . $page_name . ".html");
}

function include_footer() {
	readfile("../data/_footer.html");
}
