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
}

class Token {
	public $name; // Name of the user
}
