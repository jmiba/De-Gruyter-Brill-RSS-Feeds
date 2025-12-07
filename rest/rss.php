<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("rest", "Restaurator", __DIR__ . "/cache.json");
$rss->generateRSS();

?>