<?php

$cacheFile = "cache.json";
$cacheTime = 3600*24; // 24 hours

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    $articles = json_decode(file_get_contents($cacheFile), true);
    if (!is_array($articles) || count($articles) === 0) {
        $articles = fetchDegruyterArticles();
        file_put_contents($cacheFile, json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
} else {
    $articles = fetchDegruyterArticles();
    file_put_contents($cacheFile, json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

generateRSS($articles);

function fetchUrl($url) {
    static $context = null;

    if ($context === null) {
        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => implode("\r\n", [
                    "User-Agent: Mozilla/5.0 (compatible; IWP-RSS/1.0; +https://www.jensmittelbach.de/iwp/rss.php)",
                    "Accept: text/html,application/xhtml+xml",
                    "Accept-Language: en,en-US;q=0.9,de;q=0.8"
                ]),
                "timeout" => 30,
                "follow_location" => 1,
                "max_redirects" => 5
            ]
        ]);
    }

    return @file_get_contents($url, false, $context);
}

function normalizeWhitespace($text) {
    $text = trim($text);
    if ($text === "") {
        return "";
    }
    return preg_replace('/\s+/u', ' ', $text);
}

function parseArticleHtml($html) {
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    if (!$dom->loadHTML($html)) {
        libxml_clear_errors();
        return null;
    }
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);

    $articleNode = $xpath->query("//div[@id='text-container']//div[contains(@class, 'article')]")->item(0);
    $text = "";
    $lang = "unknown";

    if ($articleNode) {
        $langAttr = $articleNode->getAttribute("lang");
        if ($langAttr) {
            $lang = $langAttr;
        }

        $bodyNode = $xpath->query(".//div[contains(@class, 'body')]", $articleNode)->item(0);
        $text = $bodyNode ? normalizeWhitespace($bodyNode->textContent) : normalizeWhitespace($articleNode->textContent);
    }

    $doiMeta = $xpath->query("//meta[@name='citation_doi']")->item(0);
    $doi = $doiMeta ? trim($doiMeta->getAttribute("content")) : "";

    $sectionMeta = $xpath->query("//meta[@property='article:section']")->item(0);
    $section = $sectionMeta ? trim($sectionMeta->getAttribute("content")) : "";

    return [
        "text" => $text !== "" ? $text : "No abstract available",
        "lang" => $lang,
        "doi" => $doi,
        "section" => $section
    ];
}

function fetchArticleAbstract($url) {
    $default = [
        "abstract" => "No abstract available",
        "abstractEn" => "",
        "lang" => "unknown",
        "doi" => "",
        "categories" => []
    ];

    $html = fetchUrl($url);
    
    if (!$html) {
        return $default;
    }

    $parsed = parseArticleHtml($html);
    if (!$parsed) {
        return $default;
    }

    $result = [
        "abstract" => $parsed["text"],
        "abstractEn" => "",
        "lang" => $parsed["lang"],
        "doi" => $parsed["doi"],
        "categories" => $parsed["section"] !== "" ? [$parsed["section"]] : []
    ];

    if ($result["lang"] !== "en") {
        $separator = strpos($url, '?') === false ? '?' : '&';
        $englishUrl = $url . $separator . "lang=en";
        $htmlEn = fetchUrl($englishUrl);
        if ($htmlEn) {
            $parsedEn = parseArticleHtml($htmlEn);
            if ($parsedEn && strtolower($parsedEn["lang"]) === "en") {
                $result["abstractEn"] = $parsedEn["text"];
            }
        }
    } else {
        $result["abstractEn"] = $result["abstract"];
    }
    
    return $result;
}

function fetchDegruyterArticles() {
    $baseUrl = "https://www.degruyterbrill.com";
    $url = $baseUrl . "/journal/key/iwp/0/0/html";
    $html = fetchUrl($url);
    
    if (!$html) {
        die("Failed to retrieve content");
    }
    
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    $items = $xpath->query("//div[contains(@class, 'ahead-of-print-item')]");
    
    $articles = [];
    foreach ($items as $item) {
        $titleNode = $xpath->query(".//span[contains(@class, 'ahead-of-print-title')]", $item)->item(0);
        if (!$titleNode) {
            continue;
        }

        $title = normalizeWhitespace($titleNode->textContent);
        $linkNode = $titleNode->parentNode;
        while ($linkNode && $linkNode->nodeName !== "a") {
            $linkNode = $linkNode->parentNode;
        }
        if (!$linkNode) {
            continue;
        }

        $href = $linkNode->getAttribute("href");
        $link = strpos($href, "http") === 0 ? $href : $baseUrl . $href;

        $doi = $linkNode->getAttribute("data-doi");
        if (!$doi) {
            $doiBtn = $xpath->query(".//button[contains(@class, 'cite-this-button-dgb')]", $item)->item(0);
            if ($doiBtn) {
                $doi = $doiBtn->getAttribute("data-doi");
            }
        }
        
        $authorTag = $xpath->query(".//div[contains(@class, 'authors')]", $item)->item(0);
        if ($authorTag) {
            $authorsRaw = $authorTag->textContent;
            $authors = array_filter(array_map('trim', preg_split('/[,;]+/', $authorsRaw)));
            if (!$authors) {
                $authors = ["Unknown"];
            }
        } else {
            $authors = ["Unknown"];
        }
        
        $dateTag = $xpath->query(".//div[contains(@class, 'date')]", $item)->item(0);
        $dateText = $dateTag ? trim($dateTag->textContent) : "";
        $pubDate = $dateText ? date(DATE_RSS, strtotime($dateText)) : date(DATE_RSS);
        
        $articleData = fetchArticleAbstract($link);
        
        $articles[] = [
            "title" => $title,
            "link" => $articleData["doi"] ? "https://doi.org/" . $articleData["doi"] : $link,
            "authors" => $authors,
            "pubDate" => $pubDate,
            "abstract" => $articleData["abstract"],
            "abstractEn" => $articleData["abstractEn"],
            "categories" => $articleData["categories"],
            "lang" => $articleData["lang"],
            "guid" => $articleData["doi"] ? "https://doi.org/" . $articleData["doi"] : $link
        ];
    }
    
    return $articles;
}

function generateRSS($articles) {
    header("Content-Type: application/rss+xml; charset=UTF-8");

    // Generate self-referencing URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $self_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    $rssFeed = "<?xml version='1.0' encoding='UTF-8'?>\n";
    $rssFeed .= "<rss version='2.0' xmlns:dc='http://purl.org/dc/elements/1.1/' xmlns:atom='http://www.w3.org/2005/Atom'>\n";
    $rssFeed .= "<channel>\n";
    $rssFeed .= "<title>Ahead of Print: Information – Wissenschaft &amp; Praxis</title>\n";
    $rssFeed .= "<link>https://www.degruyterbrill.com/journal/key/iwp/0/0/html</link>\n";
    $rssFeed .= "<atom:link href='" . htmlspecialchars($self_url) . "' rel='self' type='application/rss+xml'/>\n";
    $rssFeed .= "<description>Ahead-of-print-Artikel in Information – Wissenschaft &amp; Praxis</description>\n";
    $rssFeed .= "<language>de-de</language>\n";
    
    foreach ($articles as $article) {
        $rssFeed .= "<item>\n";
        $rssFeed .= "<title>" . htmlspecialchars($article["title"]) . "</title>\n";
        $rssFeed .= "<link>" . htmlspecialchars($article["link"]) . "</link>\n";
        $rssFeed .= "<guid isPermaLink='true'>" . htmlspecialchars($article["guid"]) . "</guid>\n";
        
        // add authors
        foreach ($article["authors"] as $author) {
            $rssFeed .= "<dc:creator>" . htmlspecialchars($author) . "</dc:creator>\n";
        }
        $authors = implode(", ", $article["authors"]);
        $rssFeed .= "<pubDate>" . $article["pubDate"] . "</pubDate>\n";

        if ($article["lang"] == "en") {
            $rssFeed .= "<description><![CDATA[<div>By <span id='creators' style='font-weight:900;'>{$authors}</span> (article in English). </div><div style='margin-top:1em'>{$article["abstractEn"]}</div><div style='margin-top:1em'>{$article["abstract"]}</div>]]></description>\n";
        } else {
            $rssFeed .= "<description><![CDATA[<div>Von <span id='creators' style='font-weight:900;'>{$authors}</span> (Beitrag in Deutsch). </div><div style='margin-top:1em'>{$article["abstract"]}</div><div style='margin-top:1em'>{$article["abstractEn"]}</div>]]></description>\n";
        }
        

        // add categories
        foreach ($article["categories"] as $category) {
            $rssFeed .= "<category>" . htmlspecialchars($category) . "</category>\n";
        }

        $rssFeed .= "<dc:language>" . htmlspecialchars($article["lang"]) . "</dc:language>\n";
        $rssFeed .= "</item>\n";
    }
    
    $rssFeed .= "</channel>\n";
    $rssFeed .= "</rss>\n";
    
    echo $rssFeed;
}

?>
