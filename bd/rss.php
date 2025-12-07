<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("bd", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: Bibliotheksdienst",
    "Ahead-of-print-Artikel in Bibliotheksdienst",
    "https://www.degruyterbrill.com/journal/key/bd/0/0/html"
);

?>