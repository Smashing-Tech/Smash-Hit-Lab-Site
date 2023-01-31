<?php
/**
 * TODO:
 * 
 * - Database locks. These are important!
 */

$database_path = "../data/db/";

/*class RDBObject {
	/**
	 * RevisionDB object container
	 *
	
	public $revisions;
	public $rdb_;
	
	function __construct(object $source) {
		// Check if RevisionDB is already supported. If not enable it.
		if (property_exists($source, "rdb_")) {
			$this->revisions = $source->revisions;
			$this->rdb_ = 1;
		}
		else {
			$this->revisions = array($source);
			$this->rdb_ = 1;
		}
	}
	
	function top() {
		return $this->revisions[];
	}
	
	function add(object $item) {
		$this->revisions[] = $item;
	}
}*/

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
		
		// Force RevisionDB
	}
	
	function get_item_path(string $item) : string {
		/**
		 * Get the path to the file in the database.
		 */
		
		return $this->path . str_replace("/", ".", $item);
	}
	
	function load(string $item) : object | array {
		/**
		 * Load a database object
		 */
		
		$path = $this->get_item_path($item);
		
		$file = fopen($path, "r");
		
		// Need to clear cache to make sure things work; otherwise there are bugs.
		clearstatcache();
		
		$json_data = fread($file, filesize($path));
		
		$data = json_decode($json_data);
		
		fclose($file);
		
		return $data;
	}
	
	function save(string $item, $data) : void {
		/**
		 * Save a database object
		 */
		
		$file = fopen($this->get_item_path($item), "w");
		fwrite($file, json_encode($data));
		fclose($file);
	}
	
	function delete(string $item) : void {
		/**
		 * Remove a database object
		 */
		
		unlink($this->get_item_path($item));
	}
	
	function has(string $item) : bool {
		return file_exists($this->get_item_path($item));
	}
	
	function enumerate() : array {
		$array = scandir($this->get_item_path(""));
		array_shift($array);
		array_shift($array);
		return $array;
	}
}

function enumerate_stores() {
	/**
	 * Enumerate available database stores
	 */
	
	global $database_path;
	
	$array = scandir($database_path);
	array_shift($array);
	array_shift($array);
	return $array;
}

function backup_database() : string {
	/**
	 * Back up the databse to a zip file
	 */
	
	global $database_path;
	
	$zip = new ZipArchive();
	
	$filename = "../data/store/smashhitlab_" . date("Y-m-d_His", time()) . ".zip";
	
	$zip->open($filename, ZIPARCHIVE::CREATE);
	$zip->addEmptyDir("db");
	
	$stores = enumerate_stores();
	
	for ($j = 0; $j < sizeof($stores); $j++) {
		$db = new Database($stores[$j]);
		$files = $db->enumerate();
		
		$zip->addEmptyDir("db/" . $stores[$j]);
		
		for ($i = 0; $i < sizeof($files); $i++) {
			$zip->addFile($database_path . "/" . $stores[$j] . "/" . $files[$i], "db/" . $stores[$j] . "/" . $files[$i]);
		}
	}
	
	$zip->close();
	
	return $filename;
}
