<?php
/**
 * Analysis engine — pre-processes log content and orchestrates AI client calls.
 *
 * @package AILWC_Log_Analyzer
 */

namespace AILWC_Log_Analyzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates the end-to-end analysis flow.
 *
 * Responsibilities:
 *  1. Enrich the site context before the AI call:
 *       - Identify which active plugins appear in the log.
 *       - Classify any known high-signal error patterns.
 *  2. Delegate to AI_Client::analyze_log() with the enriched context.
 *  3. Return the raw JSON string (or WP_Error) to the caller.
 */
class Analysis_Engine {

	/**
	 * AI client instance.
	 *
	 * @var AI_Client
	 */
	private AI_Client $ai_client;

	/**
	 * Known error patterns mapped to a human-readable classification label.
	 *
	 * These pre-classifications are injected into the context to give the AI
	 * a head start on categorising the issue.
	 *
	 * @var array<string, string>
	 */
	private const ERROR_PATTERNS = array(
		'/PHP Fatal error/i'          => 'php_fatal',
		'/PHP Warning/i'              => 'php_warning',
		'/PHP Notice/i'               => 'php_notice',
		'/WordPress database error/i' => 'database_error',
		'/cURL error/i'               => 'network_error',
		'/memory.*exhausted/i'        => 'memory_limit',
		'/Maximum execution time/i'   => 'timeout',
		'/Call to undefined/i'        => 'missing_function',
		'/Class .* not found/i'       => 'missing_class',
		'/Unable to locate package/i' => 'missing_package',
		'/Could not connect to/i'     => 'connection_error',
		'/Permission denied/i'        => 'file_permission',
	);

	/**
	 * Constructor.
	 *
	 * @param AI_Client $ai_client Injected AI client wrapper.
	 */
	public function __construct( AI_Client $ai_client ) {
		$this->ai_client = $ai_client;
	}

	/**
	 * Runs the full analysis pipeline.
	 *
	 * Pre-processes the log to enrich context, then calls the AI client.
	 * After the AI responds, the contact_url is overridden with the authoritative
	 * support URL sourced from the identified plugin's own headers.
	 *
	 * @param string $log_content Sanitised log content.
	 * @param array  $context     Base context from Log_Integration::gather_context().
	 * @return string|\WP_Error Raw JSON string on success, WP_Error on failure.
	 */
	public function analyze( string $log_content, array $context ): string|\WP_Error {
		$plugin_files         = $this->identify_plugin_files_in_log( $log_content );
		$plugins_support_data = $this->get_plugins_support_data( $plugin_files );
		$enriched_context     = $this->enrich_context( $log_content, $context, $plugins_support_data );

		$result = $this->ai_client->analyze_log( $log_content, $enriched_context );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->inject_support_url( $result, $plugins_support_data );
	}

	// -------------------------------------------------------------------------
	// Context enrichment
	// -------------------------------------------------------------------------

	/**
	 * Enriches the base context with plugin identification and error pattern data.
	 *
	 * @param string $log_content          Sanitised log content.
	 * @param array  $context              Base context array.
	 * @param array  $plugins_support_data Structured plugin header data from get_plugins_support_data().
	 * @return array Enriched context array.
	 */
	private function enrich_context( string $log_content, array $context, array $plugins_support_data ): array {
		if ( ! empty( $plugins_support_data ) ) {
			$context['identified_plugins'] = array_column( $plugins_support_data, 'name' );
		}

		$patterns = $this->classify_error_patterns( $log_content );

		if ( ! empty( $patterns ) ) {
			$context['error_patterns'] = array_unique( $patterns );
		}

		return $context;
	}

	// -------------------------------------------------------------------------
	// Plugin identification
	// -------------------------------------------------------------------------

	/**
	 * Returns the plugin file paths of active plugins referenced in the log content.
	 *
	 * Scans for file paths of the form `plugins/{slug}/` to find plugins whose
	 * code is referenced in the log. The analyzer's own slug is excluded to
	 * avoid self-referential noise.
	 *
	 * Returns full plugin file paths (e.g. 'woo-stripe-payment/stripe-payments.php')
	 * so callers can pass them directly to get_plugin_data().
	 *
	 * @param string $log_content Sanitised log content.
	 * @return string[] List of identified plugin file paths.
	 */
	private function identify_plugin_files_in_log( string $log_content ): array {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$identified     = array();

		foreach ( $active_plugins as $plugin_path ) {
			$slug = dirname( $plugin_path );

			// Skip self — its paths will always appear in stack traces.
			if ( 'wc-ai-log-analyzer' === $slug ) {
				continue;
			}

			if ( str_contains( $log_content, 'plugins/' . $slug . '/' ) ) {
				$identified[] = $plugin_path;
			}
		}

		return array_unique( $identified );
	}

	/**
	 * Reads plugin header data for a list of plugin file paths.
	 *
	 * Calls get_plugin_data() for each identified plugin to retrieve authoritative
	 * metadata (name, Plugin URI, author, Author URI) from the plugin's file header.
	 *
	 * @param string[] $plugin_files Plugin file paths relative to the plugins directory.
	 * @return array[] List of plugin data arrays, each containing: slug, name, plugin_uri, author, author_uri.
	 */
	private function get_plugins_support_data( array $plugin_files ): array {
		if ( empty( $plugin_files ) ) {
			return array();
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins_dir = trailingslashit( dirname( dirname( AILWC_LOG_ANALYZER_FILE ) ) );
		$data        = array();

		foreach ( $plugin_files as $plugin_file ) {
			$full_path   = $plugins_dir . $plugin_file;
			$plugin_data = get_plugin_data( $full_path, false, false );
			$slug        = dirname( $plugin_file );

			$data[] = array(
				'slug'       => $slug,
				'name'       => $plugin_data['Name'] ?? $slug,
				'plugin_uri' => $plugin_data['PluginURI'] ?? '',
				'author'     => $plugin_data['Author'] ?? '',
				'author_uri' => $plugin_data['AuthorURI'] ?? '',
			);
		}

		return $data;
	}

	/**
	 * Resolves the best support URL from the identified plugins' header data.
	 *
	 * Priority order for each plugin:
	 *  1. PluginURI — the plugin author's own support/docs page.
	 *  2. AuthorURI — the author's website as a fallback.
	 *
	 * Falls back to the WooCommerce support page when no URL is available.
	 *
	 * @param array[] $plugins_support_data Plugin header data from get_plugins_support_data().
	 * @return string Resolved support URL.
	 */
	private function resolve_support_url( array $plugins_support_data ): string {
		foreach ( $plugins_support_data as $plugin ) {
			if ( ! empty( $plugin['plugin_uri'] ) ) {
				return $plugin['plugin_uri'];
			}

			if ( ! empty( $plugin['author_uri'] ) ) {
				return $plugin['author_uri'];
			}
		}

		return 'https://woocommerce.com/my-account/create-a-ticket/';
	}

	/**
	 * Injects the authoritative contact_url into the AI JSON response.
	 *
	 * The AI no longer generates contact_url — PHP sets it here using plugin
	 * header data. Falls back to the WooCommerce support page when no third-party
	 * plugin was identified in the log.
	 *
	 * @param string  $json                Raw JSON string from the AI client.
	 * @param array[] $plugins_support_data Plugin header data from get_plugins_support_data().
	 * @return string JSON string with contact_url set.
	 */
	private function inject_support_url( string $json, array $plugins_support_data ): string {
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return $json;
		}

		$decoded['contact_url'] = $this->resolve_support_url( $plugins_support_data );

		$encoded = wp_json_encode( $decoded );

		return false !== $encoded ? $encoded : $json;
	}

	// -------------------------------------------------------------------------
	// Error pattern classification
	// -------------------------------------------------------------------------

	/**
	 * Identifies known error pattern types in the log content.
	 *
	 * Returns a de-duplicated list of pattern labels (e.g. 'php_fatal',
	 * 'database_error') which are added to the AI context as hints.
	 *
	 * @param string $log_content Sanitised log content.
	 * @return string[] List of matched pattern labels.
	 */
	private function classify_error_patterns( string $log_content ): array {
		$matched = array();

		foreach ( self::ERROR_PATTERNS as $pattern => $label ) {
			if ( preg_match( $pattern, $log_content ) ) {
				$matched[] = $label;
			}
		}

		return $matched;
	}
}
