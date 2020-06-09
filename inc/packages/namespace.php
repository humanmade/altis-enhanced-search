<?php
/**
 * Altis Search Packages.
 *
 * @package altis/search
 */

namespace Altis\Enhanced_Search\Packages;

use Altis;
use Aws\ElasticsearchService\ElasticsearchServiceClient;

/**
 * Bind hooks for ElasticSearch Packages.
 *
 * @return void
 */
function setup() {
	add_action( 'network_admin_menu', __NAMESPACE__ . '\\admin_menu' );
	add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu' );
}

/**
 * Get Elasticsearch Service Client.
 *
 * @return ElasticsearchServiceClient
 */
function get_aws_client() : ElasticsearchServiceClient {
	return Altis\get_aws_sdk()->createElasticsearchService( [
		'version' => '2015-01-01',
	] );
}

/**
 * Add network admin page.
 *
 * @return void
 */
function admin_menu() {
	add_menu_page(
		__( 'Search Configuration', 'altis' ),
		__( 'Search', 'altis' ),
		'manage_options',
		'search-config',
		__NAMESPACE__ . '\\admin_page',
		'dashicons-search'
	);
}

function admin_page() {
	echo '<h1>search config</h1>';
}
