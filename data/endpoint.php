<?php

class EndpointManager {
	/**
	 * An manager for endpoints which allows endpoints to be registered in the
	 * file that they were created so we don't have to keep editing main.php to
	 * add new endpoints.
	 */
	
	private $endpoints;
	
	function __construct() {
		$this->endpoints = array();
	}
	
	function add(string $name, /*function*/ $func) {
		/**
		 * Add an endpoint function.
		 */
		
		$this->endpoints[$name] = $func;
	}
	
	function run(string $name, $context) : bool {
		/**
		 * Run an endpoint given a name for one. Returns if calling was
		 * successful.
		 */
		
		try {
			$this->endpoints[$name]($context);
		}
		catch (Exception) {
			return false;
		}
		
		return true;
	}
}

$gEndMan = new EndpointManager();

$emtest = function($page) {
	$page->add("hewwo! :3");
	$page->send();
};

$gEndMan->add("emtest", $emtest);
