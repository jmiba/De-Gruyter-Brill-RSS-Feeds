# De Gruyter/Brill RSS Feeds
Scrapes webpages of De Gruyter/Brill and turns them into RSS feeds.
- [Bibliothek Forschung und Praxis](https://www.degruyterbrill.com/journal/key/bfup/html)
- [Information – Wissenschaft & Praxis](https://www.degruyterbrill.com/journal/key/iwp/html)

## Feeds currently available
- Bibliothek Forschung und Praxis (ahead-of-print) via `bfp/rss.php`, served at [https://www.jensmittelbach.de/bfp/rss.php](https://www.jensmittelbach.de/bfp/rss.php)
- Information - Wissenschaft & Praxis (ahead-of-print) via `iwp/rss.php`, served at [https://www.jensmittelbach.de/iwp/rss.php](https://www.jensmittelbach.de/iwp/rss.php)

Both feeds track the ahead-of-print sections provided by De Gruyter/Brill for their respective journals, parse the article abstracts and metadata, and expose them as RSS 2.0 feeds for easier consumption in feed readers.

## Suggested feed readers
- [Feedly](https://feedly.com) (Web, iOS, Android): great for cross-device syncing and powerful search/saved keyword alerts.
- [Reeder](https://www.reederapp.com) (macOS, iOS): fast native interface, handy for offline reading and integration with multiple sync backends.
- [Inoreader](https://www.inoreader.com) (Web, mobile apps): advanced filtering, rules, and team sharing when monitoring many scholarly feeds.
- [NetNewsWire](https://netnewswire.com) (macOS, iOS): lightweight open-source option that works well for a small number of personal feeds.

## Setup on remote server

1. Clone the repository to your web server's public directory:
   ```bash
   git clone https://github.com/jmiba/De-Gruyter-Brill-RSS-Feeds.git .
   ```
2. Ensure PHP is installed and the web server is configured to serve `.php` files.
3. The scripts should be accessible at:
   - `https://example.com/bfp/rss.php`
   - `https://example.com/iwp/rss.php`
4. (Optional) If running via cron/CLI, ensure execution permissions:
   ```bash
   chmod +x bfp/rss.php iwp/rss.php
   ```
