<?php
/**
 * Unit tests for Admin_Interface::sanitize_settings().
 *
 * @package AILWC_Log_Analyzer
 */

namespace AILWC_Log_Analyzer\Tests;

use AILWC_Log_Analyzer\Admin_Interface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AdminInterfaceTest extends TestCase {

	private Admin_Interface $admin;

	protected function setUp(): void {
		$this->admin = new Admin_Interface();
		// Ensure no stale current settings bleed between tests.
		unset( $GLOBALS['test_options'][ AILWC_LOG_ANALYZER_OPTION ] );
		$GLOBALS['test_connectors']     = array();
		$GLOBALS['test_active_plugins'] = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['test_options'][ AILWC_LOG_ANALYZER_OPTION ] );
		$GLOBALS['test_connectors']     = array();
		$GLOBALS['test_active_plugins'] = array();
	}

	// -------------------------------------------------------------------------
	// Return type
	// -------------------------------------------------------------------------

	public function test_sanitize_settings_always_returns_an_array(): void {
		$result = $this->admin->sanitize_settings( [] );
		$this->assertIsArray( $result );
	}

	public function test_sanitize_settings_returns_array_for_non_array_input(): void {
		$result = $this->admin->sanitize_settings( null );
		$this->assertIsArray( $result );
	}

	// -------------------------------------------------------------------------
	// model_preference
	// -------------------------------------------------------------------------

	/** @dataProvider valid_model_provider */
	public function test_sanitize_settings_accepts_valid_model_preference( string $model ): void {
		$result = $this->admin->sanitize_settings( [ 'model_preference' => $model ] );
		$this->assertSame( $model, $result['model_preference'] );
	}

	public static function valid_model_provider(): array {
		return [
			[ 'anthropic' ],
			[ 'google' ],
			[ 'openai' ],
		];
	}

	public function test_sanitize_settings_rejects_unknown_model_preference(): void {
		$result = $this->admin->sanitize_settings( [ 'model_preference' => 'unknown-ai' ] );
		$this->assertArrayNotHasKey( 'model_preference', $result );
	}

	public function test_sanitize_settings_preserves_current_model_when_invalid_value_submitted(): void {
		$GLOBALS['test_options'][ AILWC_LOG_ANALYZER_OPTION ] = [ 'model_preference' => 'google' ];
		$result = $this->admin->sanitize_settings( [ 'model_preference' => 'invalid' ] );
		$this->assertSame( 'google', $result['model_preference'] );
	}

	// -------------------------------------------------------------------------
	// temperature
	// -------------------------------------------------------------------------

	public function test_sanitize_settings_accepts_temperature_within_range(): void {
		$result = $this->admin->sanitize_settings( [ 'temperature' => '7' ] );
		$this->assertSame( 7, $result['temperature'] );
	}

	public function test_sanitize_settings_clamps_temperature_above_max_to_ten(): void {
		$result = $this->admin->sanitize_settings( [ 'temperature' => '15' ] );
		$this->assertSame( 10, $result['temperature'] );
	}

	public function test_sanitize_settings_converts_negative_temperature_to_absolute_value(): void {
		// absint() makes negative inputs positive; -5 → 5, which is within 0–10.
		$result = $this->admin->sanitize_settings( [ 'temperature' => '-5' ] );
		$this->assertSame( 5, $result['temperature'] );
	}

	public function test_sanitize_settings_accepts_temperature_boundary_zero(): void {
		$result = $this->admin->sanitize_settings( [ 'temperature' => '0' ] );
		$this->assertSame( 0, $result['temperature'] );
	}

	public function test_sanitize_settings_accepts_temperature_boundary_ten(): void {
		$result = $this->admin->sanitize_settings( [ 'temperature' => '10' ] );
		$this->assertSame( 10, $result['temperature'] );
	}

	// -------------------------------------------------------------------------
	// max_file_size_mb
	// -------------------------------------------------------------------------

	public function test_sanitize_settings_accepts_max_file_size_within_range(): void {
		$result = $this->admin->sanitize_settings( [ 'max_file_size_mb' => '25' ] );
		$this->assertSame( 25, $result['max_file_size_mb'] );
	}

	public function test_sanitize_settings_clamps_max_file_size_above_limit_to_fifty(): void {
		$result = $this->admin->sanitize_settings( [ 'max_file_size_mb' => '100' ] );
		$this->assertSame( 50, $result['max_file_size_mb'] );
	}

	public function test_sanitize_settings_clamps_max_file_size_below_minimum_to_one(): void {
		$result = $this->admin->sanitize_settings( [ 'max_file_size_mb' => '0' ] );
		$this->assertSame( 1, $result['max_file_size_mb'] );
	}

	public function test_sanitize_settings_accepts_max_file_size_boundary_one(): void {
		$result = $this->admin->sanitize_settings( [ 'max_file_size_mb' => '1' ] );
		$this->assertSame( 1, $result['max_file_size_mb'] );
	}

	public function test_sanitize_settings_accepts_max_file_size_boundary_fifty(): void {
		$result = $this->admin->sanitize_settings( [ 'max_file_size_mb' => '50' ] );
		$this->assertSame( 50, $result['max_file_size_mb'] );
	}

	// -------------------------------------------------------------------------
	// allow_shop_managers
	// -------------------------------------------------------------------------

	public function test_sanitize_settings_sets_allow_shop_managers_true_when_key_present(): void {
		$result = $this->admin->sanitize_settings( [ 'allow_shop_managers' => '1' ] );
		$this->assertTrue( $result['allow_shop_managers'] );
	}

	public function test_sanitize_settings_sets_allow_shop_managers_false_when_key_absent(): void {
		$result = $this->admin->sanitize_settings( [] );
		$this->assertFalse( $result['allow_shop_managers'] );
	}

	public function test_sanitize_settings_allow_shop_managers_is_always_present_in_result(): void {
		$result = $this->admin->sanitize_settings( [] );
		$this->assertArrayHasKey( 'allow_shop_managers', $result );
	}

	// -------------------------------------------------------------------------
	// get_available_providers
	// -------------------------------------------------------------------------

	private function call_private( string $method, array $args = [] ): mixed {
		$ref = new ReflectionMethod( $this->admin, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $this->admin, $args );
	}

	public function test_get_available_providers_returns_empty_when_connectors_api_present_but_none_connected(): void {
		// wp_get_connectors() is stubbed and returns no connectors.
		$result = $this->call_private( 'get_available_providers' );

		$this->assertSame( array(), $result );
	}

	public function test_get_available_providers_includes_only_active_plugin_connectors_without_reading_api_key_option(): void {
		$GLOBALS['test_connectors']     = array(
			'anthropic' => array(
				'type'           => 'ai_provider',
				'authentication' => array(
					'method'       => 'api_key',
					'setting_name' => 'connectors_ai_anthropic_api_key',
				),
				'plugin'         => array( 'file' => 'ai-provider-for-anthropic/plugin.php' ),
			),
			'google'    => array(
				'type'           => 'ai_provider',
				'authentication' => array(
					'method'       => 'api_key',
					'setting_name' => 'connectors_ai_google_api_key',
				),
				'plugin'         => array( 'file' => 'ai-provider-for-google/plugin.php' ),
			),
		);
		$GLOBALS['test_active_plugins'] = array( 'ai-provider-for-anthropic/plugin.php' );

		$result = $this->call_private( 'get_available_providers' );

		$this->assertSame( array( 'anthropic' => 'Anthropic' ), $result );
	}

	public function test_get_available_providers_skips_connector_without_plugin_file(): void {
		$GLOBALS['test_connectors'] = array(
			'openai' => array(
				'type'           => 'ai_provider',
				'authentication' => array( 'method' => 'api_key' ),
			),
		);

		$result = $this->call_private( 'get_available_providers' );

		$this->assertSame( array(), $result );
	}

	public function test_get_available_providers_ignores_unknown_or_non_ai_connectors(): void {
		$GLOBALS['test_connectors']     = array(
			'custom_ai' => array(
				'type'   => 'ai_provider',
				'plugin' => array( 'file' => 'custom-ai/plugin.php' ),
			),
			'openai'    => array(
				'type'   => 'payment_gateway',
				'plugin' => array( 'file' => 'some-gateway/plugin.php' ),
			),
		);
		$GLOBALS['test_active_plugins'] = array( 'custom-ai/plugin.php', 'some-gateway/plugin.php' );

		$result = $this->call_private( 'get_available_providers' );

		$this->assertSame( array(), $result );
	}

	public function test_get_known_providers_returns_all_three(): void {
		$result = $this->call_private( 'get_known_providers' );

		$this->assertSame(
			array(
				'anthropic' => 'Anthropic',
				'google'    => 'Google',
				'openai'    => 'OpenAI',
			),
			$result
		);
	}
}
