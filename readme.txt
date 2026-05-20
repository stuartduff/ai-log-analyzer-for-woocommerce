=== AI Log Analyzer for WooCommerce ===
Contributors: stuartduff
Plugin URI: https://github.com/stuartduff/ai-log-analyzer-for-woocommerce
Tags: woocommerce, logs, ai, support, diagnostics
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered WooCommerce log analyzer. Understand and fix log errors without developer knowledge.

== Description ==

AI Log Analyzer for WooCommerce integrates directly with the WooCommerce log management UI to help store owners understand and resolve log file issues without needing developer assistance.

**Features:**

* Analyze any WooCommerce log file with a single click.
* Receive plain-English summaries of errors with step-by-step fix instructions.
* Automatically identify which plugin or theme is responsible for each error.
* Download a formatted diagnostic report to share with plugin support teams.
* Supports all WooCommerce log handlers: FileV2, Legacy File, and Database.

**Requirements:**

* WordPress 7.0 or higher (WordPress AI Client required)
* WooCommerce
* PHP 8.0 or higher
* An AI provider configured in Settings › Connectors (Anthropic, Google, or OpenAI)

== Installation ==

1. Upload the `ai-log-analyzer-for-woocommerce` directory to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins › Installed Plugins**.
3. Go to **Settings › Connectors** and connect at least one AI provider (Anthropic, Google, or OpenAI).
4. Navigate to **WooCommerce › AI Log Analyzer** to configure analysis settings.
5. Go to **WooCommerce › Status › Logs**, open any log file, and click **Analyse with AI**.

== Frequently Asked Questions ==

= Does this plugin send my log data to third-party services? =

Log content is sent to the AI provider you have configured in Settings › Connectors. This plugin does not store or cache log content — data is transmitted for analysis and not retained.

Before transmission, common patterns of sensitive data are automatically stripped: API keys and secrets, passwords, auth tokens, email addresses, HTTP Authorization and X-API-Key headers, DSN connection-string credentials, and PEM-encoded private keys. Redaction covers key=value pairs, JSON fields, and prefixed key names (e.g. `db_password`, `stripe_secret`).

**Note:** Redaction is best-effort and pattern-based. Values appearing in plain prose without a recognisable key name (e.g. `"invalid key 'abc123'"`) and completely custom credential field names outside the known allowlist may not be caught. Review your logs before analysis if they may contain highly sensitive data.

= Which AI providers are supported? =

Any provider configured in the WordPress Connectors API is supported: Anthropic (Claude), Google (Gemini), and OpenAI (GPT). The active provider is selected via the AI Model Preference setting; the WordPress AI Client (`wp_ai_client_prompt`) handles the request.

= Can Shop Managers use the analyzer? =

By default, administrators (users with `manage_options`) and any user with both `manage_woocommerce` and `prompt_ai` capabilities can run analyses. Access for Shop Managers can be enabled under **WooCommerce › AI Log Analyzer › Limits & Permissions**.

= What happens if a log file is very large? =

Log files larger than the configured maximum size (default 10 MB) are automatically truncated from the beginning, keeping the most recent entries which are most relevant for diagnosis.

== Screenshots ==

1. The AI analysis modal showing a severity notice, cause summary, and step-by-step fix instructions.
2. The settings page with model preference, temperature, and permissions controls.

== Changelog ==

= 1.0.0 =
* Initial release.
* AI-powered analysis via the WordPress AI Client.
* "Analyse with AI" button injected into WooCommerce log management UI.
* Settings page built with @wordpress/dataviews DataForm.
* Downloadable HTML diagnostic reports.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
