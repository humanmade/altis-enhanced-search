<?php
/**
 * Altis Search Module.
 *
 * @package altis/search
 */

namespace Altis\Enhanced_Search;

use Altis;

add_action( 'altis.modules.init', function () {
	$default_settings = [
		'enabled' => true,
		'index-documents' => false,
		'related-posts' => false,
		'facets' => false,
		'woocommerce' => false,
		'autosuggest' => false,
		'slowlog_thresholds' => true,
		'mode' => 'simple',
		'strict' => true,
		'field-boost' => [],
		'fuzziness' => 'auto:4,7',
		'users' => true,
		'terms' => true,
		'inline-index-settings' => true,
	];
	Altis\register_module( 'search', __DIR__, 'Search', $default_settings, __NAMESPACE__ . '\\bootstrap' );
} );

// Ensure ElasticPress commands don't use debug mode as it can result
// in out of memory errors on big sites as queries are logged during bulk updates.
add_action( 'altis.loaded_autoloader', function () {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! isset( $_SERVER['argv'] ) ) {
		return;
	}
	if ( ( $_SERVER['argv'][1] ?? '' ) === 'elasticpress' ) {
		defined( 'WP_DEBUG' ) or define( 'WP_DEBUG', false );
	}
}, 5 );
