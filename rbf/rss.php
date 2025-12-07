<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("rbf", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: Rundbrief Fotografie",
    "Ahead-of-print-Artikel in Rundbrief Fotografie",
    "https://www.degruyterbrill.com/journal/key/rbf/0/0/html"
);

?>