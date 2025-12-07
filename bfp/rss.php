<?php

require_once __DIR__ . '/../lib/DeGruyterRSS.php';

$rss = new DeGruyterRSS("bfup", __DIR__ . "/cache.json");
$rss->generateRSS(
    "Ahead of Print: Bibliothek – Forschung und Praxis",
    "Ahead-of-print-Artikel in Bibliothek – Forschung und Praxis",
    "https://www.degruyterbrill.com/journal/key/bfup/0/0/html"
);

?>