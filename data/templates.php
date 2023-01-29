<?php

require_once "database.php"; // Needed for the article stuff

function edit_feild($name, $type, $title, $desc, $value, $enabled = true, $options = null) : void {
	if (!$value) {
		$value = "";
	}
	
	echo "<div class=\"mod-edit-property\">";
		echo "<div class=\"mod-edit-property-label\">";
			echo "<h4>$title</h4>";
			echo "<p>$desc</p>";
		echo "</div>";
		echo "<div class=\"mod-edit-property-data\">";
			switch ($type) {
				case "text":
					$s = ($enabled) ? "" : " disabled";
					echo "<input type=\"text\" name=\"$name\" placeholder=\"$title\" value=\"$value\" $s/>";
					break;
				case "textarea":
					echo "<textarea name=\"$name\">$value</textarea>";
					break;
				case "select":
					echo "<select name=\"$name\">";
					$k = array_keys($options);
					
					for ($i = 0; $i < sizeof($k); $i++) {
						$key = $k[$i];
						$val = $options[$k[$i]];
						$selected = ($key == $value) ? "selected" : "";
						
						echo "<option value=\"$key\" $selected>$val</option>";
					}
					
					echo "</select>";
					break;
				case "button":
					$k = array_keys($options);
					
					echo "<div id=\"$name\">";
					
					for ($i = 0; $i < sizeof($k); $i++) {
						$key = $k[$i];
						$val = $options[$k[$i]];
						
						echo "<p><a href=\"$key\"><button>$val</button></a></p>";
					}
					
					echo "</div>";
					
					break;
				default:
					echo "$value";
					break;
			}
			if (!$enabled) {
				echo "<p><i>This value is read-only.</i></p>";
			}
		echo "</div>";
	echo "</div>";
}

function form_start(string $url, string $method = "post") {
	echo "<form action=\"$url\" method=\"$method\">";
}

function form_end(string $submit_text = "Submit form") {
	echo "<input type=\"submit\" value=\"$submit_text\"/>";
	echo "</form>";
}

function mod_property($title, $desc, $value) : void {
	echo "<div class=\"mod-property\">";
		echo "<div class=\"mod-property-label\">";
			echo "<p><b>$title</b></p>";
			echo "<p class=\"small-text\">$desc</p>";
		echo "</div>";
		echo "<div class=\"mod-property-data\">";
			if (str_starts_with($value ? $value : "", "https://")) {
				echo "<p><a href=\"$value\">" . $value . "</a></p>";
			} else {
				echo "<p>" . ($value ? "$value" : "<i>Not available</i>") . "</p>";
			}
		echo "</div>";
	echo "</div>";
}

function sorry(string $why = "There was a problem.", string $extra = "") {
	global $gTitle;
	$gTitle = "There was an error !!!";
	
	include_header();
	echo "<h1>Sorry</h1><p>$why</p>";
	echo $extra;
	include_footer();
	die();
}

function rich_format(string $base, bool $trusted = false) : string {
	/**
	 * Sanitise and convert rich formatted user text.
	 */
	
	$filtered = htmlspecialchars($base);
	
	// Here comes the parser ... !!!
	$body = "<p>";
	$bold = false; // Are we currently bold?
	$italic = false; // Are we currently italic?
	$code = false; // Are we currently code?
	
	// This parser is really not great, but it's simple and does what it does
	// do quite well and really I don't feel like a big parser right now.
	for ($i = 0; $i < strlen($filtered); $i++) {
		$s = substr($filtered, $i);
		
		if (str_starts_with($s, "\\")) {
			// Escape seqence
			if (strlen($s) > 1) {
				$body = $body . $s[1];
			}
			
			$i += 1;
		}
		else if (str_starts_with($s, "**")) {
			// If we are bold, then don't do it again..
			if ($bold) {
				$body = $body . "</b>";
			}
			else {
				$body = $body . "<b>";
			}
			
			$i += 1; // Add an extra so we don't just get italics
			$bold = !$bold;
		}
		else if (str_starts_with($s, "__")) {
			if ($italic) {
				$body = $body . "</i>";
			}
			else {
				$body = $body . "<i>";
			}
			
			$i += 1;
			$italic = !$italic;
		}
		else if (str_starts_with($s, "`")) {
			if ($code) {
				$body = $body . "</code>";
			}
			else {
				$body = $body . "<code>";
			}
			
			$code = !$code;
		}
		else if (str_starts_with($s, "@")) {
			$end = strcspn($s, " \r\n\t", 1, 24);
			
			// If we didn't find it then just use whatever...
			if ($end < 0) {
				$end = strlen($s);
			}
			
			$handle = substr($s, 1, $end);
			
			if (!user_exists($handle)) {
				$body = $body . "@";
			}
			else {
				// Get display name and handle
				$body = $body . get_nice_display_name($handle, false);
				
				// Skip the handle text
				$i += strlen($handle);
			}
		}
		else if ($trusted && str_starts_with($s, "{{")) {
			$end = strpos($s, "}}");
			
			// oH FUCK  so much indentation
			if ($end < 0) {
				$body = $body . "{";
			}
			else {
				$length = $end;
				$url = substr($s, 2, $end - 2);
				
				// Yay, a database lookup during parsing ...
				$db = new Database("article");
				
				// If this is an article reference ...
				if ($db->has($url)) {
					$art = $db->load($url);
					
					$title = $art->title;
					$date = "Last updated " . date("Y-m-d H:i", $art->updated);
					$text = htmlspecialchars(str_replace(array("\n", "_", "*", "`", "{", "}"), " ", substr($art->body, 0, 100))) . "...";
					
					$body = $body . "<div class=\"news-article-card\"><h4><a href=\"./?n=$url\">$title</a></h4><p class=\"small-text\">$date</p><p>$text</p></a></div>";
				}
				// Otherwise try for a youtube embed URL ...
				else if (str_starts_with($url, "yt:")) {
					$url = substr($url, 3);
					
					$body = $body . "<iframe width=\"100%\" height=\"600px\" src=\"https://www.youtube-nocookie.com/embed/$url\" frameborder=\"0\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share\" style=\"border-radius: 0.5em;\" allowfullscreen></iframe>";
				}
				// Otherwise this is just a bare URL ...
				else {
					$body = $body . "<a href=\"$url\" rel=\"nofollow\">$url</a>";
				}
				
				$i += $end + 3; // Skip the unneeded chars
			}
		}
		else {
			$body = $body . $filtered[$i];
		}
	}
	
	// If we are still bold or italics we need to stop that!
	if ($bold) {
		$body = $body . "</b>";
	}
	
	if ($italic) {
		$body = $body . "</i>";
	}
	
	if ($code) {
		$body = $body . "</code>";
	}
	
	// Dobule newlines -> paragraphs
	$body = str_replace("\n\n", "</p><p>", $body);
	
	// Single newlines -> linebreaks
	$body = str_replace("\n", "<br/>", $body);
	
	// Final closing tag
	$body = $body . "</p>";
	
	return $body;
}

function parse_comma_array(string $s) : array {
	return explode(",", $s);
}

function create_comma_array(array $s) : string {
	return join(",", $s);
}

function create_comma_array_nice(array $s) : string {
	return join(", ", $s);
}

function redirect(string $location) {
	header("Location: $location");
	die();
}
