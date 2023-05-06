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

function send_discord_message(string $message) {
	$webhook_url = get_config("discord_webhook", "");
	
	if (!$webhook_url) {
		return;
	}
	
	$body = [
		"content" => $message,
	];
	
	post($webhook_url, json_encode($body));
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
