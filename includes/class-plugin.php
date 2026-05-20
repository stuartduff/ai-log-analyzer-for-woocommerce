<?php
/**
 * Core plugin class.
 *
 * @package AI_Log_Analyzer
 */

namespace AI_Log_Analyzer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class — singleton entry point.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Admin interface instance.
	 *
	 * @var Admin_Interface
	 */
	private Admin_Interface $admin_interface;

	/**
	 * Log integration instance.
	 *
	 * @var Log_Integration
	 */
	private Log_Integration $log_integration;

	/**
	 * Analysis engine instance.
	 *
	 * @var Analysis_Engine
	 */
	private Analysis_Engine $analysis_engine;

	/**
	 * AI client instance.
	 *
	 * @var AI_Client
	 */
	private AI_Client $ai_client;

	/**
	 * Log parser instance.
	 *
	 * @var Log_Parser
	 */
	private Log_Parser $log_parser;

	/**
	 * Private constructor — use instance() instead.
	 */
	private function __construct() {}

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialises the plugin — checks dependencies, instantiates classes, registers hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! $this->check_dependencies() ) {
			return;
		}

		$this->log_parser      = new Log_Parser();
		$this->ai_client       = new AI_Client();
		$this->analysis_engine = new Analysis_Engine( $this->ai_client );
		$this->log_integration = new Log_Integration( $this->analysis_engine, $this->log_parser, $this->ai_client );
		$this->admin_interface = new Admin_Interface();

		$this->register_hooks();
	}

	/**
	 * Checks all required dependencies. Adds admin notices for any failures.
	 *
	 * @return bool True if all dependencies are satisfied.
	 */
	private function check_dependencies(): bool {
		$errors = array();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$errors[] = __( 'AI Log Analyzer for WooCommerce requires WooCommerce to be installed and active.', 'ai-log-analyzer-for-woocommerce' );
		}

		if ( version_compare( get_bloginfo( 'version' ), '7.0', '<' ) ) {
			$errors[] = __( 'AI Log Analyzer for WooCommerce requires WordPress 7.0 or higher.', 'ai-log-analyzer-for-woocommerce' );
		}

		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			$errors[] = sprintf(
				/* translators: %s: Current PHP version number. */
				__( 'AI Log Analyzer for WooCommerce requires PHP 8.0 or higher. You are running PHP %s.', 'ai-log-analyzer-for-woocommerce' ),
				PHP_VERSION
			);
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$errors[] = __( 'AI Log Analyzer for WooCommerce requires the WordPress AI Client (available in WordPress 7.0+). Please upgrade WordPress or configure an AI connector.', 'ai-log-analyzer-for-woocommerce' );
		}

		if ( ! empty( $errors ) ) {
			add_action(
				'admin_notices',
				function () use ( $errors ) {
					foreach ( $errors as $error ) {
						echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
					}
				}
			);
			return false;
		}

		return true;
	}

	/**
	 * Registers all plugin hooks via sub-class instances.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$this->admin_interface->register_hooks();
		$this->log_integration->register_hooks();
	}

	/**
	 * Checks whether the current user is authorised to run log analysis.
	 *
	 * Returns true if the user has both manage_woocommerce and prompt_ai capabilities,
	 * OR if the allow_shop_managers setting is enabled and the user has manage_woocommerce.
	 *
	 * @return bool
	 */
	public static function current_user_can_analyze(): bool {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		if ( current_user_can( 'prompt_ai' ) ) {
			return true;
		}

		$settings            = get_option( AI_LOG_ANALYZER_OPTION, array() );
		$allow_shop_managers = ! empty( $settings['allow_shop_managers'] );

		return $allow_shop_managers;
	}

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Reserved for future activation tasks (e.g. capability assignment, DB table creation).
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Reserved for future deactivation cleanup.
	}
}
