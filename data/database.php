<?php
/**
 * TODO:
 * 
 * - Database locks. These are important!
 */

$database_path = "../data/db/";

class Database {
	/**
	 * "Connection" to the database.
	 */
	
	public $path;
	
	function __construct(string $name) {
		global $database_path;
		
		$this->path = $database_path . $name . "/";
		
		// Make database folder if it doesn't exist
		if (!file_exists($this->path)) {
			mkdir($this->path, 0777, true);
		}
	}
	
	function get_item_path(string $item) : string {
		return $this->path . str_replace("/", ".", $item);
	}
	
	function load(string $item) : object {
		$path = $this->get_item_path($item);
		
		$file = fopen($path, "r");
		$data = json_decode(fread($file, filesize($path)));
		fclose($file);
		
		return $data;
	}
	
	function save(string $item, $data) : void {
		$file = fopen($this->get_item_path($item), "w");
		fwrite($file, json_encode($data));
		fclose($file);
	}
	
	function has(string $item) : bool {
		return file_exists($this->get_item_path($item));
	}
}
