<?php

/**
 * LOGIN FORM
 */

function auth_login_availability(Page $page, ?string $handle = null) {
	/**
	 * Check the status of being able to log in. The user handle should be passed
	 * when actually logging in, but can be used to show login disabled messages
	 * early if they are disabled sitewide.
	 */
	
	$verified = true;
	$admin = true;
	
	if ($handle) {
		$u = new User($handle);
		
		$verified = $u->is_verified();
		$admin = $u->is_admin();
	}
	
	switch (get_config("enable_login", "users")) {
		case "closed":
			$page->info("Sorry!", "Logging in has been disabled for all users. Please join our Discord server for updates.");
			break;
		case "admins":
			if (!$admin) {
				$page->info("Sorry!", "We have disabled logging in for most users at the moment. Please join our Discord for any updates.");
			}
			break;
		case "verified":
			if (!$admin && !$verified) {
				$page->info("Sorry!", "We have disabled logging in for most users at the moment. Please join our Discord for any updates.");
			}
			break;
		case "users":
			break;
		default:
			$page->info("This is strange!", "The site operator has not configured the site correctly. To be safe, no one is allowed to log in. Please have the hosting party delete the invalid file at \"data/db/site/settings\", then logins will be enabled again.");
			break;
	}
}

function auth_login_form(Page $page) {
	// Global header
	$page->global_header();
	
	// Check if logins are enabled
	auth_login_availability($page);
	
	// Create the login form
	$form = new Form("./?a=auth-login&submit=1");
	$form->set_container_type(FORM_CONTAINER_BLANK);
	$form->textbox("handle", "Handle", "The handle is the account name that you signed up for.");
	$form->password("password", "Password", "Your password was sent to your email when your account was created.");
	$form->submit("Login");
	
	$page->add("<div class=\"auth-form-box\">");
	
	// Heading and text
	$page->heading(1, "Log in", "20pt");
	$page->para("Enter your handle and password to log in to the Smash Hit Lab. Don't have an account? <a href=\"./?a=auth-register\">Create an account!</a>");
	
	// Add form
	$page->add($form);
	
	$page->add("</div>");
	
	// Add the global footer
	$page->global_footer();
}

function auth_login_action(Page $page) {
	$handle = $page->get("handle", true, 24, SANITISE_HTML, true);
	$password = $page->get("password", true, 100, SANITISE_NONE, true);
	$ip = crush_ip();
	$real_ip = $_SERVER['REMOTE_ADDR'];
	
	// Check if logins are enabled
	auth_login_availability($page, $handle);
	
	// Validate the handle
	if (!validate_username($handle)) {
		$page->info("Sorry!", "Your handle isn't valid. Handles can be at most 24 characters and must only use upper and lower case A - Z as well as underscores (<code>_</code>), dashes (<code>-</code>) and fullstops (<code>.</code>).");
	}
	
	// Check that the handle exists
	if (!user_exists($handle)) {
		$page->info("Sorry!", "Something went wrong while logging in. Make sure your username and password are correct, then try again.");
	}
	
	// Now that we know we can, open the user's info!
	$user = new User($handle);
	
	// Check if this user or their IP is banned, if they are not admin
	if (!$user->is_admin()) {
		// User ban
		if ($user->is_banned()) {
			$until = $user->unban_date();
			
			if ($until == "forever") {
				$page->info("You are banned forever", "You have been banned from the Smash Hit Lab.");
			}
			
			$page->info("You are banned", "You have been banned from the Smash Hit Lab until " . date("Y-m-d h:i", $until) . ".");
		}
		
		// IP ban
		if (is_ip_blocked($ip)) {
			$page->info("Sorry!", "Something went wrong while logging in. Make sure your username and password are correct, then try again.");
		}
	}
	
	// Now that we should be good, let's try to issue a token
	$token = $user->issue_token($password);
	
	if (!$token) {
		// If this is an admin, warn about failed logins.
		if ($user->admin) {
			mail($user->email, "Failed login for " . $handle, "For site safety purposes, admins are informed any time a failed login occurs on their account. If this was you, there is no need to worry.\n\nUsername: " . $handle . "\nPassword: " . htmlspecialchars($password) . "\nIP Address: " . $real_ip);
		}
		
		// We send a notification to that user when they fail to log in
		// NOTE Since we don't really have any rate limits on logins I have
		// made this available to all users, instead of admins like on the old
		// login handling code.
		notify($user->name, "Login failed from $real_ip", "/");
		
		$page->info("Sorry!", "Something went wrong while logging in. Make sure your username and password are correct, then try again.");
	}
	
	// We should be able to log the user in
	$page->cookie("tk", $token->get_id(), 60 * 60 * 24 * 14);
	$page->cookie("lb", $token->make_lockbox(), 60 * 60 * 24 * 14);
	
	// Redirect to homepage
	$page->redirect("/?u=$handle");
}

$gEndMan->add("auth-login", function($page) {
	$submitting = $page->has("submit");
	
	if ($submitting) {
		auth_login_action($page);
	}
	else {
		auth_login_form($page);
	}
});

/**
 * REGISTER FORM
 */

function auth_register_form(Page $page) {
	// Global header
	$page->global_header();
	
	// Check if logins are enabled
	auth_login_availability($page);
	
	// Create the login form
	$form = new Form("./?a=auth-login&submit=1");
	//$form->set_container_type(FORM_CONTAINER_BLANK);
	$form->textbox("handle", "Handle", "Pick a handle name that you would like. Please note that you can't change it later.");
	$form->textbox("email", "Email", "The email address you wish to assocaite with your account.");
	$form->day("birth", "Birthday", "Please enter your birthday so we can verify that you are old enough to join the Smash Hit Lab.");
	$form->container("Password", "A special string of characters you need in order to log in to your account.", "
			<ul>
				<li>We will generate a secure password and send it to your email. You do not need to worry about choosing a password.</li>
				<li>We recommend using some kind of password manager &mdash; preferably locally stored &mdash; to store your password as it will be long and random.</li>
			</ul>");
	$form->container("Terms", "Terms help protect us from each other and set standards on how we should behave.", "
			<p>When you sign up for an account, you agree to the following documents:</p>
			<ul>
				<li><a href=\"./?p=tos\">Terms of Service</a></li>
				<li><a href=\"./?p=privacy\">Privacy Policy</a></li>
				<li><a href=\"./?p=disclaimer\">General Disclaimers</a></li>
			</ul>
			<p>Most importantly:</p>
			<ul>
				<li>You need to be 16 or older in order to use the Smash Hit Lab. We will remove your account if we find that you are under 16 years old.</li>
				<li>We do not provide warranty or support unless required by law, and we shouldn't be held liable for damages related to the site unless required by law.</li>
				<li>We can update the terms of our contracts at any time and force you to stop using our services if you disagree with the new terms.</li>
			</ul>");
	$form->submit("Create account");
	
	$page->add("<div class=\"auth-form-box\">");
	
	// Heading and text
	$page->heading(1, "Create an account", "20pt");
	$page->para("To create an account at the Smash Hit Lab, decide what your handle will be, enter your email address and brithdate, then create your account. Already have an account? <a href=\"./?a=auth-login\">Log in!</a>");
	
	// Add form
	$page->add($form);
	
	$page->add("</div>");
	
	// Add the global footer
	$page->global_footer();
}

$gEndMan->add("auth-register", function($page) {
	$submitting = $page->has("submit");
	
	if ($submitting) {
		auth_login_action($page);
	}
	else {
		auth_register_form($page);
	}
});
