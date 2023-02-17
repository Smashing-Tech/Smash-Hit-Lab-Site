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
	public $created;
	public $author;
	public $security;
	public $reviews;
	
	function __construct(string $package) {
		$db = new RevisionDB("mod");
		
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
			$this->created = property_exists($mod, "created") ? $mod->created : time();
			$this->author = property_exists($mod, "author") ? $mod->author : "";
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
			$this->created = time();
			$this->author = "";
			$this->security = "No potentially insecure modifications";
			$this->status = "Released";
			$this->reviews = random_discussion_name();
		}
	}
	
	function save() {
		$db = new RevisionDB("mod");
		$db->save($this->package, $this);
	}
	
	function display() {
		echo "<h1>" . ($this->name ? $this->name : $this->package) . "</h1>";
		
		if (get_name_if_authed()) {
			echo "<p class=\"centred\">";
			echo "<a href=\"./?a=edit_mod&m=$this->package\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Edit mod info</button></a> ";
			echo "<a href=\"./?a=mod_history&m=$this->package\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">history</span> Revision history</button></a>";
			
			if (get_name_if_admin_authed()) {
				echo "<a href=\"./?a=mod_delete&package=$this->package\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">delete</span> Delete page</button></a>";
			}
			
			echo "</p>";
		}
		
		echo "<h3>About</h3>";
		echo ($this->description) ? rich_format($this->description) : "<p><i>We don't have an about section for this mod right now.</i></p>";
		
		echo "<h3>Basic info</h3>";
		mod_property("Download", "A link to where the mod can be downloaded.", $this->download);
		mod_property("Version", "The latest version of this mod.", $this->version);
		mod_property("Creators", "The people who created this mod.", create_comma_array_nice($this->creators));
		mod_property("Security", "A short statement on this mod's security.", $this->security);
		
		echo "<h3>Other info</h3>";
		if ($this->wiki) {
			mod_property("Wiki article", "A relevant wiki article about the mod.", $this->wiki);
		}
		if ($this->code) {
			mod_property("Source code", "A link to where the source code for a mod can be found.", $this->code);
		}
		mod_property("Status", "A short description of the mod's development status.", $this->status);
		mod_property("Package", "The name of the mod's APK or IPA file.", $this->package);
		
		echo "<p class=\"small-text\">This page was last updated at " . date("Y-m-d H:i", $this->updated) . " by " . get_nice_display_name($this->author) . "</p>";
		
		$disc = new Discussion($this->reviews);
		$disc->display_reverse("Reviews", "./?m=" . $this->package);
	}
	
	function display_history() {
		echo "<h1>History of " . ($this->name ? $this->name : $this->package) . "</h1>";
		
		$db = new RevisionDB("mod");
		$history = $db->history($this->package);
		
		echo "<ul>";
		
		for ($i = (sizeof($history) - 1); $i >= 0; $i--) {
			$rev = $history[$i];
			$revnum = $i + 1;
			
			echo "<li>Updated at " . date("Y-m-d H:i:s", $rev->updated) . " (Rev $revnum) by <a href=\"./?u=$rev->author\">$rev->author</a> &mdash; $rev->reason</li>";
		}
		
		echo "</ul>";
	}
	
	function display_edit() {
		echo "<h1>Editing " . ($this->name ? $this->name : $this->package) . "</h1>";
		echo "<form action=\"./?a=save_mod&amp;m=$this->package\" method=\"post\">";
		edit_feild("package", "text", "Package", "The name of the mod's APK or IPA file.", $this->package, false);
		edit_feild("name", "text", "Name", "The name that will be displayed with the mod.", $this->name);
		edit_feild("creators", "text", "Creators", "The people who created this mod.", create_comma_array($this->creators));
		edit_feild("wiki", "text", "Wiki article", "A relevant wiki article about the mod.", $this->wiki);
		edit_feild("description", "textarea", "About", "One or two paragraphs that describe the mod.", htmlspecialchars($this->description));
		edit_feild("download", "text", "Download", "A link to where the mod can be downloaded.", $this->download);
		edit_feild("code", "text", "Source code", "A link to where the source code for a mod can be found.", $this->code);
		edit_feild("tags", "text", "Tags", "Keywords and categorical description of this mod.", create_comma_array($this->tags));
		edit_feild("version", "text", "Version", "The latest version of this mod.", $this->version);
		edit_feild("updated", "text", "Updated", "The unix timestamp for the last time this page was updated.", date("Y-m-d H:i:s", $this->updated), false);
		edit_feild("created", "text", "Created", "The time when this page was first created.", date("Y-m-d H:i:s", $this->created), false);
		edit_feild("author", "text", "Author", "The person who edited this page before you.", $this->author, false);
		edit_feild("security", "text", "Security", "A short statement on this mod's security.", $this->security);
		edit_feild("status", "text", "Status", "A short description of the mod's development status.", $this->status);
		echo "<input type=\"submit\" value=\"Save edits\"/>";
		echo "</form>";
	}
	
	function save_edit(string $whom) {
		validate_length("Name", $_POST["name"], 100);
		validate_length("creators", $_POST["creators"], 300);
		validate_length("wiki", $_POST["wiki"], 500);
		validate_length("description", $_POST["description"], 2000);
		validate_length("download", $_POST["download"], 500);
		validate_length("code", $_POST["code"], 500);
		validate_length("tags", $_POST["tags"], 300);
		validate_length("version", $_POST["version"], 100);
		validate_length("security", $_POST["security"], 200);
		validate_length("status", $_POST["status"], 50);
		
		$this->name = htmlspecialchars($_POST["name"]);
		$this->creators = parse_comma_array(htmlspecialchars($_POST["creators"]));
		$this->wiki = htmlspecialchars($_POST["wiki"]);
		$this->description = $_POST["description"]; // Rich text feild
		$this->download = htmlspecialchars($_POST["download"]);
		$this->code = htmlspecialchars($_POST["code"]);
		$this->tags = parse_comma_array(htmlspecialchars($_POST["tags"]));
		$this->version = htmlspecialchars($_POST["version"]);
		$this->updated = time();
		$this->author = $whom;
		$this->security = htmlspecialchars($_POST["security"]);
		$this->status = htmlspecialchars($_POST["status"]);
		
		$this->save();
	}
	
	function delete() {
		$db = new RevisionDB("mod");
		$db->delete($this->package);
		discussion_delete_given_id($this->reviews);
	}
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

function mod_history() : void {
	include_header();
	
	if (!array_key_exists("m", $_GET)) {
		echo "<h1>Sorry</h1><p>Bad request.</p>";
		include_footer();
		return;
	}
	
	$mod_name = $_GET["m"];
	
	if (!get_name_if_authed()) {
		echo "<h1>Sorry</h1><p>You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to view page history.</p>";
		include_footer();
		return;
	}
	
	$mod = new ModPage($mod_name);
	$mod->display_history();
	
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
	$mod->save_edit($user);
	
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
			
			$default = false;
			
			if (array_key_exists("package", $_GET)) {
				$default = $_GET["package"];
			}
			
			form_start("./?a=delete_mod");
			edit_feild("page", "text", "Page name", "The name of the page to delete. This is the same as the mod's package name.", $default, !$default);
			edit_feild("reason", "text", "Reason", "Type a short reason that you would like to delete this page (optional).", "");
			form_end("Delete mod page");
			
			include_footer();
		}
		else {
			$mod = htmlspecialchars($_POST["page"]);
			$reason = htmlspecialchars($_POST["reason"]);
			
			if (!$reason) { $reason = "<i>No reason given</i>"; }
			
			$mod = new ModPage($mod);
			$mod->delete();
			
			alert("Mod page $mod->package deleted by $user: $reason");
			
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
	$db = new RevisionDB("mod");
	
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
