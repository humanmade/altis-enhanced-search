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
		'index-documents' => true,
		'related-posts' => false,
		'facets' => false,
		'woocommerce' => false,
		'autosuggest' => false,
		'slowlog_thresholds' => true,
		'mode' => 'simple',
		'strict' => true,
		'field-boost' => [],
	];
	Altis\register_module( 'search', __DIR__, 'Search', $default_settings, __NAMESPACE__ . '\\bootstrap' );
} );
