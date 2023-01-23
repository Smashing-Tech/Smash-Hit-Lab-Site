<?php

require_once "database.php";
require_once "user.php";

function edit_feild($name, $type, $title, $desc, $value, $enabled = true) : void {
	if (!$value) {
		$value = "";
	}
	
	echo "<div class=\"mod-edit-property\">";
		echo "<div class=\"mod-edit-property-label\">";
			echo "<h4>$title</h4>";
			echo "<p>$desc</p>";
		echo "</div>";
		echo "<div class=\"mod-edit-property-data\">";
			switch ($type) {
				case "text":
					echo "<input type=\"text\" name=\"$name\" value=\"$value\"" . ($enabled) ? "" : " disabled" . "/>";
				case "textaera":
					echo "<textarea name=\"$name\">$value</textaera>";
				default:
					echo "$value";
			}
		echo "</div>";
	echo "</div>";
}

function parse_comma_array(string $s) {
	return explode(",", $s);
}

class ModPage {
	string $package;
	string $name;
	string $creators;
	string $wiki;
	string $description;
	string $download;
	string $code;
	string $tags;
	string $version;
	string $updated;
	string $security;
	
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
			
			$db->save($package, $this);
		}
	}
	
	function save() {
		$db = new Database("mod");
		$db->save($this->package, $this);
	}
	
	function display() {
		echo "<p>MOD DESC: " . ($this->description) ? $this->description : "<i>No description yet.</i>" . "</p>";
	}
	
	function display_edit() {
		echo "<form action=\"./?a=save_mod&amp;m=" . $this->package . "\" method=\"post\">";
		edit_feild("package", "text", "Package", "The name of the mod's APK or IPA file.", $this->package, false);
		edit_feild("name", "text", "Name", "The name that will be displayed with the mod.", $this->name);
		edit_feild("creators", "text", "Creators", "The people who created this mod.", $this->creators);
		edit_feild("wiki", "text", "Wiki article", "A relevant wiki article about the mod.", $this->wiki);
		edit_feild("description", "textarea", "Description", "One or two paragraphs that describe the mod.", $this->description);
		edit_feild("download", "text", "Download", "A link to where the mod can be downloaded.", $this->download);
		edit_feild("code", "text", "Source code", "A link to where the source code for a mod can be found.", $this->code);
		edit_feild("tags", "text", "Tags", "Keywords and categorical description of this mod.", $this->tags);
		edit_feild("version", "text", "Version", "The latest version of this mod.", $this->version);
		edit_feild("updated", "text", "Updated", "The unix timestamp for the last time this page was updated.", date("Y-m-d H:i:s", $this->updated), false);
		edit_feild("security", "text", "Security", "A short statement on this mod's security.", $this->security);
		edit_feild("status", "text", "Status", "Cant be bothered to write a description right now.", $this->status);
		echo "<input type=\"submit\" value=\"Save edits\"/>";
		echo "</form>";
	}
	
	function save_edit() {
		$this->name = htmlspecialchars($_POST["name"]);
		$this->creators = parse_comma_array(htmlspecialchars($_POST["name"]));
		$this->wiki = htmlspecialchars($_POST["wiki"]);
		$this->description = htmlspecialchars($_POST["description"]);
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

function edit_mod(string $mod_name) : void {
	if (!get_name_if_authed()) {
		echo "<h1>Sorry</h1><p>You need to log in to edit pages.</p>";
	}
	
	$mod = new ModPage($mod_name);
	$mod->display_edit();
}
