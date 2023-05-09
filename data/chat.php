<?php

class ChatUser {
	/**
	 * Chat user info: encrypted master encryption key
	 */
	
	public $id;
	public $conversations;
	public $key;
	
	function __construct(string $id) {
		$db = new Database("chat_user");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->conversations = $conversations;
			$this->key = $info->key;
		}
		else {
			$this->id = $id;
			$this->conversations = [];
			$this->key = "";
		}
	}
	
	function save() {
		$db = new Database("chat_user");
		$db->save($this->id, $this);
	}
	
	function get_key() : string {
		/**
		 * Get the user's encrypted encryption key.
		 */
		
		return $this->key;
	}
	
	function set_key(string $key) : void {
		/**
		 * Set the user's encryption key.
		 */
		
		$this->key = $key;
		$this->save();
	}
}

class ChatConversation {
	public $id;
	public $key_refs; // Number of references held to the key
	public $key; // The plaintext key, only available if needed
	public $keys; // The conversation key encrypted using each ChatUser's key.
	public $messages; // The messages, which should be stored as base64 encrypted json strings
	public $created;
	public $updated;
	
	function __construct(string $id) {
		$db = new Database("chat_conversation");
		
		if ($db->has($id)) {
			$info = $db->load("chat_conversation");
			
			$this->id = $info->id;
			$this->keys = $info->keys;
			$this->messages = $info->messages;
			$this->created = $info->created;
			$this->updated = $info->updated;
		}
		else {
			$this->id = $id;
			$this->keys = [];
			$this->messages = [];
			$this->created = time();
			$this->updated = time();
		}
	}
	
	function save() {
		$db = new Database("chat_conversation");
		$this->updated = time();
		$db->save($this->id, $this);
	}
	
	function delete() {
		$db = new Database("chat_conversation");
		$db->delete($this->id);
	}
}

/**
 * TEMP CHAT INTERFACE
 * 
 * This is a version of chat that adds some extras. It's not e2ee!
 */
$gEndMan->add("bogo-chat", function(Page $page) {
	$sak = user_get_sak();
	
	$page->add("<div class=\"bogo-chat\">");
	$page->add("<div id=\"discussion-000000000000000000000000\"></div>");
	$page->add("<script>var DiscussionID = \"000000000000000000000000\"; var UserSAK = \"$sak\"; var DiscussionBackwards = false; var ShowHidden = false;</script>");
	$page->add(file_get_contents("../data/_discussionload.html"));
	$page->add("<script>DiscussionChatStyle = true; setInterval(ds_load, 3000);</script>");
	$page->add("</div>");
	
	$page->add("<input type=\"text\" id=\"discussions-000000000000000000000000-entry\" style=\"width: calc(100% - 1em); background: transparent; padding: 0; resize: none; display: inline-block;\" name=\"body\" placeholder=\"Type your message here...\"></input><input type=\"hidden\" name=\"key\" value=\"$sak\">");
	$page->add("<button class=\"button\" onclick=\"ds_update();\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">send</span></button>");
});
