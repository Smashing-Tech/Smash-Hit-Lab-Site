<?php

function edit_feild($name, $type, $title, $desc, $value, $enabled = true) : void {
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
				default:
					echo "$value";
			}
			if (!$enabled) {
				echo "<p><i>This value is read-only.</i></p>";
			}
		echo "</div>";
	echo "</div>";
}
