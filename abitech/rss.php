<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("abitech", "ABI Technik", __DIR__ . "/cache.json");
$rss->generateRSS();

?>