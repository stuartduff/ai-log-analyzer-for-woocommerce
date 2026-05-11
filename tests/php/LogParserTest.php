<?php
/**
 * Unit tests for Log_Parser::sanitize_log_content().
 *
 * @package AI_Log_Analyzer
 */

namespace AI_Log_Analyzer\Tests;

use AI_Log_Analyzer\Log_Parser;
use PHPUnit\Framework\TestCase;

class LogParserTest extends TestCase {

	private Log_Parser $parser;

	protected function setUp(): void {
		$this->parser = new Log_Parser();
	}

	// -------------------------------------------------------------------------
	// Email addresses
	// -------------------------------------------------------------------------

	public function test_email_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'User john.doe@example.com placed an order.' );
		$this->assertStringNotContainsString( 'john.doe@example.com', $result );
		$this->assertStringContainsString( '[EMAIL]', $result );
	}

	public function test_multiple_emails_are_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'From: a@b.com To: c@d.org' );
		$this->assertStringNotContainsString( 'a@b.com', $result );
		$this->assertStringNotContainsString( 'c@d.org', $result );
	}

	// -------------------------------------------------------------------------
	// HTTP Authorization headers
	// -------------------------------------------------------------------------

	public function test_authorization_bearer_token_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9' );
		$this->assertStringNotContainsString( 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $result );
		$this->assertStringContainsString( 'Authorization: Bearer [REDACTED]', $result );
	}

	public function test_authorization_basic_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'Authorization: Basic dXNlcjpwYXNzd29yZA==' );
		$this->assertStringNotContainsString( 'dXNlcjpwYXNzd29yZA==', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
	}

	public function test_authorization_apikey_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'Authorization: ApiKey my-short-key' );
		$this->assertStringNotContainsString( 'my-short-key', $result );
	}

	// -------------------------------------------------------------------------
	// JSON-style key: value pairs
	// -------------------------------------------------------------------------

	public function test_json_password_field_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( '{"password": "hunter2abc"}' );
		$this->assertStringNotContainsString( 'hunter2abc', $result );
		$this->assertStringContainsString( '"password": "[REDACTED]"', $result );
	}

	public function test_json_api_key_field_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( '{"api_key": "sk_test_abc123def456"}' );
		$this->assertStringNotContainsString( 'sk_test_abc123def456', $result );
	}

	public function test_json_access_token_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( '{"access_token": "ya29.A0ARrdaM-short"}' );
		$this->assertStringNotContainsString( 'ya29.A0ARrdaM-short', $result );
	}

	public function test_json_non_sensitive_key_is_not_redacted(): void {
		$result = $this->parser->sanitize_log_content( '{"order_id": "12345678", "status": "completed"}' );
		$this->assertStringContainsString( '12345678', $result );
		$this->assertStringContainsString( 'completed', $result );
	}

	// -------------------------------------------------------------------------
	// Plain key=value and key: value pairs
	// -------------------------------------------------------------------------

	public function test_short_api_key_equals_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'Request failed: api_key=shortkey123' );
		$this->assertStringNotContainsString( 'shortkey123', $result );
	}

	public function test_password_colon_separator_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'password: mysecret123' );
		$this->assertStringNotContainsString( 'mysecret123', $result );
	}

	public function test_token_in_url_query_string_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'GET /api/v1/orders?token=abc123xyz789&page=1' );
		$this->assertStringNotContainsString( 'abc123xyz789', $result );
		$this->assertStringContainsString( 'page=1', $result );
	}

	public function test_secret_key_equals_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'secret_key=wc_live_abc123def456' );
		$this->assertStringNotContainsString( 'wc_live_abc123def456', $result );
	}

	public function test_value_under_six_chars_is_not_redacted(): void {
		// "true", "false", "null" and other trivial values should survive.
		$result = $this->parser->sanitize_log_content( 'debug=true' );
		$this->assertStringContainsString( 'true', $result );
	}

	public function test_long_api_key_equals_is_redacted(): void {
		// Original behaviour: 32+ char values still caught.
		$key    = str_repeat( 'a', 32 );
		$result = $this->parser->sanitize_log_content( "api_key={$key}" );
		$this->assertStringNotContainsString( $key, $result );
	}

	// -------------------------------------------------------------------------
	// DSN connection strings
	// -------------------------------------------------------------------------

	public function test_dsn_password_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'mysql://dbuser:s3cr3tP@ss@localhost/mydb' );
		$this->assertStringNotContainsString( 's3cr3tP@ss', $result );
		$this->assertStringContainsString( '[REDACTED]', $result );
		$this->assertStringContainsString( 'dbuser', $result );
		$this->assertStringContainsString( 'localhost', $result );
	}

	// -------------------------------------------------------------------------
	// PEM-encoded keys
	// -------------------------------------------------------------------------

	public function test_pem_private_key_is_redacted(): void {
		$pem = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkq\n-----END PRIVATE KEY-----";
		$result = $this->parser->sanitize_log_content( "Key dump: {$pem} end of log" );
		$this->assertStringNotContainsString( 'MIIEvQIBADANBgkq', $result );
		$this->assertStringContainsString( '[REDACTED_KEY]', $result );
	}

	public function test_pem_certificate_is_redacted(): void {
		$pem = "-----BEGIN CERTIFICATE-----\nABCDEF123456\n-----END CERTIFICATE-----";
		$result = $this->parser->sanitize_log_content( $pem );
		$this->assertStringNotContainsString( 'ABCDEF123456', $result );
	}

	// -------------------------------------------------------------------------
	// Non-sensitive content preserved
	// -------------------------------------------------------------------------

	public function test_plain_error_message_is_preserved(): void {
		$msg    = 'PHP Fatal error: Call to undefined function wc_get_order() in /var/www/html/wp-content/plugins/my-plugin/my-plugin.php on line 42';
		$result = $this->parser->sanitize_log_content( $msg );
		$this->assertStringContainsString( 'PHP Fatal error', $result );
		$this->assertStringContainsString( '/var/www/html/wp-content/plugins/my-plugin', $result );
	}

	public function test_plugin_slug_in_path_is_not_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'Error in plugins/my-secret-plugin/my-secret-plugin.php line 10' );
		// File paths should survive — the key-name allowlist prevents slug false-positives.
		$this->assertStringContainsString( 'my-secret-plugin', $result );
	}

	public function test_order_id_is_not_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'Processing order_id=12345 status=completed' );
		$this->assertStringContainsString( '12345', $result );
		$this->assertStringContainsString( 'completed', $result );
	}

	// -------------------------------------------------------------------------
	// Gap 1: Prefixed key names (db_password, stripe_secret, paypal_token, etc.)
	// -------------------------------------------------------------------------

	public function test_db_password_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'db_password=mysecretpassword' );
		$this->assertStringNotContainsString( 'mysecretpassword', $result );
	}

	public function test_stripe_secret_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'stripe_secret=sk_test_abc123def456' );
		$this->assertStringNotContainsString( 'sk_test_abc123def456', $result );
	}

	public function test_prefixed_token_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'paypal_token=A21AAFEpH4PaLH6yjhfH' );
		$this->assertStringNotContainsString( 'A21AAFEpH4PaLH6yjhfH', $result );
	}

	public function test_deeply_prefixed_key_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'wc_stripe_api_key=pk_live_abc123def456' );
		$this->assertStringNotContainsString( 'pk_live_abc123def456', $result );
	}

	public function test_prefixed_key_in_json_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( '{"db_password": "mysecretpassword"}' );
		$this->assertStringNotContainsString( 'mysecretpassword', $result );
	}

	// -------------------------------------------------------------------------
	// Gap 2: Non-Authorization credential headers (X-API-Key, X-Auth-Token)
	// -------------------------------------------------------------------------

	public function test_x_api_key_header_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'X-API-Key: abc123def456ghi789' );
		$this->assertStringNotContainsString( 'abc123def456ghi789', $result );
	}

	public function test_x_auth_token_header_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'X-Auth-Token: xyz789abc123def456' );
		$this->assertStringNotContainsString( 'xyz789abc123def456', $result );
	}

	public function test_x_access_token_header_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'X-Access-Token: sometoken123456789' );
		$this->assertStringNotContainsString( 'sometoken123456789', $result );
	}

	// -------------------------------------------------------------------------
	// Gap 3: Quoted values containing spaces
	// -------------------------------------------------------------------------

	public function test_double_quoted_password_with_spaces_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'AUTH FAILED: password="my secret phrase"' );
		$this->assertStringNotContainsString( 'my secret phrase', $result );
	}

	public function test_single_quoted_password_with_spaces_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( "password='my secret phrase'" );
		$this->assertStringNotContainsString( 'my secret phrase', $result );
	}

	public function test_double_quoted_api_secret_with_spaces_is_redacted(): void {
		$result = $this->parser->sanitize_log_content( 'api_secret="live secret key value"' );
		$this->assertStringNotContainsString( 'live secret key value', $result );
	}
}
