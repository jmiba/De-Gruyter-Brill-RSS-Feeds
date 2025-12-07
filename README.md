# De Gruyter/Brill RSS Feeds
[![DOI](https://zenodo.org/badge/1088901183.svg)](https://doi.org/10.5281/zenodo.17847065)

Scrapes webpages of De Gruyter/Brill and turns them into RSS feeds.
- [ABI Technik](https://www.degruyterbrill.com/journal/key/abitech/html)
- [Bibliotheksdienst](https://www.degruyterbrill.com/journal/key/bd/html)
- [Bibliothek Forschung und Praxis](https://www.degruyterbrill.com/journal/key/bfup/html)
- [Information – Wissenschaft & Praxis](https://www.degruyterbrill.com/journal/key/iwp/html)
- [Libri](https://www.degruyterbrill.com/journal/key/libr/html)
- [Open Information Science](https://www.degruyterbrill.com/journal/key/opis/html)
- [Preservation, Digital Technology & Culture](https://www.degruyterbrill.com/journal/key/pdtc/html)
- [Restaurator](https://www.degruyterbrill.com/journal/key/rest/html)
- [Rundbrief Fotografie](https://www.degruyterbrill.com/journal/key/rbf/html)
- [The African Book Publishing Record](https://www.degruyterbrill.com/journal/key/abpr/html)

## Feeds currently available
- ABI Technik (ahead-of-print) via `abitech/rss.php`, served at [https://www.jensmittelbach.de/feeds/abitech/rss.php](https://www.jensmittelbach.de/feeds/abitech/rss.php)
- Bibliotheksdienst (ahead-of-print) via `bd/rss.php`, served at [https://www.jensmittelbach.de/feeds/bd/rss.php](https://www.jensmittelbach.de/feeds/bd/rss.php)
- Bibliothek Forschung und Praxis (ahead-of-print) via `bfp/rss.php`, served at [https://www.jensmittelbach.de/feeds/bfp/rss.php](https://www.jensmittelbach.de/feeds/bfp/rss.php)
- Information - Wissenschaft & Praxis (ahead-of-print) via `iwp/rss.php`, served at [https://www.jensmittelbach.de/feeds/iwp/rss.php](https://www.jensmittelbach.de/feeds/iwp/rss.php)
- Libri (ahead-of-print) via `libr/rss.php`, served at [https://www.jensmittelbach.de/feeds/libr/rss.php](https://www.jensmittelbach.de/feeds/libr/rss.php)
- Open Information Science (ahead-of-print) via `opis/rss.php`, served at [https://www.jensmittelbach.de/feeds/opis/rss.php](https://www.jensmittelbach.de/feeds/opis/rss.php)
- Preservation, Digital Technology & Culture (ahead-of-print) via `pdtc/rss.php`, served at [https://www.jensmittelbach.de/feeds/pdtc/rss.php](https://www.jensmittelbach.de/feeds/pdtc/rss.php)
- Restaurator (ahead-of-print) via `rest/rss.php`, served at [https://www.jensmittelbach.de/feeds/rest/rss.php](https://www.jensmittelbach.de/feeds/rest/rss.php)
- Rundbrief Fotografie (ahead-of-print) via `rbf/rss.php`, served at [https://www.jensmittelbach.de/feeds/rbf/rss.php](https://www.jensmittelbach.de/feeds/rbf/rss.php)
- The African Book Publishing Record (ahead-of-print) via `abpr/rss.php`, served at [https://www.jensmittelbach.de/feeds/abpr/rss.php](https://www.jensmittelbach.de/feeds/abpr/rss.php)

The feeds primarily track the **ahead-of-print** sections provided by De Gruyter/Brill.
> [!NOTE]
> **Fallback Mechanism**: If the "Ahead of Print" section is empty or unavailable for a journal, the script automaticaly falls back to fetching articles from the **latest published issue**. This ensures the feed remains active and provides content even when no articles are currently in the "Ahead of Print" queue.

The scripts parse the article abstracts and metadata and expose them as RSS 2.0 feeds for easier consumption in feed readers.

## Suggested feed readers
- [Feedly](https://feedly.com) (Web, iOS, Android): great for cross-device syncing and powerful search/saved keyword alerts.
- [Reeder](https://www.reederapp.com) (macOS, iOS): fast native interface, handy for offline reading and integration with multiple sync backends.
- [Inoreader](https://www.inoreader.com) (Web, mobile apps): advanced filtering, rules, and team sharing when monitoring many scholarly feeds.
- [NetNewsWire](https://netnewswire.com) (macOS, iOS): lightweight open-source option that works well for a small number of personal feeds.

## Setup on remote server

1. Clone the repository to your web server.
   
   **Option A: Clone into a new subdirectory (Recommended):**
   ```bash
   git clone https://github.com/jmiba/De-Gruyter-Brill-RSS-Feeds.git rss-feeds
   ```
   
   **Option B: Install into current directory (if not empty):**
   ```bash
   git init
   git remote add origin https://github.com/jmiba/De-Gruyter-Brill-RSS-Feeds.git
   git pull origin main
   ```
2. Ensure PHP is installed and the web server is configured to serve `.php` files.
3. The scripts should be accessible at:
   - `https://example.com/abitech/rss.php`
   - `https://example.com/abpr/rss.php`
   - `https://example.com/bd/rss.php`
   - `https://example.com/bfp/rss.php`
   - `https://example.com/iwp/rss.php`
   - `https://example.com/libr/rss.php`
   - `https://example.com/opis/rss.php`
   - `https://example.com/pdtc/rss.php`
   - `https://example.com/rest/rss.php`
   - `https://example.com/rbf/rss.php`
   
   *(Adjust URL path depending on Option A or B)*
4. (Optional) If running via cron/CLI, ensure execution permissions:
   ```bash
   chmod +x abitech/rss.php abpr/rss.php bd/rss.php bfp/rss.php iwp/rss.php libr/rss.php opis/rss.php pdtc/rss.php rest/rss.php rbf/rss.php
   ```

## Updating the installation

If there are changes in the git repository, you can update the files on your server by running:

```bash
git pull origin main
```
