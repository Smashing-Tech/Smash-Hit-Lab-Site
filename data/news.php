<?php

require_once "database.php";
require_once "templates.php";
require_once "user.php";
require_once "discussion.php";

function push_recent(string $name) {
	/**
	 * Add an article to the list of recently updated artcles.
	 */
	
	$db = new Database("news");
	
	if ($db->has("recent")) {
		$info = $db->load("recent");
		
		if (sizeof($info) > 10) {
			array_splice($info, 0, 1);
		}
		
		$info[] = $name;
		$db->save("recent", $info);
	}
	else {
		$db->save("recent", array($name));
	}
}

function get_recent() {
	/**
	 * Get recently updated articles.
	 */
	
	$db = new Database("news");
	
	if ($db->has("recent")) {
		return $db->load("recent");
	}
	else {
		return array();
	}
}

function get_news_edit_button(string $name) : string {
	$user = get_name_if_authed();
	
	if ($user) {
		$user = new User($user);
		
		if ($user->is_admin()) {
			return "<p class=\"centred\"><a href=\"./?a=update_news&n=$name\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Edit this article</button></a></p>";
		}
	}
	
	return "";
}

function show_news_edit_button(string $name) : void {
	echo get_news_edit_button($name);
}

class Article {
	public $name;
	public $title;
	public $body;
	public $created;
	public $updated;
	public $authors;
	public $permissions;
	public $comments;
	
	function __construct(string $name) {
		$db = new RevisionDB("article");
		
		if ($db->has($name)) {
			$info = $db->load($name);
			
			$this->name = $info->name;
			$this->title = $info->title;
			$this->body = $info->body;
			$this->created = $info->created;
			$this->updated = $info->updated;
			$this->authors = $info->authors;
			$this->permissions = property_exists($info, "permissions") ? $info->permissions : "private";
			$this->comments = property_exists($info, "comments") ? $info->comments : random_discussion_name();
			
			// If there weren't discussions before, save them now.
			if (!property_exists($info, "comments")) {
				$this->save();
			}
		}
		else {
			$this->name = $name;
			$this->title = "Untitled article";
			$this->body = "";
			$this->created = time();
			$this->updated = time();
			$this->authors = array();
			$this->permissions = "private";
			$this->comments = random_discussion_name();
		}
	}
	
	function save() {
		$db = new RevisionDB("article");
		
		$db->save($this->name, $this);
	}
	
	function get_html() {
		/**
		 * Get a news article as HTML.
		 */
		
		return rich_format($this->body, true);
	}
	
	function update(string $title, string $content, string $whom = "") {
		$this->title = htmlspecialchars($title);
		$this->body = $content;
		$this->updated = time();
		
		// Add to the authors list if not already in it.
		if ($whom && !in_array($whom, $this->authors)) {
			$this->authors[] = $whom;
		}
		
		push_recent($this->name);
		
		$this->save();
	}
	
	function set_permissions(string $name) {
		$this->permissions = $name;
		$this->save();
	}
	
	function display_update() {
		echo "<h1>Editing $this->title</h1>";
		echo "<form action=\"./?a=save_news&amp;n=$this->name\" method=\"post\">";
		edit_feild("title", "text", "Title", "Real title of the article.", $this->title);
		edit_feild("body", "textarea", "Body", "The actual article's contents.", $this->body);
		edit_feild("permissions", "select", "Visibility", "Controls weather the article can be seen by the public or not.", $this->permissions, true, array("private" => "Private", "public" => "Public"));
		echo "<input type=\"submit\" value=\"Save article\"/>";
		echo "</form>";
	}
	
	function display() {
		/**
		 * Echo out a news article.
		 */
		
		echo "<h1>$this->title</h1>";
		
		// Edit button for article
		show_news_edit_button($this->name);
		
		echo "<div class=\"article-body\">";
		echo $this->get_html();
		echo "</div>";
		
		// Display article creation date
		echo "<p class=\"small-text\">Created on " . date("Y-m-d H:i", $this->created) . " and last updated at " . date("Y-m-d H:i", $this->updated) . "</p>";
		
		// Display article editors
		echo "<p class=\"small-text\">This article was edited by ";
		
		for ($i = 0; $i < sizeof($this->authors); $i++) {
			echo get_nice_display_name($this->authors[$i]);
			
			// Nice ands and commas
			if (($i + 2) == sizeof($this->authors)) {
				echo " and ";
			}
			else if (($i + 1) != sizeof($this->authors)) {
				echo ", ";
			}
		}
		
		echo "</p>";
		
		// Display comments
		$disc = new Discussion($this->comments);
		$disc->display("Comments", "./?n=" . $this->name);
	}
}

function article_exists(string $name) : bool {
	/**
	 * Check if a news article exsists.
	 */
	
	$db = new RevisionDB("article");
	return $db->has($name);
}

function display_news(string $name) : void {
	/**
	 * Display the specified news article.
	 */
	
	if (!article_exists($name)) {
		sorry("It seems like we don't have a news article by that name.", get_news_edit_button($name));
	}
	
	$article = new Article($name);
	
	// HACK for article titles
	global $gTitle; $gTitle = $article->title;
	
	include_header();
	
	if (($article->permissions == "public") || (get_name_if_admin_authed() != null)) {
		$article->display();
	}
	else {
		sorry("It seems like we don't have a news article by that name.", get_news_edit_button($name));
	}
	
	include_footer();
}

function pretend_error() : void {
	include_header();
	echo "<h1>Sorry</h1><p>The action you have requested is not currently implemented.</p>";
	include_footer();
}

function update_news() : void {
	$user = get_name_if_admin_authed();
	
	if (!$user || !array_key_exists("n", $_GET)) {
		pretend_error();
		return;
	}
	
	// We are allowed to update the article ...
	$article = new Article($_GET["n"]);
	
	include_header();
	$article->display_update();
	include_footer();
}

function save_news() : void {
	$user = get_name_if_admin_authed();
	
	if (!$user || !array_key_exists("n", $_GET)) {
		pretend_error();
		return;
	}
	
	// We are allowed to update the article ...
	$article = new Article($_GET["n"]);
	$article->update($_POST["title"], $_POST["body"], $user);
	$article->set_permissions($_POST["permissions"]);
	
	redirect("./?n=$article->name");
}
