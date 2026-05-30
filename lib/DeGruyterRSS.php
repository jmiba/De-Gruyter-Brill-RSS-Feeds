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
        $staleArticles = [];
        if (file_exists($this->cacheFile) && (time() - filemtime($this->cacheFile)) < $this->cacheTime) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            $staleArticles = $this->extractCachedArticles($cached);
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
        } elseif (file_exists($this->cacheFile)) {
            $cached = json_decode(file_get_contents($this->cacheFile), true);
            $staleArticles = $this->extractCachedArticles($cached);
            if (is_array($cached) && count($cached) > 0 && isset($cached["articles"])) {
                if (!$this->journalName && isset($cached["journalName"]) && !$this->isInvalidJournalTitle($cached["journalName"])) {
                    $this->journalName = $cached["journalName"];
                }
                if (isset($cached["source"])) {
                    $this->isAheadOfPrint = $cached["source"] === "ahead-of-print";
                }
                if (isset($cached["feedLanguage"])) {
                    $this->feedLanguage = $cached["feedLanguage"];
                }
            }
        }

        $articles = $this->fetchArticles();
        if (count($articles) === 0 && count($staleArticles) > 0 && $this->lastErrorType === "upstream") {
            return $staleArticles;
        }
        if (count($articles) > 0 && count($staleArticles) > 0) {
            $articles = $this->mergeMissingAbstractsFromCache($articles, $staleArticles);
        }

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

    private function fetchUrl($url, &$responseCode = null, &$responseHeaders = null)
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

    private function isHumanVerificationResponse($responseCode, array $responseHeaders = [], $html = "")
    {
        foreach ($responseHeaders as $headerLine) {
            if (stripos($headerLine, 'x-amzn-waf-action: challenge') !== false) {
                return true;
            }
        }

        if (intval($responseCode) === 202 && $this->normalizeWhitespace($html) === "") {
            return true;
        }

        $normalizedHtml = strtolower($this->normalizeWhitespace(strip_tags($html)));
        if ($normalizedHtml === "") {
            return false;
        }

        return strpos($normalizedHtml, 'human verification') !== false
            || strpos($normalizedHtml, 'security check') !== false
            || strpos($normalizedHtml, 'verify you are a human') !== false
            || strpos($normalizedHtml, 'lassen sie uns prüfen, ob sie ein mensch sind') !== false
            || strpos($normalizedHtml, 'führen sie die sicherheitsprüfung durch') !== false;
    }

    private function normalizeWhitespace($text)
    {
        $text = trim($text);
        if ($text === "") {
            return "";
        }
        return preg_replace('/\s+/u', ' ', $text);
    }

    private function extractDoiFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === "") {
            return "";
        }

        if (preg_match('#/document/doi/(.+?)/(html|xml|pdf)$#', $path, $matches)) {
            return rawurldecode($matches[1]);
        }

        return "";
    }

    private function cleanCrossrefAbstract($abstract)
    {
        if (!is_string($abstract) || trim($abstract) === "") {
            return "";
        }

        $text = preg_replace('/<jats:title\b[^>]*>.*?<\/jats:title>/isu', ' ', $abstract);
        $text = preg_replace('/<[^>]+>/', ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $this->normalizeWhitespace($text);
    }

    private function fetchCrossrefMetadata($doi)
    {
        $default = [
            "doi" => trim($doi),
            "abstract" => "",
            "lang" => "unknown"
        ];

        $doi = trim($doi);
        if ($doi === "") {
            return $default;
        }

        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => implode("\r\n", [
                    "User-Agent: Mozilla/5.0 (compatible; DGB-RSS/1.0; +https://www.jensmittelbach.de/" . $this->journalKey . "/rss.php)",
                    "Accept: application/json"
                ]),
                "timeout" => 30,
                "ignore_errors" => true
            ]
        ]);

        $json = @file_get_contents("https://api.crossref.org/works/" . rawurlencode($doi), false, $context);
        $responseCode = 0;
        if (function_exists('http_get_last_response_headers')) {
            $responseHeaders = http_get_last_response_headers();
            if (is_array($responseHeaders)) {
                foreach ($responseHeaders as $headerLine) {
                    if (preg_match('#HTTP/\d+\.\d+ (\d+)#', $headerLine, $matches)) {
                        $responseCode = intval($matches[1]);
                    }
                }
            }
        }

        if (!$json || $responseCode >= 400) {
            return $default;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data["message"]) || !is_array($data["message"])) {
            return $default;
        }

        $message = $data["message"];
        $language = isset($message["language"]) ? strtolower(trim($message["language"])) : "unknown";
        if (!in_array($language, ["en", "de"], true)) {
            $language = "unknown";
        }

        return [
            "doi" => !empty($message["DOI"]) ? trim($message["DOI"]) : $default["doi"],
            "abstract" => isset($message["abstract"]) ? $this->cleanCrossrefAbstract($message["abstract"]) : "",
            "lang" => $language
        ];
    }

    private function applyCrossrefFallback(array $result, $doi)
    {
        $crossref = $this->fetchCrossrefMetadata($doi);
        if ($crossref["abstract"] === "") {
            return $result;
        }

        $currentAbstract = isset($result["abstract"]) ? strtolower($this->normalizeWhitespace($result["abstract"])) : "";
        if ($currentAbstract === "" || $currentAbstract === "no abstract available") {
            $result["abstract"] = $crossref["abstract"];
        }

        if ($result["doi"] === "" && $crossref["doi"] !== "") {
            $result["doi"] = $crossref["doi"];
        }

        if (($result["lang"] === "unknown" || $result["lang"] === "") && $crossref["lang"] !== "unknown") {
            $result["lang"] = $crossref["lang"];
        }

        if ($result["abstractEn"] === "" && ($result["lang"] === "en" || $crossref["lang"] === "en")) {
            $result["abstractEn"] = $crossref["abstract"];
        }

        return $result;
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
            strpos($allText, "server error") !== false ||
            strpos($allText, "human verification") !== false ||
            strpos($allText, "security check") !== false ||
            strpos($allText, "verify you are a human") !== false ||
            strpos($allText, "lassen sie uns prüfen, ob sie ein mensch sind") !== false ||
            strpos($allText, "führen sie die sicherheitsprüfung durch") !== false
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

            $articleData = $this->fetchArticleAbstract($link, $doi);

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

    private function extractStructuredText(DOMXPath $xpath, DOMNode $node)
    {
        $parts = [];
        $paragraphs = $xpath->query(".//p", $node);
        foreach ($paragraphs as $paragraph) {
            $text = $this->normalizeWhitespace($paragraph->textContent);
            if ($text !== "") {
                $parts[] = $text;
            }
        }

        if (count($parts) > 0) {
            return implode(" ", $parts);
        }

        return $this->normalizeWhitespace($node->textContent);
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
        if ($this->detectPageErrorType($xpath) !== null) {
            return null;
        }

        $articleNode = $xpath->query("//div[@id='text-container']//div[contains(@class, 'article')]")->item(0);
        $text = "";
        $textEn = "";
        $lang = "unknown";

        if ($articleNode) {
            $langAttr = $articleNode->getAttribute("lang");
            if ($langAttr) {
                $lang = $langAttr;
            }

            $abstractNode = $xpath->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' abstract ')]", $articleNode)->item(0);
            if ($abstractNode) {
                $text = $this->extractStructuredText($xpath, $abstractNode);
            }

            $abstractEnNode = $xpath->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' abstract-en ')]", $articleNode)->item(0);
            if ($abstractEnNode) {
                $textEn = $this->extractStructuredText($xpath, $abstractEnNode);
            }

            if ($text === "") {
                $descriptionMeta = $xpath->query("//meta[@name='description']")->item(0);
                if ($descriptionMeta) {
                    $text = $this->normalizeWhitespace($descriptionMeta->getAttribute("content"));
                }
            }

            if ($text === "") {
                $bodyNode = $xpath->query(".//div[contains(@class, 'body')]", $articleNode)->item(0);
                $text = $bodyNode ? $this->normalizeWhitespace($bodyNode->textContent) : $this->normalizeWhitespace($articleNode->textContent);
            }
        }

        $doiMeta = $xpath->query("//meta[@name='citation_doi']")->item(0);
        $doi = $doiMeta ? trim($doiMeta->getAttribute("content")) : "";

        $sectionMeta = $xpath->query("//meta[@property='article:section']")->item(0);
        $section = $sectionMeta ? trim($sectionMeta->getAttribute("content")) : "";

        return [
            "text" => $text !== "" ? $text : "No abstract available",
            "textEn" => $textEn,
            "lang" => $lang,
            "doi" => $doi,
            "section" => $section
        ];
    }

    private function fetchArticleAbstract($url, $fallbackDoi = "")
    {
        $resolvedDoi = trim($fallbackDoi);
        if ($resolvedDoi === "") {
            $resolvedDoi = $this->extractDoiFromUrl($url);
        }

        $default = [
            "abstract" => "No abstract available",
            "abstractEn" => "",
            "lang" => "unknown",
            "doi" => $resolvedDoi,
            "categories" => []
        ];

        $html = $this->fetchUrl($url, $responseCode, $responseHeaders);

        if (!$html) {
            return $this->applyCrossrefFallback($default, $resolvedDoi);
        }

        if ($this->isHumanVerificationResponse($responseCode, $responseHeaders, $html)) {
            return $this->applyCrossrefFallback($default, $resolvedDoi);
        }

        $parsed = $this->parseArticleHtml($html);
        if (!$parsed) {
            return $this->applyCrossrefFallback($default, $resolvedDoi);
        }

        $result = [
            "abstract" => $parsed["text"],
            "abstractEn" => $parsed["textEn"],
            "lang" => $parsed["lang"],
            "doi" => $parsed["doi"] !== "" ? $parsed["doi"] : $resolvedDoi,
            "categories" => $parsed["section"] !== "" ? [$parsed["section"]] : []
        ];

        if ($result["lang"] === "en") {
            $result["abstractEn"] = $result["abstract"];
        } elseif ($result["abstractEn"] === "") {
            $separator = strpos($url, '?') === false ? '?' : '&';
            $englishUrl = $url . $separator . "lang=en";
            $htmlEn = $this->fetchUrl($englishUrl, $englishResponseCode, $englishResponseHeaders);
            if ($htmlEn && !$this->isHumanVerificationResponse($englishResponseCode, $englishResponseHeaders, $htmlEn)) {
                $parsedEn = $this->parseArticleHtml($htmlEn);
                if ($parsedEn && strtolower($parsedEn["lang"]) === "en") {
                    $result["abstractEn"] = $parsedEn["text"];
                }
            }
        }

        return $this->applyCrossrefFallback($result, $result["doi"]);
    }

    private function extractCachedArticles($cached)
    {
        if (!is_array($cached) || count($cached) === 0) {
            return [];
        }

        if (isset($cached["articles"]) && is_array($cached["articles"])) {
            return $cached["articles"];
        }

        return isset($cached[0]) ? $cached : [];
    }

    private function mergeMissingAbstractsFromCache(array $articles, array $staleArticles)
    {
        $staleByGuid = [];
        $staleByLink = [];
        $staleByTitle = [];

        foreach ($staleArticles as $staleArticle) {
            if (!is_array($staleArticle)) {
                continue;
            }

            if (!empty($staleArticle["guid"])) {
                $staleByGuid[$staleArticle["guid"]] = $staleArticle;
            }
            if (!empty($staleArticle["link"])) {
                $staleByLink[$staleArticle["link"]] = $staleArticle;
            }
            if (!empty($staleArticle["title"])) {
                $staleByTitle[strtolower($this->normalizeWhitespace($staleArticle["title"]))] = $staleArticle;
            }
        }

        foreach ($articles as &$article) {
            $abstract = isset($article["abstract"]) ? $this->normalizeWhitespace($article["abstract"]) : "";
            if ($abstract !== "" && strtolower($abstract) !== "no abstract available") {
                continue;
            }

            $titleKey = !empty($article["title"]) ? strtolower($this->normalizeWhitespace($article["title"])) : "";
            $match = null;

            if (!empty($article["guid"]) && isset($staleByGuid[$article["guid"]])) {
                $match = $staleByGuid[$article["guid"]];
            } elseif (!empty($article["link"]) && isset($staleByLink[$article["link"]])) {
                $match = $staleByLink[$article["link"]];
            } elseif ($titleKey !== "" && isset($staleByTitle[$titleKey])) {
                $match = $staleByTitle[$titleKey];
            }

            if (!$match) {
                continue;
            }

            if (!empty($match["abstract"]) && strtolower($this->normalizeWhitespace($match["abstract"])) !== "no abstract available") {
                $article["abstract"] = $match["abstract"];
            }
            if (empty($article["abstractEn"]) && !empty($match["abstractEn"])) {
                $article["abstractEn"] = $match["abstractEn"];
            }
            if ((empty($article["lang"]) || $article["lang"] === "unknown") && !empty($match["lang"])) {
                $article["lang"] = $match["lang"];
            }
            if (empty($article["categories"]) && !empty($match["categories"])) {
                $article["categories"] = $match["categories"];
            }
        }
        unset($article);

        return $articles;
    }

    private function fetchArticles()
    {
        $this->lastErrorType = null;

        // Try Ahead of Print first
        $aopUrl = $this->baseUrl . "/journal/key/" . $this->journalKey . "/0/0/html";
        $aopHtml = $this->fetchUrl($aopUrl, $responseCode, $aopResponseHeaders);
        if ($this->isHumanVerificationResponse($responseCode, $aopResponseHeaders, $aopHtml)) {
            $this->lastErrorType = "upstream";
            return [];
        }
        $aopArticles = $this->parseListingArticles($aopHtml);

        // Prefer AoP only if it really contains article items.
        if (count($aopArticles) > 0) {
            $this->isAheadOfPrint = true;
            return $aopArticles;
        }

        // Fall back to latest issue when AoP is unavailable or empty.
        $this->isAheadOfPrint = false;
        $journalUrl = $this->baseUrl . "/journal/key/" . $this->journalKey . "/html";
        $journalHtml = $this->fetchUrl($journalUrl, $journalResponseCode, $journalResponseHeaders);

        if ($journalResponseCode === 404) {
            $this->lastErrorType = "not_found";
            return [];
        }
        if ($this->isHumanVerificationResponse($journalResponseCode, $journalResponseHeaders, $journalHtml)) {
            $this->lastErrorType = "upstream";
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
                    $issueHtml = $this->fetchUrl($issueUrl, $issueResponseCode, $issueResponseHeaders);
                    if ($this->isHumanVerificationResponse($issueResponseCode, $issueResponseHeaders, $issueHtml)) {
                        $this->lastErrorType = "upstream";
                        return [];
                    }
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
        usort($articles, function ($a, $b) {
            $aTime = isset($a["pubDate"]) ? strtotime($a["pubDate"]) : 0;
            $bTime = isset($b["pubDate"]) ? strtotime($b["pubDate"]) : 0;

            if ($aTime === $bTime) {
                $aTitle = isset($a["title"]) ? $a["title"] : "";
                $bTitle = isset($b["title"]) ? $b["title"] : "";
                return strcasecmp($aTitle, $bTitle);
            }

            // Descending: newest publication date first.
            return $bTime <=> $aTime;
        });

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
        $channelDate = count($articles) > 0 ? $articles[0]["pubDate"] : date(DATE_RSS);

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
        $rssFeed .= "<pubDate>" . htmlspecialchars($channelDate) . "</pubDate>\n";
        $rssFeed .= "<lastBuildDate>" . htmlspecialchars($channelDate) . "</lastBuildDate>\n";

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
