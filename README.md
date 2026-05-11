# AI Log Analyzer for WooCommerce

An AI-powered WooCommerce log file analyzer. Integrates with the WordPress 7.0+ AI Client (`wp_ai_client_prompt`) to help store owners understand and resolve log issues directly from the WooCommerce status logs tab — without needing developer support.

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.0+ |
| WordPress | 7.0+ |
| WooCommerce | Latest stable |
| Node.js | 18+ |
| Composer | 2.x |

> WordPress 7.0 introduces the [WordPress AI Client](https://make.wordpress.org/core/) (`wp_ai_client_prompt`). The plugin will not initialize without it.

## Local Development Setup

```bash
# 1. Clone into your local WordPress install
git clone git@github.com:stuartduff/wc-ai-log-analyzer.git wp-content/plugins/wc-ai-log-analyzer
cd wp-content/plugins/wc-ai-log-analyzer

# 2. Install PHP dependencies
composer install

# 3. Install JS dependencies
yarn install

# 4. Compile assets
yarn build
```

Activate the plugin from **WP Admin → Plugins**, then navigate to **WooCommerce → Status → Logs** to use it.

For active JS development with hot reloading:

```bash
yarn start
```

## Project Structure

```
wc-ai-log-analyzer/
├── ai-log-analyzer.php          # Plugin entry point — constants, autoloader, bootstrap
├── includes/
│   ├── class-plugin.php         # Singleton core — dependency checks, wires all classes
│   ├── class-ai-client.php      # Wrapper around wp_ai_client_prompt()
│   ├── class-analysis-engine.php# Orchestrates analysis + injects support URLs
│   ├── class-log-integration.php# WC log UI integration, AJAX handlers, HTML reports
│   └── class-log-parser.php     # Log content sanitization and pre-processing
├── admin/
│   ├── class-admin-interface.php# Settings page registration and rendering
│   └── views/                   # PHP view templates
├── src/
│   └── analyze/                 # React frontend (wp-scripts)
│       ├── index.js             # Entry point — mounts the React app
│       ├── index.scss           # Styles
│       ├── analysis-results.jsx # Root component
│       ├── components/          # Severity notice, fix steps, support contact
│       └── hooks/               # use-analyze.js — AJAX fetch hook
├── build/                       # Compiled JS/CSS (gitignored — run yarn build)
└── tests/
    ├── php/                     # PHPUnit test suites
    └── js/                      # wp-scripts JS test suites
```

## Available Scripts

### JavaScript

```bash
yarn build          # Production build → build/
yarn start          # Development build with file watcher
yarn lint:js        # ESLint — src/
yarn lint:css       # Stylelint — src/
yarn test:js        # JS unit tests via wp-scripts
```

### PHP

```bash
composer lint       # PHPCS with WordPress Coding Standards
composer lint:fix   # PHPCBF auto-fix
composer test       # PHPUnit
```

### Translations & Release

```bash
yarn makepot        # Regenerate languages/ai-log-analyzer-for-woocommerce.pot
yarn build:zip      # Build production zip → dist/ai-log-analyzer-for-woocommerce.zip
```

Both commands shell out to [WP-CLI](https://wp-cli.org/) (`wp i18n make-pot`), so `wp` must be on your `PATH` — on macOS install with `brew install wp-cli`.

`yarn build:zip` runs `bin/build-zip.sh`, which:

1. Cleans `dist/`.
2. Runs the production webpack build.
3. Reinstalls Composer with `--no-dev --optimize-autoloader` for a slim autoloader.
4. Stages the plugin into a slug-named folder, excluding everything in `.distignore`.
5. Zips it to `dist/ai-log-analyzer-for-woocommerce.zip` (installable via **WP Admin → Plugins → Add New → Upload**).
6. Restores Composer dev dependencies.

## Architecture Overview

### AI Integration

The plugin uses the WordPress AI Client API introduced in WordPress 7.0. The `AI_Client` class wraps `wp_ai_client_prompt()` and supports three model preferences (configurable via plugin settings):

| Setting | Model |
|---|---|
| `anthropic` | `claude-sonnet-4-6` |
| `google` | `gemini-3.1-pro-preview` |
| `openai` | `gpt-5.4` |

The AI is always called with a structured JSON output schema (`RESPONSE_SCHEMA` in `AI_Client`) and a system instruction that enforces plain-English, non-technical responses aimed at store owners.

### Log Handler Support

The plugin detects which WooCommerce log handler is active and reads log content accordingly:

| Handler | Detection | Read method |
|---|---|---|
| FileV2 | Default (non-numeric, non-.log ID) | `FileController::get_file_by_id()` |
| Legacy | File ID ends in `.log` | Reads from `WC_LOG_DIR` |
| Database | Numeric file ID | Queries `{prefix}_woocommerce_log` |

### Permissions

A user can run log analysis if they have `manage_woocommerce` **and** `prompt_ai` capabilities, or if the *Allow Shop Managers* setting is enabled and they have `manage_woocommerce`. See `Plugin::current_user_can_analyze()`.

### React Frontend

The `src/analyze/` app is built with `@wordpress/scripts` and enqueued only on the WooCommerce status logs tab. It mounts into a `<div id="ai-log-analyzer-results">` injected via `admin_footer`. Communication with the backend uses two AJAX actions:

- `ai_analyze_log` — triggers analysis, returns structured JSON
- `ai_download_report` — generates and streams a self-contained HTML report

## Coding Standards

- **PHP**: WordPress Coding Standards (WPCS 3.x). Run `composer lint` before committing.
- **JS/CSS**: enforced via `@wordpress/scripts` ESLint and Stylelint configs.
- **Namespace**: `AI_Log_Analyzer\` (PSR-4, mapped to `includes/`).
- **Option key**: `ai_log_analyzer_settings` (constant `AI_LOG_ANALYZER_OPTION`).
- **Text domain**: `ai-log-analyzer`.

## Contributing

1. Fork the repository and create a feature branch from `main`.
2. Follow the coding standards above — CI will fail on lint errors.
3. Add or update tests for any changed behaviour.
4. Open a pull request with a clear description of what changed and why.

For bug reports, please include the WordPress version, WooCommerce version, PHP version, and any relevant log output.

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
