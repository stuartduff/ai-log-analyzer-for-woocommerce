<?php
/**
 * AI client wrapper around wp_ai_client_prompt().
 *
 * @package AILWC_Log_Analyzer
 */

namespace AILWC_Log_Analyzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the WordPress AI Client (wp_ai_client_prompt).
 *
 * Reads model_preference and temperature from plugin settings, builds the
 * prompt and JSON output schema, and delegates execution to the WP 7.0
 * AI Client API.
 */
class AI_Client {

	/**
	 * Map of setting slugs to concrete model identifiers.
	 *
	 * Each key is the `model_preference` option value; the value is the model
	 * ID passed to using_model_preference(). The AI Client will fall back to
	 * any available model when the preferred one is unavailable.
	 *
	 * @var array<string, string>
	 */
	private const MODEL_MAP = array(
		'anthropic' => 'claude-sonnet-4-6',
		'google'    => 'gemini-3.1-pro-preview',
		'openai'    => 'gpt-5.4',
	);

	/**
	 * JSON output schema for the structured AI response.
	 *
	 * The contact_url field is intentionally excluded — it is set authoritatively
	 * by Analysis_Engine::inject_support_url() using plugin header data after the
	 * AI responds, so the AI is not asked to guess URLs.
	 *
	 * @var array<string, mixed>
	 */
	private const RESPONSE_SCHEMA = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'properties'           => array(
			'severity'  => array(
				'type' => 'string',
				'enum' => array( 'critical', 'warning', 'notice' ),
			),
			'summary'   => array( 'type' => 'string' ),
			'cause'     => array( 'type' => 'string' ),
			'fix_steps' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'contact'   => array( 'type' => 'string' ),
		),
		'required'             => array( 'severity', 'summary', 'cause', 'fix_steps', 'contact' ),
	);

	/**
	 * Analyses log content using the WordPress AI Client.
	 *
	 * Reads plugin settings to select the preferred model and temperature, then
	 * calls wp_ai_client_prompt() with a structured JSON schema. Returns the
	 * raw JSON string returned by the AI, or a WP_Error on failure.
	 *
	 * @param string $log_content Sanitised log content.
	 * @param array  $context     Site context (wp_version, wc_version, active_plugins, etc.).
	 * @return string|\WP_Error Raw JSON string on success, WP_Error on failure.
	 */
	public function analyze_log( string $log_content, array $context ): string|\WP_Error {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new \WP_Error(
				'no_ai_client',
				__( 'WordPress AI Client is not available. Please upgrade to WordPress 7.0.', 'ai-log-analyzer-for-woocommerce' )
			);
		}

		$settings         = get_option( AILWC_LOG_ANALYZER_OPTION, array() );
		$model_preference = $settings['model_preference'] ?? 'anthropic';
		// Settings store temperature as 0–10 integer; API expects 0.0–1.0 float.
		$temperature = ( (int) ( $settings['temperature'] ?? 3 ) ) / 10;

		$preferred_model = self::MODEL_MAP[ $model_preference ] ?? self::MODEL_MAP['anthropic'];

		return wp_ai_client_prompt( $this->build_prompt( $log_content, $context ) )
			->using_system_instruction( $this->build_system_instruction() )
			->using_temperature( $temperature )
			->using_model_preference( $preferred_model )
			->as_json_response( self::RESPONSE_SCHEMA )
			->generate_text();
	}

	/**
	 * Builds the system instruction that governs how the AI responds.
	 *
	 * @return string
	 */
	private function build_system_instruction(): string {
		return implode(
			' ',
			array(
				'You are a WooCommerce support specialist helping a store owner understand their error logs.',
				'The user is NOT a developer — use plain, reassuring English and focus on actionable steps.',
				'Avoid stack traces, class names, and code snippets in your response.',
				'For the contact field: write a plain-English sentence describing who the store owner should contact for support,',
				'mentioning the plugin or service name where relevant (e.g. "Contact the Payment Plugins for Stripe support team for help with this issue.").',
				'Do not include URLs in the contact field — a support link will be provided separately.',
			)
		);
	}

	/**
	 * Builds the user prompt from the log content and site context.
	 *
	 * @param string $log_content Sanitised log content.
	 * @param array  $context     Site context array.
	 * @return string
	 */
	private function build_prompt( string $log_content, array $context ): string {
		$lines = array(
			'Analyse the WooCommerce log below and return a JSON object.',
			'',
			'Site context:',
			'- WordPress: ' . ( $context['wp_version'] ?? 'unknown' ),
			'- WooCommerce: ' . ( $context['wc_version'] ?? 'unknown' ),
			'- PHP: ' . ( $context['php_version'] ?? 'unknown' ),
			'- Theme: ' . ( $context['theme'] ?? 'unknown' ),
		);

		if ( ! empty( $context['identified_plugins'] ) ) {
			$lines[] = '- Plugins likely causing this issue: ' . implode( ', ', $context['identified_plugins'] );
		}

		if ( ! empty( $context['active_plugins'] ) ) {
			$lines[] = '- All active plugins: ' . implode( ', ', $context['active_plugins'] );
		}

		if ( ! empty( $context['error_patterns'] ) ) {
			$lines[] = '- Detected error types: ' . implode( ', ', $context['error_patterns'] );
		}

		$lines[] = '';
		$lines[] = 'Log:';
		$lines[] = $log_content;

		return implode( "\n", $lines );
	}
}
