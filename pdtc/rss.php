<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("pdtc", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: Preservation, Digital Technology & Culture",
    "Ahead-of-print-Artikel in Preservation, Digital Technology & Culture",
    "https://www.degruyterbrill.com/journal/key/pdtc/0/0/html"
);

?>