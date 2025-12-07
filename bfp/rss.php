<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("bfup", "Bibliothek – Forschung und Praxis", __DIR__ . "/cache.json");
$rss->generateRSS();

?>