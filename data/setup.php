<?php

function setup_is_available() {
	$db = new Database("user");
	
	return (sizeof($db->enumerate()) === 0);
}

function setup_form(Page $page) {
	if (!setup_is_available()) {
		$page->info("This is odd", "It seems like your Labbyware install is already set up.");
	}
	
	global $gDatabasePath;
	
	$page->global_header();
	$page->heading(1, "Site setup");
	
	$form = new Form("./?a=site-setup&submit=1");
	$form->container("Welcome!", "Welcome to Labbyware!", "Labbyware is a combination of a news site, wiki and forum that allows communities to interact. It is currently in a very early stage, and we're still developing some features and adapting the site from the Smash Hit Lab, so you might encounter issues.");
	$form->textbox("name", "Site name", "This will be the name of your community. If you are using multiverse mode, this will be the name of the origin community.");
	$form->select("type", "Install type", "Labbyware can be used in universe and multiverse mode. Universe allows you to have a single global install. Multiverse allows you create a collection of communities on one server with a shared userbase.", array("universe" => "Universe &mdash; for one community", "multiverse" => "Multiverse &mdash; for many communities"));
	$form->submit();
	
	$page->add($form);
	$page->global_footer();
}

function setup_action(Page $page) {
	if (!setup_is_available()) {
		$page->info("This is odd", "It seems like your Labbyware install is already set up.");
	}
	
	$page->redirect("./?u=$handle");
}

$gEndMan->add("site-setup", function(Page $page) {
	$submitted = $page->has("submit");
	
	if ($submitted) {
		setup_action($page);
	}
	else {
		setup_form($page);
	}
});
