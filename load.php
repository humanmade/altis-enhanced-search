<?php

// phpcs:ignore
namespace Altis\Enhanced_Search;

use function Altis\register_module;

function register() {
	$default_settings = [
		'enabled' => true,
		'index-documents' => true,
		'slowlog_thresholds' => true,
	];
	register_module( 'search', __DIR__, 'Search', $default_settings, __NAMESPACE__ . '\\bootstrap' );
}

// Don't self-initialize if this is not an Altis execution.
if ( ! function_exists( 'add_action' ) ) {
	return;
}

add_action( 'altis.modules.init', __NAMESPACE__ . '\\register' );
