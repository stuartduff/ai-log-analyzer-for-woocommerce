<?php
/**
 * Analysis results mount-point template.
 *
 * Outputs only the React root element. All UI is rendered by the analyze
 * React app (build/analyze.js). The element is appended to admin_footer so
 * it is available regardless of which WC log handler is active.
 *
 * @package AILWC_Log_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="ai-log-analyzer-results"></div>
