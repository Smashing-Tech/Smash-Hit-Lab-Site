<?php

require_once "database.php";
require_once "user.php";
require_once "templates.php";
require_once "discussion.php";

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
	public $reviews;
	
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
			$this->reviews = property_exists($mod, "reviews") ? $mod->reviews : random_discussion_name();
			
			// If there weren't discussions before, save them now.
			if (!property_exists($mod, "reviews")) {
				$this->save();
			}
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
			$this->reviews = random_discussion_name();
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
		
		$disc = new Discussion($this->reviews);
		$disc->display_reverse("Reviews", "./?m=" . $this->package);
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
	
	function delete() {
		$db = new Database("mod");
		$db->delete($this->package);
		discussion_delete_given_id($this->reviews);
	}
}

function display_mod_page(string $mod_name) : void {
	$mod = new ModPage($mod_name);
	$mod->display();
}

function display_mod() {
	/**
	 * Ugly HACK -ed version to make the title display without much effort.
	 */
	
	$mod = new ModPage($_GET["m"]);
	
	global $gTitle; $gTitle = $mod->name;
	
	include_header();
	$mod->display();
	include_footer();
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
		sorry("Malformed request.");
	}
	
	$mod_name = $_GET["m"];
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to save pages.");
	}
	
	$mod = new ModPage($mod_name);
	$mod->save_edit();
	
	// Admin alert!
	alert("Mod page $mod_name updated by $user", "./?m=$mod_name");
	
	redirect("./?m=$mod_name");
}

function delete_mod() : void {
	$user = get_name_if_admin_authed();
	
	if ($user) {
		if (!array_key_exists("page", $_POST)) {
			include_header();
			
			echo "<h1>Delete mod page</h1>";
			
			form_start("./?a=delete_mod");
			edit_feild("page", "text", "Page name", "The name of the page to delete. This is the same as the mod's package name.", "");
			edit_feild("reason", "text", "Reason", "Type a short reason that you would like to delete this page (optional).", "");
			form_end("Delete mod page");
			
			include_footer();
		}
		else {
			$mod = htmlspecialchars($_POST["page"]);
			$reason = htmlspecialchars($_POST["reason"]);
			
			$mod = new ModPage($mod);
			$mod->delete();
			
			alert("Mod page $mod->package deleted by $name: $reason");
			
			include_header();
			echo "<h1>Page was deleted!</h1><p>The mod page and assocaited discussion was deleted successfully.</p>";
			include_footer();
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function list_mods() : void {
	$db = new Database("mod");
	
	$list = $db->enumerate();
	
	include_header();
	echo "<h1>List of Mods</h1>";
	
	if (get_name_if_authed()) {
		readfile("../data/_mkmod.html");
	}
	
	echo "<ul>";
	
	for ($i = 0; $i < sizeof($list); $i++) {
		$mp = new ModPage($list[$i]);
		$title = $mp->name ? $mp->name : $mp->package;
		$desc = htmlspecialchars(substr($mp->description, 0, 100));
		
		if (strlen($desc) >= 100) {
			$desc = $desc . "...";
		}
		
		$url = "./?m=" . htmlspecialchars($mp->package);
		echo "<li><a href=\"$url\">$title</a><br/>$desc</li>";
	}
	
	echo "</ul>";
	include_footer();
}
