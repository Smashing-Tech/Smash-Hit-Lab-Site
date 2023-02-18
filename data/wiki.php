<?php

require_once "database.php";
require_once "user.php";
require_once "templates.php";
require_once "discussion.php";

class WikiPage {
	public $name;
	public $content;
	public $tags;
	public $updated;
	public $author;
	public $comments;
	public $reason;
	
	function __construct(string $name) {
		$db = new RevisionDB("wiki");
		
		if ($db->has($name)) {
			$info = $db->load($name);
			
			$this->name = $info->name;
			$this->title = $info->title;
			$this->content = $info->content;
			$this->tags = $info->tags;
			$this->updated = $info->updated;
			$this->author = $info->author;
			$this->comments = $info->comments;
			$this->reason = $info->reason;
			
			// If there weren't discussions before, save them now.
			if (!property_exists($info, "comments")) {
				$this->save();
			}
		}
		else {
			$this->name = htmlspecialchars($name);
			$this->title = htmlspecialchars($name);
			$this->content = "";
			$this->tags = array();
			$this->updated = time();
			$this->author = "";
			$this->comments = random_discussion_name();
			$this->reason = "";
		}
	}
	
	function save() {
		$db = new RevisionDB("wiki");
		$db->save($this->name, $this);
	}
	
	function real() {
		$db = new RevisionDB("wiki");
		return $db->has($this->name);
	}
	
	function display() {
		echo "<h1>" . ($this->title ? $this->title : $this->name) . "</h1>";
		
		if (get_name_if_authed()) {
			echo "<p class=\"centred\">";
			echo "<a href=\"./?a=wiki_update&w=$this->name\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Edit this page</button></a> ";
			echo "<a href=\"./?a=wiki_history&w=$this->name\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">history</span> Revision history</button></a>";
			
			if (get_name_if_admin_authed()) {
				echo "<a href=\"./?a=wiki_delete&name=$this->name\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">delete</span> Delete page</button></a>";
			}
			
			echo "</p>";
		}
		
		if (!$this->real()) {
			echo "<p>It seems like this page doesn't exist right now.</p>";
			return;
		}
		
		echo rich_format($this->content);
		
		echo "<p class=\"small-text\">This page was last updated at " . date("Y-m-d H:i", $this->updated) . " by " . get_nice_display_name($this->author) . "</p>";
		
		$disc = new Discussion($this->comments);
		$disc->display_reverse("Comments", "./?w=$this->name");
	}
	
	function display_history() {
		echo "<h1>History of " . ($this->title ? $this->title : $this->name) . "</h1>";
		
		$db = new RevisionDB("wiki");
		$history = $db->history($this->name);
		
		echo "<ul>";
		
		for ($i = (sizeof($history) - 1); $i >= 0; $i--) {
			$rev = $history[$i];
			$revnum = $i + 1;
			
			echo "<li>Updated at " . date("Y-m-d H:i:s", $rev->updated) . " (Rev $revnum) by <a href=\"./?u=$rev->author\">$rev->author</a> &mdash; $rev->reason</li>";
		}
		
		echo "</ul>";
	}
	
	function display_update() {
		echo "<h1>Editing " . ($this->title ? $this->title : $this->name) . "</h1>";
		form_start("./?a=wiki_update&amp;w=$this->name&amp;submit=1");
		edit_feild("title", "text", "Title", ".", $this->title);
		edit_feild("content", "textarea", "Content", ".", htmlspecialchars($this->content));
		edit_feild("tags", "text", "Tags", ".", create_comma_array($this->tags));
		edit_feild("reason", "text", "Reason", ".", "");
		form_end("Save edits");
	}
	
	function save_update(string $whom) {
		validate_length("Title", $_POST["title"], 100);
		validate_length("Page content", $_POST["content"], 15000);
		validate_length("Tags", $_POST["tags"], 500);
		validate_length("Reason", $_POST["reason"], 300);
		
		$this->title = htmlspecialchars($_POST["title"]);
		$this->content = $_POST["content"]; // Rich text feild
		$this->tags = parse_comma_array(htmlspecialchars($_POST["tags"]));
		$this->updated = time();
		$this->author = $whom;
		$this->reason = htmlspecialchars($_POST["reason"]);
		
		alert("Wiki page $this->title was updated by $this->author", "./?w=$this->name");
		
		$this->save();
	}
	
	function delete() {
		$db = new RevisionDB("wiki");
		$db->delete($this->name);
		discussion_delete_given_id($this->comments);
	}
}

function wiki_display() {
	/**
	 * Ugly HACK -ed version to make the title display without much effort.
	 */
	
	$info = new WikiPage($_GET["w"]);
	
	global $gTitle; $gTitle = $info->title;
	
	include_header();
	$info->display();
	include_footer();
}

function wiki_update() : void {
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to edit pages.");
	}
	
	if (!array_key_exists("w", $_GET)) {
		sorry("You didn't include which page name/id you want to update.");
	}
	
	$page = $_GET["w"];
	
	if (!array_key_exists("submit", $_GET)) {
		include_header();
		
		$info = new WikiPage($page);
		$info->display_update();
		
		include_footer();
	}
	else {
		$info = new WikiPage($page);
		$info->save_update($user);
		
		redirect("./?w=$info->name");
	}
}

function wiki_history() : void {
	if (!get_name_if_authed()) {
		sorry("You need to <a href=\"./?p=login\">log in</a> or <a href=\"./?p=register\">create an account</a> to edit pages.");
	}
	
	if (!array_key_exists("w", $_GET)) {
		sorry("You didn't include which page name/id you want to update.");
	}
	
	$page = $_GET["w"];
	
	include_header();
	
	$info = new WikiPage($page);
	$info->display_history();
	
	include_footer();
}

function wiki_delete() : void {
	$user = get_name_if_admin_authed();
	
	if ($user) {
		if (!array_key_exists("page", $_POST)) {
			include_header();
			
			echo "<h1>Delete wiki page</h1>";
			
			$default_name = "";
			
			if (array_key_exists("name", $_GET)) {
				$default_name = $_GET["name"];
			}
			
			form_start("./?a=wiki_delete");
			edit_feild("page", "text", "Page name", "The name of the page to delete.", $default_name, !$default_name);
			edit_feild("reason", "text", "Reason", "Type a short reason that you would like to delete this page (optional).", "");
			form_end("Delete wiki page");
			
			include_footer();
		}
		else {
			$info = htmlspecialchars($_POST["page"]);
			$reason = htmlspecialchars($_POST["reason"]);
			
			if (!$reason) { $reason = "<i>No reason given</i>"; }
			
			$info = new WikiPage($info);
			
			if (!$info->real()) {
				sorry("The page you are trying to delete does not exist.");
			}
			
			$info->delete();
			
			alert("Wiki page $info->name deleted by $user: $reason");
			
			include_header();
			echo "<h1>Page was deleted!</h1><p>The wiki page and assocaited discussion was deleted successfully.</p>";
			include_footer();
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}
