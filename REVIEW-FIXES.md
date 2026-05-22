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

- [ ] Add a `== Source Code ==` (or similar) section to `readme.txt` that links to the public GitHub repository where the unminified source lives.
  - The reviewer flagged `build/analyze.js` as minified/compiled with no accompanying source.
  - The link must point to a **publicly accessible** repository — the reviewer will check.
- [ ] Include build instructions in the readme (or in the repo) so future developers know how to rebuild the assets (e.g. `npm install && npm run build`).

---

## Issue 3 — Inline `<style>` tag instead of `wp_enqueue_style()`

- [ ] Remove the raw `<style>` block at `includes/class-log-integration.php:573`.
- [ ] Extract those styles into a separate CSS file and enqueue it with `wp_enqueue_style()` (or `wp_add_inline_style()` if they must be dynamic) hooked on `admin_enqueue_scripts`.

---

## Issue 4 — Undocumented use of third-party / external services

- [ ] Add an `== External Services ==` section to `readme.txt` that covers every AI provider the plugin can send data to via the WordPress AI Client.
  - For **each** provider, document:
    1. What the service is and what it is used for.
    2. What data is sent (log file content) and when (on user-initiated analysis).
    3. A link to the service's **Terms of Service**.
    4. A link to the service's **Privacy Policy**.
  - At minimum, document the providers currently selectable in settings (e.g. Anthropic, OpenAI, and any others).
  - Verify every ToS/Privacy link is live and contains the correct content — the reviewer will check.

---

## Issue 5 — Hardcoded `WP_PLUGIN_DIR` constant for path resolution

- [ ] Replace the usage at `includes/class-analysis-engine.php:174`:
  ```php
  // Before
  $full_path = WP_PLUGIN_DIR . '/' . $plugin_file;

  // After — use the plugin's own defined constant (already defined in the main file)
  $full_path = AI_LOG_ANALYZER_PATH . $plugin_file;
  // or, if $plugin_file is relative to the plugins root, use plugins_url() / plugin_dir_path()
  ```
- [ ] Audit the rest of the codebase for any other direct use of `WP_PLUGIN_DIR`, `WP_CONTENT_DIR`, or similar raw constants and replace them with `plugin_dir_path()` / `plugin_dir_url()` calls or the constants already defined in the main plugin file.

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
