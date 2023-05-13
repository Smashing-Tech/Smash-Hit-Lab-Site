<?php

require_once "../data/main.php";

$page = new Page();

$okay = $gEndMan->run("get-ads-info", $page);

if (!$okay) {
    $page->add("<ads revision=\"0\"/>");
}

$page->send();