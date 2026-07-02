<?php
/**
 * Plugin Name: AI Log Analyzer for WooCommerce
 * Plugin URI:  https://github.com/stuartduff/ai-log-analyzer-for-woocommerce
 * Description: AI-powered WooCommerce log file analyzer that helps store owners understand and resolve log issues independently, or with the help the plugin or theme developer if needed.
 * Version:     1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Author:      Stuart Duff
 * Author URI:  https://stuartduff.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-log-analyzer-for-woocommerce
 * Domain Path: /languages
 *
 * @package AILWC_Log_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AILWC_LOG_ANALYZER_VERSION', '1.0.0' );
define( 'AILWC_LOG_ANALYZER_FILE', __FILE__ );
define( 'AILWC_LOG_ANALYZER_PATH', plugin_dir_path( __FILE__ ) );
define( 'AILWC_LOG_ANALYZER_URL', plugin_dir_url( __FILE__ ) );
define( 'AILWC_LOG_ANALYZER_OPTION', 'ailwc_log_analyzer_settings' );

require_once AILWC_LOG_ANALYZER_PATH . 'includes/class-plugin.php';
require_once AILWC_LOG_ANALYZER_PATH . 'includes/class-log-parser.php';
require_once AILWC_LOG_ANALYZER_PATH . 'includes/class-ai-client.php';
require_once AILWC_LOG_ANALYZER_PATH . 'includes/class-analysis-engine.php';
require_once AILWC_LOG_ANALYZER_PATH . 'includes/class-log-integration.php';
require_once AILWC_LOG_ANALYZER_PATH . 'includes/class-admin-interface.php';

register_activation_hook( __FILE__, array( 'AILWC_Log_Analyzer\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AILWC_Log_Analyzer\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( AILWC_Log_Analyzer\Plugin::instance(), 'init' ) );
