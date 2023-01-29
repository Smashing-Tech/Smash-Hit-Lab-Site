<?php
/**
 * Discssions for comments and reviews
 */

require_once "user.php";
require_once "notifications.php";
require_once "templates.php";
require_once "config.php";

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
		$img = get_profile_image($this->author);
		
		// Default PFP
		if (!$img) {
			$img = "./icon.png";
		}
		
		$img = "<img src=\"$img\"/>";
		$text = rich_format($this->body);
		$after = htmlspecialchars($_SERVER['REQUEST_URI']);
		$hidden_text = ($this->is_hidden()) ? "Unhide" : "Hide";
		$actions = (get_name_if_admin_authed() || (get_name_if_authed() === $this->author)) ? "<p><a href=\"./?a=discussion_hide&id=$id&index=$index&after=$after\">$hidden_text</a></p>" : "";
		
		return "<div class=\"comment-card\"><div class=\"comment-card-inner\"><div class=\"comment-card-inner-left\">$img</div><div class=\"comment-card-inner-right\"><p>$name</p><p class=\"small-text\">$date</p><p>$text</p>$actions</div></div></div>";
	}
}

class Discussion {
	/**
	 * This is the main discussion class, which represents one discussion.
	 */
	
	public $id;
	public $followers;
	public $comments;
	
	function __construct(string $id) {
		$db = new Database("discussion");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->followers = property_exists($info, "followers") ? $info->followers : array();
			$this->comments = $info->comments;
			
			// Make sure that comments are Comment type objects
			for ($i = 0; $i < sizeof($this->comments); $i++) {
				$this->comments[$i] = (new Comment())->load($this->comments[$i]);
			}
		}
		else {
			$this->id = $id;
			$this->followers = array();
			$this->comments = array();
		}
	}
	
	function save() {
		$db = new Database("discussion");
		$db->save($this->id, $this);
	}
	
	function delete() {
		$db = new Database("discussion");
		$db->delete($this->id);
	}
	
	function get_id() {
		return (sizeof($this->comments) > 0) ? $this->id : null;
	}
	
	function is_following(string $user) {
		/**
		 * Check if a user is following a discussion.
		 */
		
		return array_search($user, $this->followers, true) !== false;
	}
	
	function toggle_follow(string $user) {
		/**
		 * Toggle the given user's follow status for this discussion.
		 */
		
		// Remove the follower status
		if (($index = array_search($user, $this->followers, true)) !== false) {
			array_splice($this->followers, $index, 1);
		}
		// Add the follower status
		else {
			$this->followers[] = $user;
		}
		
		$this->save();
	}
	
	function add_comment(string $author, string $body) {
		$this->comments[] = (new Comment())->create($author, $body);
		$this->save();
		
		// Notify users
		// I hate having to use &after= on this, but it's really the only
		// way to do things without having each discussion assocaited with a
		// URL (which is probably a good idea, actually).
		// HACK I think if we start with "./" for now, it should be secure
		// enough.
		$url = $_GET['after'];
		
		if (!str_starts_with($url, "./") || !str_starts_with($url, "/")) {
			$url = "./" . $url;
		}
		
		// Notify post followers
		notify_many($this->followers, "New message from $author", $url);
		
		// Notify any mentioned users
		notify_scan($body, $url);
		
		// Admin alert!
		alert("Discssion $this->id updated by $author", "./" . $url);
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
	
	function delete_comment(int $index) {
		if (isset($this->comments[$index])) {
			array_splice($this->comments, $index, 1);
			$this->save();
		}
	}
	
	function get_author(int $index) {
		if (isset($this->comments[$index])) {
			return $this->comments[$index]->author;
		}
		else {
			return null;
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
	
	function enumerate_shown() {
		/**
		 * Return the number of shown comments.
		 */
		
		return sizeof($this->comments) - $this->enumerate_hidden();
	}
	
	function display_edit(int $index, string $url = "") {
		/**
		 * Display the comment edit box.
		 */
		
		$enabled = get_config("enable_discussions", "enabled");
		
		switch ($enabled) {
			case "enabled": {
				if (!get_name_if_authed()) {
					echo "<div class=\"comment-card comment-edit\"><p>Want to leave a comment? <a href=\"./?a=login\">Log in</a> or <a href=\"./?a=register\">create an account</a> to share your thoughts!</p></div>";
					return;
				}
				
				$comment = new Comment();
				
				if ($index >= 0) {
					$comment = $this->comments[$index];
				}
				
				$name = get_nice_display_name(get_name_if_authed());
				$url = htmlspecialchars($_SERVER['REQUEST_URI']); // Yes this should be sanitised for mod pages
				$body = htmlspecialchars($comment->body);
				$img = get_profile_image(get_name_if_authed());
				
				if (!$img) {
					$img = "./icon.png";
				}
				
				echo "<div class=\"comment-card comment-edit\"><div class=\"comment-card-inner\"><div class=\"comment-card-inner-left\"><img src=\"$img\"/></div><div class=\"comment-card-inner-right\"><form action=\"./?a=discussion_update&id=$this->id&index=$index&after=$url\" method=\"post\"><p>$name</p><p><textarea style=\"width: calc(100% - 1em); background: transparent; padding: 0;\" name=\"body\" placeholder=\"Add your comment...\">$body</textarea></p><p><input type=\"submit\" value=\"Post comment\"></p></form></div></div></div>";
				break;
			}
			case "closed": {
				echo "<div class=\"comment-card comment-edit\"><p>Discussions have been closed sitewide. You can chat on our Discord server for now!</p></div>";
				break;
			}
			// If they are fully disabled there should be a message about it.
			default: {
				break;
			}
		}
	}
	
	function display_title(string $title) {
		echo "<h4>$title (" . $this->enumerate_shown() . ")</h4>";
	}
	
	function display_follow() {
		$name = get_name_if_admin_authed();
		
		if ($name) {
			$follow = ($this->is_following($name)) ? "Unfollow" : "Follow";
			$url = $_SERVER['REQUEST_URI'];
			
			echo "<p><a href=\"./?a=discussion_follow&id=$this->id&after=$url\"><button>$follow this discussion</button></a></p>";
		}
	}
	
	function display_hidden() {
		$hidden = $this->enumerate_hidden();
		
		if ($hidden > 0 && get_name_if_admin_authed()) {
			$s = ($hidden == 1) ? " was" : "s were";
			echo "<p><i>Please note that $hidden other comment$s hidden.</i></p>";
		}
	}
	
	function display_comments(bool $reverse = false) {
		$size = sizeof($this->comments);
		
		for ($i = 0; $i < $size; $i++) {
			$j = ($reverse ? ($size - $i - 1) : $i);
			echo $this->comments[$j]->render($this->id, $j);
		}
	}
	
	function display_disabled() : bool {
		$disabled = (get_config("enable_discussions", "enabled") === "disabled");
		
		if ($disabled) {
			echo "<div class=\"comment-card comment-edit\"><p>Discussions have been disabled sitewide. Existing comments are not shown, but will return when discussions are enabled again.</p></div>";
		}
		
		return $disabled;
	}
	
	function display(string $title = "Discussion", string $url = "") {
		$this->display_title($title);
		if ($this->display_disabled()) { return; }
		$this->display_follow();
		$this->display_hidden();
		$this->display_comments();
		$this->display_edit(-1, $url);
	}
	
	function display_reverse(string $title = "Discussion", string $url = "") {
		$this->display_title($title);
		if ($this->display_disabled()) { return; }
		$this->display_edit(-1, $url);
		$this->display_comments(true);
		$this->display_hidden();
		$this->display_follow();
	}
}

function discussion_exists(string $name) {
	$db = new Database("discussion");
	return $db->has($name);
}

function discussion_delete_given_id(string $id) {
	$d = new Discussion($id);
	$d->delete();
}

function discussion_update() {
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("You need to be logged in to post comments.");
	}
	
	if (get_config("enable_discussions", "enabled") !== "enabled") {
		sorry("Updating discussions has been disabled.");
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
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("You need to log in to hide a comment.");
	}
	
	if (get_config("enable_discussions", "enabled") === "disabled") {
		sorry("Updating discussions has been disabled.");
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		sorry("Need an id to update.");
	}
	
	$discussion = $_GET["id"];
	
	if (!array_key_exists("index", $_GET)) {
		sorry("Need an index to update.");
	}
	
	$index = $_GET["index"];
	
	$discussion = new Discussion($discussion);
	
	// If the user requesting is not the author and is not admin, we deny the
	// request.
	if ($discussion->get_author($index) !== $user->name && !$user->is_admin()) {
		sorry("You cannot hide a comment which you have not written.");
	}
	
	$discussion->hide_comment($index);
	
	if (array_key_exists("after", $_GET)) {
		redirect($_GET["after"]);
	}
	else {
		sorry("It's done but no clue what page you were on...");
	}
}

function discussion_delete() {
	$user = get_name_if_admin_authed();
	
	if (!$user) {
		sorry("The action you have requested is not currently implemented.");
	}
	
	$user = new User($user);
	
	if (get_config("enable_discussions", "enabled") === "disabled") {
		sorry("Updating discussions has been disabled.");
	}
	
	if (!array_key_exists("id", $_GET)) {
		sorry("Need an id to update.");
	}
	
	$discussion = $_GET["id"];
	
	if (!array_key_exists("index", $_GET)) {
		sorry("Need an index to update.");
	}
	
	$index = $_GET["index"]; // If it's -1 then it's a new comment
	
	$discussion = new Discussion($discussion);
	
	$discussion->delete_comment($index);
	
	if (array_key_exists("after", $_GET)) {
		redirect($_GET["after"]);
	}
	else {
		sorry("It's done but no clue what page you were on...");
	}
}

function discussion_follow() {
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("You need to be logged in to follow discussions.");
	}
	
	if (get_config("enable_discussions", "enabled") !== "enabled") {
		sorry("There is no reason to follow a discussion which has been closed.");
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		sorry("Need an id to follow.");
	}
	
	$discussion = $_GET["id"];
	$discussion = new Discussion($discussion);
	$discussion->toggle_follow($user->name);
	
	if (array_key_exists("after", $_GET)) {
		redirect($_GET["after"]);
	}
	else {
		sorry("It's done but no clue what page you were on...");
	}
}
