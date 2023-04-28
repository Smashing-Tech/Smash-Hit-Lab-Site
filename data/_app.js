/**
 * JavaScript for the Smash Hit Lab website!
 */

function shl_request(verb, url, body, func) {
	/**
	 * Make an xhr request using the given verb, url and function
	 */
	
	let xhr = new XMLHttpRequest();
	xhr.onreadystatechange = func;
	xhr.open(verb, url, true);
	xhr.send(body);
}

function shl_get_param(name) {
	return (new URLSearchParams(document.location.search)).get(name);
}

function shl_magic_editor_init() {
	let elements = document.getElementsByClassName("shl-magic-editor-feild");
	
	// do stuff with them ...
}

function shl_magic_editor_begin() {
	let elements = document.getElementsByClassName("shl-magic-editor-feild");
	
	// Enable contenteditable
	for (let i = 0; i < elements.length; i++) {
		elements[i].contentEditable = "true";
	}
	
	// Save button
	let button = document.getElementById("shl-magic-editor-main-button");
	
	button.innerHTML = "<span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">check</span> Save page";
	button.onclick = shl_magic_editor_end;
}

function shl_magic_editor_save() {
	let data = {
		"page": shl_get_param("m"),
		"about": document.getElementById("mod-about").innerHTML
	};
	
	shl_request("POST", "./?a=mod-save&api=1", JSON.stringify(data), shl_magic_editor_save_after);
}

function shl_magic_editor_save_after() {
	if (this.readyState == 4) {
		console.log(this.responseText);
	}
}

function shl_magic_editor_end() {
	let elements = document.getElementsByClassName("shl-magic-editor-feild");
	
	// Make request
	shl_magic_editor_save();
	
	// Disable contenteditable
	for (let i = 0; i < elements.length; i++) {
		elements[i].contentEditable = "false";
	}
	
	// Edit button
	let button = document.getElementById("shl-magic-editor-main-button");
	
	button.innerHTML = "<span class=\"material-icons\" style=\"position: relative; top: 5px; margin-right: 3px;\">edit</span> Edit page";
	button.onclick = shl_magic_editor_begin;
}

function shl_main() {
	console.log("_app.js loaded");
}
