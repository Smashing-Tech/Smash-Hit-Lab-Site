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
				"PrimaryColour" => "#107cff",
				"PrimaryColour.Darker" => "#0b5ab4",
				"PrimaryColour.Hover" => "#0b5ab4",
				"PrimaryColour.Text" => "#ffffff",
				"LightBackground" => "#cde4ff",
				"LightBackground.Text" => "#000000",
				"Background" => "#ebf4ff",
				"Background.Text" => "#000000",
				"DarkBackground" => "#a5cfff",
				"DarkBackground.Text" => "#000000",
				"DarkBackground.TextHover" => "#000000",
				"Button.Glow.Offset" => "0.2em",
				"Button.Glow.Radius" => "0.4em",
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
	
	$form->textbox("PrimaryColour", "PrimaryColour", "", $s->get("PrimaryColour"));
	$form->textbox("PrimaryColour-Darker", "PrimaryColour.Darker", "", $s->get("PrimaryColour.Darker"));
	$form->textbox("PrimaryColour-Hover", "PrimaryColour.Hover", "", $s->get("PrimaryColour.Hover"));
	$form->textbox("PrimaryColour-Text", "PrimaryColour.Text", "", $s->get("PrimaryColour.Text"));
	$form->textbox("LightBackground", "LightBackground", "", $s->get("LightBackground"));
	$form->textbox("LightBackground-Text", "LightBackground.Text", "", $s->get("LightBackground.Text"));
	$form->textbox("Background", "Background", "", $s->get("Background"));
	$form->textbox("Background-Text", "Background.Text", "", $s->get("Background.Text"));
	$form->textbox("DarkBackground", "DarkBackground", "", $s->get("DarkBackground"));
	$form->textbox("DarkBackground-Text", "DarkBackground.Text", "", $s->get("DarkBackground.Text"));
	$form->textbox("DarkBackground-TextHover", "DarkBackground.TextHover", "", $s->get("DarkBackground.TextHover"));
	$form->textbox("Button-Glow-Offset", "Button.Glow.Offset", "", $s->get("Button.Glow.Offset"));
	$form->textbox("Button-Glow-Radius", "Button.Glow.Radius", "", $s->get("Button.Glow.Radius"));
	
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
	$s->set("PrimaryColour.Darker", $page->get("PrimaryColour-Darker", true, 9));
	$s->set("PrimaryColour.Hover", $page->get("PrimaryColour-Hover", true, 9));
	$s->set("PrimaryColour.Text", $page->get("PrimaryColour-Text", true, 9));
	$s->set("LightBackground", $page->get("LightBackground", true, 9));
	$s->set("LightBackground.Text", $page->get("LightBackground-Text", true, 9));
	$s->set("Background", $page->get("Background", true, 9));
	$s->set("Background.Text", $page->get("Background-Text", true, 9));
	$s->set("DarkBackground", $page->get("DarkBackground", true, 9));
	$s->set("DarkBackground.Text", $page->get("DarkBackground-Text", true, 9));
	$s->set("Button.Glow.Offset", $page->get("Button-Glow-Offset", true, 9));
	$s->set("Button.Glow.Radius", $page->get("Button-Glow-Radius", true, 9));
	
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
