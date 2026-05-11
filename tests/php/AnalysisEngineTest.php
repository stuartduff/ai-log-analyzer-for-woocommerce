<?php
/**
 * Unit tests for Analysis_Engine.
 *
 * @package AI_Log_Analyzer
 */

namespace AI_Log_Analyzer\Tests;

use AI_Log_Analyzer\AI_Client;
use AI_Log_Analyzer\Analysis_Engine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AnalysisEngineTest extends TestCase {

	private function make_engine(): Analysis_Engine {
		return new Analysis_Engine( $this->createMock( AI_Client::class ) );
	}

	private function call_private( object $obj, string $method, array $args = [] ): mixed {
		$ref = new ReflectionMethod( $obj, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $obj, $args );
	}

	// -------------------------------------------------------------------------
	// classify_error_patterns
	// -------------------------------------------------------------------------

	public function test_classify_php_fatal(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'PHP Fatal error: Call to undefined function wc_get_order()' ] );
		$this->assertContains( 'php_fatal', $result );
	}

	public function test_classify_php_warning(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'PHP Warning: Division by zero in /var/www/html/wp-content/plugins/foo/bar.php on line 10' ] );
		$this->assertContains( 'php_warning', $result );
	}

	public function test_classify_php_notice(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'PHP Notice: Undefined variable $order_id' ] );
		$this->assertContains( 'php_notice', $result );
	}

	public function test_classify_database_error(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'WordPress database error: You have an error in your SQL syntax' ] );
		$this->assertContains( 'database_error', $result );
	}

	public function test_classify_network_error(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'cURL error 6: Could not resolve host: api.stripe.com' ] );
		$this->assertContains( 'network_error', $result );
	}

	public function test_classify_memory_limit(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'Fatal error: Allowed memory size of 134217728 bytes exhausted' ] );
		$this->assertContains( 'memory_limit', $result );
	}

	public function test_classify_timeout(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'Maximum execution time of 30 seconds exceeded in /var/www/html' ] );
		$this->assertContains( 'timeout', $result );
	}

	public function test_classify_missing_function(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'Fatal error: Call to undefined function wc_get_product()' ] );
		$this->assertContains( 'missing_function', $result );
	}

	public function test_classify_missing_class(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'PHP Fatal error: Class WC_Payment_Gateway not found' ] );
		$this->assertContains( 'missing_class', $result );
	}

	public function test_classify_connection_error(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'Error: Could not connect to the server' ] );
		$this->assertContains( 'connection_error', $result );
	}

	public function test_classify_file_permission(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'Warning: fopen(/var/www/html/wp-content/uploads/log.txt): failed to open stream: Permission denied' ] );
		$this->assertContains( 'file_permission', $result );
	}

	public function test_classify_returns_empty_array_for_clean_log(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ '2025-01-01 12:00:00 INFO Order #123 completed successfully. Payment captured.' ] );
		$this->assertSame( [], $result );
	}

	public function test_classify_multiple_patterns_in_one_log(): void {
		$engine = $this->make_engine();
		$log    = "PHP Fatal error: something\nWordPress database error: bad query";
		$result = $this->call_private( $engine, 'classify_error_patterns', [ $log ] );
		$this->assertContains( 'php_fatal', $result );
		$this->assertContains( 'database_error', $result );
		$this->assertCount( 2, $result );
	}

	public function test_classify_is_case_insensitive(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'classify_error_patterns', [ 'php warning: something happened in a file' ] );
		$this->assertContains( 'php_warning', $result );
	}

	// -------------------------------------------------------------------------
	// resolve_support_url
	// -------------------------------------------------------------------------

	public function test_resolve_support_url_prefers_plugin_uri_over_author_uri(): void {
		$engine  = $this->make_engine();
		$plugins = [
			[ 'slug' => 'my-plugin', 'name' => 'My Plugin', 'plugin_uri' => 'https://myplugin.com', 'author' => 'Dev', 'author_uri' => 'https://dev.com' ],
		];
		$result = $this->call_private( $engine, 'resolve_support_url', [ $plugins ] );
		$this->assertSame( 'https://myplugin.com', $result );
	}

	public function test_resolve_support_url_falls_back_to_author_uri_when_plugin_uri_empty(): void {
		$engine  = $this->make_engine();
		$plugins = [
			[ 'slug' => 'my-plugin', 'name' => 'My Plugin', 'plugin_uri' => '', 'author' => 'Dev', 'author_uri' => 'https://dev.com' ],
		];
		$result = $this->call_private( $engine, 'resolve_support_url', [ $plugins ] );
		$this->assertSame( 'https://dev.com', $result );
	}

	public function test_resolve_support_url_falls_back_to_woocommerce_when_no_plugins(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'resolve_support_url', [ [] ] );
		$this->assertSame( 'https://woocommerce.com/my-account/create-a-ticket/', $result );
	}

	public function test_resolve_support_url_falls_back_to_woocommerce_when_both_uris_empty(): void {
		$engine  = $this->make_engine();
		$plugins = [
			[ 'slug' => 'my-plugin', 'name' => 'My Plugin', 'plugin_uri' => '', 'author' => 'Dev', 'author_uri' => '' ],
		];
		$result = $this->call_private( $engine, 'resolve_support_url', [ $plugins ] );
		$this->assertSame( 'https://woocommerce.com/my-account/create-a-ticket/', $result );
	}

	public function test_resolve_support_url_uses_first_plugin_with_a_uri(): void {
		$engine  = $this->make_engine();
		$plugins = [
			[ 'slug' => 'plugin-a', 'name' => 'Plugin A', 'plugin_uri' => 'https://plugin-a.com', 'author' => '', 'author_uri' => '' ],
			[ 'slug' => 'plugin-b', 'name' => 'Plugin B', 'plugin_uri' => 'https://plugin-b.com', 'author' => '', 'author_uri' => '' ],
		];
		$result = $this->call_private( $engine, 'resolve_support_url', [ $plugins ] );
		$this->assertSame( 'https://plugin-a.com', $result );
	}

	// -------------------------------------------------------------------------
	// inject_support_url
	// -------------------------------------------------------------------------

	public function test_inject_support_url_adds_contact_url_from_plugin_uri(): void {
		$engine  = $this->make_engine();
		$json    = json_encode( [ 'severity' => 'warning', 'summary' => 'Test' ] );
		$plugins = [
			[ 'slug' => 'my-plugin', 'name' => 'My Plugin', 'plugin_uri' => 'https://myplugin.com', 'author' => '', 'author_uri' => '' ],
		];
		$result  = $this->call_private( $engine, 'inject_support_url', [ $json, $plugins ] );
		$decoded = json_decode( $result, true );
		$this->assertSame( 'https://myplugin.com', $decoded['contact_url'] );
	}

	public function test_inject_support_url_uses_woocommerce_fallback_when_no_plugins(): void {
		$engine  = $this->make_engine();
		$json    = json_encode( [ 'severity' => 'notice' ] );
		$result  = $this->call_private( $engine, 'inject_support_url', [ $json, [] ] );
		$decoded = json_decode( $result, true );
		$this->assertSame( 'https://woocommerce.com/my-account/create-a-ticket/', $decoded['contact_url'] );
	}

	public function test_inject_support_url_returns_original_string_on_invalid_json(): void {
		$engine  = $this->make_engine();
		$invalid = 'not-valid-json{{{';
		$result  = $this->call_private( $engine, 'inject_support_url', [ $invalid, [] ] );
		$this->assertSame( $invalid, $result );
	}

	public function test_inject_support_url_preserves_existing_fields(): void {
		$engine  = $this->make_engine();
		$data    = [ 'severity' => 'critical', 'summary' => 'Something broke', 'cause' => 'Bad config' ];
		$json    = json_encode( $data );
		$result  = $this->call_private( $engine, 'inject_support_url', [ $json, [] ] );
		$decoded = json_decode( $result, true );
		$this->assertSame( 'critical', $decoded['severity'] );
		$this->assertSame( 'Something broke', $decoded['summary'] );
		$this->assertSame( 'Bad config', $decoded['cause'] );
	}

	// -------------------------------------------------------------------------
	// enrich_context
	// -------------------------------------------------------------------------

	public function test_enrich_context_adds_error_patterns_when_log_contains_known_errors(): void {
		$engine = $this->make_engine();
		$log    = 'PHP Fatal error: something went wrong';
		$result = $this->call_private( $engine, 'enrich_context', [ $log, [], [] ] );
		$this->assertArrayHasKey( 'error_patterns', $result );
		$this->assertContains( 'php_fatal', $result['error_patterns'] );
	}

	public function test_enrich_context_does_not_add_error_patterns_for_clean_log(): void {
		$engine = $this->make_engine();
		$log    = '2025-01-01 All orders processed successfully without any issues.';
		$result = $this->call_private( $engine, 'enrich_context', [ $log, [], [] ] );
		$this->assertArrayNotHasKey( 'error_patterns', $result );
	}

	public function test_enrich_context_deduplicates_error_patterns(): void {
		$engine = $this->make_engine();
		$log    = "PHP Fatal error: first\nPHP Fatal error: second";
		$result = $this->call_private( $engine, 'enrich_context', [ $log, [], [] ] );
		$this->assertCount( 1, array_filter( $result['error_patterns'], fn( $p ) => 'php_fatal' === $p ) );
	}

	public function test_enrich_context_adds_identified_plugin_names(): void {
		$engine  = $this->make_engine();
		$plugins = [
			[ 'slug' => 'woo-stripe', 'name' => 'Stripe for WooCommerce', 'plugin_uri' => '', 'author' => '', 'author_uri' => '' ],
		];
		$result = $this->call_private( $engine, 'enrich_context', [ '', [], $plugins ] );
		$this->assertArrayHasKey( 'identified_plugins', $result );
		$this->assertContains( 'Stripe for WooCommerce', $result['identified_plugins'] );
	}

	public function test_enrich_context_does_not_add_identified_plugins_when_none_found(): void {
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'enrich_context', [ '', [], [] ] );
		$this->assertArrayNotHasKey( 'identified_plugins', $result );
	}

	public function test_enrich_context_preserves_existing_context_keys(): void {
		$engine  = $this->make_engine();
		$context = [ 'wp_version' => '6.8', 'php_version' => '8.2', 'theme' => 'Storefront' ];
		$result  = $this->call_private( $engine, 'enrich_context', [ '', $context, [] ] );
		$this->assertSame( '6.8', $result['wp_version'] );
		$this->assertSame( '8.2', $result['php_version'] );
		$this->assertSame( 'Storefront', $result['theme'] );
	}

	// -------------------------------------------------------------------------
	// identify_plugin_files_in_log
	// -------------------------------------------------------------------------

	protected function tearDown(): void {
		unset( $GLOBALS['test_options']['active_plugins'] );
	}

	public function test_identify_returns_empty_when_no_active_plugins(): void {
		$GLOBALS['test_options']['active_plugins'] = [];
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'identify_plugin_files_in_log', [ 'some log content' ] );
		$this->assertSame( [], $result );
	}

	public function test_identify_returns_empty_when_no_plugin_slug_appears_in_log(): void {
		$GLOBALS['test_options']['active_plugins'] = [ 'woocommerce/woocommerce.php' ];
		$engine = $this->make_engine();
		$result = $this->call_private( $engine, 'identify_plugin_files_in_log', [ 'PHP Fatal error: something unrelated' ] );
		$this->assertSame( [], $result );
	}

	public function test_identify_returns_plugin_path_when_slug_found_in_log(): void {
		$GLOBALS['test_options']['active_plugins'] = [ 'woocommerce/woocommerce.php' ];
		$engine = $this->make_engine();
		$log    = 'Error in /var/www/html/wp-content/plugins/woocommerce/includes/class-wc.php on line 42';
		$result = $this->call_private( $engine, 'identify_plugin_files_in_log', [ $log ] );
		$this->assertContains( 'woocommerce/woocommerce.php', $result );
	}

	public function test_identify_excludes_own_plugin_slug(): void {
		$GLOBALS['test_options']['active_plugins'] = [ 'wc-ai-log-analyzer/ai-log-analyzer.php' ];
		$engine = $this->make_engine();
		$log    = 'Stack trace includes plugins/wc-ai-log-analyzer/includes/class-analysis-engine.php';
		$result = $this->call_private( $engine, 'identify_plugin_files_in_log', [ $log ] );
		$this->assertSame( [], $result );
	}

	public function test_identify_returns_multiple_plugins_found_in_log(): void {
		$GLOBALS['test_options']['active_plugins'] = [
			'woocommerce/woocommerce.php',
			'woo-stripe-payment/stripe.php',
			'unrelated-plugin/unrelated.php',
		];
		$engine = $this->make_engine();
		$log    = "Error in plugins/woocommerce/class.php\nAlso in plugins/woo-stripe-payment/class.php";
		$result = $this->call_private( $engine, 'identify_plugin_files_in_log', [ $log ] );
		$this->assertContains( 'woocommerce/woocommerce.php', $result );
		$this->assertContains( 'woo-stripe-payment/stripe.php', $result );
		$this->assertNotContains( 'unrelated-plugin/unrelated.php', $result );
		$this->assertCount( 2, $result );
	}

	public function test_identify_deduplicates_results(): void {
		// Same plugin file path listed twice in active_plugins should still appear once.
		$GLOBALS['test_options']['active_plugins'] = [
			'woocommerce/woocommerce.php',
			'woocommerce/woocommerce.php',
		];
		$engine = $this->make_engine();
		$log    = 'Error in plugins/woocommerce/class-wc.php on line 10';
		$result = $this->call_private( $engine, 'identify_plugin_files_in_log', [ $log ] );
		$this->assertCount( 1, $result );
	}

	public function test_identify_does_not_match_partial_slug(): void {
		// 'woocommerce' slug must not match 'plugins/woocommerce-payments/' in the log
		// because the check appends a trailing slash: 'plugins/woocommerce/'.
		$GLOBALS['test_options']['active_plugins'] = [ 'woocommerce/woocommerce.php' ];
		$engine = $this->make_engine();
		$log    = 'Error in plugins/woocommerce-payments/class-gateway.php on line 5';
		$result = $this->call_private( $engine, 'identify_plugin_files_in_log', [ $log ] );
		$this->assertSame( [], $result );
	}
}
