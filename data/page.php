<?php

define("SANITISE_HTML", 1);
define("SANITISE_EMAIL", 2);
define("SANITISE_NONE", 3);

class Page {
	public $title;
	public $body;
	public $header;
	public $footer;
	public $api;
	
	function __construct() {
		$this->title = null;
		$this->body = "";
		$this->header = false;
		$this->footer = false;
		$this->api = false;
	}
	
	function http_header(string $key, string $value) : void {
		// TODO: Defer?
		header("$key: $value");
	}
	
	function cookie(string $key, string $value, int $expire) {
		// TODO: Defer?
		setcookie($key, $value, time() + $expire, "/");
	}
	
	function redirect(string $url) : void {
		$this->http_header("Location", $url);
		die();
	}
	
	function type(string $contenttype) : void {
		$this->http_header("Content-Type", $contenttype);
	}
	
	function info($title = "Done", $desc = "The action completed successfully.") : void {
		include_header();
		echo "<h1>$title</h1><p>$desc</p>";
		include_footer();
		die();
	}
	
	function get(string $key, bool $require = true, ?int $length = null, int $sanitise = SANITISE_HTML, $require_post = false) : ?string {
		$value = null;
		
		if (array_key_exists($key, $_POST)) {
			$value = $_POST[$key];
		}
		
		if (!$require_post && array_key_exists($key, $_GET)) {
			$value = $_GET[$key];
		}
		
		// We consider a blank string not to be a value
		if ($value === "") {
			$value = null;
		}
		
		if ($require && !$value) {
			$this->info("An error occured", "Error: parameter '$key' is required.");
		}
		
		// Validate length
		if ($length && strlen($value) > $length) {
			if ($require) {
				$this->info("Max length exceded", "The parameter '$key' is too long. The max length is $length characters.");
			}
			else {
				return null;
			}
		}
		
		// If we have the value, we finally need to sanitise it.
		if ($value) {
			switch ($sanitise) {
				case SANITISE_HTML: {
					$value = htmlspecialchars($value);
					break;
				}
				case SANITISE_NONE: {
					break;
				}
				default: {
					$value = "";
					break;
				}
			}
		}
		
		return $value;
	}
	
	function set(string $key, mixed $value) {
		/**
		 * Set an output value for JSON mode
		 */
		
		$this->body[$key] = $value;
	}
	
	function has(string $key) : bool {
		return (array_key_exists($key, $_POST) || array_key_exists($key, $_GET));
	}
	
	function title(string $title) : void {
		$this->title = $title;
	}
	
	function heading(int $level, string $data) : void {
		$this->add("<h$level>$data</h$level>");
	}
	
	function para(string $text) : void {
		$this->add("<p>$text</p>");
	}
	
	function global_header() : void {
		$this->header = true;
	}
	
	function global_footer() : void {
		$this->footer = true;
	}
	
	function add(string | Form $data) : void {
		if ($data instanceof Form) {
			$this->body .= $data->render();
		}
		else {
			$this->body .= $data;
		}
	}
	
	private function render_html() : string {
		$data = "";
		
		// Global header
		if ($this->header) {
			//global $gTitle; $gTitle = $this->title;
			//require_once("_header.html");
		}
		
		$data .= $this->body;
		
		// Global footer
		if ($this->footer) {
			//require_once("_footer.html");
		}
		
		return $data;
	}
	
	private function render_json() : string {
		return json_encode($body);
	}
	
	function render() : string {
		return $this->render_html();
	}
	
	function send() : void {
		if ($this->header) {
			global $gTitle; $gTitle = $this->title;
			include_header();
		}
		
		echo $this->render();
		
		if ($this->footer) {
			include_footer();
		}
		
		die();
	}
}

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
