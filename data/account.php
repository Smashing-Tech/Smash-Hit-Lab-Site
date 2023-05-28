<?php

function account_id_from_email(string $email) : string {
	/**
	 * Get the account ID from the user's email
	 */
	
	return sha256("User's Email is " + $email);
}

class Account {
	public $id;
	public $email;
	public $uid;
	
	function __construct(string $id) {
		$db = new Database($id);
		
		if (!$db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->email = $info->email;
			$this->uid = $info->uid;
		}
		else {
			$this->id = $info->id;
			$this->email = $info->email;
			$this->uid = $info->uid;
		}
	}
	
	function save() : void {
		$db = new Database($id);
		$db->save($this->id, $this);
	}
	
	function delete() : void {
		$db = new Database($id);
		$db->delete($this->id);
	}
	
	function from_user() : void {
		
	}
}
