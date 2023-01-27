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

function show_news_edit_button(string $name) : void {
	$user = get_name_if_authed();
	
	if ($user) {
		$user = new User($user);
		
		if ($user->is_admin()) {
			echo "<p><a href=\"./?a=update_news&n=$name\"><button>Edit this article</button></a></p>";
		}
	}
}

class Article {
	public $name;
	public $title;
	public $body;
	public $created;
	public $updated;
	public $authors;
	public $comments;
	
	function __construct(string $name) {
		$db = new Database("article");
		
		if ($db->has($name)) {
			$info = $db->load($name);
			
			$this->name = $info->name;
			$this->title = $info->title;
			$this->body = $info->body;
			$this->created = $info->created;
			$this->updated = $info->updated;
			$this->authors = $info->authors;
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
			$this->comments = random_discussion_name();
		}
	}
	
	function save() {
		$db = new Database("article");
		
		$db->save($this->name, $this);
	}
	
	function get_html() {
		/**
		 * Get a news article as HTML.
		 */
		
		$filtered = htmlspecialchars($this->body);
		
		// Here comes the parser ... !!!
		$body = "<p>";
		$bold = false; // Are we currently bold?
		$italic = false; // Are we currently italic?
		$code = false; // Are we currently code?
		
		// This parser is really not great, but it's simple and does what it does
		// do quite well and really I don't feel like a big parser right now.
		for ($i = 0; $i < strlen($filtered); $i++) {
			$s = substr($filtered, $i);
			
			if (str_starts_with($s, "**")) {
				// If we are bold, then don't do it again..
				if ($bold) {
					$body = $body . "</b>";
				}
				else {
					$body = $body . "<b>";
				}
				
				$i += 1; // Add an extra so we don't just get italics
				$bold = !$bold;
			}
			else if (str_starts_with($s, "__")) {
				if ($italic) {
					$body = $body . "</i>";
				}
				else {
					$body = $body . "<i>";
				}
				
				$i += 1;
				$italic = !$italic;
			}
			else if (str_starts_with($s, "`")) {
				if ($code) {
					$body = $body . "</code>";
				}
				else {
					$body = $body . "<code>";
				}
				
				$code = !$code;
			}
			else if (str_starts_with($s, "{{")) {
				$end = strpos($s, "}}");
				
				// oH FUCK  so much indentation
				if ($end < 0) {
					$body = $body . "{";
				}
				else {
					$length = $end;
					$url = substr($s, 2, $end - 2);
					
					// Yay, a database lookup during parsing ...
					$db = new Database("article");
					
					// If this is an article reference ...
					if ($db->has($url)) {
						$art = $db->load($url);
						
						$title = $art->title;
						$date = "Last updated " . date("Y-m-d H:i", $art->updated);
						$text = htmlspecialchars(str_replace(array("\n", "_", "*", "`", "{", "}"), " ", substr($art->body, 0, 100))) . "...";
						
						$body = $body . "<div class=\"news-article-card\"><h4><a href=\"./?n=$url\">$title</a></h4><p class=\"small-text\">$date</p><p>$text</p></a></div>";
					}
					// Otherwise try for a youtube embed URL ...
					else if (str_starts_with($url, "yt:")) {
						$url = substr($url, 3);
						
						$body = $body . "<iframe width=\"100%\" height=\"600px\" src=\"https://www.youtube-nocookie.com/embed/$url\" frameborder=\"0\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share\" style=\"border-radius: 0.5em;\" allowfullscreen></iframe>";
					}
					// Otherwise this is just a bare URL ...
					else {
						$body = $body . "<a href=\"$url\" rel=\"nofollow\">$url</a>";
					}
					
					$i += $end + 3; // Skip the unneeded chars
				}
			}
			else {
				$body = $body . $filtered[$i];
			}
		}
		
		// If we are still bold or italics we need to stop that!
		if ($bold) {
			$body = $body . "</b>";
		}
		
		if ($italic) {
			$body = $body . "</i>";
		}
		
		if ($code) {
			$body = $body . "</code>";
		}
		
		// Dobule newlines -> paragraphs
		$body = str_replace("\n\n", "</p><p>", $body);
		
		// Single newlines -> linebreaks
		$body = str_replace("\n", "<br/>", $body);
		
		// Final closing tag
		$body = $body . "</p>";
		
		return $body;
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
	
	function display_update() {
		echo "<h1>Editing $this->title</h1>";
		echo "<form action=\"./?a=save_news&amp;n=$this->name\" method=\"post\">";
		edit_feild("name", "text", "Name", "Internal name of the article.", $this->name, false);
		edit_feild("title", "text", "Title", "Real title of the article.", $this->title);
		edit_feild("body", "textarea", "Body", "The actual article's contents.</p><p>**bold** = <b>bold</b><br/>__italic__ = <i>italic</i><br/>`code` = <code>code</code><br/>Two newlines = New paragraph<br/>One newline = linebreak", $this->body);
		edit_feild("created", "text", "Created", "When the article was made.", date("Y-m-d H:m:s", $this->created), false);
		edit_feild("updated", "text", "Updated", "When the article was edited.", date("Y-m-d H:m:s", $this->updated), false);
		echo "<p><b>Warning:</b> By updating this article, your name will be added to the update list and it will be pushed to the top of the site. Please think carefully before continuing.</p>";
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
	
	$db = new Database("article");
	return $db->has($name);
}

function display_news(string $name) : void {
	/**
	 * Display the specified news article.
	 */
	
	if (!article_exists($name)) {
		echo "<h1>Sorry</h1><p>It seems like we don't have a news article by that name.</p>";
		show_news_edit_button($name);
		return;
	}
	
	$article = new Article($name);
	$article->display();
}

function pretend_error() : void {
	include_header();
	echo "<h1>Sorry</h1><p>The action you have requested is not currently implemented.</p>";
	include_footer();
}

function update_news() : void {
	$user = get_name_if_authed();
	
	if (!$user || !array_key_exists("n", $_GET)) {
		pretend_error();
		return;
	}
	
	$user = new User($user);
	
	if (!$user->is_admin()) {
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
	$user = get_name_if_authed();
	
	if (!$user || !array_key_exists("n", $_GET)) {
		pretend_error();
		return;
	}
	
	$user = new User($user);
	
	if (!$user->is_admin()) {
		pretend_error();
		return;
	}
	
	// We are allowed to update the article ...
	$article = new Article($_GET["n"]);
	$article->update($_POST["title"], $_POST["body"], $user->name);
	
	redirect("./?n=$article->name");
}
