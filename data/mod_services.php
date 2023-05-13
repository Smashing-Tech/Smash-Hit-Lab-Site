<?php

class ServiceMod {
	public $id;
	public $title;
	public $ad_png;
	public $ad_xml;
	
	function __construct(?string $id) {
		$db = new Database("services");
		
		if ($id && $db->has($id)) {
			$info = $db->load($id);
			
			$this->id = $info->id;
			$this->title = $info->title;
			$this->ad_png = $info->ad_png;
			$this->ad_xml = $info->ad_xml;
		}
		else {
			$this->id = $id;
			$this->title = "New untitled mod";
			$this->ad_png = "";
			$this->ad_xml = "";
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
	
	if ($user) {
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
	else {
		$page->info();
	}
});

$gEndMan->add("services-info", function (Page $page) {
	$user = user_get_current();
	$id = $page->get("id");
	
	if ($user && $user->has_mod($id)) {
		$sv = new ServiceMod($id);
		
		$page->heading(1, "$sv->title");
		$page->para("Mod ID: $sv->id");
	}
	else {
		$page->info("Sorry!", "You do not have access to this mod!");
	}
});
