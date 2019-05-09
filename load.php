<?php

namespace Altis\Enhanced_Search;

use function Altis\register_module;

// Don't self-initialize if this is not an Altis execution.
if ( ! function_exists( 'add_action' ) ) {
	return;
}

add_action( 'altis.modules.init', function () {
	$default_settings = [
		'enabled' => true,
		'index-documents' => true,
	];
	register_module( 'search', __DIR__, 'Search', $default_settings, function () {
		add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
		add_filter( 'altis_healthchecks', __NAMESPACE__ . '\\add_elasticsearch_healthcheck' );
	} );
} );
