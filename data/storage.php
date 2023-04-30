<?php

class StorageItem {
	/**
	 * A site storage item
	 */
	
	public $id;
	public $roles;
	public $users;
	
	function __construct(string $id) {
		$db = new Database("storage");
		
		if ($db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->roles = $info->roles;
			$this->users = $info->users;
		}
		else {
			$this->id = $id;
			$this->roles = [];
			$this->users = [];
		}
	}
	
	function exists() {
		return (new Database("storage"))->has($this->id);
	}
	
	function save() {
		$db = new Database("storage");
		$db->save($this->id, $this);
	}
	
	function delete() {
		$db = new Database("storage");
		$db->delete($this->id);
	}
}
