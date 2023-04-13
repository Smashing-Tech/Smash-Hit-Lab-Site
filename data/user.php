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
	
	$alphabet = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*-_+=[]{}<>()";
	
	$pw = random_bytes(25);
	
	for ($i = 0; $i < strlen($pw); $i++) {
		$pw[$i] = $alphabet[floor((ord($pw[$i]) / 255) * strlen($alphabet))];
	}
	
	return $pw;
}

function crush_ip(?string $ip = null) : string {
	/**
	 * Crush an IP address into a partial hash.
	 * 
	 * Normally IP addresses are used to deny access, so it's okay if there are
	 * collisions (and in fact this should help with privacy).
	 * 
	 * TODO IPv6 address might not be handled as well
	 * 
	 * TODO This is also used for denying tokens from the wrong IP, so it's worth
	 * considering if this mitigates that.
	 */
	
	if ($ip === null) {
		$ip = $_SERVER["REMOTE_ADDR"];
	}
	
	return substr(md5($ip), 0, 6);
}

class Token {
	public $name; // Name of the token
	public $user; // Name of the user
	public $created; // Time the user logged in
	public $expire; // Expiration date of the token
	public $ip; // IP the token was created under
	public $lockbox; // IP the token was created under
	
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
			$this->ip = property_exists($token, "ip") ? $token->ip : crush_ip();
			$this->lockbox = property_exists($token, "lockbox") ? $token->lockbox : "";
		}
		// Create a new token
		else {
			$this->name = $name;
			$this->user = null;
			$this->created = time();
			$this->expire = time() + 60 * 60 * 24 * 7 * 2; // Expire in 2 weeks
			$this->ip = crush_ip();
			$this->lockbox = "";
		}
	}
	
	function save() {
		$db = new Database("token");
		$db->save($this->name, $this);
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
	
	function get_user(?string $lockbox = null, bool $require_lockbox = false) {
		/**
		 * Get the username with a token, or null if the token can't be used.
		 * This will also verify the lockbox if given.
		 * 
		 * Lockboxes are not nessicarially enforced here; if you pass in NULL
		 * then the LB isn't checked unless $require_lockbox == true. This is
		 * kind of legacy code.
		 * 
		 * TODO Make it not work this way
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
		
		// Check the lockbox
		$lbok = $this->verify_lockbox($lockbox);
		
		if (($lockbox || $require_lockbox) && !$lbok) {
			return null;
		}
		
		// Not the same IP (TODO: Needs some extra conditions so it's not annoying)
		if ($this->ip !== crush_ip()) {
			$this->delete(); // We also destroy the token in case someone has been
			                 // tampering with it.
			return null;
		}
		
		// probably okay to use
		return $this->user;
	}
	
	function get_id() : string {
		return $this->name;
	}
	
	function make_lockbox() : string {
		/**
		 * Create a lockbox value and store its hash
		 */
		
		$lockbox = random_hex();
		$this->lockbox = hash("sha256", $lockbox);
		$this->save();
		
		return $lockbox;
	}
	
	function verify_lockbox(?string $lockbox) : bool {
		/**
		 * Verify that a lockbox matches
		 */
		
		return ($lockbox) && (hash("sha256", $lockbox) === $this->lockbox);
	}
}

function get_yt_image(string $handle) : string {
	/**
	 * Get the URL of the user's YouTube profile picture.
	 */
	
	try {
		$ytpage = @file_get_contents("https://youtube.com/@$handle/featured");
		
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
	catch (Exception $e) {
		return "";
	}
}

function get_gravatar_image(string $email, string $default = "identicon") : string {
	/**
	 * Get a gravatar image URL.
	 */
	
	return "https://www.gravatar.com/avatar/" . md5(strtolower(trim($email))) . "?s=300&d=$default";
}

function has_gravatar_image(string $email) {
	/**
	 * Check if an email has a gravatar image
	 */
	
	return !!(@file_get_contents(get_gravatar_image($email, "404")));
}

function find_pfp($user) {
	/**
	 * One time find a user's pfp url
	 */
	
	$img_youtube = get_yt_image($user->youtube);
	$img_gravatar = get_gravatar_image($user->email);
	
	// In the case where there is no gravatar but a yt image, display that
	// instead.
	if (!has_gravatar_image($user->email) && $img_youtube) {
		$img_gravatar = null;
	}
	
	return $img_gravatar ? $img_gravatar : $img_youtube;
}

function dechexa(int $num) {
	if ($num < 16) {
		return "0" . dechex($num);
	}
	else {
		return dechex($num);
	}
}

function colour_add(float $scalar, $colour) {
	$colour["red"] += $scalar;
	$colour["green"] += $scalar;
	$colour["blue"] += $scalar;
	
	return $colour;
}

function colour_mul(float $scalar, $colour) {
	$colour["red"] *= $scalar;
	$colour["green"] *= $scalar;
	$colour["blue"] *= $scalar;
	
	return $colour;
}

function colour_hex($colour) {
	return "#" . dechexa(min(floor($colour["red"] * 255), 255)) . dechexa(min(floor($colour["green"] * 255), 255)) . dechexa(min(floor($colour["blue"] * 255), 255));
}

function colour_brightness($colour) {
	$R = $colour["red"] / 255;
	$G = $colour["green"] / 255;
	$B = $colour["blue"] / 255;
	
	return max($R, $G, $B);
}

function colour_saturation($colour) {
	$R = $colour["red"] / 255;
	$G = $colour["green"] / 255;
	$B = $colour["blue"] / 255;
	$M = max($R, $G, $B); $M = $M ? $M : 1;
	
	return 1.0 - (min($R, $G, $B) / $M);
}

function frand() : float {
	return mt_rand() / mt_getrandmax();
}

function special_function($n) {
	return max(1.0 - 4.0 * pow($n - 0.65, 2), -0.1);
}

function get_image_accent_colour(string $url) {
	/**
	 * Get the accent colour of the image at the given URL.
	 */
	
	if (!$url || !function_exists("imagecreatefromjpeg")) {
		return null;
	}
	
	$img = @imagecreatefromjpeg($url);
	
	// Try PNG
	if (!$img) {
		$img = @imagecreatefrompng($url);
	}
	
	if (!$img) {
		return null;
	}
	
	$colours = array();
	
	//floor(imagesx($img) / 2), floor(imagesy($img) / 2)
	
	// Get the accent colour
	// We pick the colour that is most unique. This means we need a function that
	// weighs heavy with large differences but barely does anything with small
	// ones.
	$colour = array("red" => 255, "green" => 255, "blue" => 255);
	$points_to_beat = 0;
	
	for ($i = 0; $i < 135; $i++) {
		// Pick a random point radialy (more likely to hit near the centre)
		$theta = frand() * 6.28;
		$radius = frand();
		
		$x = floor(($radius * cos($theta) + 1.0) * 0.5 * (imagesx($img) - 1));
		$y = floor(($radius * sin($theta) + 1.0) * 0.5 * (imagesx($img) - 1));
		
		$candidate = imagecolorat($img, $x, $y);
		
		// Get the proper colour names
		$candidate = imagecolorsforindex($img, $candidate);
		
		// Calculate score
		$points = colour_saturation($candidate) + 0.5 * special_function(colour_brightness($candidate));
		
		// If we've got a better score then we win!
		if ($points > $points_to_beat) {
			$colour = $candidate;
		}
	}
	
	// Dividing by 255
	$colour = colour_mul(1 / 255, $colour);
	
	// Making it the right brightness
	$colour = colour_mul(1 / colour_brightness($colour), $colour);
	
	// Normalise colour
	$n = sqrt(($colour["red"] * $colour["red"]) + ($colour["green"] * $colour["green"]) + ($colour["blue"] * $colour["blue"]));
	
	if ($n < 0.3) {
		$colour = colour_add(0.1 + $n, $colour);
		$n += 0.1;
	}
	
	$base = colour_mul(1 / $n, $colour);
	
	return derive_pallete_from_colour($base);
}

function derive_pallete_from_colour(array $base) : array {
	// Create variants
	$colours[] = colour_hex(colour_mul(0.1, $base)); // Darkest
	$colours[] = colour_hex(colour_mul(0.15, $base)); // Dark (BG)
	$colours[] = colour_hex(colour_mul(0.245, $base)); // Dark lighter
	$colours[] = colour_hex(colour_add(0.5, colour_mul(0.6, $base))); // Text
	
	return $colours;
}

function colour_from_hex($hex) : array {
	list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
	
	return array("red" => $r / 255, "green" => $g / 255, "blue" => $b / 255);
}

function validate_username(string $name) : bool {
	$chars = str_split("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_-.");
	
	// Charset limit
	for ($i = 0; $i < strlen($name); $i++) {
		if (array_search($name[$i], $chars, true) === false) {
			return false;
		}
	}
	
	// Size limit
	if (strlen($name) > 24) {
		return false;
	}
	
	return true;
}

#[AllowDynamicProperties]
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
			
			$this->schema = (property_exists($info, "schema") ? $info->schema : 0);
			$this->name = $info->name;
			$this->display = (property_exists($info, "display") ? $info->display : $info->name);
			$this->password = $info->password;
			$this->tokens = $info->tokens;
			$this->email = $info->email ? $info->email : "";
			$this->allow_messages = (property_exists($info, "allow_messages") ? $info->allow_messages : false);
			$this->created = (property_exists($info, "created") ? $info->created : time());
			$this->verified = property_exists($info, "verified") ? $info->verified : null;
			$this->ban = property_exists($info, "ban") ? $info->ban : null;
			$this->wall = property_exists($info, "wall") ? $info->wall : random_discussion_name();
			$this->youtube = property_exists($info, "youtube") ? $info->youtube : "";
			$this->image = property_exists($info, "image") ? $info->image : "";
			$this->accent = property_exists($info, "accent") ? $info->accent : null;
			$this->about = property_exists($info, "about") ? $info->about : "";
			$this->sak = property_exists($info, "sak") ? $info->sak : random_hex();
			$this->manual_colour = property_exists($info, "manual_colour") ? $info->manual_colour : "";
			$this->roles = property_exists($info, "roles") ? $info->roles : array();
			
			// If there weren't discussions before, save them now.
			if (!property_exists($info, "wall")) {
				$this->save();
			}
			
			// If we didn't have a pfp before, find and save it now!
			if ((!$this->image) || (!$this->accent)) {
				$this->update_image();
				$this->save();
			}
			
			// Schema R1: Update discussions URL
			if ($this->schema < 2) {
				$disc = new Discussion($this->wall);
				$disc->set_url("./?u=$this->name");
				
				$this->schema = 2;
				$this->save();
			}
		}
		else {
			$this->name = $name;
			$this->display = $name;
			$this->password = null;
			$this->tokens = array();
			$this->email = "";
			$this->created = time();
			$this->verified = null;
			$this->ban = null;
			$this->wall = random_discussion_name();
			$this->youtube = "";
			$this->image = "";
			$this->accent = null;
			$this->about = "";
			$this->sak = random_hex();
			$this->manual_colour = "";
			$this->roles = array();
			
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
		/**
		 * Delete the user
		 */
		
		// Wipe tokens
		$this->wipe_tokens();
		
		// Wipe discussions
		discussion_nuke_comments_by($this->name);
		
		// Delete the user
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
	
	function verify_sak(string $key) : bool {
		/**
		 * Verify that the SAK is okay, and generate the next one.
		 */
		
		if ($this->sak == $key) {
			$this->sak = random_hex();
			$this->save();
			return true;
		}
		else {
			return false;
		}
	}
	
	function get_sak() : string {
		/**
		 * Get the current SAK.
		 */
		
		return $this->sak;
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
		
		$password = @random_password();
		
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
		
		return $token;
	}
	
	function verify(?string $verifier) : void {
		$this->verified = $verifier;
		$this->save();
	}
	
	function is_admin() : bool {
		/**
		 * Check if the user can preform administrative tasks.
		 */
		
		return $this->has_role("admin");
	}
	
	function update_image() : void {
		/**
		 * Update the profile image
		 */
		
		$this->image = find_pfp($this);
		
		if ($this->image) {
			$this->accent = get_image_accent_colour($this->image);
		}
		
		if ($this->manual_colour) {
			$this->accent = derive_pallete_from_colour(colour_from_hex($this->manual_colour));
		}
	}
	
	function get_image() : string {
		return $this->image;
	}
	
	function get_display() : string {
		return $this->display ? $this->display : $this->name;
	}
	
	function set_roles(array $roles) : void {
		/**
		 * Set the user's roles
		 */
		
		$this->roles = $roles;
		$this->save();
	}
	
	function toggle_role(string $role) : bool {
		/**
		 * Toggle a given role
		 */
		
		$roles = $this->roles;
		
		$key = array_search($role, $roles);
		
		$set = false;
		
		// Add role
		if ($key === false) {
			$roles[] = $role;
			$set = true;
		}
		// Remove role
		else {
			$roles = array_diff($roles, array($role));
		}
		
		$this->roles = $roles;
		$this->save();
		
		return $set;
	}
	
	function add_role(string $role) : void {
		if (array_search($role, $this->roles) === false) {
			$this->roles[] = $role;
		}
		
		$this->save();
	}
	
	function remove_role(string $role) : void {
		if (array_search($role, $this->roles) !== false) {
			$this->roles = array_diff($this->roles, array($role));
		}
		
		$this->save();
	}
	
	function has_role(string $role) : bool {
		/**
		 * Check if the user has a certian role
		 */
		
		return (array_search($role, $this->roles) !== false);
	}
	
	function count_roles() : int {
		/**
		 * Get the number of roles this user has
		 */
		
		return sizeof($this->roles);
	}
	
	function get_role_score() : int {
		/**
		 * Get a number assocaited with a user's highest role.
		 */
		
		$n = 0;
		
		for ($i = 0; $i < sizeof($this->roles); $i++) {
			switch ($this->roles[$i]) {
				case "mod": max($n, 1); break;
				case "admin": max($n, 2); break;
				case "headmaster": max($n, 3); break;
				default: break;
			}
		}
		
		return $n;
	}
}

function user_exists(string $username) : bool {
	/**
	 * Check if a user exists in the database.
	 */
	
	$db = new Database("user");
	return $db->has($username);
}

function check_token(string $name, string $lockbox) {
	/**
	 * Given the name of the token, get the user's assocaited name, or NULL if
	 * the token is not valid.
	 */
	
	$token = new Token($name);
	
	return $token->get_user($lockbox, true);
}

function get_name_if_authed() {
	/**
	 * Get the user's name if they are authed properly, otherwise do nothing.
	 */
	
	if (!array_key_exists("tk", $_COOKIE)) {
		return null;
	}
	
	if (!array_key_exists("lb", $_COOKIE)) {
		return null;
	}
	
	return check_token($_COOKIE["tk"], $_COOKIE["lb"]);
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

function user_get_sak() : string {
	/**
	 * Get the SAK of the current user.
	 */
	
	$user = get_name_if_authed();
	
	if (!$user) {
		return "";
	}
	
	return (new User($user))->get_sak();
}

function user_verify_sak(string $key) : bool {
	/**
	 * Verify that the SAK of the current user matches the given one.
	 */
	
	$user = get_name_if_authed();
	
	if (!$user) {
		return false;
	}
	
	return (new User($user))->verify_sak($key);
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
	
	$pfpi = (ord($user->name[0]) % 6) + 1;;
	
	return $user->image ? $user->image : "./img/defaultuser$pfpi.png";
}

function edit_account() {
	/**
	 * Display the account data editing page.
	 */
	
	$user = get_name_if_authed();
	
	global $gTitle; $gTitle = "Editing account info";
	include_header();
	
	if (!$user) {
		echo "<h1>This is strange</h1><p>Please log in to edit your user preferences.</p>";
		include_footer();
		return;
	}
	
	$user = new User($user);
	
	display_user_banner($user);
	
	form_start("./?a=save_account");
	
	edit_feild("display", "text", "Display name", "This is the name that will be displayed instead of your handle. It can be any name you prefer to be called.", $user->display);
	edit_feild("about", "textarea", "About", "You can write a piece of text detailing whatever you like on your userpage. Please don't include personal information!", $user->about);
	edit_feild("youtube", "text", "YouTube", "The handle for your YouTube account, not including the at sign (@). We will use this account to give you a profile picture.", $user->youtube);
	edit_feild("email", "text", "Email", "The email address that you prefer to be contacted about for account related issues.", $user->email, !$user->is_admin());
	edit_feild("colour", "text", "Page colour", "The base colour that the colour of your userpage is derived from. Represented as hex #RRGGBB.", $user->manual_colour);
	edit_feild("messages", "select", "Allow emails", "Choose if you want other users to be able to send you emails without revealing your email address.", $user->allow_messages ? "1" : "0", true, array("0" => "Disallow messages", "1" => "Allow messages"));
	
	form_end("Save account details");
	
	echo "<h2>Other actions</h2>";
	
	edit_feild("delete", "label", "Delete account", "If you would like to delete your account, you can start by clicking the button.", "<a href=\"./?a=account_delete\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">person_off</span> Delete my account</button></a>");
	
	display_user_accent_script($user);
	
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
	
	validate_length("Display name", $_POST["display"], 30);
	validate_length("Email", $_POST["email"], 300);
	validate_length("YouTube", $_POST["youtube"], 30);
	validate_length("Colour", $_POST["colour"], 7);
	validate_length("About", $_POST["about"], 2000);
	
	$user = new User($user);
	$user->display = htmlspecialchars($_POST["display"]);
	
	if (($user->display != $user->name) && user_exists($user->display)) {
		sorry("You cannot set your display name to that of another user's handle.");
	}
	
	$new_mail = htmlspecialchars($_POST["email"]);
	
	if (!$user->is_admin()) {
		$user->email = $new_mail;
	}
	else if ($user->email != $new_mail) {
		sorry("Your rank prevents you from updating your email address.");
	}
	
	$user->youtube = htmlspecialchars($_POST["youtube"]);
	$user->manual_colour = htmlspecialchars($_POST["colour"]);
	
	// User messages
	$user->allow_messages = ($_POST["messages"] === "1") ? true : false;
	
	// If the user started it with an @ then we will try to make it okay for
	// them.
	if (str_starts_with($user->youtube, "@")) {
		$user->youtube = substr($user->youtube, 1);
	}
	
	$user->update_image();
	
	// Finally the about section
	// This is sanitised at display time
	$user->about = $_POST["about"];
	
	$user->save();
	
	redirect("/?u=" . $user->name);
}

function display_user(string $user) {
	/**
	 * Display user account info
	 */
	
	$stalker = get_name_if_authed();
	
	// if (!$stalker) {
	// 	sorry("Only logged in users can view profile pages.");
	// }
	
	// We need this so admins can have some extra options like banning users
	$stalker = $stalker ? (new User($stalker)) : null;
	
	if (!user_exists($user)) {
		sorry("We could not find that user in our database.");
	}
	
	$user = new User($user);
	
	// HACK Page title
	global $gTitle; $gTitle = ($user->display ? $user->display : $user->name) . " (@$user->name)";
	
	include_header();
	
	// 
	// If these contains have passed, we can view the user page
	// 
	
	display_user_banner($user);
	
	// Include the tabs script
	readfile("../data/_user_tabs.html");
	
	// If the user has an about section, then we should show it.
	echo "<div class=\"user-tab-data about\">";
	echo "<h3>About</h3>";
	if ($user->about) {
		$pd = new Parsedown();
		$pd->setSafeMode(true);
		echo $pd->text($user->about);
	}
	else {
		echo "<p><i>This user has not added any information to their about page.</i></p>";
	}
	echo "</div>";
	
	echo "<div class=\"user-tab-data details\">";
	echo "<h3>Details</h3>";
	mod_property("Join date", "The date that the user joined the Smash Hit Lab.", Date("Y-m-d", $user->created));
	
	// Show roles
	if ($user->count_roles()) {
		mod_property("Roles", "Users that are members of certian roles can preform extra administrative actions.", join(", ", $user->roles));
	}
	
	// Maybe show youtube?
	if ($user->youtube) {
		mod_property("YouTube", "This user's YouTube account.", "<a href=\"https://youtube.com/@$user->youtube\">@$user->youtube</a>");
	}
	
	// Show if the user is verified
	if ($user->is_verified()) {
		mod_property("Verified", "Verified members are checked by staff to be who they claim they are.", "Verified by $user->verified");
	}
	
	echo "</div>";
	echo "<div class=\"user-tab-data actions\">";
	
	echo "<h3>Actions</h3>";
	
	// Show the email action if the user allows it
	if ($stalker && $user->allow_messages) {
		mod_property("Email", "You can send a private message to this user via email.", "<a href=\"./?a=user_email&handle=$user->name\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">email</span> Send email</button></a>");
	}
	// We can offer the user to use the message wall
	else {
		mod_property("Send message", "You can send this user a message publicly via their wall.", "<button class=\"button secondary\" onclick=\"user_tabs_select('wall')\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">forum</span> Message wall</button>");
	}
	
	// Admins can view some extra data like emails
	if ($stalker && $stalker->is_admin()) {
		if ($user->is_banned()) {
			mod_property("Unban time", "The time at which this user will be allowed to log in again.", $user->unban_date());
		}
		
		// If the wanted user isn't admin, we can ban them
		if (!$user->is_admin()) {
			mod_property("Ban user", "Banning this user will revoke access and prevent them from logging in until a set amount of time has passed.", "<a href=\"./?a=user_ban&handle=$user->name\"><button><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">gavel</span> Ban this user</button></a>");
		}
		
		mod_property("Change roles", "Roles set permissions for what users are allowed to do. They are often used for giving someone moderator or admin privleges.", "<a href=\"./?a=user_roles&handle=$user->name\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">security</span> Edit roles</button></a>");
		
		mod_property("Verified", "Verified members are checked by staff to be who they claim they are.", "<a href=\"./?a=user_verify&handle=$user->name\"><button class=\"button secondary\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">verified</span> Toggle verified status</button></a>");
	}
	echo "</div>";
	
	// Finally the message wall for this user
	// Display comments
	echo "<div class=\"user-tab-data wall\">";
	$disc = new Discussion($user->wall);
	$disc->display_reverse("Message wall", "./?u=" . $user->name);
	echo "</div>";
	
	// Edit profile button
	if ($stalker && $stalker->name === $user->name) {
		echo "<a href=\"./?a=edit_account\"><button style=\"position: fixed; bottom: 2em; right: 2em;\" class=\"button\"><span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Edit profile</button></a>";
	}
	
	// User tab script
	echo "<script>user_tabs_init();</script>";
	
	// Colourful user profile, if we can show it
	display_user_accent_script($user);
	
	// Footer
	include_footer();
}

function display_user_banner(User $user) {
	$display_name = $user->display ? $user->display : $user->name;
	
	echo "<div class=\"mod-edit-property\">";
		echo "<div class=\"mod-edit-property-label\">";
			if ($user->image) {
				echo "<div class=\"profile-header-image-wrapper\"><img class=\"profile-header-image\" src=\"$user->image\"/></div>";
			}
		echo "</div>";
		echo "<div class=\"mod-edit-property-data\">";
			echo "<h1 class=\"left-align\">$display_name</h1>";
			echo "<h2 class=\"left-align\">@$user->name</h2>";
		echo "</div>";
	echo "</div>";
}

function duas_setvar(string $var, string $val) {
	echo "qs.style.setProperty('$var', '$val');";
}

function display_user_accent_script(User $user) {
	if ($user->accent) {
		$darkest = $user->accent[0];
		$dark = $user->accent[1];
		$darkish = $user->accent[2];
		$bright = $user->accent[3];
		
		echo "<script>var qs = document.querySelector(':root');";
		
		duas_setvar("--colour-primary", $bright);
		duas_setvar("--colour-primary-darker", "#ffffff");
		duas_setvar("--colour-primary-hover", "#ffffff");
		duas_setvar("--colour-primary-a", $bright . "40");
		duas_setvar("--colour-primary-b", $bright . "80");
		duas_setvar("--colour-primary-c", $bright . "c0");
		duas_setvar("--colour-primary-text", "#000000");
		
		duas_setvar("--colour-background-light", $darkish);
		duas_setvar("--colour-background-light-a", $darkish . "40");
		duas_setvar("--colour-background-light-b", $darkish . "80");
		duas_setvar("--colour-background-light-c", $darkish . "c0");
		duas_setvar("--colour-background-light-text", $bright);
		
		duas_setvar("--colour-background", $dark);
		duas_setvar("--colour-background-a", $dark . "40");
		duas_setvar("--colour-background-b", $dark . "80");
		duas_setvar("--colour-background-c", $dark . "c0");
		duas_setvar("--colour-background-text", $bright);
		
		duas_setvar("--colour-background-dark", $darkest);
		duas_setvar("--colour-background-dark-a", $darkest . "40");
		duas_setvar("--colour-background-dark-b", $darkest . "80");
		duas_setvar("--colour-background-dark-c", $darkest . "c0");
		duas_setvar("--colour-background-dark-text", $bright);
		duas_setvar("--colour-background-dark-text-hover", $bright);
		
		echo "</script>";
	}
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

function user_email() {
	$actor = get_name_if_authed();
	
	if ($actor) {
		if (!array_key_exists("submit", $_GET)) {
			include_header();
			echo "<h1>Send email</h1>";
			form_start("./?a=user_email&submit=1");
			
			$know_handle = array_key_exists("handle", $_GET);
			edit_feild("handle", "text", "Handle", "The handle of the user to send mail.", $know_handle ? $_GET["handle"] : "", !$know_handle);
			form_textarea("message", "Message", "The message you want to send this user.");
			
			form_end("Send email");
			include_footer();
		}
		else {
			$handle = htmlspecialchars($_POST["handle"]);
			$message = htmlspecialchars($_POST["message"]);
			
			// Validate message length before adding our own header
			validate_length("Message", $message, 2000);
			
			// Open user info of the actor
			$actor = new User($actor);
			
			// Prepend message header
			$message = "This is a message from $actor->name ($actor->email) at the Smash Hit Lab. It is NOT an official email, and you don't have to reply or acknowledge it.\n\n=============================================\n\n" . $message;
			
			// Open the user info
			$user = new User($handle);
			
			// Disallow sending if the user doesn't want it
			if (!$user->allow_messages) {
				sorry("This user does not want to be sent emails.");
			}
			
			// Send email, making sure we try to reply to the actor.
			mail(
				$user->email,
				"Message from $actor->name",
				$message,
				array("Reply-To" => "$actor->name <$actor->email>")
			);
			
			// Inform the user of success
			inform("Message sent", "The following message was sent to $user->name:</p><p><pre>$message</pre>");
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}

function account_delete() {
	$user = get_name_if_authed();
	
	if ($user) {
		$user = new User($user);
		
		if (!array_key_exists("submit", $_GET)) {
			include_header();
			
			echo "<h1>Delete your account</h1>";
			
			form_start("./?a=account_delete&submit=1");
			
			edit_feild("reason", "text", "Reason", "You can optionally provide us a short reason for deleteing your account.", "");
			edit_feild("understand", "select", "Acknowledgement", "<b>By deleting your account, you agree that your data will be deleted forever, and that there is no possible way we can recover it.</b>", "0", true, array("0" => "No, I don't understand", "1" => "Yes, I understand"));
			
			form_end("Delete my account");
			
			include_footer();
		}
		else {
			// Must accept agreement
			if (!$_POST["understand"]) {
				sorry("You must agree to the acknowledgement in order to delete your account.");
			}
			
			// Must not be admin
			if ($user->is_admin()) {
				sorry("Admin accounts cannot be deleted.");
			}
			
			// Validate the length
			validate_length("Reason", $_POST["reason"], 50);
			
			$reason = htmlspecialchars($_POST["reason"]);
			
			// Send alert
			alert("User account $user->name was deleted: $reason");
			
			// Delete the account
			$user->delete();
			
			// Show the final page ;(
			include_header();
			echo "<h1>Account deleted</h1>";
			echo "<p>Your account has been deleted. Maybe we will see you again? TwT</p>";
			include_footer();
		}
	}
	else {
		sorry("The action you have requested is not currently implemented.");
	}
}
