<?php

class ForumThread {
	public $id;
	public $title;
	public $created;
	public $author;
	public $replies;
	
	function __construct(string $id) {
		$db = new Database("thread");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->title = $info->title;
			$this->created = $info->created;
			$this->author = $info->author;
			$this->replies = $info->replies;
		}
		else {
			$this->id = $id;
			$this->title = "Untitled";
			$this->created = time();
			$this->author = "";
			$this->replies = $id;
		}
	}
	
	function save() {
		$db = new Database("thread");
		$db->save($this->id, $this);
	}
	
	function exists() {
		$db = new Database("thread");
		return $db->has($this->id);
	}
	
	function delete() {
		$disc = new Discussion($this->id);
		$disc->delete();
		
		$db = new Database("thread");
		$db->delete($this->id);
	}
}

$gEndMan->add("forum-home", function (Page $page) {
	$actor = user_get_current();
	
	$page->heading(1, "Forum");
	
	if ($actor) {
		$page->add("<p style=\"text-align: center;\"><button onclick=\"shl_show_dialogue('new-thread')\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Create thread</button></p>");
		
		$page->add(create_form_dialogue_code('new-thread', "./?a=forum-create&submit=1", "Create a new thread", "<p><input type=\"text\" style=\"width: 96%;\" name=\"title\" placeholder=\"Title\"/></p>
		<p><textarea name=\"content\" style=\"width: 96%;\" placeholder=\"Your message (supports markdown)\"></textarea></p>", "<button>Create thread</button>"));
	}
	
	$recent = get_config("forum_recent", []);
	
	for ($i = 0; $i < sizeof($recent); $i++) {
		$thread = new ForumThread($recent[$i]);
		
		if ($thread->exists()) {
			$page->add("<div class=\"thread-card\">
	<h4><a href=\"./?a=forum-view&thread=$thread->id\">$thread->title</a></h4>
	<p>At " . date("Y-m-d H:i:s", $thread->created) . " by @$thread->author</p>
</div>");
		}
	}
});

$gEndMan->add("forum-create", function (Page $page) {
	$actor = user_get_current();
	
	if ($actor) {
		if (!$page->has("submit")) {
			$page->info("Problem", "You cannot do that.");
		}
		else {
			// Forum threads use the same id's as their discussions
			$thread = new ForumThread(random_discussion_name());
			
			// Get content
			$content = $page->get("content", true, 3500);
			
			// Set properties
			$thread->title = $page->get("title", true, 120);
			$thread->author = $actor->name;
			
			// Create discussion
			$disc = new Discussion($thread->id);
			$disc->set_url("./?a=forum-view&thread=$thread->id");
			$disc->add_comment($thread->author, $content);
			
			// Save thread info and discussion
			$thread->save();
			
			// Add to list of recent threads
			// We record up to 20 recent threads, the others go unlisted
			$recent = get_config("forum_recent", []);
			$recent = array_merge([$thread->id, ], array_slice($recent, 0, 19));
			set_config("forum_recent", $recent);
			
			// Redirect to thread
			$page->redirect("./?a=forum-view&thread=$thread->id");
		}
	}
	else {
		$page->info("Problem", "You cannot do that.");
	}
});

$gEndMan->add("forum-view", function (Page $page) {
	// we need to use legacy include_footer/include_header for discussions does
	// not support $page->add atm.
	include_header();
	
	$actor = user_get_current();
	
	// Get ID
	$id = $page->get("thread");
	
	$thread = new ForumThread($id);
	
	if (!$thread->exists()) {
		echo "<h1>This thread isn't real</h1><p>Sorry but this thread does not exist.</p>";
		include_footer();
		return;
	}
	
	// Display title
	echo "<h1>$thread->title</h1>";
	
	// Moderation actions
	if ($actor->is_mod()) {
		echo "<p style=\"text-align: center;\"><a href=\"./?a=forum-delete&thread=$thread->id\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">delete</span> Delete</button></a></p>";
	}
	
	// Display the full discussion
	$disc = new Discussion($id);
	$disc->display();
	
	include_footer();
});

$gEndMan->add("forum-delete", function (Page $page) {
	$actor = user_get_current();
	
	if ($actor && $actor->is_mod()) {
		if (!$page->has("submit")) {
			$page->heading(1, "Delete thread");
			
			$form = new Form("./?a=forum-delete&submit=1");
			$form->textbox("thread", "Thread ID", "The ID of the thread to delete.", $page->get("thread", false), !$page->has("thread"));
			$form->textbox("reason", "Reason", "Reason for deletion of the thread.");
			$form->submit("Delete thread");
			
			$page->add($form);
		}
		else {
			$thread = new ForumThread($page->get("thread"));
			
			if (!$thread->exists()) {
				$page->info("Invalid thread", "That thread does not exist and cannot be deleted.");
			}
			
			$thread->delete();
			
			alert("Moderator @$actor->name deleted thread and discussion $thread->id\n\nReason: " . $page->get("reason", false));
			
			$page->redirect("./?a=forum-home");
		}
	}
	else {
		$page->info("Problem", "You cannot do that.");
	}
});
