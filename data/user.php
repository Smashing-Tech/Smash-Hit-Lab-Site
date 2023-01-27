<?php

require_once "database.php";
require_once "templates.php";

function random_hex() : string {
	/**
	 * Cryptographically secure random hex values.
	 */
	
	return bin2hex(random_bytes(32));
}

function random_password() : string {
	/**
	 * Randomly generates a new password.
	 */
	
	$alphabet = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*-_+=";
	
	$pw = random_bytes(25);
	
	// TODO: The distribution isn't even in this case..
	for ($i = 0; $i < strlen($pw); $i++) {
		$pw[$i] = $alphabet[ord($pw[$i]) % strlen($alphabet)];
	}
	
	return $pw;
}

class Token {
	public $name; // Name of the token
	public $user; // Name of the user
	public $created; // Time the user logged in
	public $expire; // Expiration date of the token
	
	function __construct(string $name = null) {
		$db = new Database("token");
		
		// Generate new token name
		// We just reroll until we get an unused one
		if (!$name) {
			do {
				$name = random_hex();
			} while ($db->has($name));
		}
		
		// Load an existing token
		if ($db->has($name)) {
			$token = $db->load($name);
			
			$this->name = $token->name;
			$this->user = $token->user;
			$this->created = $token->created;
			$this->expire = $token->expire;
		}
		// Create a new token
		else {
			$this->name = $name;
			$this->user = null;
			$this->created = time();
			$this->expire = time() + 60 * 60 * 24 * 7 * 2; // Expire in 2 weeks
		}
	}
	
	function set_user(string $user) {
		/**
		 * Set who the token is for if not already set. We don't allow changing
		 * the name once it is set for safety reasons.
		 * 
		 * This returns the name of the issued token if it works.
		 */
		
		if ($this->user == null) {
			$this->user = $user;
			
			$db = new Database("token");
			
			$db->save($this->name, $this);
			
			return $this->name;
		}
		
		return null;
	}
	
	function get_user() {
		/**
		 * Get the username with a token, or null if the token can't be used.
		 */
		
		// Not initialised
		if ($this->user == null) {
			return null;
		}
		
		// Expired
		if (time() >= $this->expire) {
			return null;
		}
		
		// Too early
		if (time() < $this->created) {
			return null;
		}
		
		// probably okay to use
		return $this->user;
	}
}

class User {
	public $name; // Yes, it would probably be better to store users by ID.
	public $display; // The display name of the user
	public $password; // Password hash and salt
	public $tokens; // Currently active tokens
	public $email; // The user's email address
	public $created; // The time the user joined our site
	public $admin; // If the user is an admin
	
	function __construct(string $name) {
		$db = new Database("user");
		
		if ($db->has($name)) {
			$info = $db->load($name);
			
			$this->name = $info->name;
			$this->display = (property_exists($info, "display") ? $info->display : $info->name);
			$this->password = $info->password;
			$this->tokens = $info->tokens;
			$this->email = $info->email;
			$this->created = (property_exists($info, "created") ? $info->created : time());
			$this->admin = $info->admin;
			$this->wall = property_exists($info, "wall") ? $info->wall : random_discussion_name();
			
			// If there weren't discussions before, save them now.
			if (!property_exists($info, "wall")) {
				$this->save();
			}
		}
		else {
			$this->name = $name;
			$this->display = $name;
			$this->password = null;
			$this->tokens = array();
			$this->email = null;
			$this->created = time();
			$this->admin = false;
			$this->wall = random_discussion_name();
		}
	}
	
	function save() : void {
		$db = new Database("user");
		
		$db->save($this->name, $this);
	}
	
	function set_password(string $password) : bool {
		/**
		 * Set the user's password.
		 * 
		 * @return False on failure, true on success
		 */
		
		$this->password = password_hash($password, PASSWORD_ARGON2I);
		
		return true;
	}
	
	function new_password() : string {
		/**
		 * Generate a new password for this user.
		 * 
		 * @return The plaintext password is returned and a hashed value is
		 * stored.
		 */
		
		$password = random_password();
		
		$this->set_password($password);
		
		return $password;
	}
	
	function set_email(string $email) : void {
		/**
		 * Set the email for this user.
		 */
		
		$this->email = $email;
	}
	
	function authinticate(string $password) : bool {
		/**
		 * Check the stored password against the given password.
		 */
		
		return password_verify($password, $this->password);
	}
	
	function issue_token(string $password) {
		/**
		 * Add a new token for this user and return its name.
		 */
		
		// Check the password
		if (!$this->authinticate($password)) {
			return null;
		}
		
		// Create a new token
		$token = new Token();
		$name = $token->set_user($this->name);
		$this->tokens[] = $name;
		$this->save();
		
		return $name;
	}
	
	function is_admin() {
		/**
		 * Check if the user can preform administrative tasks.
		 */
		
		return $this->admin;
	}
}

function user_exists(string $username) : bool {
	/**
	 * Check if a user exists in the database.
	 */
	
	$db = new Database("user");
	return $db->has($username);
}

function check_token(string $name) {
	/**
	 * Given the name of the token, get the user's assocaited name, or NULL if
	 * the token is not valid.
	 */
	
	$token = new Token($name);
	return $token->get_user();
}

function get_name_if_authed() {
	/**
	 * Get the user's name if they are authed properly, otherwise do nothing.
	 */
	
	if (!array_key_exists("tk", $_COOKIE)) {
		return null;
	}
	
	return check_token($_COOKIE["tk"]);
}

function get_display_name_if_authed() {
	/**
	 * Get the user's preferred disply name if authed.
	 */
	
	$user = get_name_if_authed();
	
	if (!$user) {
		return null;
	}
	
	$user = new User($user);
	return $user->display ? $user->display : $user->name;
}

function get_name_if_admin_authed() {
	/**
	 * Get the user's name if they are authed and they are an admin.
	 */
	
	$user = get_name_if_authed();
	
	// Check if authed
	if (!$user) {
		return null;
	}
	
	$user = new User($user);
	
	// Check if admin
	if (!$user->is_admin()) {
		return null;
	}
	
	return $user->name;
}

function get_nice_display_name(string $user) {
	/**
	 * Get a nicely formatted display name for any user.
	 */
	
	$user = new User($user);
	
	$string = "";
	
	if ($user->name == $user->display) {
		$string = "<a href=\"./?u=$user->name\">$user->name</a>";
	}
	else {
		$string = "<a href=\"./?u=$user->name\">$user->display</a>";//<span class=\"small-text\"> [$user->name]</span>";
	}
	
	if ($user->admin) {
		$string = $string . " <span class=\"small-text staff-badge\">staff</span>";
	}
	
	return $string;
}

function edit_account() {
	/**
	 * Display the account data editing page.
	 */
	
	$user = get_name_if_authed();
	
	include_header();
	
	if (!$user) {
		echo "<h1>This is strange</h1><p>Please log in to edit your user preferences.</p>";
		include_footer();
		return;
	}
	
	$user = new User($user);
	
	echo "<h1>Account information</h1>";
	echo "<form action=\"./?a=save_account\" method=\"post\">";
	
	edit_feild("name", "text", "Handle", "The string that idenifies you in the database.", $user->name, false);
	edit_feild("display", "text", "Display name", "Choose the name that you prefer to be called.", $user->display);
	edit_feild("email", "text", "Email", "The email address that you prefer to be contacted about for account related issues.", $user->email);
	
	echo "<input type=\"submit\" value=\"Save details\"/>";
	echo "</form>";
	
	include_footer();
}

function save_account() {
	/**
	 * Save account details
	 */
	
	$user = get_name_if_authed();
	
	if (!$user) {
		include_header();
		echo "<h1>This is strange</h1><p>Please log in to edit your user preferences.</p>";
		include_footer();
		return;
	}
	
	$user = new User($user);
	$user->display = htmlspecialchars($_POST["display"]);
	$user->email = htmlspecialchars($_POST["email"]);
	$user->save();
	
	redirect("/?a=edit_account");
}

function display_user(string $user) {
	/**
	 * Display user account info
	 */
	
	$stalker = get_name_if_authed();
	
	if (!$stalker) {
		include_header();
		echo "<h1>Sorry</h1><p>Only logged in users can view profile pages.</p>";
		include_footer();
		return;
	}
	
	// We need this so admins can have some extra options like banning users
	$stalker = new User($stalker);
	
	if (!user_exists($user)) {
		echo "<h1>Sorry</h1><p>We could not find that user in our database.</p>";
		return;
	}
	
	$user = new User($user);
	
	if (!$user->is_admin() && !$stalker->is_admin() && ($stalker != $user)) {
		echo "<h1>Sorry</h1><p>We don't allow viewing non-admin user profiles.</p>";
		return;
	}
	
	// If these contains have passed, we can view the user page
	$display_name = $user->display ? $user->display : $user->name;
	echo "<h1>$display_name</h1><h2>($user->name)</h2>";
	
	mod_property("Join date", "The date that the user joined the Smash Hit Lab.", Date("Y-m-d", $user->created));
	
	// Show if this user is an admin
	if ($user->is_admin()) {
		mod_property("Rank", "The offical position this user holds at the Smash Hit Lab.", "Staff and Administrators");
	}
	
	// Admins can view some extra data like emails
	if ($stalker->is_admin()) {
		echo "<h3>Admin-only info and actions</h3>";
		
		mod_property("Email", "This user's email address.", $user->email);
		mod_property("Token count", "The number of currently active tokens this user has.", sizeof($user->tokens));
	}
	
	// Finally the message wall for this user
	// Display comments
	$disc = new Discussion($user->wall);
	$disc->display_reverse("Message wall", "./?u=" . $user->name);
}
