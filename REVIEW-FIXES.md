# WordPress.org Plugin Review — Fixes Required

Review ID: F1 ai-log-analyzer-for-woocommerce/stuartduff/21May26/T1 21May26/3.9 (P0TDX316651HGN)
Received: 22 May 2026

---

## Issue 1 — composer.json missing from the plugin

- [x] Add `composer.json` to the root of the plugin so it is included in the SVN submission.
  - The file was excluded via `.distignore` or was never committed. It must be present at `ai-log-analyzer-for-woocommerce/composer.json`.
  - Even if it is only used for development, WordPress.org requires it to be publicly available.

---

## Issue 2 — No publicly documented resource for compiled/minified JS

- [x] Add a `== Source Code ==` (or similar) section to `readme.txt` that links to the public GitHub repository where the unminified source lives.
  - The reviewer flagged `build/analyze.js` as minified/compiled with no accompanying source.
  - The link must point to a **publicly accessible** repository — the reviewer will check.
- [x] Include build instructions in the readme (or in the repo) so future developers know how to rebuild the assets (e.g. `npm install && npm run build`).

---

## Issue 3 — Inline `<style>` tag instead of `wp_enqueue_style()`

**Clarification for reviewer (likely a false positive):**

The `<style>` block at `includes/class-log-integration.php:573` is not injected into a WordPress admin page. It is part of a self-contained HTML string returned as a **downloadable diagnostic report** (the "Download Report" feature). The HTML document is served as a file download and is never rendered inside WordPress.

Inline styles are the correct and only practical approach for a standalone HTML document — the same pattern used by HTML email templates — because the document has no access to WordPress-enqueued stylesheets.

All styles that apply to WordPress admin pages are correctly enqueued via `wp_enqueue_style()` on the `admin_enqueue_scripts` hook and compiled from `src/analyze/index.scss`.

- [x] ~~Remove the raw `<style>` block~~ — not applicable; this is a self-contained HTML download, not a WordPress page injection. Will clarify with reviewer.

---

## Issue 4 — Undocumented use of third-party / external services

- [x] Add an `== External Services ==` section to `readme.txt` covering Anthropic, Google (Gemini), and OpenAI.
  - Documents what each service is used for, what data is sent, when it is sent, and data retention.
  - Includes ToS and Privacy Policy links for all three providers.
  - **Before resubmitting: verify all six policy URLs are live and point to the correct content — the reviewer will check them.**

---

## Issue 5 — Hardcoded `WP_PLUGIN_DIR` constant for path resolution

- [x] Replace `WP_PLUGIN_DIR . '/' . $plugin_file` at `includes/class-analysis-engine.php:174` with `trailingslashit( dirname( dirname( AI_LOG_ANALYZER_FILE ) ) ) . $plugin_file` — derives the plugins directory from the plugin's own `__FILE__`-based constant instead of the raw WP constant.
- [x] Audited the full codebase — no other raw `WP_PLUGIN_DIR`, `WP_CONTENT_DIR`, or similar constants found outside of tests/vendor. (`ABSPATH` used for the core include at line 168 is universally acceptable.)

---

## Issue 6 — Generic / too-short prefix (`ai_`, `AI_Log_Analyzer`)

The reviewer flagged that the prefix `ai` is only two characters and is a common word, risking conflicts.

### Affected identifiers to rename

| Location | Current name | Suggested replacement |
|---|---|---|
| `class-log-integration.php:71` | `wp_ajax_ai_analyze_log` | `wp_ajax_ailwc_analyze_log` |
| `class-log-integration.php:72` | `wp_ajax_nopriv_ai_analyze_log` | `wp_ajax_nopriv_ailwc_analyze_log` |
| `class-log-integration.php:73` | `wp_ajax_ai_download_report` | `wp_ajax_ailwc_download_report` |
| `class-log-integration.php:74` | `wp_ajax_nopriv_ai_download_report` | `wp_ajax_nopriv_ailwc_download_report` |
| `class-log-integration.php:121` | JS object `aiLogAnalyzer` / nonce `ai_analyze_log` | `ailwcLogAnalyzer` / `ailwc_analyze_log` |
| All `includes/*.php` + `admin/*.php` | `namespace AI_Log_Analyzer` | `namespace AILWC_Log_Analyzer` (or similar ≥4-char prefix) |
| `class-admin-interface.php:35` | `register_setting('ai_log_analyzer', ...)` | `register_setting('ailwc_log_analyzer', ...)` |
| Main file `:24–28` | `AI_LOG_ANALYZER_VERSION` etc. | `AILWC_LOG_ANALYZER_VERSION` etc. (or keep if you choose a different prefix) |
| Main file `:28` | `'ai_log_analyzer_settings'` option name | `'ailwc_log_analyzer_settings'` |

> **Choose your prefix first.** The reviewer suggested `ailoanfo` but any unique prefix ≥ 4 characters works. `ailwc` is used above as an example — confirm your choice and apply it consistently everywhere before making changes.

- [ ] Decide on a prefix (≥ 4 characters, unique, not `wp_` / `__` / `_`).
- [ ] Rename all AJAX action hooks.
- [ ] Rename the JS localised object and all nonce keys.
- [ ] Rename the namespace across all PHP files.
- [ ] Rename `register_setting` / `update_option` option name keys.
- [ ] Rename all `define()` constants.
- [ ] Update any references to the old names in JS files (e.g. `aiLogAnalyzer.ajaxUrl` in `build/analyze.js` source).
- [ ] Run a full-codebase search for the old strings to catch anything missed.

---

## Final checklist before resubmitting

- [ ] Run [Plugin Check](https://wordpress.org/plugins/plugin-check/) and resolve any new warnings.
- [ ] Run PHPCS + WPCS over the plugin and fix violations.
- [ ] Test on a clean WordPress install with `WP_DEBUG = true` — no PHP notices, warnings, or errors.
- [ ] Upload the corrected zip via "Add your plugin" on WordPress.org.
- [ ] Reply to the review email — briefly, without listing every change (the team re-reviews the whole plugin).
