<?php
/**
 * WooCommerce log system integration.
 *
 * Handles script enqueuing, mount-point injection, AJAX handlers, and
 * log content extraction for all three WC log handler types.
 *
 * @package AILWC_Log_Analyzer
 */

namespace AILWC_Log_Analyzer;

use Automattic\WooCommerce\Internal\Admin\Logging\FileV2\FileController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates the AI Log Analyzer with the WooCommerce log management UI.
 */
class Log_Integration {

	/**
	 * Analysis engine instance.
	 *
	 * @var Analysis_Engine
	 */
	private Analysis_Engine $analysis_engine;

	/**
	 * Log parser instance.
	 *
	 * @var Log_Parser
	 */
	private Log_Parser $log_parser;

	/**
	 * AI client instance (used directly by AJAX handlers).
	 *
	 * @var AI_Client
	 */
	private AI_Client $ai_client;

	/**
	 * Constructor.
	 *
	 * @param Analysis_Engine $analysis_engine Injected analysis engine.
	 * @param Log_Parser      $log_parser      Injected log parser.
	 * @param AI_Client       $ai_client       Injected AI client.
	 */
	public function __construct( Analysis_Engine $analysis_engine, Log_Parser $log_parser, AI_Client $ai_client ) {
		$this->analysis_engine = $analysis_engine;
		$this->log_parser      = $log_parser;
		$this->ai_client       = $ai_client;
	}

	/**
	 * Registers all WordPress hooks for log integration.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_analyze_scripts' ) );
		add_action( 'admin_footer', array( $this, 'output_analysis_results_mount' ) );

		// wc_logs_render_page fires only for custom (non-built-in) handlers.
		// For FileV2, Legacy, and DB, we fall back to admin_footer above.
		add_action( 'wc_logs_render_page', array( $this, 'render_analysis_results_mount' ) );

		add_action( 'wp_ajax_ailwc_analyze_log', array( $this, 'handle_analyze_log' ) );
		add_action( 'wp_ajax_nopriv_ailwc_analyze_log', array( $this, 'handle_nopriv' ) );
		add_action( 'wp_ajax_ailwc_download_report', array( $this, 'handle_download_report' ) );
		add_action( 'wp_ajax_nopriv_ailwc_download_report', array( $this, 'handle_nopriv' ) );
	}

	// -------------------------------------------------------------------------
	// Script enqueuing & mount points
	// -------------------------------------------------------------------------

	/**
	 * Enqueues the analyze React app on the WooCommerce status page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_analyze_scripts( string $hook ): void {
		if ( 'woocommerce_page_wc-status' !== $hook ) {
			return;
		}

		if ( 'logs' !== filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS ) ) {
			return;
		}

		$asset_file = AILWC_LOG_ANALYZER_PATH . 'build/analyze.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'ailwc-log-analyzer-analyze',
			AILWC_LOG_ANALYZER_URL . 'build/analyze.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( AILWC_LOG_ANALYZER_PATH . 'build/analyze.css' ) ) {
			wp_enqueue_style(
				'ailwc-log-analyzer-analyze',
				AILWC_LOG_ANALYZER_URL . 'build/analyze.css',
				array(),
				$asset['version']
			);
		}

		wp_localize_script(
			'ailwc-log-analyzer-analyze',
			'ailwcLogAnalyzer',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'ailwc_analyze_log' ),
				'hasConnector' => function_exists( 'wp_ai_client_prompt' ),
				'i18n'         => array(
					'analyzeButton' => __( 'Analyse with AI', 'ai-log-analyzer-for-woocommerce' ),
					'analyzing'     => __( 'Analysing log…', 'ai-log-analyzer-for-woocommerce' ),
					'error'         => __( 'An error occurred during analysis. Please try again.', 'ai-log-analyzer-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Outputs the analysis-results mount-point div in the admin footer when on
	 * the WC Logs tab. This handles FileV2, Legacy, and DB handlers which do
	 * not trigger the wc_logs_render_page action.
	 *
	 * @return void
	 */
	public function output_analysis_results_mount(): void {
		$screen = get_current_screen();

		if ( null === $screen || 'woocommerce_page_wc-status' !== $screen->id ) {
			return;
		}

		if ( 'logs' !== filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS ) ) {
			return;
		}

		require_once AILWC_LOG_ANALYZER_PATH . 'admin/views/analysis-results.php';
	}

	/**
	 * Outputs the analysis-results mount-point via the wc_logs_render_page action.
	 * This handles any custom (non-built-in) log handlers.
	 *
	 * @param string $handler The active log handler class name.
	 * @return void
	 */
	public function render_analysis_results_mount( string $handler ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $handler reserved for future custom-handler targeting.
		require_once AILWC_LOG_ANALYZER_PATH . 'admin/views/analysis-results.php';
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler for log analysis requests.
	 *
	 * @return void
	 */
	public function handle_analyze_log(): void {
		check_ajax_referer( 'ailwc_analyze_log', 'nonce' );

		if ( ! Plugin::current_user_can_analyze() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to analyse logs.', 'ai-log-analyzer-for-woocommerce' ) ),
				403
			);
		}

		$file_id = sanitize_text_field( wp_unslash( $_POST['file_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- default '' handles missing key.

		if ( empty( $file_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No log file ID provided.', 'ai-log-analyzer-for-woocommerce' ) ) );
		}

		$log_content = $this->extract_log_content( $file_id );

		if ( is_wp_error( $log_content ) ) {
			wp_send_json_error( array( 'message' => $log_content->get_error_message() ) );
		}

		$log_content = $this->log_parser->sanitize_log_content( $log_content );
		$context     = $this->gather_context();

		$result = $this->analysis_engine->analyze( $log_content, $context );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$decoded = json_decode( $result, true );

		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'The AI client returned an invalid response.', 'ai-log-analyzer-for-woocommerce' ) ) );
		}

		$required_keys = array( 'summary', 'severity', 'cause', 'fix_steps', 'contact', 'contact_url' );

		foreach ( $required_keys as $key ) {
			if ( ! array_key_exists( $key, $decoded ) ) {
				wp_send_json_error(
					array(
						/* translators: %s: Missing JSON key name. */
						'message' => sprintf( __( 'Incomplete AI response — missing key: %s.', 'ai-log-analyzer-for-woocommerce' ), esc_html( $key ) ),
					)
				);
			}
		}

		wp_send_json_success( $decoded );
	}

	/**
	 * AJAX handler for HTML report download requests.
	 *
	 * @return void
	 */
	public function handle_download_report(): void {
		check_ajax_referer( 'ailwc_analyze_log', 'nonce' );

		if ( ! Plugin::current_user_can_analyze() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to download reports.', 'ai-log-analyzer-for-woocommerce' ) ),
				403
			);
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- JSON blob; individual fields are sanitized in generate_html_report() after decoding.
		$raw           = wp_unslash( $_POST['analysis_data'] ?? '{}' );
		$analysis_data = json_decode( $raw, true );

		if ( ! is_array( $analysis_data ) || empty( $analysis_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid report data.', 'ai-log-analyzer-for-woocommerce' ) ) );
		}

		$report = $this->generate_html_report( $analysis_data );

		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="wc-log-analysis-report.html"' );
		header( 'Content-Length: ' . strlen( $report ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- full HTML document output, not a fragment.
		echo $report;
		exit;
	}

	/**
	 * Rejects unauthenticated AJAX requests with a 401.
	 *
	 * @return void
	 */
	public function handle_nopriv(): void {
		wp_send_json_error(
			array( 'message' => __( 'You must be logged in to perform this action.', 'ai-log-analyzer-for-woocommerce' ) ),
			401
		);
	}

	// -------------------------------------------------------------------------
	// Log content extraction
	// -------------------------------------------------------------------------

	/**
	 * Extracts log content for the given file identifier.
	 *
	 * Detects the handler type from the file_id format:
	 *  - Numeric → Database handler (source = file_id).
	 *  - Ends in .log → Legacy File handler.
	 *  - Otherwise → FileV2 handler.
	 *
	 * @param string $file_id Log file identifier.
	 * @return string|\WP_Error Log content string on success, WP_Error on failure.
	 */
	public function extract_log_content( string $file_id ): string|\WP_Error {
		$handler = $this->detect_handler_type( $file_id );

		switch ( $handler ) {
			case 'db':
				return $this->extract_db_log_content( $file_id );
			case 'legacy':
				return $this->extract_legacy_log_content( $file_id );
			default:
				return $this->extract_filev2_log_content( $file_id );
		}
	}

	/**
	 * Detects the log handler type from the file_id format.
	 *
	 * @param string $file_id Log file identifier.
	 * @return string 'db', 'legacy', or 'filev2'.
	 */
	private function detect_handler_type( string $file_id ): string {
		// Numeric IDs → Database handler (source field used as identifier).
		if ( ctype_digit( $file_id ) ) {
			return 'db';
		}

		// .log extension → Legacy File handler.
		if ( str_ends_with( strtolower( $file_id ), '.log' ) ) {
			return 'legacy';
		}

		return 'filev2';
	}

	/**
	 * Extracts log content from a FileV2 log file.
	 *
	 * Uses FileController::get_file_by_id() to resolve the path and validates
	 * it is within WC_LOG_DIR before reading.
	 *
	 * @param string $file_id FileV2 file identifier.
	 * @return string|\WP_Error
	 */
	private function extract_filev2_log_content( string $file_id ): string|\WP_Error {
		if ( ! class_exists( FileController::class ) ) {
			return new \WP_Error(
				'wc_not_available',
				__( 'WooCommerce FileV2 log handler is not available.', 'ai-log-analyzer-for-woocommerce' )
			);
		}

		$file_controller = wc_get_container()->get( FileController::class );
		$file            = $file_controller->get_file_by_id( $file_id );

		if ( is_wp_error( $file ) ) {
			return new \WP_Error( 'log_not_found', __( 'Log file not found.', 'ai-log-analyzer-for-woocommerce' ) );
		}

		$path = $file->get_path();

		return $this->read_log_file( $path );
	}

	/**
	 * Extracts log content from a Legacy File handler log.
	 *
	 * The file_id is the full log filename (e.g. woocommerce-2025-01-15-abc.log).
	 * Path is resolved within WC_LOG_DIR.
	 *
	 * @param string $file_id Full log filename.
	 * @return string|\WP_Error
	 */
	private function extract_legacy_log_content( string $file_id ): string|\WP_Error {
		// Sanitize to prevent path traversal.
		$safe_name = sanitize_file_name( $file_id );

		if ( empty( $safe_name ) ) {
			return new \WP_Error( 'invalid_file_id', __( 'Invalid log file identifier.', 'ai-log-analyzer-for-woocommerce' ) );
		}

		$path = trailingslashit( WC_LOG_DIR ) . $safe_name;

		return $this->read_log_file( $path );
	}

	/**
	 * Extracts log content from the WooCommerce database log handler.
	 *
	 * Queries the woocommerce_log table filtered by source, ordered most-recent first.
	 *
	 * @param string $file_id Log source identifier (maps to the `source` column).
	 * @return string|\WP_Error
	 */
	private function extract_db_log_content( string $file_id ): string|\WP_Error {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT timestamp, level, message, context FROM %i WHERE source = %s ORDER BY timestamp DESC LIMIT 1000',
				$wpdb->prefix . 'woocommerce_log',
				$file_id
			),
			ARRAY_A
		);

		if ( null === $rows ) {
			return new \WP_Error( 'db_error', __( 'Database query failed.', 'ai-log-analyzer-for-woocommerce' ) );
		}

		if ( empty( $rows ) ) {
			return new \WP_Error( 'log_not_found', __( 'No log entries found for this source.', 'ai-log-analyzer-for-woocommerce' ) );
		}

		$lines = array();

		foreach ( $rows as $row ) {
			$line = sprintf( '[%s] [%s] %s', $row['timestamp'], strtoupper( $row['level'] ), $row['message'] );

			if ( ! empty( $row['context'] ) ) {
				$line .= ' | Context: ' . $row['context'];
			}

			$lines[] = $line;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Reads a log file from disk after validating the path is within WC_LOG_DIR.
	 *
	 * Applies the max_file_size_mb setting, truncating from the start if needed
	 * (keeping the most recent tail).
	 *
	 * @param string $path Absolute path to the log file.
	 * @return string|\WP_Error
	 */
	private function read_log_file( string $path ): string|\WP_Error {
		// Resolve symlinks and validate the path is within WC_LOG_DIR.
		$real_path = realpath( $path );
		$real_dir  = realpath( WC_LOG_DIR );

		if ( false === $real_path || false === $real_dir ) {
			return new \WP_Error( 'log_not_found', __( 'Log file not found.', 'ai-log-analyzer-for-woocommerce' ) );
		}

		// str_starts_with path validation — the key security check.
		// trailingslashit ensures a path like /wc-logs-evil/ cannot pass a prefix check against /wc-logs.
		if ( ! str_starts_with( $real_path, trailingslashit( $real_dir ) ) ) {
			return new \WP_Error(
				'invalid_path',
				__( 'Log file path is outside the allowed log directory.', 'ai-log-analyzer-for-woocommerce' )
			);
		}

		if ( ! is_readable( $real_path ) ) {
			return new \WP_Error( 'log_not_readable', __( 'Log file is not readable.', 'ai-log-analyzer-for-woocommerce' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $real_path );

		if ( false === $content ) {
			return new \WP_Error( 'log_read_error', __( 'Could not read log file.', 'ai-log-analyzer-for-woocommerce' ) );
		}

		return $this->truncate_to_limit( $content );
	}

	/**
	 * Truncates log content to the configured max_file_size_mb limit.
	 *
	 * Content is truncated from the start so the most recent lines are preserved.
	 * The truncation point is adjusted to the next newline to avoid a partial line.
	 *
	 * @param string $content Raw log content.
	 * @return string Possibly-truncated content.
	 */
	private function truncate_to_limit( string $content ): string {
		$settings  = get_option( AILWC_LOG_ANALYZER_OPTION, array() );
		$max_mb    = max( 1, (int) ( $settings['max_file_size_mb'] ?? 10 ) );
		$max_bytes = $max_mb * 1024 * 1024;

		if ( strlen( $content ) <= $max_bytes ) {
			return $content;
		}

		// Keep the most recent tail.
		$content = substr( $content, -$max_bytes );

		// Advance past the first (partial) line.
		$newline_pos = strpos( $content, "\n" );

		if ( false !== $newline_pos ) {
			$content = substr( $content, $newline_pos + 1 );
		}

		return $content;
	}

	// -------------------------------------------------------------------------
	// Context gathering
	// -------------------------------------------------------------------------

	/**
	 * Gathers site context for inclusion in the AI prompt.
	 *
	 * @return array{wp_version: string, wc_version: string, active_plugins: string[], php_version: string, theme: string}
	 */
	private function gather_context(): array {
		return array(
			'wp_version'     => get_bloginfo( 'version' ),
			'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
			'active_plugins' => $this->get_active_plugin_names(),
			'php_version'    => PHP_VERSION,
			'theme'          => wp_get_theme()->get( 'Name' ),
		);
	}

	/**
	 * Returns a list of active plugin display names.
	 *
	 * @return string[]
	 */
	private function get_active_plugin_names(): array {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$all_plugins    = get_plugins();
		$names          = array();

		foreach ( $active_plugins as $plugin_file ) {
			$names[] = ! empty( $all_plugins[ $plugin_file ]['Name'] )
				? $all_plugins[ $plugin_file ]['Name']
				: $plugin_file;
		}

		return $names;
	}

	// -------------------------------------------------------------------------
	// HTML report generation
	// -------------------------------------------------------------------------

	/**
	 * Generates a self-contained HTML diagnostic report from analysis data.
	 *
	 * @param array $analysis_data Validated analysis result array.
	 * @return string Full HTML document.
	 */
	private function generate_html_report( array $analysis_data ): string {
		$severity       = sanitize_text_field( $analysis_data['severity'] ?? 'notice' );
		$summary        = sanitize_text_field( $analysis_data['summary'] ?? '' );
		$cause          = sanitize_text_field( $analysis_data['cause'] ?? '' );
		$fix_steps      = (array) ( $analysis_data['fix_steps'] ?? array() );
		$contact        = sanitize_text_field( $analysis_data['contact'] ?? '' );
		$contact_url    = esc_url_raw( $analysis_data['contact_url'] ?? '' );
		$generated_date = gmdate( 'Y-m-d H:i:s' ) . ' UTC';

		$severity_color = array(
			'critical' => '#cc1818',
			'warning'  => '#9e5900',
			'notice'   => '#1d6327',
		);

		$color          = $severity_color[ $severity ] ?? '#1d6327';
		$fix_steps_html = '';

		foreach ( $fix_steps as $step ) {
			$fix_steps_html .= '<li>' . esc_html( $step ) . '</li>';
		}

		$contact_link = '';
		if ( ! empty( $contact_url ) ) {
			$contact_link = ' &mdash; <a href="' . esc_attr( $contact_url ) . '">' . esc_html( $contact_url ) . '</a>';
		}

		return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . esc_html__( 'WC AI Log Analysis Report', 'ai-log-analyzer-for-woocommerce' ) . '</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; color: #1e1e1e; }
  h1 { font-size: 1.4rem; margin-bottom: 4px; }
  .meta { color: #666; font-size: 0.85rem; margin-bottom: 24px; }
  .severity { display: inline-block; padding: 2px 10px; border-radius: 3px; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: ' . esc_attr( $color ) . '; border: 1px solid ' . esc_attr( $color ) . '; margin-bottom: 16px; }
  h2 { font-size: 1rem; margin: 24px 0 8px; border-bottom: 1px solid #e0e0e0; padding-bottom: 6px; }
  ol { margin: 0; padding-left: 1.4em; }
  ol li { margin-bottom: 6px; }
  .contact { background: #f6f7f7; border-left: 4px solid #007cba; padding: 12px 16px; border-radius: 2px; }
  footer { margin-top: 40px; font-size: 0.8rem; color: #888; border-top: 1px solid #eee; padding-top: 12px; }
</style>
</head>
<body>
<h1>' . esc_html__( 'WooCommerce Log Analysis Report', 'ai-log-analyzer-for-woocommerce' ) . '</h1>
<p class="meta">' . esc_html__( 'Generated', 'ai-log-analyzer-for-woocommerce' ) . ': ' . esc_html( $generated_date ) . '</p>

<span class="severity">' . esc_html( $severity ) . '</span>

<h2>' . esc_html__( 'Summary', 'ai-log-analyzer-for-woocommerce' ) . '</h2>
<p>' . esc_html( $summary ) . '</p>

<h2>' . esc_html__( 'Root Cause', 'ai-log-analyzer-for-woocommerce' ) . '</h2>
<p>' . esc_html( $cause ) . '</p>

<h2>' . esc_html__( 'Steps to Fix', 'ai-log-analyzer-for-woocommerce' ) . '</h2>
<ol>' . $fix_steps_html . '</ol>

<h2>' . esc_html__( 'Support Contact', 'ai-log-analyzer-for-woocommerce' ) . '</h2>
<div class="contact">
  <p>' . esc_html( $contact ) . $contact_link . '</p>
</div>

<footer>' . esc_html__( 'Generated by AI Log Analyzer for WooCommerce', 'ai-log-analyzer-for-woocommerce' ) . '</footer>
</body>
</html>';
	}
}
