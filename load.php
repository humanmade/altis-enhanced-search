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
	$options = [
		'defaults' => $default_settings,
	];
	Altis\register_module( 'search', __DIR__, 'Search', $options, __NAMESPACE__ . '\\bootstrap' );
} );
