<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("pdtc", "Preservation, Digital Technology & Culture", __DIR__ . "/cache.json");
$rss->generateRSS();

?>