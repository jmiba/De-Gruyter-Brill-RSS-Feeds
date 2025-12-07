# De Gruyter/Brill RSS Feeds
Scrapes webpages of De Gruyter/Brill and turns them into RSS feeds.
- [ABI Technik](https://www.degruyterbrill.com/journal/key/abitech/html)
- [Bibliotheksdienst](https://www.degruyterbrill.com/journal/key/bd/html)
- [Bibliothek Forschung und Praxis](https://www.degruyterbrill.com/journal/key/bfup/html)
- [Information – Wissenschaft & Praxis](https://www.degruyterbrill.com/journal/key/iwp/html)

## Feeds currently available
- ABI Technik (ahead-of-print) via `abitech/rss.php`, served at [https://www.jensmittelbach.de/abitech/rss.php](https://www.jensmittelbach.de/abitech/rss.php)
- Bibliotheksdienst (ahead-of-print) via `bd/rss.php`, served at [https://www.jensmittelbach.de/bd/rss.php](https://www.jensmittelbach.de/bd/rss.php)
- Bibliothek Forschung und Praxis (ahead-of-print) via `bfp/rss.php`, served at [https://www.jensmittelbach.de/bfp/rss.php](https://www.jensmittelbach.de/bfp/rss.php)
- Information - Wissenschaft & Praxis (ahead-of-print) via `iwp/rss.php`, served at [https://www.jensmittelbach.de/iwp/rss.php](https://www.jensmittelbach.de/iwp/rss.php)

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
   - `https://example.com/bd/rss.php`
   - `https://example.com/bfp/rss.php`
   - `https://example.com/iwp/rss.php`
   *(Adjust URL path depending on Option A or B)*
4. (Optional) If running via cron/CLI, ensure execution permissions:
   ```bash
   chmod +x abitech/rss.php bd/rss.php bfp/rss.php iwp/rss.php
   ```

## Updating the installation

If there are changes in the git repository, you can update the files on your server by running:

```bash
git pull origin main
```
