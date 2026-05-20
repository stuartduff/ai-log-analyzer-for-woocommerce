<?php
/**
 * Admin interface — settings page registration using the WordPress Settings API.
 *
 * @package AI_Log_Analyzer
 */

namespace AI_Log_Analyzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the WooCommerce submenu settings page and option registration.
 */
class Admin_Interface {

	/**
	 * Registers all WordPress hooks for the admin interface.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 100 );
	}

	/**
	 * Registers the option and Settings API sections/fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'ai_log_analyzer',
			AI_LOG_ANALYZER_OPTION,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'model_preference'    => 'anthropic',
					'temperature'         => 3,
					'max_file_size_mb'    => 10,
					'allow_shop_managers' => false,
				),
			)
		);

		// Section: Analysis.
		add_settings_section(
			'ai_log_analyzer_analysis',
			__( 'Analysis', 'ai-log-analyzer-for-woocommerce' ),
			'__return_false',
			'ai_log_analyzer'
		);

		add_settings_field(
			'model_preference',
			__( 'AI Model Preference', 'ai-log-analyzer-for-woocommerce' ),
			array( $this, 'render_model_preference_field' ),
			'ai_log_analyzer',
			'ai_log_analyzer_analysis'
		);

		add_settings_field(
			'temperature',
			__( 'Temperature (0–10)', 'ai-log-analyzer-for-woocommerce' ),
			array( $this, 'render_temperature_field' ),
			'ai_log_analyzer',
			'ai_log_analyzer_analysis'
		);

		// Section: Limits & Permissions.
		add_settings_section(
			'ai_log_analyzer_limits',
			__( 'Limits &amp; Permissions', 'ai-log-analyzer-for-woocommerce' ),
			'__return_false',
			'ai_log_analyzer'
		);

		add_settings_field(
			'max_file_size_mb',
			__( 'Maximum File Size (MB)', 'ai-log-analyzer-for-woocommerce' ),
			array( $this, 'render_max_file_size_field' ),
			'ai_log_analyzer',
			'ai_log_analyzer_limits'
		);

		add_settings_field(
			'allow_shop_managers',
			__( 'Allow Shop Managers', 'ai-log-analyzer-for-woocommerce' ),
			array( $this, 'render_allow_shop_managers_field' ),
			'ai_log_analyzer',
			'ai_log_analyzer_limits'
		);
	}

	/**
	 * Sanitizes the settings array before it is saved.
	 *
	 * @param mixed $input Raw input from the settings form.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( mixed $input ): array {
		$current   = (array) get_option( AI_LOG_ANALYZER_OPTION, array() );
		$sanitized = $current;

		if ( isset( $input['model_preference'] ) ) {
			$allowed = array_keys( $this->get_available_providers() );
			$value   = sanitize_text_field( wp_unslash( $input['model_preference'] ) );
			if ( in_array( $value, $allowed, true ) ) {
				$sanitized['model_preference'] = $value;
			}
		}

		if ( isset( $input['temperature'] ) ) {
			$sanitized['temperature'] = min( 10, max( 0, absint( $input['temperature'] ) ) );
		}

		if ( isset( $input['max_file_size_mb'] ) ) {
			$sanitized['max_file_size_mb'] = min( 50, max( 1, absint( $input['max_file_size_mb'] ) ) );
		}

		// Checkbox is absent from POST when unchecked.
		$sanitized['allow_shop_managers'] = isset( $input['allow_shop_managers'] );

		return $sanitized;
	}

	/**
	 * Renders the AI Model Preference radio buttons.
	 *
	 * Only providers that are both active and have an API key configured in
	 * the WordPress Connectors settings are shown. Falls back to all three
	 * choices when the Connectors API is unavailable (pre-WP 7.0).
	 *
	 * @return void
	 */
	public function render_model_preference_field(): void {
		$options = (array) get_option( AI_LOG_ANALYZER_OPTION, array() );
		$current = $options['model_preference'] ?? 'anthropic';
		$choices = $this->get_available_providers();
		$name    = AI_LOG_ANALYZER_OPTION . '[model_preference]';

		foreach ( $choices as $value => $label ) {
			printf(
				'<label style="margin-right:1.5em"><input type="radio" name="%s" value="%s"%s> %s</label>',
				esc_attr( $name ),
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label )
			);
		}

		if ( function_exists( 'wp_get_connectors' ) ) {
			printf(
				'<p class="description">%s <a href="%s">%s</a></p>',
				esc_html__( 'Only connected providers are shown.', 'ai-log-analyzer-for-woocommerce' ),
				esc_url( admin_url( 'options-connectors.php' ) ),
				esc_html__( 'Manage connectors', 'ai-log-analyzer-for-woocommerce' )
			);
		}
	}

	/**
	 * Returns AI providers that are active and have an API key configured.
	 *
	 * Uses the WordPress Connectors API (WP 7.0+) when available. Falls back
	 * to all three hardcoded choices on older WordPress installs.
	 *
	 * @return array<string, string> Slug-to-label map of available providers.
	 */
	private function get_available_providers(): array {
		$all = array(
			'anthropic' => __( 'Anthropic', 'ai-log-analyzer-for-woocommerce' ),
			'google'    => __( 'Google', 'ai-log-analyzer-for-woocommerce' ),
			'openai'    => __( 'OpenAI', 'ai-log-analyzer-for-woocommerce' ),
		);

		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return $all;
		}

		$available = array();
		foreach ( wp_get_connectors() as $id => $connector ) {
			if ( 'ai_provider' !== $connector['type'] ) {
				continue;
			}
			if ( ! isset( $all[ $id ] ) ) {
				continue;
			}
			if ( ! call_user_func( $connector['plugin']['is_active'] ) ) {
				continue;
			}
			if ( $this->connector_has_api_key( $connector['authentication'] ) ) {
				$available[ $id ] = $all[ $id ];
			}
		}

		return ! empty( $available ) ? $available : $all;
	}

	/**
	 * Checks whether a connector's authentication data resolves to a non-empty key.
	 *
	 * Mirrors the precedence order used by WordPress core: env var → constant → DB.
	 *
	 * @param array $auth Connector authentication array from wp_get_connectors().
	 * @return bool
	 */
	private function connector_has_api_key( array $auth ): bool {
		if ( 'api_key' !== ( $auth['method'] ?? '' ) ) {
			return true;
		}

		if ( ! empty( $auth['env_var_name'] ) ) {
			$env = getenv( $auth['env_var_name'] );
			if ( false !== $env && '' !== $env ) {
				return true;
			}
		}

		if ( ! empty( $auth['constant_name'] ) && defined( $auth['constant_name'] ) ) {
			$value = constant( $auth['constant_name'] );
			if ( is_string( $value ) && '' !== $value ) {
				return true;
			}
		}

		if ( ! empty( $auth['setting_name'] ) ) {
			$db_value = get_option( $auth['setting_name'], '' );
			if ( '' !== $db_value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Renders the Temperature number input.
	 *
	 * @return void
	 */
	public function render_temperature_field(): void {
		$options = (array) get_option( AI_LOG_ANALYZER_OPTION, array() );
		$value   = $options['temperature'] ?? 3;
		$name    = AI_LOG_ANALYZER_OPTION . '[temperature]';

		printf(
			'<input type="number" name="%s" value="%s" min="0" max="10" step="1" class="small-text">',
			esc_attr( $name ),
			esc_attr( (string) $value )
		);
		echo '<p class="description">' . esc_html__( 'Stored as an integer and divided by 10 when passed to the AI (e.g. 3 → 0.3).', 'ai-log-analyzer-for-woocommerce' ) . '</p>';
	}

	/**
	 * Renders the Maximum File Size number input.
	 *
	 * @return void
	 */
	public function render_max_file_size_field(): void {
		$options = (array) get_option( AI_LOG_ANALYZER_OPTION, array() );
		$value   = $options['max_file_size_mb'] ?? 10;
		$name    = AI_LOG_ANALYZER_OPTION . '[max_file_size_mb]';

		printf(
			'<input type="number" name="%s" value="%s" min="1" max="50" step="1" class="small-text">',
			esc_attr( $name ),
			esc_attr( (string) $value )
		);
		echo '<p class="description">' . esc_html__( 'Log files larger than this limit will be truncated from the start (most recent content kept).', 'ai-log-analyzer-for-woocommerce' ) . '</p>';
	}

	/**
	 * Renders the Allow Shop Managers checkbox.
	 *
	 * @return void
	 */
	public function render_allow_shop_managers_field(): void {
		$options = (array) get_option( AI_LOG_ANALYZER_OPTION, array() );
		$checked = ! empty( $options['allow_shop_managers'] );
		$name    = AI_LOG_ANALYZER_OPTION . '[allow_shop_managers]';

		printf(
			'<label><input type="checkbox" name="%s" value="1"%s> %s</label>',
			esc_attr( $name ),
			checked( $checked, true, false ),
			esc_html__( 'Allow users with the Shop Manager role to run log analysis (in addition to admins).', 'ai-log-analyzer-for-woocommerce' )
		);
	}

	/**
	 * Registers the AI Log Analyzer submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'AI Log Analyzer', 'ai-log-analyzer-for-woocommerce' ),
			__( 'AI Log Analyzer', 'ai-log-analyzer-for-woocommerce' ),
			'manage_woocommerce',
			'ai-log-analyzer',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Renders the settings page template.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		require_once AI_LOG_ANALYZER_PATH . 'admin/views/settings-page.php';
	}
}
