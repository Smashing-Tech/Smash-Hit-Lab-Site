<?php

require_once "database.php";
require_once "user.php";
require_once "templates.php";

function parse_comma_array(string $s) : array {
	return explode(",", $s);
}

function create_comma_array(array $s) : string {
	return join(",", $s);
}

function create_comma_array_nice(array $s) : string {
	return join(", ", $s);
}

class ModPage {
	public $package;
	public $name;
	public $creators;
	public $wiki;
	public $description;
	public $download;
	public $code;
	public $tags;
	public $version;
	public $updated;
	public $security;
	
	function __construct(string $package) {
		$db = new Database("mod");
		
		if ($db->has($package)) {
			$mod = $db->load($package);
			
			$this->package = $mod->package;
			$this->name = $mod->name;
			$this->creators = $mod->creators;
			$this->wiki = $mod->wiki;
			$this->description = $mod->description;
			$this->download = $mod->download;
			$this->code = $mod->code;
			$this->tags = $mod->tags;
			$this->version = $mod->version;
			$this->updated = $mod->updated;
			$this->security = $mod->security;
			$this->status = $mod->status;
		}
		else {
			$this->package = $package;
			$this->name = "Untitled Mod";
			$this->creators = array();
			$this->wiki = null;
			$this->description = null;
			$this->download = null;
			$this->code = null;
			$this->tags = array();
			$this->version = null;
			$this->updated = time();
			$this->security = "No potentially insecure modifications";
			$this->status = "Released";
		}
	}
	
	function save() {
		$db = new Database("mod");
		$db->save($this->package, $this);
	}
	
	function display() {
		echo "<h1>" . ($this->name ? $this->name : $this->package) . "</h1>";
		
		if (get_name_if_authed()) {
			echo "<p><a href=\"./?a=edit_mod&m=$this->package\"><button>Edit mod info</button></a></p>";
		}
		
		echo "<h4>Description</h4>";
		echo "<p>" . (($this->description) ? $this->description : "<i>No description yet.</i>") . "</p>";
		
		echo "<h4>Other info</h4>";
		mod_property("Download", "A link to where the mod can be downloaded.", $this->download);
		mod_property("Version", "The latest version of this mod.", $this->version);
		mod_property("Creators", "The people who created this mod.", create_comma_array_nice($this->creators));
		mod_property("Security", "A short statement on this mod's security.", $this->security);
		if ($this->wiki) {
			mod_property("Wiki article", "A relevant wiki article about the mod.", $this->wiki);
		}
		if ($this->code) {
			mod_property("Source code", "A link to where the source code for a mod can be found.", $this->code);
		}
		mod_property("Status", "A short description of the mod's development status.", $this->status);
		mod_property("Package", "The name of the mod's APK or IPA file.", $this->package);
		mod_property("Updated", "The unix timestamp for the last time this page was updated.", date("Y-m-d H:i:s", $this->updated));
	}
	
	function display_edit() {
		echo "<h1>Editing " . ($this->name ? $this->name : $this->package) . "</h1>";
		echo "<form action=\"./?a=save_mod&amp;m=$this->package\" method=\"post\">";
		edit_feild("package", "text", "Package", "The name of the mod's APK or IPA file.", $this->package, false);
		edit_feild("name", "text", "Name", "The name that will be displayed with the mod.", $this->name);
		edit_feild("creators", "text", "Creators", "The people who created this mod.", create_comma_array($this->creators));
		edit_feild("wiki", "text", "Wiki article", "A relevant wiki article about the mod.", $this->wiki);
		edit_feild("description", "textarea", "Description", "One or two paragraphs that describe the mod.", str_replace("<br/>", "\n", $this->description));
		edit_feild("download", "text", "Download", "A link to where the mod can be downloaded.", $this->download);
		edit_feild("code", "text", "Source code", "A link to where the source code for a mod can be found.", $this->code);
		edit_feild("tags", "text", "Tags", "Keywords and categorical description of this mod.", create_comma_array($this->tags));
		edit_feild("version", "text", "Version", "The latest version of this mod.", $this->version);
		edit_feild("updated", "text", "Updated", "The unix timestamp for the last time this page was updated.", date("Y-m-d H:i:s", $this->updated), false);
		edit_feild("security", "text", "Security", "A short statement on this mod's security.", $this->security);
		edit_feild("status", "text", "Status", "A short description of the mod's development status.", $this->status);
		echo "<input type=\"submit\" value=\"Save edits\"/>";
		echo "</form>";
	}
	
	function save_edit() {
		$this->name = htmlspecialchars($_POST["name"]);
		$this->creators = parse_comma_array(htmlspecialchars($_POST["creators"]));
		$this->wiki = htmlspecialchars($_POST["wiki"]);
		$this->description = str_replace("\n", "<br/>", htmlspecialchars($_POST["description"]));
		$this->download = htmlspecialchars($_POST["download"]);
		$this->code = htmlspecialchars($_POST["code"]);
		$this->tags = parse_comma_array(htmlspecialchars($_POST["tags"]));
		$this->version = htmlspecialchars($_POST["version"]);
		$this->updated = time();
		$this->security = htmlspecialchars($_POST["security"]);
		$this->status = htmlspecialchars($_POST["status"]);
		
		$this->save();
	}
}

function display_mod(string $mod_name) : void {
	$mod = new ModPage($mod_name);
	$mod->display();
}

function edit_mod() : void {
	include_header();
	
	if (!array_key_exists("m", $_GET)) {
		echo "<h1>Sorry</h1><p>Bad request.</p>";
		include_footer();
		return;
	}
	
	$mod_name = $_GET["m"];
	
	if (!get_name_if_authed()) {
		echo "<h1>Sorry</h1><p>You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to edit pages.</p>";
		include_footer();
		return;
	}
	
	$mod = new ModPage($mod_name);
	$mod->display_edit();
	
	include_footer();
}

function save_mod() : void {
	if (!array_key_exists("m", $_GET)) {
		include_header();
		echo "<h1>Sorry</h1><p>Bad request.</p>";
		include_footer();
		return;
	}
	
	$mod_name = $_GET["m"];
	
	if (!get_name_if_authed()) {
		include_header();
		echo "<h1>Sorry</h1><p>You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to save pages.</p>";
		include_footer();
		return;
	}
	
	$mod = new ModPage($mod_name);
	$mod->save_edit();
	
	header("Location: /?m=$mod_name");
	die();
}
