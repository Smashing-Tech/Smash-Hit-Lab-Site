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
