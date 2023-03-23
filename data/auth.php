<?php

function auth_login_form(Page $page) {
	$page->redirect("./?p=login");
}

function auth_login_action(Page $page) {
	$handle = $page->get("handle", true, $require_post = true);
	$password = $page->get("password", true, $sanitise = SANITISE_NONE, $require_post = true);
	
	// TODO migrate login function to here
	
	$page->redirect("./?u=$handle");
}

$gEndMan->add("auth-login", function($page) {
	$submitting = $page->has("submit");
	
	if ($submitting) {
		auth_login_action($page);
	}
	else {
		auth_login_form($page);
	}
})
