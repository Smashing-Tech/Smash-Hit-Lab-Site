<?php

function post(string $url, string $body) {
	/**
	 * Do a POST request to the given URL with the given body.
	 */
	
	$options = [
		"http" => [
			"method" => "POST",
			"header" => "Content-Type: application/json\r\n",
			"content" => $body,
			"timeout" => 3,
		]
	];
	
	$context = stream_context_create($options);
	$result = file_get_contents($url, false, $context);
	
	return $result;
}

function send_discord_message(string $message, string $webhook_url = "") {
	if (!$webhook_url) {
		return;
	}
	
	$body = [
		"content" => $message,
	];
	
	post($webhook_url, json_encode($body));
}

function alert(string $title, string $url = "") {
	/**
	 * Add a notification to a user's inbox.
	 */
	
	// Create the message
	$webhook_url = get_config("discord_webhook", "");
	$message = date("Y-m-d H:i:s", time()) . " — " . $title . ($url ? "\n[Relevant link](https://smashhitlab.000webhostapp.com/$url)" : "");
	
	// Send via primary webhook
	send_discord_message($message, $webhook_url);
	
	// Send to secondary webhook
	$webhook_url = get_config("secondary_discord_webhook", "");
	
	if ($webhook_url) {
		send_discord_message($message, $webhook_url);
	}
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

function dechexa(int $num) {
	if ($num < 16) {
		return "0" . dechex($num);
	}
	else {
		return dechex($num);
	}
}

function frand() : float {
	return mt_rand() / mt_getrandmax();
}

function __js_style_var__(string $var, string $val) {
	echo "qs.style.setProperty('$var', '$val');";
}

function render_accent_script(string $colour) {
	$p = new Piece();
	
	$swatch = derive_pallete_from_colour(colour_from_hex($colour));
	$darkest = $swatch[0];
	$dark = $swatch[1];
	$darkish = $swatch[2];
	$bright = $swatch[3];
	
	$p->add("<script>var qs = document.querySelector(':root');");
	
	$p->add(__js_style_var__("--colour-primary", $bright));
	$p->add(__js_style_var__("--colour-primary-darker", "#ffffff"));
	$p->add(__js_style_var__("--colour-primary-hover", "#ffffff"));
	$p->add(__js_style_var__("--colour-primary-a", $bright . "40"));
	$p->add(__js_style_var__("--colour-primary-b", $bright . "80"));
	$p->add(__js_style_var__("--colour-primary-c", $bright . "c0"));
	$p->add(__js_style_var__("--colour-primary-text", "#000000"));
	
	$p->add(__js_style_var__("--colour-background-light", $darkish));
	$p->add(__js_style_var__("--colour-background-light-a", $darkish . "40"));
	$p->add(__js_style_var__("--colour-background-light-b", $darkish . "80"));
	$p->add(__js_style_var__("--colour-background-light-c", $darkish . "c0"));
	$p->add(__js_style_var__("--colour-background-light-text", $bright));
	
	$p->add(__js_style_var__("--colour-background", $dark));
	$p->add(__js_style_var__("--colour-background-a", $dark . "40"));
	$p->add(__js_style_var__("--colour-background-b", $dark . "80"));
	$p->add(__js_style_var__("--colour-background-c", $dark . "c0"));
	$p->add(__js_style_var__("--colour-background-text", $bright));
	
	$p->add(__js_style_var__("--colour-background-dark", $darkest));
	$p->add(__js_style_var__("--colour-background-dark-a", $darkest . "40"));
	$p->add(__js_style_var__("--colour-background-dark-b", $darkest . "80"));
	$p->add(__js_style_var__("--colour-background-dark-c", $darkest . "c0"));
	$p->add(__js_style_var__("--colour-background-dark-text", $bright));
	$p->add(__js_style_var__("--colour-background-dark-text-hover", $bright));
	
	$p->add("</script>");
	
	return $p->render();
}
