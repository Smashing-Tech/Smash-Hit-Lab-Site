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
	public $ip; // IP the token was created under
	
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
			$this->ip = property_exists($token, "ip") ? $token->ip : "0.0.0.0";
		}
		// Create a new token
		else {
			$this->name = $name;
			$this->user = null;
			$this->created = time();
			$this->expire = time() + 60 * 60 * 24 * 7 * 2; // Expire in 2 weeks
			$this->ip = $_SERVER['REMOTE_ADDR'];
		}
	}
	
	function delete() {
		/**
		 * Delete the token so it can't be used anymore.
		 */
		
		$db = new Database("token");
		
		if ($db->has($this->name)) {
			$db->delete($this->name);
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

function get_yt_image(string $handle) : string {
	/**
	 * Get the URL of the user's YouTube profile picture.
	 */
	
	$ytpage = file_get_contents("https://youtube.com/@$handle/featured");
	
	if (!$ytpage) {
		return "";
	}
	
	$before = "<meta property=\"og:image\" content=\"";
	
	if ($before < 0) {
		return "";
	}
	
	// Carve out anything before this url
	$i = strpos($ytpage, $before);
	$ytpage = substr($ytpage, $i + strlen($before));
	
	// Carve out anything after this url
	$i = strpos($ytpage, "\"");
	$ytpage = substr($ytpage, 0, $i);
	
	// We have the string!!!
	return $ytpage;
}

class User {
	// NOTE These are not updated and since I don't have to add them I'm not
	//      going to do that.
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
			$this->verified = property_exists($info, "verified") ? $info->verified : null;
			$this->admin = $info->admin;
			$this->ban = property_exists($info, "ban") ? $info->ban : null;
			$this->wall = property_exists($info, "wall") ? $info->wall : random_discussion_name();
			$this->youtube = property_exists($info, "youtube") ? $info->youtube : "";
			$this->ytimg = property_exists($info, "ytimg") ? $info->ytimg : "";
			
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
			$this->verified = null;
			$this->admin = false;
			$this->ban = null;
			$this->wall = random_discussion_name();
			$this->youtube = "";
			$this->ytimg = "";
			
			// Make sure the new user is following their wall by default.
			$d = new Discussion($this->wall);
			$d->toggle_follow($this->name);
		}
	}
	
	function save() : void {
		$db = new Database("user");
		
		$db->save($this->name, $this);
	}
	
	function wipe_tokens(bool $ipban = false, ?int $duration = null) : void {
		/**
		 * Delete any active tokens this user has. If $ipban is true, any ip's
		 * assocaited with the tokens are also banned. You must provide $duration
		 * if $ipban == true
		 */
		
		$tdb = new Database("token");
		
		for ($i = 0; $i < sizeof($this->tokens); $i++) {
			if ($tdb->has($this->tokens[$i])) {
				if ($ipban) {
					$token = new Token($this->tokens[$i]);
					block_ip($token->ip, $duration);
				}
				
				$tdb->delete($this->tokens[$i]);
			}
		}
		
		$this->tokens = array();
	}
	
	function delete() : void {
		$db = new Database("user");
		
		$db->delete($this->name);
	}
	
	function set_ban(?int $until) : void {
		$this->ban = ($until === -1) ? (-1) : (time() + $until);
		$this->wipe_tokens(true, $until);
		$this->save();
	}
	
	function unset_ban() : void {
		$this->ban = null;
		$this->save();
	}
	
	function ban_expired() : bool {
		return ($this->ban !== -1) && (time() > $this->ban);
	}
	
	function is_banned() : bool {
		/**
		 * Update banned status and check if the user is banned.
		 */
		
		if ($this->ban_expired()) {
			$this->unset_ban();
		}
		
		return ($this->ban !== null);
	}
	
	function is_verified() : bool {
		return ($this->verified != null);
	}
	
	function unban_date() : string {
		/**
		 * The user must be banned for this to return a value.
		 */
		
		if ($this->ban > 0) {
			return date("Y-m-d H:i:s", $this->ban);
		}
		else {
			return "forever";
		}
	}
	
	function clean_foreign_tokens() : void {
		/**
		 * Clean the any tokens this user claims to have but does not
		 * actually have.
		 */
		
		$db = new Database("token");
		$valid = array();
		
		// TODO Yes, I really shouldn't work with database primitives here, but
		// I can't find what I called the standard functions to do this stuff.
		for ($i = 0; $i < sizeof($this->tokens); $i++) {
			if ($db->has($this->tokens[$i])) {
				$token = new Token($this->tokens[$i]);
				
				if ($token->get_user() === $this->name) {
					// It should be a good token.
					$valid[] = $this->tokens[$i];
				}
				else {
					// It's a dirty one!
					$token->delete();
				}
			}
		}
		
		$this->tokens = $valid;
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
		
		// First, run maintanance
		$this->clean_foreign_tokens();
		
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
	
	function verify(string $verifier) {
		$this->verified = $verifier;
		$this->save();
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

function get_nice_display_name(string $user, bool $badge = true) {
	/**
	 * Get a nicely formatted display name for any user.
	 */
	
	if (!user_exists($user)) {
		return "Deleted user";
	}
	
	$user = new User($user);
	
	$string = "";
	
	if ($user->name == $user->display) {
		$string = "<a href=\"./?u=$user->name\">$user->name</a>";
	}
	else {
		$string = "<a href=\"./?u=$user->name\">$user->display</a>";
	}
	
	if ($badge) {
		if ($user->is_admin()) {
			$string = $string . " <span class=\"small-text staff-badge\">staff</span>";
		}
		else if ($user->is_banned()) {
			$string = $string . " <span class=\"small-text banned-badge\">banned</span>";
		}
		else if ($user->is_verified()) {
			$string = $string . " <span class=\"small-text verified-badge\">verified</span>";
		}
	}
	
	return $string;
}

function get_profile_image(string $user) {
	/**
	 * Get the URL to a user's profile image.
	 */
	
	$user = new User($user);
	
	return $user->ytimg;
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
	edit_feild("youtube", "text", "YouTube", "The handle for your YouTube account.", $user->youtube);
	
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
		sorry("Please log in to edit your user preferences.");
	}
	
	$user = new User($user);
	$user->display = htmlspecialchars($_POST["display"]);
	
	if (($user->display != $user->name) && user_exists($user->display)) {
		sorry("You cannot set your display name to that of another user's handle.");
	}
	
	$user->email = htmlspecialchars($_POST["email"]);
	$user->youtube = htmlspecialchars($_POST["youtube"]);
	
	if ($user->youtube) {
		$user->ytimg = get_yt_image($user->youtube);
	}
	
	$user->save();
	
	redirect("/?a=edit_account");
}

function display_user(string $user) {
	/**
	 * Display user account info
	 */
	
	$stalker = get_name_if_authed();
	
	if (!$stalker) {
		sorry("Only logged in users can view profile pages.");
	}
	
	// We need this so admins can have some extra options like banning users
	$stalker = new User($stalker);
	
	if (!user_exists($user)) {
		sorry("We could not find that user in our database.");
	}
	
	$user = new User($user);
	
	// HACK Page title
	global $gTitle; $gTitle = ($user->display ? $user->display : $user->name) . " (@$user->name)";
	
	include_header();
	
	// if (!$user->is_admin() && !$stalker->is_admin() && ($stalker != $user)) {
	// 	echo "<h1>Sorry</h1><p>We don't allow viewing non-admin user profiles.</p>";
	// 	return;
	// }
	
	// If the user has a YouTube PFP, then display it large!
	if ($user->ytimg) {
		echo "<div class=\"profile-header-image-wrapper\"><img class=\"profile-header-image\" src=\"$user->ytimg\"/></div>";
	}
	
	// If these contains have passed, we can view the user page
	$display_name = $user->display ? $user->display : $user->name;
	echo "<h1>$display_name</h1><h2>@$user->name</h2>";
	
	mod_property("Join date", "The date that the user joined the Smash Hit Lab.", Date("Y-m-d", $user->created));
	
	// Show if this user is an admin
	if ($user->is_admin()) {
		mod_property("Rank", "The offical position this user holds at the Smash Hit Lab.", "Staff and Administrators");
	}
	
	// Maybe show youtube?
	if ($user->youtube) {
		mod_property("YouTube", "This user's YouTube account.", "<a href=\"https://youtube.com/@$user->youtube\">@$user->youtube</a>");
	}
	
	// Show if the user is verified
	if ($user->is_verified()) {
		mod_property("Verified", "Verified members are checked by staff to be who they claim they are.", "Verified by $user->verified");
	}
	
	// Admins can view some extra data like emails
	if ($stalker->is_admin()) {
		echo "<h3>Admin-only info and actions</h3>";
		
		mod_property("Email", "This user's email address.", $user->email);
		mod_property("Token count", "The number of currently active tokens this user has.", sizeof($user->tokens));
		mod_property("Ban status", "When this user will be unbanned, if banned.", ( $user->is_banned() ? $user->unban_date() : "Not banned" ));
		mod_property("Verified", "Verified members are checked by staff to be who they claim they are.", "<a href=\"./?a=user_verify&handle=$user->name\"><button>Toggle verified status</button></a>");
	}
	
	// Finally the message wall for this user
	// Display comments
	$disc = new Discussion($user->wall);
	$disc->display_reverse("Message wall", "./?u=" . $user->name);
	
	// Footer
	include_footer();
}

function user_verify() {
	$verifier = get_name_if_admin_authed();
	
	if ($verifier) {
		$handle = htmlspecialchars($_GET["handle"]);
		
		$user = new User($handle);
		
		if ($user->is_verified()) {
			$user->verify(null);
		}
		else {
			$user->verify($verifier);
		}
		
		alert("User $user->name was marked verified", "./?u=$user->name");
		
		redirect("./?u=$user->name");
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}
