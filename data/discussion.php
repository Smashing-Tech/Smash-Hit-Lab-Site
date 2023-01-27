<?php
/**
 * Discssions for comments and reviews
 */

require_once "user.php";
require_once "templates.php";

function random_discussion_name() : string {
	/**
	 * Cryptographically secure random hex values modified for discussion names.
	 */
	
	return bin2hex(random_bytes(8));
}

class Comment {
	/**
	 * This is the comment class, which represents a signle comment as part of
	 * a larger discussion.
	 */
	
	public $author;
	public $body;
	public $created;
	public $updated;
	public $hidden;
	
	function __construct() {
		$this->author = "";
		$this->body = "";
		$this->created = 0;
		$this->updated = 0;
		$this->hidden = false;
	}
	
	function load(object $base) {
		$this->author = $base->author;
		$this->body = $base->body;
		$this->created = $base->created;
		$this->updated = $base->updated;
		$this->hidden = $base->hidden;
		
		return $this;
	}
	
	function create(string $author, string $message) {
		$this->author = $author;
		$this->body = $message;
		$this->created = time();
		$this->updated = time();
		
		return $this;
	}
	
	function update(string $body) {
		$this->body = $body;
		$this->updated = time();
	}
	
	function hide() {
		$this->hidden = !$this->hidden;
	}
	
	function is_hidden() {
		return $this->hidden;
	}
	
	function render(string $id, int $index) {
		if ($this->is_hidden()) {
			return "";
		}
		
		$date = date("Y-m-d H:i:s", $this->created);
		$name = get_nice_display_name($this->author);
		$text = rich_format($this->body);
		$after = htmlspecialchars($_SERVER['REQUEST_URI']);
		$actions = (get_name_if_admin_authed()) ? "<p><a href=\"./?a=discussion_hide&id=$id&index=$index&after=$after\">Hide comment</a></p>" : "";
		return "<div class=\"news-article-card\"><p>$name</p><p class=\"small-text\">$date</p><p>$text</p>$actions</div>";
	}
}

class Discussion {
	/**
	 * This is the main discussion class, which represents one discussion.
	 */
	
	public $id;
	public $comments;
	
	function __construct(string $id) {
		$db = new Database("discussion");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->comments = $info->comments;
			
			// Make sure that comments are Comment type objects
			for ($i = 0; $i < sizeof($this->comments); $i++) {
				$this->comments[$i] = (new Comment())->load($this->comments[$i]);
			}
		}
		else {
			$this->id = $id;
			$this->comments = array();
		}
	}
	
	function save() {
		$db = new Database("discussion");
		$db->save($this->id, $this);
	}
	
	function get_id() {
		return (sizeof($this->comments) > 0) ? $this->id : null;
	}
	
	function add_comment(string $author, string $body) {
		$this->comments[] = (new Comment())->create($author, $body);
		$this->save();
	}
	
	function update_comment(int $index, string $author, string $body) {
		if ($this->comments[$index]->author === $author) {
			$this->comments[$index]->update($body);
			$this->save();
			
			return true;
		}
		else {
			return false;
		}
	}
	
	function hide_comment(int $index) {
		if (isset($this->comments[$index])) {
			$this->comments[$index]->hide();
			$this->save();
		}
	}
	
	function enumerate_hidden() {
		/**
		 * Return the number of hidden comments.
		 */
		
		$hidden = 0;
		
		for ($i = 0; $i < sizeof($this->comments); $i++) {
			if ($this->comments[$i]->is_hidden()) {
				$hidden++;
			}
		}
		
		return $hidden;
	}
	
	function display_edit(int $index, string $url = "") {
		/**
		 * Display the comment edit box.
		 */
		
		if (!get_name_if_authed()) {
			echo "<div class=\"news-article-card comment-edit\"><p>Want to leave a comment? <a href=\"./?a=login\">Log in</a> or <a href=\"./?a=register\">create an account</a> to share your thoughts!</p></div>";
			return;
		}
		
		$comment = new Comment();
		
		if ($index >= 0) {
			$comment = $this->comments[$index];
		}
		
		$url = htmlspecialchars($_SERVER['REQUEST_URI']); // Yes this should be sanitised for mod pages
		$body = htmlspecialchars($comment->body);
		
		echo "<div class=\"news-article-card comment-edit\"><form action=\"./?a=discussion_update&id=$this->id&index=$index&after=$url\" method=\"post\"><h4>Add your comment</h4><p><textarea style=\"width: calc(100% - 1em);\" name=\"body\">$body</textarea></p><p><input type=\"submit\" value=\"Post comment\"></p></form></div>";
	}
	
	function display_title(string $title) {
		echo "<h4>$title (" . sizeof($this->comments) . ")</h4>";
	}
	
	function display_hidden() {
		$hidden = $this->enumerate_hidden();
		
		if ($hidden > 0) {
			$s = ($hidden == 1) ? " was" : "s were";
			echo "<p><i>Please note that $hidden comment$s hidden by staff.</i></p>";
		}
	}
	
	function display_comments(bool $reverse = false) {
		$size = sizeof($this->comments);
		
		for ($i = 0; $i < $size; $i++) {
			echo $this->comments[($reverse ? ($size - $i - 1) : $i)]->render($this->id, $i);
		}
	}
	
	function display(string $title = "Discussion", string $url = "") {
		$this->display_title($title);
		$this->display_hidden();
		$this->display_comments();
		$this->display_edit(-1, $url);
	}
	
	function display_reverse(string $title = "Discussion", string $url = "") {
		$this->display_title($title);
		$this->display_edit(-1, $url);
		$this->display_comments(true);
		$this->display_hidden();
	}
}

function discussion_exists(string $name) {
	$db = new Database("discussion");
	return $db->has($name);
}

function discussion_update() {
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("You need to be logged in to post comments.");
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		sorry("Need an id to update.");
	}
	
	$discussion = $_GET["id"];
	
	if (!array_key_exists("index", $_GET)) {
		sorry("Need an index to update.");
	}
	
	$index = $_GET["index"]; // If it's -1 then it's a new comment
	
	if (!array_key_exists("body", $_POST)) {
		sorry("Need a body for content.");
	}
	
	$discussion = new Discussion($discussion);
	
	if ($index == "-1") {
		$discussion->add_comment($user->name, $_POST["body"]);
		
		if (array_key_exists("after", $_GET)) {
			redirect($_GET["after"]);
		}
		else {
			sorry("It's done but no clue what page you were on...");
		}
	}
	else {
		sorry("Editing comments is not yet a feature.");
	}
}

function discussion_hide() {
	$user = get_name_if_admin_authed();
	
	if (!$user) {
		sorry("The action you have requested is not currently implemented.");
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		sorry("Need an id to update.");
	}
	
	$discussion = $_GET["id"];
	
	if (!array_key_exists("index", $_GET)) {
		sorry("Need an index to update.");
	}
	
	$index = $_GET["index"]; // If it's -1 then it's a new comment
	
	$discussion = new Discussion($discussion);
	
	$discussion->hide_comment($index);
	
	if (array_key_exists("after", $_GET)) {
		redirect($_GET["after"]);
	}
	else {
		sorry("It's done but no clue what page you were on...");
	}
}
