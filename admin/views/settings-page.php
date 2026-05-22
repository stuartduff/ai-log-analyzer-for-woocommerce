<?php
/**
 * Settings page template — WordPress Settings API form.
 *
 * @package AILWC_Log_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'ailwc_log_analyzer' );
		do_settings_sections( 'ailwc_log_analyzer' );
		submit_button( __( 'Save Settings', 'ai-log-analyzer-for-woocommerce' ) );
		?>
	</form>
</div>
