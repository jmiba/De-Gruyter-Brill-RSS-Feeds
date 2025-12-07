<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("opis", "Open Information Science", __DIR__ . "/cache.json");
$rss->generateRSS();

?>