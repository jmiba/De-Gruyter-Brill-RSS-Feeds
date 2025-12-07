<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("iwp", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: Information – Wissenschaft & Praxis",
    "Ahead-of-print-Artikel in Information – Wissenschaft & Praxis",
    "https://www.degruyterbrill.com/journal/key/iwp/0/0/html"
);

?>