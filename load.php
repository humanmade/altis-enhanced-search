<?php

// phpcs:ignore
namespace Altis\Enhanced_Search;

use function Altis\register_module;

add_action( 'altis.modules.init', function () {
	$default_settings = [
		'enabled'            => true,
		'index-documents'    => true,
		'related-posts'      => false,
		'facets'             => false,
		'woocommerce'        => false,
		'autosuggest'        => false,
		'slowlog_thresholds' => true,
	];
	register_module( 'search', __DIR__, 'Search', $default_settings, __NAMESPACE__ . '\\bootstrap' );
} );
