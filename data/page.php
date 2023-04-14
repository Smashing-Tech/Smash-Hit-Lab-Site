<?php

define("SANITISE_HTML", 1);
define("SANITISE_EMAIL", 2);
define("SANITISE_NONE", 3);

define("PAGE_MODE_API", 1);
define("PAGE_MODE_HTML", 2);
define("PAGE_MODE_RAW", 3);

class Page {
	public $title;
	public $body;
	public $mode;
	
	function __construct() {
		$this->title = null;
		$this->body = "";
		$this->mode = PAGE_MODE_HTML;
	}
	
	function set_mode(int $mode) : void {
		$this->mode = $mode;
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
	
	function allow_cache() : void {
		$this->http_header("Cache-Control", "max-age=86400");
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
		
		// NOTE We need account for the fact that the string zero in php is
		// considered truthy :/
		if ($require && !$value && $value !== "0") {
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
	
	function heading(int $level, string $data, ?string $size = null) : void {
		$size = ($size) ? (" style=\"font-size: $size;\"") : ("");
		$this->add("<h$level$size>$data</h$level>");
	}
	
	function para(string $text) : void {
		$this->add("<p>$text</p>");
	}
	
	function global_header() : void {
		assert($this->mode === PAGE_MODE_HTML);
	}
	
	function global_footer() : void {
		assert($this->mode === PAGE_MODE_HTML);
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
		assert($this->mode === PAGE_MODE_HTML);
		
		$data = "";
		
		$data .= $this->body;
		
		return $data;
	}
	
	private function render_json() : string {
		assert($this->mode === PAGE_MODE_API);
		
		return json_encode($body);
	}
	
	function render() : string {
		switch ($this->mode) {
			case PAGE_MODE_HTML:
				return $this->render_html();
				break;
			case PAGE_MODE_API:
				return $this->render_json();
				break;
			default:
				return $this->body;
				break;
		}
	}
	
	function send() : void {
		if ($this->mode === PAGE_MODE_HTML) {
			global $gTitle; $gTitle = $this->title;
			include_header();
		}
		
		echo $this->render();
		
		if ($this->mode === PAGE_MODE_HTML) {
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
	require_once("../data/_footer.html");
}
