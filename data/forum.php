<?php

#[AllowDynamicProperties]
class Forum {
	public $id;
	public $title;
	public $discussion;
	public $exists;
	
	function __construct(string $id) {
		$db = new RevisionDB("forum");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->title = $info->title;
			$this->discussion = $info->discussion;
			$this->exists = true;
		}
		else {
			$this->id = $id;
			$this->title = "";
			$this->discussion = random_discussion_name();
			$this->exists = false;
		}
	}
	
	function save() : void {
		$db = new RevisionDB("forum");
		
		unset($this->exists);
		
		$db->save($this->id, $this);
		
		$this->exists = true;
	}
	
	function display() : void {
		/**
		 * Echo out a news article.
		 */
		
		// Show the title
		echo "<h1>$this->title</h1>";
		
		// Display this thread
		$disc = new Discussion($this->discussion);
		$disc->display("Thread", "./?a=forum-view&id=" . $this->id);
	}
	
	function set_title(string $title) : void {
		/**
		 * Set the thread title
		 */
		
		$this->title = $title;
	}
}

function forum_entry_count() : int {
	$forum = new Database("forum");
	$size = sizeof($forum->enumerate());
	return $size ? $size : 0;
}

$gEndMan->add("forum-view", function(Page $page) {
	/**
	 * Display the specified news article.
	 */
	
	$forum = new Forum($page->get("id"));
	
	// HACK for article titles
	global $gTitle; $gTitle = $forum->title;
	
	if ($forum->exists) {
		include_header();
		$forum->display();
		include_footer();
	}
	else {
		$page->info("This forum thread does not exist.");
	}
});

$gEndMan->add("forum-create", function(Page $page) {
	$user = get_name_if_authed();
	
	if ($user) {
		if ($page->has("submit")) {
			$id = strval(forum_entry_count());
			$title = $page->get("title", true, 350);
			
			$forum = new Forum($id);
			$forum->set_title($title);
			$forum->save();
			
			$url = "./?a=forum-view&id=$id";
			
			alert("Forum thread with id $id created by $user", $url);
			
			$page->redirect($url);
		}
		else {
			$page->heading(1, "Create thread");
			
			$form = new Form("./?a=forum-create&submit=1");
			$form->textbox("title", "Title", "A short description of the topic of the thread.");
			$form->submit("Create new thread");
			
			$page->add($form);
		}
	}
	else {
		$page->info();
	}
});
