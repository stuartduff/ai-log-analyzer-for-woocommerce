<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Defines ABSPATH so WordPress-guarded files load correctly,
 * declares plugin constants, stubs the subset of WordPress functions
 * needed by the classes under test, then pulls in the Composer autoloader.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

// Plugin constants referenced by class files under test.
define( 'AI_LOG_ANALYZER_OPTION', 'ai_log_analyzer_settings' );
define( 'AI_LOG_ANALYZER_PATH', dirname( __DIR__, 2 ) . '/' );
define( 'AI_LOG_ANALYZER_URL', 'http://localhost/wp-content/plugins/wc-ai-log-analyzer/' );
define( 'AI_LOG_ANALYZER_VERSION', '1.0.0' );

// Backing store for the get_option() stub — tests set entries here to control return values.
$GLOBALS['test_options'] = array();

// -------------------------------------------------------------------------
// WordPress function stubs
// -------------------------------------------------------------------------

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $GLOBALS['test_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0, $depth = 512 ) {
		return json_encode( $data, $flags, $depth );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $string );
		$string = strip_tags( $string );
		if ( $remove_breaks ) {
			$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
		}
		return trim( $string );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( wp_strip_all_tags( stripslashes( (string) $str ) ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return $GLOBALS['test_caps'][ $capability ] ?? false;
	}
}

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
