<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("libr", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: Libri",
    "Ahead-of-print-Artikel in Libri",
    "https://www.degruyterbrill.com/journal/key/libr/0/0/html"
);

?>