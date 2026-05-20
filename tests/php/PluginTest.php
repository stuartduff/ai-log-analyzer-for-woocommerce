<?php
/**
 * Unit tests for Plugin::current_user_can_analyze().
 *
 * @package AI_Log_Analyzer
 */

namespace AI_Log_Analyzer\Tests;

use AI_Log_Analyzer\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['test_caps']    = array();
		$GLOBALS['test_options'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['test_caps']    = array();
		$GLOBALS['test_options'] = array();
	}

	private function set_caps( array $caps ): void {
		$GLOBALS['test_caps'] = $caps;
	}

	// -------------------------------------------------------------------------
	// Blocked — no manage_woocommerce
	// -------------------------------------------------------------------------

	public function test_returns_false_when_user_lacks_manage_woocommerce(): void {
		$this->set_caps( array( 'manage_options' => true, 'prompt_ai' => true ) );
		$this->assertFalse( Plugin::current_user_can_analyze() );
	}

	// -------------------------------------------------------------------------
	// Allowed — administrator path (manage_options)
	// -------------------------------------------------------------------------

	public function test_returns_true_for_administrator_without_prompt_ai(): void {
		$this->set_caps( array( 'manage_woocommerce' => true, 'manage_options' => true ) );
		$this->assertTrue( Plugin::current_user_can_analyze() );
	}

	public function test_returns_true_for_administrator_with_prompt_ai(): void {
		$this->set_caps( array( 'manage_woocommerce' => true, 'manage_options' => true, 'prompt_ai' => true ) );
		$this->assertTrue( Plugin::current_user_can_analyze() );
	}

	// -------------------------------------------------------------------------
	// Allowed — prompt_ai path (non-admin with explicit AI capability)
	// -------------------------------------------------------------------------

	public function test_returns_true_when_user_has_manage_woocommerce_and_prompt_ai(): void {
		$this->set_caps( array( 'manage_woocommerce' => true, 'prompt_ai' => true ) );
		$this->assertTrue( Plugin::current_user_can_analyze() );
	}

	// -------------------------------------------------------------------------
	// Shop manager — controlled by allow_shop_managers setting
	// -------------------------------------------------------------------------

	public function test_returns_false_for_shop_manager_when_setting_disabled(): void {
		$this->set_caps( array( 'manage_woocommerce' => true ) );
		$GLOBALS['test_options'][ AI_LOG_ANALYZER_OPTION ] = array( 'allow_shop_managers' => false );
		$this->assertFalse( Plugin::current_user_can_analyze() );
	}

	public function test_returns_false_for_shop_manager_when_setting_absent(): void {
		$this->set_caps( array( 'manage_woocommerce' => true ) );
		$this->assertFalse( Plugin::current_user_can_analyze() );
	}

	public function test_returns_true_for_shop_manager_when_setting_enabled(): void {
		$this->set_caps( array( 'manage_woocommerce' => true ) );
		$GLOBALS['test_options'][ AI_LOG_ANALYZER_OPTION ] = array( 'allow_shop_managers' => true );
		$this->assertTrue( Plugin::current_user_can_analyze() );
	}
}
