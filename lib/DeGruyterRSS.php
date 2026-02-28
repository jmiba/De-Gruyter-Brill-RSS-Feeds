<?php

class DeGruyterRSS
{
    private $journalKey;
    private $journalName;
    private $cacheFile;
    private $cacheTime;
    private $baseUrl = "https://www.degruyterbrill.com";
    private $isAheadOfPrint = true; // Track source type
    private $feedLanguage;
    private $lastErrorType = null; // null, "not_found", "upstream"

    public function __construct($journalKey, $journalName = null, $cacheFile = "cache.json", $cacheTime = 86400, $feedLanguage = "en-us")
    {
        $this->journalKey = $journalKey;
        $this->journalName = $journalName;
        $this->cacheFile = $cacheFile;
        $this->cacheTime = $cacheTime;
        $this->feedLanguage = strtolower(trim($feedLanguage)) ?: "en-us";
    }

    public function getArticles()
    {
        if (file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile)) < $this->cacheTime) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            if (is_array($cached) && count($cached) > 0) {
                // Support new cache format with metadata or legacy array-only cache
                if (isset($cached["articles"])) {
                    $cachedArticles = is_array($cached["articles"]) ? $cached["articles"] : [];
                    if (!$this->journalName && isset($cached["journalName"]) && !$this->isInvalidJournalTitle($cached["journalName"])) {
                        $this->journalName = $cached["journalName"];
                    }
                    if (isset($cached["source"])) {
                        $this->isAheadOfPrint = $cached["source"] === "ahead-of-print";
                    }
                    if (isset($cached["feedLanguage"])) {
                        $this->feedLanguage = $cached["feedLanguage"];
                    }
                    // Do not lock in empty feeds from transient upstream errors.
                    if (count($cachedArticles) > 0) {
                        return $cachedArticles;
                    }
                } elseif (count($cached) > 0) {
                    return $cached;
                }
            }
        }

        $articles = $this->fetchArticles();

        $payload = [
            "journalKey" => $this->journalKey,
            "journalName" => $this->journalName,
            "source" => $this->isAheadOfPrint ? "ahead-of-print" : "latest-issue",
            "fetchedAt" => time(),
            "feedLanguage" => $this->feedLanguage,
            "articles" => $articles
        ];

        if (count($articles) > 0) {
            file_put_contents($this->cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        return $articles;
    }

    private function fetchUrl($url, &$responseCode = null)
    {
        static $context = null;

        if ($context === null) {
            $context = stream_context_create([
                "http" => [
                    "method" => "GET",
                    "header" => implode("\r\n", [
                        "User-Agent: Mozilla/5.0 (compatible; DGB-RSS/1.0; +https://www.jensmittelbach.de/" . $this->journalKey . "/rss.php)",
                        "Accept: text/html,application/xhtml+xml",
                        "Accept-Language: en,en-US;q=0.9,de;q=0.8"
                    ]),
                    "timeout" => 30,
                    "follow_location" => 1,
                    "max_redirects" => 5,
                    "ignore_errors" => true
                ]
            ]);
        }

        $responseCode = 0;
        $handle = @fopen($url, "rb", false, $context);
        if ($handle === false) {
            return false;
        }

        $meta = stream_get_meta_data($handle);
        $result = stream_get_contents($handle);
        fclose($handle);

        $responseHeaders = [];
        if (isset($meta["wrapper_data"])) {
            if (is_array($meta["wrapper_data"])) {
                $responseHeaders = $meta["wrapper_data"];
            } elseif (is_string($meta["wrapper_data"])) {
                $responseHeaders = [$meta["wrapper_data"]];
            }
        }

        // Multiple HTTP status lines can be present due to redirects.
        foreach ($responseHeaders as $headerLine) {
            if (preg_match('#HTTP/\d+\.\d+ (\d+)#', $headerLine, $matches)) {
                $responseCode = intval($matches[1]);
            }
        }

        return $result;
    }

    private function normalizeWhitespace($text)
    {
        $text = trim($text);
        if ($text === "") {
            return "";
        }
        return preg_replace('/\s+/u', ' ', $text);
    }

    private function normalizeJournalTitle($title)
    {
        $title = $this->normalizeWhitespace($title);
        if ($title === "") {
            return "";
        }

        // Strip trailing brand fragments such as " | De Gruyter"
        $title = preg_replace('/\\s*\\|\\s*De\\s*Gruyter.*/i', '', $title);
        $title = preg_replace('/\\s*\\|\\s*Brill.*/i', '', $title);
        $title = preg_replace('/\\s*[-–]\\s*Ahead of Print.*/i', '', $title);
        // Remove trailing volume/issue fragments often appended on Ahead-of-Print pages
        $title = preg_replace('/\\s+Volume\\s+\\d+\\s+Issue\\s+\\d+$/i', '', $title);

        return $this->normalizeWhitespace($title);
    }

    private function isInvalidJournalTitle($title)
    {
        $normalized = strtolower($this->normalizeWhitespace($title));
        if ($normalized === "") {
            return true;
        }

        $badPhrases = [
            "unspecified server error",
            "internal server error",
            "server error",
            "page not found",
            "access denied",
            "temporarily unavailable"
        ];

        foreach ($badPhrases as $phrase) {
            if (strpos($normalized, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    private function detectPageErrorType(DOMXPath $xpath)
    {
        $texts = [];

        $pageTitle = $xpath->query("//title")->item(0);
        if ($pageTitle) {
            $texts[] = $pageTitle->textContent;
        }

        $headingTitle = $xpath->query("//h1")->item(0);
        if ($headingTitle) {
            $texts[] = $headingTitle->textContent;
        }

        $allText = strtolower($this->normalizeWhitespace(implode(" ", $texts)));
        if ($allText === "") {
            return null;
        }

        if (strpos($allText, "page not found") !== false || strpos($allText, "404") !== false) {
            return "not_found";
        }

        if (
            strpos($allText, "unspecified server error") !== false ||
            strpos($allText, "internal server error") !== false ||
            strpos($allText, "temporarily unavailable") !== false ||
            strpos($allText, "server error") !== false
        ) {
            return "upstream";
        }

        return null;
    }

    private function setJournalNameFromDom(DOMXPath $xpath)
    {
        if ($this->journalName) {
            return;
        }

        $candidates = [];
        $metaTitle = $xpath->query("//meta[@name='citation_journal_title']")->item(0);
        if ($metaTitle) {
            $candidates[] = $metaTitle->getAttribute("content");
        }

        $ogTitle = $xpath->query("//meta[@property='og:title']")->item(0);
        if ($ogTitle) {
            $candidates[] = $ogTitle->getAttribute("content");
        }

        $pageTitle = $xpath->query("//title")->item(0);
        if ($pageTitle) {
            $candidates[] = $pageTitle->textContent;
        }

        $headingTitle = $xpath->query("//h1[contains(@class, 'page-title') or contains(@class, 'journal-title')]")->item(0);
        if ($headingTitle) {
            $candidates[] = $headingTitle->textContent;
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeJournalTitle($candidate);
            if ($normalized !== "" && !$this->isInvalidJournalTitle($normalized)) {
                $this->journalName = $normalized;
                return;
            }
        }
    }

    private function parseListingArticles($html)
    {
        if (!$html) {
            return [];
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        if (!$dom->loadHTML($html)) {
            libxml_clear_errors();
            return [];
        }
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $this->setJournalNameFromDom($xpath);
        $items = $xpath->query("//div[contains(@class, 'ahead-of-print-item')]");

        $articles = [];
        foreach ($items as $item) {
            $titleNode = $xpath->query(".//span[contains(@class, 'ahead-of-print-title')]", $item)->item(0);
            if (!$titleNode) {
                continue;
            }

            $title = $this->normalizeWhitespace($titleNode->textContent);
            $linkNode = $titleNode->parentNode;
            while ($linkNode && $linkNode->nodeName !== "a") {
                $linkNode = $linkNode->parentNode;
            }
            if (!$linkNode) {
                continue;
            }

            $href = $linkNode->getAttribute("href");
            $link = strpos($href, "http") === 0 ? $href : $this->baseUrl . $href;

            // EXCLUDE FRONTMATTER
            if (stripos($link, "frontmatter") !== false) {
                continue;
            }

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

            $articleData = $this->fetchArticleAbstract($link);

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

    private function parseArticleHtml($html)
    {
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
            $text = $bodyNode ? $this->normalizeWhitespace($bodyNode->textContent) : $this->normalizeWhitespace($articleNode->textContent);
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

    private function fetchArticleAbstract($url)
    {
        $default = [
            "abstract" => "No abstract available",
            "abstractEn" => "",
            "lang" => "unknown",
            "doi" => "",
            "categories" => []
        ];

        $html = $this->fetchUrl($url);

        if (!$html) {
            return $default;
        }

        $parsed = $this->parseArticleHtml($html);
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
            $htmlEn = $this->fetchUrl($englishUrl);
            if ($htmlEn) {
                $parsedEn = $this->parseArticleHtml($htmlEn);
                if ($parsedEn && strtolower($parsedEn["lang"]) === "en") {
                    $result["abstractEn"] = $parsedEn["text"];
                }
            }
        } else {
            $result["abstractEn"] = $result["abstract"];
        }

        return $result;
    }

    private function fetchArticles()
    {
        $this->lastErrorType = null;

        // Try Ahead of Print first
        $aopUrl = $this->baseUrl . "/journal/key/" . $this->journalKey . "/0/0/html";
        $aopHtml = $this->fetchUrl($aopUrl, $responseCode);
        $aopArticles = $this->parseListingArticles($aopHtml);

        // Prefer AoP only if it really contains article items.
        if (count($aopArticles) > 0) {
            $this->isAheadOfPrint = true;
            return $aopArticles;
        }

        // Fall back to latest issue when AoP is unavailable or empty.
        $this->isAheadOfPrint = false;
        $journalUrl = $this->baseUrl . "/journal/key/" . $this->journalKey . "/html";
        $journalHtml = $this->fetchUrl($journalUrl, $journalResponseCode);

        if ($journalResponseCode === 404) {
            $this->lastErrorType = "not_found";
            return [];
        }
        if ($journalResponseCode >= 500) {
            $this->lastErrorType = "upstream";
            return [];
        }

        if ($journalHtml) {
            $dom = new DOMDocument;
            libxml_use_internal_errors(true);
            if ($dom->loadHTML($journalHtml)) {
                libxml_clear_errors();
                $xpath = new DOMXPath($dom);
                $this->setJournalNameFromDom($xpath);

                $journalErrorType = $this->detectPageErrorType($xpath);
                if ($journalErrorType === "not_found") {
                    $this->lastErrorType = "not_found";
                    return [];
                }
                if ($journalErrorType === "upstream") {
                    $this->lastErrorType = "upstream";
                    return [];
                }

                // Selector based on observation: a#view-latest-issue
                $latestIssueLink = $xpath->query("//a[@id='view-latest-issue']")->item(0);
                if ($latestIssueLink) {
                    $latestIssueHref = $latestIssueLink->getAttribute("href");
                    $issueUrl = strpos($latestIssueHref, "http") === 0 ? $latestIssueHref : $this->baseUrl . $latestIssueHref;
                    $issueHtml = $this->fetchUrl($issueUrl, $issueResponseCode);
                    if ($issueResponseCode >= 500) {
                        $this->lastErrorType = "upstream";
                        return [];
                    }
                    $issueArticles = $this->parseListingArticles($issueHtml);
                    if (count($issueArticles) > 0) {
                        return $issueArticles;
                    }
                }
            } else {
                libxml_clear_errors();
                $this->lastErrorType = "upstream";
                return [];
            }
        } else {
            $this->lastErrorType = "upstream";
            return [];
        }

        // If neither source provided parseable article items, return empty.
        return [];
    }

    public function generateRSS()
    {
        $articles = $this->getArticles();

        if ($this->lastErrorType === "not_found") {
            header("Content-Type: text/plain; charset=UTF-8", true, 404);
            echo "Journal not found for key: " . $this->journalKey . "\n";
            return;
        }

        if ($this->lastErrorType === "upstream") {
            header("Content-Type: text/plain; charset=UTF-8", true, 503);
            echo "Upstream source temporarily unavailable for key: " . $this->journalKey . "\n";
            return;
        }

        if (!$this->journalName) {
            $this->journalName = strtoupper($this->journalKey);
        }

        $isGermanFeed = stripos($this->feedLanguage, "de") === 0;

        // Construct title and description dynamically
        $sourceLabel = $this->isAheadOfPrint ? "(Ahead of Print)" : "(Latest Issue)";
        $title = "{$this->journalName} $sourceLabel";
        $descriptionPrefix = $this->isAheadOfPrint
            ? ($isGermanFeed ? "Ahead-of-print-Artikel" : "Ahead-of-print articles")
            : ($isGermanFeed ? "Artikel aus der neuesten Ausgabe" : "Articles from the latest issue");
        $description = $descriptionPrefix . " in {$this->journalName}";
        $link = $this->baseUrl . "/journal/key/" . $this->journalKey . "/0/0/html";

        header("Content-Type: application/rss+xml; charset=UTF-8");

        // Generate self-referencing URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        // Avoid warning if HTTP_HOST or REQUEST_URI are missing (CLI)
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $self_url = $protocol . "://" . $host . $uri;

        $rssFeed = "<?xml version='1.0' encoding='UTF-8'?>\n";
        $rssFeed .= "<rss version='2.0' xmlns:dc='http://purl.org/dc/elements/1.1/' xmlns:atom='http://www.w3.org/2005/Atom'>\n";
        $rssFeed .= "<channel>\n";
        $rssFeed .= "<title>" . htmlspecialchars($title) . "</title>\n";
        $rssFeed .= "<link>" . htmlspecialchars($link) . "</link>\n";
        $rssFeed .= "<atom:link href='" . htmlspecialchars($self_url) . "' rel='self' type='application/rss+xml'/>\n";
        $rssFeed .= "<description>" . htmlspecialchars($description) . "</description>\n";
        $rssFeed .= "<language>" . htmlspecialchars($this->feedLanguage) . "</language>\n";

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
                $intro = $isGermanFeed
                    ? "Von <span id='creators' style='font-weight:900;'>{$authors}</span> (Beitrag auf Englisch). "
                    : "By <span id='creators' style='font-weight:900;'>{$authors}</span> (article in English). ";
                $rssFeed .= "<description><![CDATA[<div>{$intro}</div><div style='margin-top:1em'>{$article["abstractEn"]}</div><div style='margin-top:1em'>{$article["abstract"]}</div>]]></description>\n";
            } else {
                $intro = $isGermanFeed
                    ? "Von <span id='creators' style='font-weight:900;'>{$authors}</span> (Beitrag auf Deutsch). "
                    : "By <span id='creators' style='font-weight:900;'>{$authors}</span> (article in German). ";
                $rssFeed .= "<description><![CDATA[<div>{$intro}</div><div style='margin-top:1em'>{$article["abstract"]}</div><div style='margin-top:1em'>{$article["abstractEn"]}</div>]]></description>\n";
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
}
