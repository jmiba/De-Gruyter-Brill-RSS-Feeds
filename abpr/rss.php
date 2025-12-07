<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("abpr", "The African Book Publishing Record", __DIR__ . "/cache.json");
$rss->generateRSS();

?>