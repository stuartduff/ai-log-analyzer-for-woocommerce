<?php
/**
 * Unit tests for Admin_Interface::sanitize_settings().
 *
 * @package AI_Log_Analyzer
 */

namespace AI_Log_Analyzer\Tests;

use AI_Log_Analyzer\Admin_Interface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AdminInterfaceTest extends TestCase {

	private Admin_Interface $admin;

	protected function setUp(): void {
		$this->admin = new Admin_Interface();
		// Ensure no stale current settings bleed between tests.
		unset( $GLOBALS['test_options'][ AI_LOG_ANALYZER_OPTION ] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['test_options'][ AI_LOG_ANALYZER_OPTION ] );
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
		$GLOBALS['test_options'][ AI_LOG_ANALYZER_OPTION ] = [ 'model_preference' => 'google' ];
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
	// connector_has_api_key
	// -------------------------------------------------------------------------

	private function call_private( string $method, array $args = [] ): mixed {
		$ref = new ReflectionMethod( $this->admin, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $this->admin, $args );
	}

	public function test_connector_has_api_key_returns_true_when_method_is_not_api_key(): void {
		// Non-api_key auth methods (e.g. OAuth) are assumed to be configured externally.
		$result = $this->call_private( 'connector_has_api_key', [ [ 'method' => 'oauth' ] ] );
		$this->assertTrue( $result );
	}

	public function test_connector_has_api_key_returns_false_when_all_sources_empty(): void {
		$auth   = [ 'method' => 'api_key' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		$this->assertFalse( $result );
	}

	public function test_connector_has_api_key_returns_true_when_env_var_is_set(): void {
		putenv( 'TEST_CONNECTOR_KEY_PRESENT=sk_live_abc123' );
		$auth   = [ 'method' => 'api_key', 'env_var_name' => 'TEST_CONNECTOR_KEY_PRESENT' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		putenv( 'TEST_CONNECTOR_KEY_PRESENT' );
		$this->assertTrue( $result );
	}

	public function test_connector_has_api_key_returns_false_when_env_var_is_empty_string(): void {
		putenv( 'TEST_CONNECTOR_KEY_EMPTY=' );
		$auth   = [ 'method' => 'api_key', 'env_var_name' => 'TEST_CONNECTOR_KEY_EMPTY' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		putenv( 'TEST_CONNECTOR_KEY_EMPTY' );
		$this->assertFalse( $result );
	}

	public function test_connector_has_api_key_returns_false_when_env_var_does_not_exist(): void {
		$auth   = [ 'method' => 'api_key', 'env_var_name' => 'TEST_CONNECTOR_KEY_UNDEFINED_XYZ' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		$this->assertFalse( $result );
	}

	public function test_connector_has_api_key_returns_true_when_constant_defined_with_value(): void {
		define( 'TEST_CONNECTOR_CONST_VALID', 'sk_live_abc123' );
		$auth   = [ 'method' => 'api_key', 'constant_name' => 'TEST_CONNECTOR_CONST_VALID' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		$this->assertTrue( $result );
	}

	public function test_connector_has_api_key_returns_false_when_constant_defined_as_empty_string(): void {
		define( 'TEST_CONNECTOR_CONST_EMPTY', '' );
		$auth   = [ 'method' => 'api_key', 'constant_name' => 'TEST_CONNECTOR_CONST_EMPTY' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		$this->assertFalse( $result );
	}

	public function test_connector_has_api_key_returns_false_when_constant_not_defined(): void {
		$auth   = [ 'method' => 'api_key', 'constant_name' => 'TEST_CONNECTOR_CONST_NEVER_DEFINED' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		$this->assertFalse( $result );
	}

	public function test_connector_has_api_key_returns_true_when_db_option_has_value(): void {
		$GLOBALS['test_options']['anthropic_api_key'] = 'sk_ant_abc123';
		$auth   = [ 'method' => 'api_key', 'setting_name' => 'anthropic_api_key' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		unset( $GLOBALS['test_options']['anthropic_api_key'] );
		$this->assertTrue( $result );
	}

	public function test_connector_has_api_key_returns_false_when_db_option_is_empty(): void {
		$GLOBALS['test_options']['anthropic_api_key_empty'] = '';
		$auth   = [ 'method' => 'api_key', 'setting_name' => 'anthropic_api_key_empty' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		unset( $GLOBALS['test_options']['anthropic_api_key_empty'] );
		$this->assertFalse( $result );
	}

	public function test_connector_has_api_key_returns_false_when_db_option_not_set(): void {
		$auth   = [ 'method' => 'api_key', 'setting_name' => 'nonexistent_api_key_option' ];
		$result = $this->call_private( 'connector_has_api_key', [ $auth ] );
		$this->assertFalse( $result );
	}
}
