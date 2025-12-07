<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("rbf", "Rundbrief Fotografie", __DIR__ . "/cache.json");
$rss->generateRSS();

?>