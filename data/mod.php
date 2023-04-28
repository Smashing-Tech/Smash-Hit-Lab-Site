<?php

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
	public $reason;
	public $security;
	public $reviews;
	
	function __construct(string $package, int $revision = -1) {
		$db = new RevisionDB("mod");
		
		if ($db->has($package)) {
			$mod = $db->load($package, $revision);
			
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
			$this->reason = property_exists($mod, "reason") ? $mod->reason : "";
			$this->security = $mod->security;
			$this->status = $mod->status;
			$this->reviews = property_exists($mod, "reviews") ? $mod->reviews : random_discussion_name();
			$this->image = property_exists($mod, "image") ? $mod->image : "";
			
			// If there weren't discussions before, save them now.
			if (!property_exists($mod, "reviews")) {
				$this->save();
			}
			
			// Update discussions URL
			$disc = new Discussion($this->reviews);
			$disc->set_url("./?m=$this->package");
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
			$this->reason = "";
			$this->security = "";
			$this->status = "Released";
			$this->reviews = random_discussion_name();
			$this->image = "";
		}
	}
	
	function save() {
		$db = new RevisionDB("mod");
		$db->save($this->package, $this);
	}
	
	function rename(string $new_slug) : bool {
		/**
		 * Rename the page, checking if it already exists.
		 * 
		 * Returns: false = page already exists, true = renamed successfully
		 */
		
		$db = new RevisionDB("mod");
		
		// Check if page already exists
		if ($db->has($new_slug)) {
			return false;
		}
		
		// Delete old page
		$db->delete($this->package);
		
		// Create new page
		$this->package = $new_slug;
		$this->save();
		
		return true;
	}
	
	function get_display_name() {
		return ($this->name ? $this->name : $this->package);
	}
	
	function display() {
		if ($this->image) {
			echo "<div class=\"mod-banner\" style=\"background-image: linear-gradient(to top, #222c, #0008), url('$this->image');\">";
			echo "<h1>" . $this->get_display_name() . "</h1>";
			echo "</div>";
		}
		else {
			echo "<h1>" . $this->get_display_name() . "</h1>";
		}
		
		// Header
		if (get_name_if_authed()) {
			echo "<p class=\"centred\">";
			echo "<a href=\"./?a=edit_mod&m=$this->package\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Edit this mod</button></a> ";
			echo "<button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Magic editor (beta)</button> ";
			echo "<a href=\"./?a=mod_history&m=$this->package\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">history</span> History</button></a> ";
			echo "<a href=\"./?a=mod-rename&oldslug=$this->package\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit_location</span> Rename</button></a> ";
			
			if (get_name_if_admin_authed()) {
				echo "<a href=\"./?a=mod_delete&package=$this->package\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">delete</span> Delete</button></a> ";
			}
			
			echo "</p>";
		}
		
		if ($this->description) {
			echo "<h3>About</h3>";
			
			$pd = new Parsedown();
			$pd->setSafeMode(true);
			echo $pd->text($this->description);
		}
		
		if ($this->download || $this->version || $this->creators || $this->security) {
			echo "<h3>Basic info</h3>";
		}
		
		mod_property("Download", "A link to where the mod can be downloaded.", $this->download, true);
		mod_property("Version", "The latest version of this mod.", $this->version, true);
		mod_property("Creators", "The people who created this mod.", create_comma_array_nice($this->creators), true);
		mod_property("Security", "A short statement on this mod's security.", $this->security, true);
		
		if ($this->wiki || $this->code || $this->status || $this->package) {
			echo "<h3>Other info</h3>";
		}
		
		mod_property("Wiki article", "A relevant wiki article about the mod.", $this->wiki, true);
		mod_property("Source code", "A link to where the source code for a mod can be found.", $this->code, true);
		mod_property("Status", "A short description of the mod's development status.", $this->status, true);
		
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
			
			echo "<li><a href=\"./?m=$this->package&index=$i\">Edit at " . date("Y-m-d H:i:s", $rev->updated) . "</a> (Index $i) by <a href=\"./?u=$rev->author\">$rev->author</a> &mdash; $rev->reason</li>";
		}
		
		echo "</ul>";
	}
	
	function display_edit() {
		echo "<h1>Editing " . ($this->name ? $this->name : $this->package) . "</h1>";
		echo "<form action=\"./?a=save_mod&amp;m=$this->package\" method=\"post\">";
		echo "<h3>Main</h3>";
		edit_feild("name", "text", "Name", "The name that will be displayed with the mod.", $this->name);
		edit_feild("description", "textarea", "About", "One or two paragraphs that describe the mod.", htmlspecialchars($this->description));
		
		echo "<h3>Basic</h3>";
		edit_feild("image", "text", "Banner image", "The URL of the banner image to use for this mod.", $this->image, get_name_if_admin_authed() !== null);
		edit_feild("download", "text", "Download", "A link to where the mod can be downloaded.", $this->download);
		edit_feild("version", "text", "Version", "The latest version of this mod.", $this->version);
		edit_feild("creators", "text", "Creators", "The people who created this mod.", create_comma_array($this->creators));
		edit_feild("security", "text", "Security", "A short statement on this mod's security.", $this->security);
		edit_feild("wiki", "text", "Wiki article", "A relevant wiki article about the mod.", $this->wiki);
		
		echo "<h3>Extra</h3>";
		edit_feild("code", "text", "Source code", "A link to where the source code for a mod can be found.", $this->code);
		edit_feild("tags", "text", "Tags", "Keywords and categorical description of this mod.", create_comma_array($this->tags));
		
		edit_feild("status", "select", "Status", "A short description of the mod's development status.", $this->status, true, [
			"" => "None",
			"Released" => "Released",
			"Abandoned" => "Abandoned",
			"Completed" => "Completed",
			"On hiatus" => "On hiatus",
			"Incomplete" => "Incomplete",
			"Planning" => "Planning"
		]);
		
		echo "<h3>Edit info</h3>";
		edit_feild("reason", "text", "Edit reason", "Optional description of why this mod was edited.", "");
		
		form_end("Save edits");
	}
	
	function save_edit(string $whom) {
		validate_length("Name", $_POST["name"], 100);
		validate_length("creators", $_POST["creators"], 300);
		validate_length("wiki", $_POST["wiki"], 500);
		validate_length("description", $_POST["description"], 2000);
		validate_length("image", $_POST["image"], 1000);
		validate_length("download", $_POST["download"], 500);
		validate_length("code", $_POST["code"], 500);
		validate_length("tags", $_POST["tags"], 300);
		validate_length("version", $_POST["version"], 100);
		validate_length("security", $_POST["security"], 200);
		validate_length("status", $_POST["status"], 50);
		validate_length("reason", $_POST["reason"], 400);
		
		$this->name = htmlspecialchars($_POST["name"]);
		$this->creators = parse_comma_array(htmlspecialchars($_POST["creators"]));
		$this->wiki = htmlspecialchars($_POST["wiki"]);
		$this->description = $_POST["description"]; // Rich text feild
		$this->image = htmlspecialchars($_POST["image"]);
		$this->download = htmlspecialchars($_POST["download"]);
		$this->code = htmlspecialchars($_POST["code"]);
		$this->tags = parse_comma_array(htmlspecialchars($_POST["tags"]));
		$this->version = htmlspecialchars($_POST["version"]);
		$this->updated = time();
		$this->author = $whom;
		$this->security = htmlspecialchars($_POST["security"]);
		$this->status = htmlspecialchars($_POST["status"]);
		$this->reason = htmlspecialchars($_POST["reason"]);
		
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
	
	$revision = array_key_exists("index", $_GET) ? $_GET["index"] : -1;
	$mod = new ModPage($_GET["m"], $revision);
	
	global $gTitle; $gTitle = $mod->name;
	
	if ($revision >= 0) {
		$gTitle = $gTitle . " (old rev $revision)";
	}
	
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
	alert("Mod page $mod_name updated by @$user", "./?m=$mod_name");
	
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
			
			alert("Mod page $mod->package deleted by @$user\n\nReason: $reason");
			
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
	
	echo "<div class=\"mod-listing\">";
	
	for ($i = 0; $i < sizeof($list); $i++) {
		$mp = new ModPage($list[$i]);
		$title = $mp->name ? $mp->name : $mp->package;
		$desc = htmlspecialchars(substr($mp->description, 0, 100));
		
		if (strlen($desc) >= 100) {
			$desc = $desc . "...";
		}
		
		$url = "./?m=" . htmlspecialchars($mp->package);
		$img = $mp->image ? $mp->image : "./?a=generate-logo-coloured&seed=$title";
		
		echo "<a href=\"$url\">
		<div class=\"mod-card-outer\">
			<div class=\"mod-card-image\" style=\"background-image: url('$img');\"></div>
			<div class=\"mod-card-data\">
				<h4>$title</h4>
				<p>$desc</p>
			</div>
		</div></a>";
	}
	
	echo "</div>";
	include_footer();
}

$gEndMan->add("mod-rename", function(Page $page) {
	$user = get_name_if_authed();
	
	if ($user) {
		if (!$page->has("submit")) {
			$form = new Form("./?a=mod-rename&submit=1");
			$form->hidden("oldslug", $page->get("oldslug"));
			$form->textbox("newslug", "New name", "What do you want the new name of the page to be?", $page->get("oldslug"));
			$form->submit("Rename page");
			
			$page->heading(1, "Rename page");
			$page->add($form);
		}
		else {
			$old_slug = $page->get("oldslug");
			$new_slug = $page->get("newslug");
			
			// Rename the page
			$mod = new ModPage($old_slug);
			$result = $mod->rename($new_slug);
			
			if ($result) {
				alert("@$user renamed mod page '$old_slug' to '$new_slug'", "./?m=$new_slug");
				$page->redirect("./?m=$new_slug");
			}
			else {
				$page->info("Something happened", "A page with this name already exists.");
			}
		}
	}
	else {
		$page->info("Sorry!", "You need to be logged in to rename pages.");
	}
});
