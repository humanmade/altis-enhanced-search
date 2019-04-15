<?php

namespace HM\Platform\Enhanced_Search;

use function HM\Platform\register_module;

// Don't self-initialize if this is not a Platform execution.
if ( ! function_exists( 'add_action' ) ) {
	return;
}

add_action( 'hm-platform.modules.init', function () {
	$default_settings = [
		'enabled' => true,
		'index-documents' => true,
	];
	register_module( 'search', __DIR__, 'Search', $default_settings, function () {
		add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
		add_filter( 'hm_platform_healthchecks', __NAMESPACE__ . '\\add_elasticsearch_healthcheck' );
	} );
} );
