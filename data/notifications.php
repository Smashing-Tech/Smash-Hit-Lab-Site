<?php
/**
 * User notifications
 */

require_once "user.php";
require_once "templates.php";
require_once "config.php"; // For admin alerts

class Notification {
	/**
	 * A single notification.
	 */
	
	public $title; // Title of the notification
	public $url; // Notification URL
	public $created; // Time of the notification
	
	function __construct(?string $title = null, ?string $url = null) {
		if ($title !== null && $url !== null) {
			$this->title = $title;
			$this->url = $url;
			$this->created = time();
		}
	}
	
	function set(string $title, string $url) {
		$this->title = $title;
		$this->url = $url;
		$this->created = time();
		
		return $this;
	}
	
	function load(object $info) {
		$this->title = $info->title;
		$this->url = $info->url;
		$this->created = $info->created;
		
		return $this;
	}
	
	function render() {
		$title = htmlspecialchars($this->title);
		$url = htmlspecialchars($this->url);
		$date = date("Y-m-d H:i:s", $this->created);
		
		return ($url) ? "<li>$date &mdash; <a href=\"$url\">$title</a></li>" : "<li>$date &mdash $title</li>";
	}
}

class UserNotifications {
	/**
	 * The notifications for a single user.
	 */
	
	public $name; // Name of the user
	public $notifications; // The user's noifications
	public $db_name; // The database to use (default: notify)
	
	function __construct(string $name, string $db_name = "notify") {
		$db = new Database($db_name);
		
		if ($db->has($name)) {
			$info = $db->load($name);
			
			$this->name = $info->name;
			$this->notifications = $info->notifications;
			
			// Just as we do with user comments, we make sure these are
			// really notification objects.
			for ($i = 0; $i < sizeof($this->notifications); $i++) {
				$this->notifications[$i] = (new Notification())->load($this->notifications[$i]);
			}
		}
		else {
			$this->name = $name;
			$this->notifications = array();
		}
		
		$this->db_name = $db_name;
	}
	
	function save() {
		$db = new Database($this->db_name);
		$db->save($this->name, $this);
	}
	
	function clear() {
		$this->notifications = array();
		$this->save();
	}
	
	function notify(string $title, string $url = "") {
		$this->notifications[] = (new Notification())->set($title, $url);
		$this->save();
	}
	
	function number() {
		return sizeof($this->notifications);
	}
	
	function display($title = "Notifications") {
		if ($title) {
			echo "<h1>$title</h1>";
		}
		
		if (sizeof($this->notifications) === 0) {
			echo "<p><i>No new notifications!</i></p>";
			return;
		}
		
		echo "<ul>";
		
		for ($i = 0; $i < sizeof($this->notifications); $i++) {
			echo $this->notifications[$i]->render();
		}
		
		echo "</ul>";
	}
}

function notify(string $user, string $title, string $url) {
	/**
	 * Add a notification to a user's inbox.
	 */
	
	$un = new UserNotifications($user);
	$un->notify($title, $url);
}

function notify_many(array $users, string $title, string $url) {
	/**
	 * Notify a list of a users
	 */
	
	for ($i = 0; $i < sizeof($users); $i++) {
		notify($users[$i], $title, $url);
	}
}

function check_notifications() {
	/**
	 * Notification checking action handler.
	 */
	
	$user = get_name_if_authed();
	
	if (!$user) {
		sorry("To check your notifications, please log in first.");
	}
	
	$un = new UserNotifications($user);
	
	include_header();
	$un->display();
	include_footer();
	
	$un->clear();
}

function display_notification_charm(string $name) {
	/**
	 * Display the notifications text in the navbar, if there are any to display.
	 */
	
	$un = new UserNotifications($name);
	$count = $un->number();
	
	if ($count) {
		echo "<div class=\"cb-top-item\"><a href=\"./?a=notifications\">Notifications ($count)</a></div>";
	}
}

/**
 * ADMIN ALERTS
 */

function alert(string $title, string $url = "") {
	/**
	 * Add a notification to a user's inbox.
	 */
	
	$users = get_config("admins", array());
	
	for ($i = 0; $i < sizeof($users); $i++) {
		$un = new UserNotifications($users[$i], "alert");
		$un->notify($title, $url);
	}
}
