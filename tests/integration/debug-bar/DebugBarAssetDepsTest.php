<?php
/**
 * Test the Debug Bar ElasticPress panel's asset dependencies.
 *
 * phpcs:disable WordPress.Files, HM.Files, HM.Functions.NamespacedFunctions, WordPress.NamingConventions
 */

namespace DebugBar;

/**
 * Test the Debug Bar ElasticPress panel's asset dependencies.
 *
 * The panel is constructed on every request, but Query Monitor only registers
 * its `query-monitor` script and style handles when its HTML dispatcher will
 * actually render. Enqueuing against a handle that was never registered emits
 * a _doing_it_wrong() notice from WP_Dependencies::all_deps() in WordPress
 * 6.9.1+, so the panel has to declare that dependency conditionally.
 */
class DebugBarAssetDepsTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Tester
	 *
	 * @var \IntegrationTester
	 */
	protected $tester;

	/**
	 * Reset the dependency registries before each test.
	 *
	 * WP_Dependencies dedupes its missing-dependency notice per handle per
	 * instance, so a shared $wp_styles would make the second test here pass for
	 * the wrong reason. Start each test from a clean instance.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'Debug_Bar_Panel' ) ) {
			$this->markTestSkipped( 'Requires the dev-tools module with Query Monitor enabled.' );
		}

		$GLOBALS['wp_styles'] = new \WP_Styles();
		$GLOBALS['wp_scripts'] = new \WP_Scripts();
	}

	/**
	 * Build the panel.
	 *
	 * @return \EP_Debug_Bar_ElasticPress
	 */
	protected function get_panel() {
		\Altis\Enhanced_Search\load_debug_bar_elasticpress();

		return new \EP_Debug_Bar_ElasticPress();
	}

	/**
	 * The panel must not depend on `query-monitor` when it isn't registered.
	 *
	 * @return void
	 */
	public function testNoQueryMonitorDependencyWhenUnregistered() {
		$this->assertFalse( wp_style_is( 'query-monitor', 'registered' ), 'Precondition: query-monitor is not registered.' );

		$this->get_panel()->enqueue_scripts_styles();

		$this->assertNotContains( 'query-monitor', wp_styles()->registered['debug-bar-elasticpress']->deps );
		$this->assertNotContains( 'query-monitor', wp_scripts()->registered['debug-bar-elasticpress']->deps );
	}

	/**
	 * The panel must still depend on `query-monitor` when it is registered.
	 *
	 * Guards against over-correcting: the dependency is what keeps the panel
	 * styles loading after Query Monitor's own.
	 *
	 * @return void
	 */
	public function testQueryMonitorDependencyWhenRegistered() {
		wp_register_style( 'query-monitor', 'https://example.com/qm.css', [], '1.0' );
		wp_register_script( 'query-monitor', 'https://example.com/qm.js', [], '1.0', false );

		$this->get_panel()->enqueue_scripts_styles();

		$this->assertContains( 'query-monitor', wp_styles()->registered['debug-bar-elasticpress']->deps );
		$this->assertContains( 'query-monitor', wp_scripts()->registered['debug-bar-elasticpress']->deps );
	}

	/**
	 * Printing the panel's assets must not raise a doing_it_wrong notice.
	 *
	 * WP_UnitTestCase fails the test on any unexpected _doing_it_wrong(), which
	 * is precisely the notice this guards against.
	 *
	 * @return void
	 */
	public function testPrintingAssetsRaisesNoNotice() {
		$this->get_panel()->enqueue_scripts_styles();

		ob_start();
		wp_styles()->do_items();
		wp_scripts()->do_items();
		ob_end_clean();

		$this->assertTrue( wp_style_is( 'debug-bar-elasticpress', 'done' ) );
	}
}
