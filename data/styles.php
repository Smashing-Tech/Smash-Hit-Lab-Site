<?php

class Styles {
	/**
	 * Site CSS generator
	 * 
	 * "key": "value" pair gets to !(key) -> value
	 */
	
	public $base;
	public $vars;
	public $db;
	
	function __construct() {
		$this->base = file_get_contents("../data/_styles.css");
		
		$this->db = new Database("site");
		
		if ($this->db->has("styles")) {
			$this->vars = (array) $this->db->load("styles")->vars;
		}
		else {
			$this->vars = [
				"PrimaryColour" => "#00aaff",
				"Background" => "#ffffff",
				"DarkBackground" => "#d0d0d0",
				"LightBackground" => "#e8e8e8",
				"TextColour" => "#000000",
			];
		}
	}
	
	function save() : void {
		// We don't save the actual css contents
		$a = $this->base;
		unset($this->base);
		$this->db->save("styles", $this);
		$this->base = $a;
	}
	
	function get(string $key) : string {
		if (array_key_exists($key, $this->vars)) {
			return $this->vars[$key];
		}
		else {
			return "";
		}
	}
	
	function set(string $key, string $value) : void {
		$this->vars[$key] = $value;
	}
	
	function render() : string {
		$out = $this->base;
		
		// Do the variable replacements
		foreach ($this->vars as $key => $value) {
			$out = str_replace("!(" . $key . ")", $this->vars[$key], $out);
		}
		
		return $out;
	}
}

function site_styles_form(Page $page) {
	/**
	 * Creates the styles update form
	 */
	
	$s = new Styles();
	
	$page->global_header();
	$page->heading(1, "Site styles");
	
	$form = new Form("./?a=site-styles&submit=1");
	$form->textbox("PrimaryColour", "Primary colour", "The primary site colour.", $s->get("PrimaryColour"));
	$form->textbox("Background", "Background colour", "The colour of the site's background.", $s->get("Background"));
	$form->textbox("DarkBackground", "Dark background colour", "The colour of the darker site surfaces, like the navbar.", $s->get("DarkBackground"));
	$form->textbox("LightBackground", "Light background colour", "The colour of the site's lighter surfaces, like the comment cards.", $s->get("LightBackground"));
	$form->submit("Update styles");
	
	$page->add($form);
	
	$page->global_footer();
}

function site_styles_update(Page $page) {
	/**
	 * Saves styles
	 */
	
	$s = new Styles();
	
	$s->set("PrimaryColour", $page->get("PrimaryColour", true, 9));
	$s->set("Background", $page->get("Background", true, 9));
	$s->set("DarkBackground", $page->get("DarkBackground", true, 9));
	$s->set("LightBackground", $page->get("LightBackground", true, 9));
	$s->save();
	
	$page->info("Styles saved", "The site styles were updated successfully! You might have to clear your browser's cache in order to see the changes, though.");
}

$gEndMan->add("site-styles", function(Page $page) {
	$user = get_name_if_admin_authed();
	
	if ($user) {
		$submitting = $page->get("submit", false);
		
		if ($submitting) {
			site_styles_update($page);
		}
		else {
			site_styles_form($page);
		}
	}
	else {
		$page->info("Sorry", "The action you have requested is not currently implemented.");
	}
});

$gEndMan->add("site-css", function(Page $page) {
	/**
	 * Render and serve the css file
	 */
	
	$s = new Styles();
	
	$page->type("text/css");
	$page->add($s->render());
	$page->send();
});
