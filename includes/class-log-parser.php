<?php
/**
 * Log parser — sanitises log content before it is sent to the AI client.
 *
 * @package AI_Log_Analyzer
 */

namespace AI_Log_Analyzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles sensitive data sanitisation of log content.
 */
class Log_Parser {

	/**
	 * Sensitive key names whose values should always be redacted.
	 *
	 * Applied across all separator styles (=, :, JSON colon).
	 */
	private const SENSITIVE_KEYS = 'api_?key|api_?secret|access_?token|auth(?:_?token)?|client_?secret|password|passwd|secret(?:_?key)?|private_?key|token';

	/**
	 * Sanitises log content by stripping sensitive data before sending to the AI client.
	 *
	 * Covers:
	 *  - HTTP Authorization headers and common X-* credential headers
	 *  - JSON-style "key": "value" pairs (including prefixed keys like "db_password")
	 *  - Plain key=value and key: value pairs — prefixed keys, quoted values with spaces,
	 *    and unquoted token values (config files, URL query strings)
	 *  - DSN connection-string passwords (mysql://user:pass@host)
	 *  - PEM-encoded private keys and certificates
	 *  - Email addresses
	 *
	 * @param string $content Raw log content.
	 * @return string Sanitised content.
	 */
	public function sanitize_log_content( string $content ): string {
		// HTTP credential headers: Authorization and common X-* variants.
		// The auth scheme (Bearer, Basic, etc.) is optional to cover bare-value headers
		// like X-API-Key.
		$content = preg_replace(
			'/((?:Authorization|X-(?:API-?Key|Auth(?:-?Token)?|Access-?Token))\s*:\s*(?:Bearer|Basic|Digest|Token|ApiKey)?\s*)[^\s\r\n,]+/i',
			'$1[REDACTED]',
			$content
		);

		// JSON-style "sensitive_key": "value" pairs.
		// (?:\w+_)? allows prefixed keys such as "db_password" or "stripe_secret".
		$content = preg_replace(
			'/"((?:\w+_)?(?:' . self::SENSITIVE_KEYS . '))"\s*:\s*"[^"]+"/i',
			'"$1": "[REDACTED]"',
			$content
		);

		// Plain key=value or key: value pairs.
		// (?:\w+_)? allows prefixed keys (db_password, paypal_token, wc_stripe_api_key).
		// \b after the key prevents matching mid-word (e.g. tokenizer=value).
		// Value alternation: double-quoted, single-quoted (both allow spaces), or
		// an unquoted token of 6+ chars.
		$content = preg_replace(
			'/\b((?:\w+_)?(?:' . self::SENSITIVE_KEYS . '))\b\s*[=:]\s*(?:"[^"]*"|\'[^\']*\'|[a-zA-Z0-9_\-\.\/+%]{6,})/i',
			'$1=[REDACTED]',
			$content
		);

		// DSN-style connection strings (e.g. mysql://user:pass@host).
		$content = preg_replace( '#(://[^:]+:)[^@]+(@)#', '$1[REDACTED]$2', $content );

		// PEM-encoded private keys and certificates.
		$content = preg_replace(
			'/-----BEGIN [A-Z ]+-----.*?-----END [A-Z ]+-----/s',
			'[REDACTED_KEY]',
			$content
		);

		// Email addresses — runs last so emails used as values above are already gone.
		$content = preg_replace( '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[EMAIL]', $content );

		return $content;
	}
}
