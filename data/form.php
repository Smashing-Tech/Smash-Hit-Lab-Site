<?php

class Form {
	/**
	 * Makes a nice form
	 * 
	 * TODO Split the templates from the form function that so they can be used
	 * outside of forms if needed.
	 */
	
	public $body;
	
	function __construct(string $url, string $method = "post") {
		$method = htmlspecialchars($method);
		$url = htmlspecialchars($url);
		$this->body = "<form action=\"$url\" method=\"$method\">";
	}
	
	function container(string $title, string $desc, string $data) {
		/**
		 * The basic container for everything else.
		 */
		
		$a = $this->body;
		
		$a .= "<div class=\"mod-edit-property\">";
			$a .= "<div class=\"mod-edit-property-label\">";
				// If there is no title there is little reason for a desc. as well.
				if ($title) {
					$a .= "<h4>$title</h4>";
					$a .= "<p>$desc</p>";
				}
			$a .= "</div>";
			$a .= "<div class=\"mod-edit-property-data\">";
				$a .= "<p>$data</p>";
			$a .= "</div>";
		$a .= "</div>";
		
		$this->body = $a;
	}
	
	function textbox(string $name, string $title, string $desc, string $value = "", bool $enabled = true) {
		$s = ($enabled) ? "" : " readonly";
		$data = "<input type=\"text\" name=\"$name\" placeholder=\"$title\" value=\"$value\" $s/>";
		
		$this->container($title, $desc, $data);
	}
	
	function password(string $name, string $title, string $desc, string $value = "", bool $enabled = true) {
		$s = ($enabled) ? "" : " readonly";
		$data = "<input type=\"password\" name=\"$name\" placeholder=\"$title\" value=\"$value\" $s/>";
		
		$this->container($title, $desc, $data);
	}
	
	function textaera(string $name, string $title, string $desc, string $value = "", bool $enabled = true) {
		$s = ($enabled) ? "" : " readonly";
		$data = "<textarea name=\"$name\" $s>$value</textarea>";
		
		$this->container($title, $desc, $data);
	}
	
	function select(string $name, string $title, string $desc, array $options, bool $enabled = true) {
		$data = "<select name=\"$name\">";
		$k = array_keys($options);
		
		for ($i = 0; $i < sizeof($k); $i++) {
			$key = $k[$i];
			$val = $options[$k[$i]];
			$selected = ($key == $value) ? "selected" : "";
			
			$data .= "<option value=\"$key\" $selected>$val</option>";
		}
		
		$data .= "</select>";
		
		$this->container($title, $desc, $data);
	}
	
	function submit(string $text = "Continue") {
		$sak = user_get_sak();
		$data = "<input type=\"hidden\" name=\"key\" value=\"$sak\">";
		$data .= "<input type=\"submit\" value=\"$text\"/>";
		
		$this->container("", "", $data);
		$this->body .= "</form>";
	}
	
	function render() {
		return $this->body;
	}
}