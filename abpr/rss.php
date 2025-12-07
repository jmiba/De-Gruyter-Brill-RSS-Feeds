<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("abpr", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: The African Book Publishing Record",
    "Ahead-of-print-Artikel in The African Book Publishing Record",
    "https://www.degruyterbrill.com/journal/key/abpr/0/0/html"
);

?>