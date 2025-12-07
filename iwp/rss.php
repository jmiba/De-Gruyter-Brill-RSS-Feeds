<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("iwp", "Information – Wissenschaft & Praxis", __DIR__ . "/cache.json");
$rss->generateRSS();

?>