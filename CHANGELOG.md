# Changelog

All notable changes to this project are documented in this file.

## [1.0.4] - 2026-02-28
### Changed
- RSS items are now explicitly sorted by `pubDate` in descending order (newest first) before feed generation.
- Added a stable title-based tie-breaker when publication dates are identical.

## [1.0.3] - 2026-02-28
### Fixed
- Avoided deprecated `$http_response_header` usage by switching HTTP response parsing to `fopen()` + `stream_get_meta_data()`.
- Prevented upstream error pages (for example "Unspecified server error") from being used as journal titles.
- Improved source fallback behavior: Ahead-of-Print is only used when parseable article items exist, otherwise latest issue is tried.
- Added explicit HTTP error responses for invalid upstream states:
  - `404` when a journal key does not exist.
  - `503` when the upstream source is temporarily unavailable.
- Stopped caching empty article payloads from transient upstream failures.

### Changed
- Journal-title extraction now rejects known error phrases before setting feed metadata.
