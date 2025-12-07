<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("rest", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: Restaurator",
    "Ahead-of-print-Artikel in Restaurator",
    "https://www.degruyterbrill.com/journal/key/rest/0/0/html"
);

?>