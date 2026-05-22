<?php
/**
 * Unit tests for Log_Integration.
 *
 * @package AILWC_Log_Analyzer
 */

namespace AILWC_Log_Analyzer\Tests;

use AILWC_Log_Analyzer\AI_Client;
use AILWC_Log_Analyzer\Analysis_Engine;
use AILWC_Log_Analyzer\Log_Integration;
use AILWC_Log_Analyzer\Log_Parser;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LogIntegrationTest extends TestCase {

	private function make_integration(): Log_Integration {
		return new Log_Integration(
			$this->createMock( Analysis_Engine::class ),
			$this->createMock( Log_Parser::class ),
			$this->createMock( AI_Client::class )
		);
	}

	private function call_private( object $obj, string $method, array $args = [] ): mixed {
		$ref = new ReflectionMethod( $obj, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $obj, $args );
	}

	protected function tearDown(): void {
		// Clean up any test_options set during tests.
		unset( $GLOBALS['test_options'][ AILWC_LOG_ANALYZER_OPTION ] );
	}

	// -------------------------------------------------------------------------
	// detect_handler_type
	// -------------------------------------------------------------------------

	public function test_detect_handler_type_returns_db_for_numeric_string(): void {
		$integration = $this->make_integration();
		$this->assertSame( 'db', $this->call_private( $integration, 'detect_handler_type', [ '12345' ] ) );
	}

	public function test_detect_handler_type_returns_db_for_single_digit(): void {
		$integration = $this->make_integration();
		$this->assertSame( 'db', $this->call_private( $integration, 'detect_handler_type', [ '7' ] ) );
	}

	public function test_detect_handler_type_returns_legacy_for_dot_log_filename(): void {
		$integration = $this->make_integration();
		$this->assertSame( 'legacy', $this->call_private( $integration, 'detect_handler_type', [ 'woocommerce-2025-01-15-abc123.log' ] ) );
	}

	public function test_detect_handler_type_returns_legacy_for_uppercase_log_extension(): void {
		$integration = $this->make_integration();
		$this->assertSame( 'legacy', $this->call_private( $integration, 'detect_handler_type', [ 'fatal-errors-2025-01-15.LOG' ] ) );
	}

	public function test_detect_handler_type_returns_filev2_for_hash_identifier(): void {
		$integration = $this->make_integration();
		$this->assertSame( 'filev2', $this->call_private( $integration, 'detect_handler_type', [ 'abc123def456ghi789' ] ) );
	}

	public function test_detect_handler_type_returns_filev2_for_hyphenated_id(): void {
		$integration = $this->make_integration();
		$this->assertSame( 'filev2', $this->call_private( $integration, 'detect_handler_type', [ 'abc-def-ghi-jkl-mno' ] ) );
	}

	public function test_detect_handler_type_treats_hex_prefixed_id_as_filev2(): void {
		// "0x7a" is NOT ctype_digit — letters disqualify it from the db branch.
		$integration = $this->make_integration();
		$this->assertSame( 'filev2', $this->call_private( $integration, 'detect_handler_type', [ '0x7a' ] ) );
	}

	// -------------------------------------------------------------------------
	// truncate_to_limit
	// -------------------------------------------------------------------------

	public function test_truncate_to_limit_returns_content_unchanged_when_under_limit(): void {
		$GLOBALS['test_options'][ AILWC_LOG_ANALYZER_OPTION ] = [ 'max_file_size_mb' => 1 ];
		$integration = $this->make_integration();
		$content     = str_repeat( 'a', 100 );
		$result      = $this->call_private( $integration, 'truncate_to_limit', [ $content ] );
		$this->assertSame( $content, $result );
	}

	public function test_truncate_to_limit_returns_content_unchanged_at_exact_limit(): void {
		$GLOBALS['test_options'][ AILWC_LOG_ANALYZER_OPTION ] = [ 'max_file_size_mb' => 1 ];
		$integration = $this->make_integration();
		$max_bytes   = 1 * 1024 * 1024;
		$content     = str_repeat( 'x', $max_bytes );
		$result      = $this->call_private( $integration, 'truncate_to_limit', [ $content ] );
		$this->assertSame( $content, $result );
	}

	public function test_truncate_to_limit_truncates_content_over_limit(): void {
		$GLOBALS['test_options'][ AILWC_LOG_ANALYZER_OPTION ] = [ 'max_file_size_mb' => 1 ];
		$integration = $this->make_integration();
		$max_bytes   = 1 * 1024 * 1024;
		$content     = str_repeat( 'x', $max_bytes + 1000 );
		$result      = $this->call_private( $integration, 'truncate_to_limit', [ $content ] );
		$this->assertLessThanOrEqual( $max_bytes, strlen( $result ) );
	}

	public function test_truncate_to_limit_preserves_most_recent_tail(): void {
		$GLOBALS['test_options'][ AILWC_LOG_ANALYZER_OPTION ] = [ 'max_file_size_mb' => 1 ];
		$integration = $this->make_integration();
		$max_bytes   = 1 * 1024 * 1024;
		// Padding is exactly max_bytes, then a newline and a sentinel tail line.
		// Total length = max_bytes + 1 + len("most recent log entry") > max_bytes.
		$content = str_repeat( 'a', $max_bytes ) . "\nmost recent log entry";
		$result  = $this->call_private( $integration, 'truncate_to_limit', [ $content ] );
		$this->assertStringContainsString( 'most recent log entry', $result );
	}

	public function test_truncate_to_limit_skips_partial_first_line_after_truncation(): void {
		$GLOBALS['test_options'][ AILWC_LOG_ANALYZER_OPTION ] = [ 'max_file_size_mb' => 1 ];
		$integration = $this->make_integration();
		$max_bytes   = 1 * 1024 * 1024;
		// After substr(-max_bytes), the tail starts with the end of a long line
		// ("PARTIAL"), then a newline, then "COMPLETE_LINE".
		// The algorithm must skip "PARTIAL" and return only "COMPLETE_LINE".
		$content = str_repeat( 'a', $max_bytes ) . "PARTIAL\nCOMPLETE_LINE";
		$result  = $this->call_private( $integration, 'truncate_to_limit', [ $content ] );
		$this->assertSame( 'COMPLETE_LINE', $result );
	}

	// -------------------------------------------------------------------------
	// generate_html_report
	// -------------------------------------------------------------------------

	private function base_report_data( array $overrides = [] ): array {
		return array_merge(
			[
				'severity'    => 'notice',
				'summary'     => 'Test summary',
				'cause'       => 'Test cause',
				'fix_steps'   => [ 'Step one', 'Step two' ],
				'contact'     => 'Contact support',
				'contact_url' => '',
			],
			$overrides
		);
	}

	public function test_generate_html_report_is_a_complete_html_document(): void {
		$integration = $this->make_integration();
		$result      = $this->call_private( $integration, 'generate_html_report', [ $this->base_report_data() ] );
		$this->assertStringContainsString( '<!DOCTYPE html>', $result );
		$this->assertStringContainsString( '</html>', $result );
	}

	public function test_generate_html_report_includes_severity_label(): void {
		$integration = $this->make_integration();
		$result      = $this->call_private( $integration, 'generate_html_report', [ $this->base_report_data( [ 'severity' => 'critical' ] ) ] );
		$this->assertStringContainsString( 'critical', $result );
	}

	public function test_generate_html_report_uses_critical_colour(): void {
		$integration = $this->make_integration();
		$result      = $this->call_private( $integration, 'generate_html_report', [ $this->base_report_data( [ 'severity' => 'critical' ] ) ] );
		$this->assertStringContainsString( '#cc1818', $result );
	}

	public function test_generate_html_report_uses_warning_colour(): void {
		$integration = $this->make_integration();
		$result      = $this->call_private( $integration, 'generate_html_report', [ $this->base_report_data( [ 'severity' => 'warning' ] ) ] );
		$this->assertStringContainsString( '#9e5900', $result );
	}

	public function test_generate_html_report_uses_notice_colour(): void {
		$integration = $this->make_integration();
		$result      = $this->call_private( $integration, 'generate_html_report', [ $this->base_report_data( [ 'severity' => 'notice' ] ) ] );
		$this->assertStringContainsString( '#1d6327', $result );
	}

	public function test_generate_html_report_escapes_html_in_summary(): void {
		$integration = $this->make_integration();
		$data        = $this->base_report_data( [ 'summary' => '<script>alert("xss")</script>' ] );
		$result      = $this->call_private( $integration, 'generate_html_report', [ $data ] );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	public function test_generate_html_report_escapes_html_in_cause(): void {
		$integration = $this->make_integration();
		$data        = $this->base_report_data( [ 'cause' => '<img src=x onerror=alert(1)>' ] );
		$result      = $this->call_private( $integration, 'generate_html_report', [ $data ] );
		$this->assertStringNotContainsString( '<img', $result );
	}

	public function test_generate_html_report_renders_fix_steps_as_list_items(): void {
		$integration = $this->make_integration();
		$data        = $this->base_report_data( [ 'fix_steps' => [ 'Deactivate the plugin', 'Clear your cache', 'Re-activate the plugin' ] ] );
		$result      = $this->call_private( $integration, 'generate_html_report', [ $data ] );
		$this->assertStringContainsString( '<li>Deactivate the plugin</li>', $result );
		$this->assertStringContainsString( '<li>Clear your cache</li>', $result );
		$this->assertStringContainsString( '<li>Re-activate the plugin</li>', $result );
	}

	public function test_generate_html_report_includes_contact_link_when_url_present(): void {
		$integration = $this->make_integration();
		$data        = $this->base_report_data( [ 'contact_url' => 'https://support.example.com' ] );
		$result      = $this->call_private( $integration, 'generate_html_report', [ $data ] );
		$this->assertStringContainsString( 'https://support.example.com', $result );
		$this->assertStringContainsString( '<a href=', $result );
	}

	public function test_generate_html_report_omits_contact_link_when_url_is_empty(): void {
		$integration = $this->make_integration();
		$data        = $this->base_report_data( [ 'contact_url' => '' ] );
		$result      = $this->call_private( $integration, 'generate_html_report', [ $data ] );
		$this->assertStringNotContainsString( '<a href=', $result );
	}

	public function test_generate_html_report_handles_empty_fix_steps_gracefully(): void {
		$integration = $this->make_integration();
		$data        = $this->base_report_data( [ 'fix_steps' => [] ] );
		$result      = $this->call_private( $integration, 'generate_html_report', [ $data ] );
		$this->assertStringContainsString( '<ol>', $result );
		$this->assertStringNotContainsString( '<li>', $result );
	}
}
