<!-- Contains user and auth things. -->
<?php

require_once "database.php";

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
		
		if ($this->name == null) {
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
	public $password; // Password hash and salt
	public $tokens; // Currently active tokens
	public $email; // The user's email address
	public $admin; // If the user is an admin
	
	function __construct(string $name) {
		$db = new Database("user");
		
		if ($db->has($name)) {
			$info = $db->load($name);
			
			$this->name = $info->name;
			$this->password = $info->password;
			$this->tokens = $info->tokens;
			$this->email = $info->email;
			$this->admin = $info->admin;
		}
		else {
			$this->name = $name;
			$this->password = null;
			$this->tokens = array();
			$this->email = null;
			$this->admin = false;
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
		
		return $name;
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
