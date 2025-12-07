<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("bd", "Bibliotheksdienst", __DIR__ . "/cache.json");
$rss->generateRSS();

?>