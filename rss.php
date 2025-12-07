<?php

require_once __DIR__ . '/lib/DeGruyterRSS.php';

$journalKey = isset($_GET['key']) ? trim($_GET['key']) : null;
$journalName = isset($_GET['name']) ? trim($_GET['name']) : null;
$ttl = isset($_GET['ttl']) ? (int) $_GET['ttl'] : 86400;
$ttl = $ttl > 0 ? $ttl : 86400;

if (!$journalKey) {
    header("Content-Type: text/plain; charset=UTF-8", true, 400);
    echo "Missing required query parameter: key\n";
    echo "Example: rss.php?key=abitech&name=ABI+Technik\n";
    exit;
}

if (!preg_match('/^[a-z0-9_-]+$/i', $journalKey)) {
    header("Content-Type: text/plain; charset=UTF-8", true, 400);
    echo "Invalid key. Allowed characters: a-z, 0-9, underscore, hyphen.\n";
    exit;
}

$cacheDir = __DIR__ . "/cache";
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$cacheFile = $cacheDir . "/" . strtolower($journalKey) . ".json";

$rss = new DeGruyterRSS($journalKey, $journalName ?: null, $cacheFile, $ttl);
$rss->generateRSS();

?>
