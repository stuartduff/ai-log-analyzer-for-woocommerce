<?php
/**
 * Unit tests for AI_Client prompt-building methods.
 *
 * @package AI_Log_Analyzer
 */

namespace AI_Log_Analyzer\Tests;

use AI_Log_Analyzer\AI_Client;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AIClientTest extends TestCase {

	private AI_Client $client;

	protected function setUp(): void {
		$this->client = new AI_Client();
	}

	private function call_private( string $method, array $args = [] ): mixed {
		$ref = new ReflectionMethod( $this->client, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $this->client, $args );
	}

	// -------------------------------------------------------------------------
	// build_system_instruction
	// -------------------------------------------------------------------------

	public function test_build_system_instruction_returns_a_non_empty_string(): void {
		$result = $this->call_private( 'build_system_instruction' );
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_build_system_instruction_mentions_woocommerce(): void {
		$result = $this->call_private( 'build_system_instruction' );
		$this->assertStringContainsStringIgnoringCase( 'WooCommerce', $result );
	}

	public function test_build_system_instruction_instructs_to_avoid_stack_traces(): void {
		$result = $this->call_private( 'build_system_instruction' );
		$this->assertStringContainsStringIgnoringCase( 'stack trace', $result );
	}

	public function test_build_system_instruction_tells_ai_not_to_include_urls_in_contact_field(): void {
		$result = $this->call_private( 'build_system_instruction' );
		$this->assertStringContainsString( 'contact', $result );
		$this->assertStringContainsStringIgnoringCase( 'URL', $result );
	}

	// -------------------------------------------------------------------------
	// build_prompt — site context lines
	// -------------------------------------------------------------------------

	public function test_build_prompt_includes_log_content(): void {
		$log    = 'PHP Fatal error: Class not found on line 42';
		$result = $this->call_private( 'build_prompt', [ $log, [] ] );
		$this->assertStringContainsString( $log, $result );
	}

	public function test_build_prompt_includes_wp_version_from_context(): void {
		$result = $this->call_private( 'build_prompt', [ '', [ 'wp_version' => '6.8' ] ] );
		$this->assertStringContainsString( '6.8', $result );
	}

	public function test_build_prompt_includes_wc_version_from_context(): void {
		$result = $this->call_private( 'build_prompt', [ '', [ 'wc_version' => '9.3.1' ] ] );
		$this->assertStringContainsString( '9.3.1', $result );
	}

	public function test_build_prompt_includes_php_version_from_context(): void {
		$result = $this->call_private( 'build_prompt', [ '', [ 'php_version' => '8.2.0' ] ] );
		$this->assertStringContainsString( '8.2.0', $result );
	}

	public function test_build_prompt_includes_theme_from_context(): void {
		$result = $this->call_private( 'build_prompt', [ '', [ 'theme' => 'Storefront' ] ] );
		$this->assertStringContainsString( 'Storefront', $result );
	}

	public function test_build_prompt_falls_back_to_unknown_when_wp_version_missing(): void {
		$result = $this->call_private( 'build_prompt', [ '', [] ] );
		$this->assertStringContainsString( 'WordPress: unknown', $result );
	}

	public function test_build_prompt_falls_back_to_unknown_when_wc_version_missing(): void {
		$result = $this->call_private( 'build_prompt', [ '', [] ] );
		$this->assertStringContainsString( 'WooCommerce: unknown', $result );
	}

	// -------------------------------------------------------------------------
	// build_prompt — conditional context lines
	// -------------------------------------------------------------------------

	public function test_build_prompt_includes_identified_plugins_line_when_present(): void {
		$context = [ 'identified_plugins' => [ 'Stripe for WooCommerce', 'PayPal Payments' ] ];
		$result  = $this->call_private( 'build_prompt', [ '', $context ] );
		$this->assertStringContainsString( 'Stripe for WooCommerce', $result );
		$this->assertStringContainsString( 'PayPal Payments', $result );
	}

	public function test_build_prompt_omits_identified_plugins_line_when_empty(): void {
		$context = [ 'identified_plugins' => [] ];
		$result  = $this->call_private( 'build_prompt', [ '', $context ] );
		$this->assertStringNotContainsString( 'Plugins likely causing', $result );
	}

	public function test_build_prompt_includes_active_plugins_line_when_present(): void {
		$context = [ 'active_plugins' => [ 'WooCommerce', 'Jetpack' ] ];
		$result  = $this->call_private( 'build_prompt', [ '', $context ] );
		$this->assertStringContainsString( 'All active plugins', $result );
		$this->assertStringContainsString( 'WooCommerce', $result );
	}

	public function test_build_prompt_omits_active_plugins_line_when_empty(): void {
		$context = [ 'active_plugins' => [] ];
		$result  = $this->call_private( 'build_prompt', [ '', $context ] );
		$this->assertStringNotContainsString( 'All active plugins', $result );
	}

	public function test_build_prompt_includes_error_patterns_line_when_present(): void {
		$context = [ 'error_patterns' => [ 'php_fatal', 'database_error' ] ];
		$result  = $this->call_private( 'build_prompt', [ '', $context ] );
		$this->assertStringContainsString( 'Detected error types', $result );
		$this->assertStringContainsString( 'php_fatal', $result );
		$this->assertStringContainsString( 'database_error', $result );
	}

	public function test_build_prompt_omits_error_patterns_line_when_empty(): void {
		$context = [ 'error_patterns' => [] ];
		$result  = $this->call_private( 'build_prompt', [ '', $context ] );
		$this->assertStringNotContainsString( 'Detected error types', $result );
	}
}
