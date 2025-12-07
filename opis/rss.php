<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("opis", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: Open Information Science",
    "Ahead-of-print-Artikel in Open Information Science",
    "https://www.degruyterbrill.com/journal/key/opis/0/0/html"
);

?>