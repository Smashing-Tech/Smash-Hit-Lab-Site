<?php

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

function sorry(string $why = "There was a problem.") {
	include_header();
	echo "<h1>Sorry</h1><p>$why</p>";
	include_footer();
	die();
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
