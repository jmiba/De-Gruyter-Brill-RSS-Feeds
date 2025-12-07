<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("abitech", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: ABI Technik",
    "Ahead-of-print-Artikel in ABI Technik",
    "https://www.degruyterbrill.com/journal/key/abitech/0/0/html"
);

?>