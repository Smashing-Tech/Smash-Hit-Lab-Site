<?php
/**
 * Discssions for comments and reviews
 */

function random_discussion_name() : string {
	/**
	 * Cryptographically secure random hex values modified for discussion names.
	 */
	
	return bin2hex(random_bytes(8));
}

#[AllowDynamicProperties]
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
	
	function render_body() {
		$pd = new Parsedown();
		$pd->setSafeMode(true);
		return $pd->text($this->body);
	}
}

class Discussion {
	/**
	 * This is the main discussion class, which represents one discussion.
	 */
	
	public $id;
	public $followers;
	public $comments;
	public $url;
	public $locked;
	public $access;
	
	function __construct(string $id) {
		$db = new Database("discussion");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->followers = property_exists($info, "followers") ? $info->followers : array();
			$this->comments = $info->comments;
			$this->url = property_exists($info, "url") ? $info->url : null;
			$this->locked = property_exists($info, "locked") ? $info->locked : false;
			$this->access = property_exists($info, "access") ? $info->access : null;
			
			// Make sure that comments are Comment type objects
			for ($i = 0; $i < sizeof($this->comments); $i++) {
				$this->comments[$i] = (new Comment())->load($this->comments[$i]);
			}
		}
		else {
			$this->id = $id;
			$this->followers = array();
			$this->comments = array();
			$this->url = null;
			$this->locked = false;
			$this->access = null;
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
	
	function set_access(string $handle) : void {
		$this->access[] = $handle;
		$this->save();
	}
	
	function has_access(string $handle) : bool {
		return ($this->access === null) || in_array($handle, $this->access);
	}
	
	function is_locked() {
		return $this->locked;
	}
	
	function toggle_locked() {
		/**
		 * Lock or unlock a thread.
		 */
		
		//            vv It's the toggle operator :P
		$this->locked =! $this->locked;
		$this->save();
	}
	
	function get_url() : ?string {
		/**
		 * Get the URL where this discussion appears
		 */
		
		return ($this->url) ? $this->url : "";
	}
	
	function set_url(string $url) : bool {
		/**
		 * Set the URL assocaited with the discussion, if not already set.
		 */
		
		if ($this->url === null) {
			$this->url = $url;
			$this->save();
			return true;
		}
		else {
			return false;
		}
	}
	
	function add_comment(string $author, string $body) {
		$this->comments[] = (new Comment())->create($author, $body);
		$this->save();
		
		// Notify users
		// We start by grabbing the assocaited URL
		$url = $this->get_url();
		
		// Notify post followers
		notify_many($this->followers, "New message from @$author", $url . "#discussion-$this->id-" . (sizeof($this->comments) - 1));
		
		// Notify any mentioned users
		notify_scan($body, $url);
		
		// Admin alert!
		alert("Discussion $this->id updated by $author", $url);
	}
	
	function update_comment(int $index, string $author, string $body) {
		if ($this->comments[$index]->author === $author && !$this->locked) {
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
	
	function delete_comments_by(string $author) {
		/**
		 * Delete comments by a given author.
		 */
		
		for ($i = 0; $i < sizeof($this->comments); $i++) {
			if ($this->get_author($i) == $author) {
				$this->delete_comment($i);
			}
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
	
	function list_since(int $index) {
		/**
		 * Return a list of comments since (and including) a given comment, also
		 * including some extra data
		 */
		
		$size = sizeof($this->comments);
		
		if ($index > ($size - 1)) {
			return array();
		}
		
		$comments = array_slice($this->comments, $index);
		
		// Put indexes on comments
		for ($i = 0; $i < sizeof($comments); $i++) {
			$comments[$i]->index = $i;
		}
		
		// Remove hidden comments
		for ($i = 0; $i < sizeof($comments);) {
			if ($comments[$i]->is_hidden()) {
				array_splice($comments, $i, 1);
			}
			// We can only increment if it doesn't exist since everything
			// will shift down when things are removed!
			else {
				$i++;
			}
		}
		
		// Add extra metadata and format them
		for ($i = 0; $i < sizeof($comments); $i++) {
			$user = new User($comments[$i]->author);
			
			$comments[$i]->display = $user->get_display();
			$comments[$i]->image = $user->get_image();
			$comments[$i]->body = $comments[$i]->render_body();
			$comments[$i]->actions = array("reply");
			
			if (get_name_if_admin_authed() || (get_name_if_authed() == $user->name)) {
				$comments[$i]->actions[] = "hide";
			}
			
			$comments[$i]->badge = get_user_badge($user);
		}
		
		return $comments;
	}
	
	function display_edit(int $index, string $url = "") {
		/**
		 * Display the comment edit box.
		 */
		
		$enabled = get_config("enable_discussions", "enabled");
		
		if ($enabled == "enabled" && $this->is_locked()) {
			$enabled = "closed";
		}
		
		switch ($enabled) {
			case "enabled": {
				if (!get_name_if_authed()) {
					echo "<div id=\"discussion-$this->id-box\" class=\"comment-card comment-edit\"><p>Want to leave a comment? <a href=\"./?a=login\">Log in</a> or <a href=\"./?a=register\">create an account</a> to share your thoughts!</p></div>";
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
				$sak = user_get_sak();
				
				if (!$img) {
					$img = "./icon.png";
				}
				
				echo "<div id=\"discussion-$this->id-box\" class=\"comment-card comment-edit\"><div class=\"comment-card-inner\"><div class=\"comment-card-inner-left\"><img src=\"$img\"/></div><div class=\"comment-card-inner-right\"><div><p>$name</p><p><textarea id=\"discussions-$this->id-entry\" style=\"width: calc(100% - 1em); background: transparent; padding: 0; resize: none; display: inline-block;\" name=\"body\" placeholder=\"What would you like to say?\">$body</textarea></p><p><input type=\"hidden\" name=\"key\" value=\"$sak\">";
				echo "<button class=\"button\" onclick=\"ds_update();\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">send</span> Post comment</button>";
				echo "<span id=\"discussions-$this->id-error\"></span></p></div></div></div></div>";
				break;
			}
			case "closed": {
				echo "<div class=\"comment-card comment-edit\">";
				echo "<p style=\"text-align: center\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px; font-size: 128px;\">nights_stay</span></p>";
				echo "<p style=\"text-align: center\">This discussion has been closed. You can still chat on our Discord server!</p>";
				echo "</div>";
				break;
			}
			// If they are fully disabled there should be a message about it.
			default: {
				break;
			}
		}
	}
	
	function display_title(string $title) {
		echo "<h3 class=\"left-align\" style=\"margin-top: 0; position: relative; top: -10px;\">$title (" . $this->enumerate_shown() . ")</h3>";
	}
	
	function display_reload() {
		echo "<button class=\"button secondary\" onclick=\"ds_clear(); ds_load();\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">refresh</span> Reload</button>";
	}
	
	function display_follow() {
		$name = get_name_if_authed();
		
		if ($name) {
			$following = $this->is_following($name);
			
			$follow = ($following) ? "Unfollow" : "Follow";
			$secondary = ($following) ? " secondary" : "";
			$url = $_SERVER['REQUEST_URI'];
			
			echo "<a href=\"./?a=discussion_follow&id=$this->id&after=$url\"><button class=\"button$secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">notification_add</span> $follow</button></a>";
		}
	}
	
	function display_lock() {
		$name = get_name_if_admin_authed();
		
		if ($name) {
			$locked = $this->is_locked();
			
			$text = ($locked) ? "Unlock" : "Lock";
			$url = $_SERVER['REQUEST_URI'];
			
			echo "<a href=\"./?a=discussion_lock&id=$this->id&after=$url\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">lock</span> $text</button></a>";
		}
	}
	
	function display_bar(string $title) {
		echo "<div class=\"comments-header\">";
			echo "<div class=\"comments-header-label\">";
				$this->display_title($title);
			echo "</div>";
			echo "<div class=\"comments-header-data right-align\"><p>";
				$this->display_reload();
				echo " ";
				$this->display_lock();
				echo " ";
				$this->display_follow();
			echo "</p></div>";
		echo "</div>";
	}
	
	function display_hidden() {
		$hidden = $this->enumerate_hidden();
		
		if ($hidden > 0 && get_name_if_admin_authed()) {
			$s = ($hidden == 1) ? " was" : "s were";
			echo "<p><i>Please note that $hidden other comment$s hidden.</i></p>";
		}
	}
	
	function display_comments(bool $reverse = false) {
		echo "<div id=\"discussion-$this->id\"></div>";
		echo "<script>ds_clear(); ds_load();</script>";
	}
	
	function display_disabled() : bool {
		$disabled = (get_config("enable_discussions", "enabled") === "disabled");
		
		if ($disabled) {
			echo "<div class=\"comment-card comment-edit\"><p>Discussions have been disabled sitewide. Existing comments are not shown, but will return when discussions are enabled again.</p></div>";
		}
		
		return $disabled;
	}
	
	function comments_load_script(bool $backwards = false) {
		$sak = user_get_sak();
		echo "<script>var DiscussionID = \"$this->id\"; var UserSAK = \"$sak\"; var DiscussionBackwards = " . ($backwards ? "true" : "false") . ";</script>";
		readfile("../data/_discussionload.html");
	}
	
	function display(string $title = "Discussion", string $url = "") {
		$this->comments_load_script();
		$this->display_bar($title);
		if ($this->display_disabled()) { return; }
		$this->display_hidden();
		$this->display_comments();
		$this->display_edit(-1, $url);
	}
	
	function display_reverse(string $title = "Discussion", string $url = "") {
		$this->comments_load_script(true);
		$this->display_bar($title);
		if ($this->display_disabled()) { return; }
		$this->display_hidden();
		$this->display_edit(-1, $url);
		$this->display_comments(true);
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

function discussion_nuke_comments_by(string $author) {
	/**
	 * Nuke every comment by a user in every discussion.
	 */
	
	$db = new Database("discussion");
	$entries = $db->enumerate();
	
	for ($i = 0; $i < sizeof($entries); $i++) {
		$d = new Discussion($entries[$i]);
		$d->delete_comments_by($author);
	}
}

function discussion_update() {
	if (array_key_exists("api", $_GET)) {
		return discussion_update_new();
	}
	
	$user = get_name_if_authed();
	
	if (!$user || !array_key_exists("key", $_POST) || !user_verify_sak($_POST["key"])) {
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
	
	$body = (array_key_exists("raw", $_GET) ? file_get_contents("php://input") : $_POST["body"]);
	
	validate_length("Body of message", $body, 4000);
	
	$discussion = new Discussion($discussion);
	
	if ($index == "-1") {
		$discussion->add_comment($user->name, $body);
		
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

function discussion_update_new() {
	$user = get_name_if_authed();
	
	// Send mimetype (we need to anyways)
	header('Content-type: application/json');
	
	if (!$user || !array_key_exists("key", $_GET) || !user_verify_sak($_GET["key"])) {
		echo "{\"error\": \"not_authed\", \"message\": \"You need to log in first.\"}"; return;
	}
	
	if (get_config("enable_discussions", "enabled") !== "enabled") {
		echo "{\"error\": \"discussions_disabled\", \"message\": \"Discussions are currently inactive sitewide.\"}"; return;
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		echo "{\"error\": \"api\", \"message\": \"API: Missing 'id' feild.\"}"; return;
	}
	
	$discussion = $_GET["id"];
	
	if (!array_key_exists("index", $_GET)) {
		echo "{\"error\": \"api\", \"message\": \"API: Missing 'index' feild.\"}"; return;
	}
	
	$index = $_GET["index"]; // If it's -1 then it's a new comment
	
	$body = file_get_contents("php://input");
	
	if (strlen($body) < 1) {
		echo "{\"error\": \"no_content\", \"message\": \"This comment does not have any content.\"}"; return;
	}
	
	if (strlen($body) > 3500) {
		echo "{\"error\": \"too_long\", \"message\": \"Your comment is too long! Please make sure your comment is less than 3500 characters.\"}"; return;
	}
	
	$discussion = new Discussion($discussion);
	
	if ($index == "-1") {
		$discussion->add_comment($user->name, $body);
		
		echo "{\"error\": \"done\", \"message\": \"Your comment was posted successfully!\"}"; return;
	}
	else {
		echo "{\"error\": \"not_supported\", \"message\": \"Updating existing comments is not supported.\"}"; return;
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
	
	if (!array_key_exists("key", $_GET)) {
		sorry("Need an index to update.");
	}
	
	$sak = $_GET["key"];
	
	$discussion = new Discussion($discussion);
	
	// If the user requesting is not the author and is not admin, we deny the
	// request.
	if (($discussion->get_author($index) !== $user->name && !$user->is_admin()) || (!$user->verify_sak($sak))) {
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

function discussion_poll() {
	if (!array_key_exists("id", $_GET) || !array_key_exists("index", $_GET)) {
		sorry("Problem doing that.");
	}
	
	$user = get_name_if_authed();
	
	// List the comments
	$disc = new Discussion($_GET["id"]);
	$comments = $disc->list_since($_GET["index"]);
	
	// Create the result data
	$result = new stdClass;
	$result->anything = (sizeof($comments) !== 0);
	$result->comments = $comments;
	$result->actor = $user;
	$result->next_sak = user_get_sak();
	
	// Send mimetype
	header('Content-type: application/json');
	
	// Send json data
	echo json_encode($result);
}

function discussion_lock() {
	$user = get_name_if_admin_authed();
	
	if (!$user) {
		sorry("The action you have requested is not currently implemented.");
	}
	
	if (get_config("enable_discussions", "enabled") !== "enabled") {
		sorry("There is no reason to lock a discussion when discussions are already unavailable.");
	}
	
	$user = new User($user);
	
	if (!array_key_exists("id", $_GET)) {
		sorry("Need an id to lock.");
	}
	
	$discussion = $_GET["id"];
	$discussion = new Discussion($discussion);
	$discussion->toggle_locked();
	
	if (array_key_exists("after", $_GET)) {
		redirect($_GET["after"]);
	}
	else {
		sorry("It's done but no clue what page you were on...");
	}
}

function discussion_view() {
	if (get_config("enable_discussions", "enabled") !== "enabled") {
		sorry("Can't do that right now.");
	}
	
	$discussion = $_GET["id"];
	$discussion = new Discussion($discussion);
	
	$real_id = $discussion->get_id();
	
	if ($real_id === null) {
		sorry("Discussion empty!");
	}
	
	include_header();
	
	echo "<h1>Viewing #$real_id</h1>";
	
	$discussion->display();
	
	include_footer();
}
