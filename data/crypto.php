<?php

function random_base32(int $nchar) : string {
	$alphabet = "0123456789abcdefghijklmnopqrstuv";
	
	$base = random_bytes($nchar);
	$name = "";
	
	for ($i = 0; $i < strlen($base); $i++) {
		$name .= $alphabet[ord($base[$i]) & 0b11111];
	}
	
	return $name;
}
