<?php

class ServiceMod {
	public $id;
	public $title;
	public $ad_png;
	public $ad_xml;
	public $imperssions;
	
	function __construct(?string $id) {
		$db = new Database("services");
		
		if ($id && $db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->title = $info->title;
			$this->ad_png = $info->ad_png;
			$this->ad_xml = $info->ad_xml;
			$this->imperssions = (property_exists($info, "imperssions")) ? $info->imperssions : 0;
		}
		else {
			$this->id = $id;
			$this->title = "New untitled mod";
			$this->ad_png = "";
			$this->ad_xml = "";
			$this->imperssions = 0;
		}
	}
	
	function save() {
		$db = new Database("services");
		$db->save($this->id, $this);
	}
	
	function exists() : bool {
		$db = new Database("services");
		return $db->has($this->id);
	}
	
	function create(User $user, string $title) {
		// Generate ID
		$db = new Database("services");
		
		do {
			$this->id = random_base64(5);
		} while ($db->has($this->id));
		
		// Set everything else
		$this->title = $title;
		$this->save();
		
		$user->add_mod($this->id);
	}
	
	function set_ads(string $ad_png, string $ad_xml) {
		$this->imperssions = 0;
		$this->ad_png = base64_encode($ad_png);
		$this->ad_xml = base64_encode($ad_xml);
		$this->save();
	}
	
	function incr_imperssions() {
		$this->imperssions += 1;
		$this->save();
	}
}

$gEndMan->add("services-home", function (Page $page) {
	$user = user_get_current();
	
	if ($user) {
		$page->heading(1, "Mod Services");
		$page->add("<div class=\"comment-card\"><p>Mod Services are in a prerelease state and currently only support adverts.</p></div>");
		$page->add("<p style=\"text-align: center;\">");
		$page->link_button("add", "Create new mod", "./?a=services-create", true);
		
		$page->add("<ul>");
		for ($i = 0; $i < sizeof($user->mods); $i++) {
			$id = $user->mods[$i];
			$sv = new ServiceMod($id);
			$page->add("<li><a href=\"./?a=services-info&id=$id\">$sv->title</a> (id = $id)</li>");
		}
		$page->add("</ul>");
		
		$page->add("</p>");
	}
	else {
		$page->info("Log in first!", "You need to log in to preform this action.");
	}
});

$gEndMan->add("services-create", function (Page $page) {
	$user = user_get_current();
	
	if ($user && $user->is_verified()) {
		if ($page->has("submit")) {
			$sv = new ServiceMod(null);
			$sv->create($user, $page->get("title"));
			
			$page->redirect("./?a=services-info&id=$sv->id");
		}
		else {
			$page->heading(1, "Create mod");
			
			$form = new Form("./?a=services-create&submit=1");
			$form->textbox("title", "Title", "The title of your mod.");
			$form->submit("Create mod");
			
			$page->add($form);
		}
	}
	else if ($user && !$user->is_verified()) {
		$page->info("We're sorry, but ...", "... you need to be verified to use this service, as it could be abused if we allow unverified users to use it.");
	}
	else {
		$page->info();
	}
});

$gEndMan->add("services-info", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	if ($user && $user->has_mod($id) && $user->is_verified()) {
		$sv = new ServiceMod($id);
		
		$page->heading(1, $sv->title);
		
		$page->section_start("Advertisements", "You can create and update the ad channel for your mod.");
		$page->link_button("new_releases", "Update adverts", "./?a=services-adverts&id=$id");
		$page->section_end();
		
		$page->section_start("Preview ads", "Preview what your ad looks like.");
		$page->link_button("image", "Preview adverts", "./?a=services-adverts-preview&id=$id");
		$page->section_end();
		
		$page->section_start("Patches", "Patches that you can apply to libsmashhit.so.");
		$page->link_button("layers", "How to patch", "./?a=services-patch&id=$id");
		$page->section_end();
		
		$page->section_start("Mod ID", "The identifier for your mod.");
		$page->para("<code>$sv->id</code>");
		$page->section_end();
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("services-adverts", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	if ($user && $user->has_mod($id) && $user->is_verified()) {
		if (!$page->has("submit")) {
			$page->heading(1, "Update advertisements");
			
			$form = new Form("./?a=services-adverts&id=$id&submit=1");
			$form->upload("file_xml", "XML file", "The UI XML file for your advert.");
			$form->upload("file_png", "PNG file", "The PNG image for your advert.");
			$form->submit("Save adverts");
			
			$page->add($form);
		}
		else {
			$sv = new ServiceMod($id);
			$sv->set_ads(
				$page->get_file("file_png", "image/png"),
				$page->get_file("file_xml", "text/xml"));
			
			$page->redirect("./?a=services-info&id=$id");
		}
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("services-adverts-preview", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	if ($user && $user->has_mod($id) && $user->is_verified()) {
		$sv = new ServiceMod($id);
		
		$page->section_start("Impressions", "The number of times this ad has been viewed.");
		$page->para("$sv->imperssions");
		$page->section_end();
		
		$page->heading(1, "Preview advertisement");
		$page->add("<img src=\"data:image/png;base64," . $sv->ad_png . "\"/>");
		$page->add("<pre>" . htmlspecialchars(base64_decode($sv->ad_xml)) . "</pre>");
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("services-patch", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	if ($user && $user->has_mod($id) && $user->is_verified()) {
		$page->heading("Patching libsmashhit binary");
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});

$gEndMan->add("get-ads-info", function (Page $page) {
	// Platform is where the ID goes unless we really have the id
	$platform = $page->has("id") ? $page->get("id") : $page->get("platform");
	//$version = $page->get("version");
	$rev = $page->get("rev");
	$date = $page->get("date");
	
	$rev += 1;
	
	// temp
	(new ServiceMod($platform))->incr_imperssions();
	
	$page->set_mode(PAGE_MODE_RAW);
	$page->type("text/xml");
	$page->add("<ads revision=\"$rev\" showfront=\"1\" onlyfree=\"0\" sale=\"0\" folder=\"?a=get-ads-data&amp;id=$platform&amp;name=\"/>");
});

$gEndMan->add("get-ads-data", function (Page $page) {
	$id = $page->get("id");
	$name = $page->get("name");
	
	$sv = new ServiceMod($id);
	
	$page->set_mode(PAGE_MODE_RAW);
	
	if (str_ends_with($name, "png")) {
		$page->type("image/png");
		$page->add(base64_decode($sv->ad_png));
	}
	else if (str_ends_with($name, "xml")) {
		$page->type("text/xml");
		$page->add(base64_decode($sv->ad_xml));
	}
});
