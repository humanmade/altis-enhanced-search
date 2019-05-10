<?php

namespace HM\Platform\Enhanced_Search;

use Aws\Credentials;
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use EP_Dashboard;
use EP_Feature;
use function HM\Platform\get_config;
use function HM\Platform\get_environment_type;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use WP_Error;
use WP_Query;

function bootstrap() {
	if ( ! defined( 'ELASTICSEARCH_HOST' ) ) {
		return;
	}
	if ( ! defined( 'EP_HOST' ) ) {
		define( 'EP_HOST', sprintf( '%s://%s:%d', ELASTICSEARCH_PORT === 443 ? 'https' : 'http', ELASTICSEARCH_HOST, ELASTICSEARCH_PORT ) );
	}

	if ( ! defined( 'EP_IS_NETWORK' ) ) {
		define( 'EP_IS_NETWORK', true );
	}

	// Disable being able to use the admin to run a full data sync.
	if ( ! defined( 'EP_DASHBOARD_SYNC' ) ) {
		define( 'EP_DASHBOARD_SYNC', false );
	}
	add_filter( 'http_request_args', __NAMESPACE__ . '\\on_http_request_args', 10, 2 );
	add_filter( 'ep_pre_request_url', function ( $url ) {
		return set_url_scheme( $url, ELASTICSEARCH_PORT === 443 ? 'https' : 'http' );
	});
	add_action( 'ep_remote_request', __NAMESPACE__ . '\\log_remote_request_errors' );
	add_filter( 'posts_request', __NAMESPACE__ . '\\noop_wp_query_on_failed_ep_request', 11, 2 );
	add_filter( 'found_posts_query', __NAMESPACE__ . '\\noop_wp_query_on_failed_ep_request', 6, 2 );
	add_filter( 'ep_admin_wp_query_integration', '__return_true' );
	add_filter( 'ep_indexable_post_status', __NAMESPACE__ . '\\get_elasticpress_indexable_post_statuses' );
	add_filter( 'ep_indexable_post_types', __NAMESPACE__ . '\\get_elasticpress_indexable_post_types' );
	add_filter( 'ep_feature_active', __NAMESPACE__ . '\\override_elasticpress_feature_activation', 10, 3 );

	require_once dirname( __DIR__ ) . '/vendor/10up/elasticpress/elasticpress.php';

	// Now ElasticPress has been included, we can remove some of it's filters.

	// Remove Admin UI for ElasticPress
	remove_action( 'network_admin_menu', [ EP_Dashboard::factory(), 'action_admin_menu' ] );
	remove_action( 'admin_bar_menu', [ EP_Dashboard::factory(), 'action_network_admin_bar_menu' ], 50 );

}

function on_http_request_args( $args, $url ) {
	$host = parse_url( $url, PHP_URL_HOST );

	if ( ELASTICSEARCH_HOST !== $host ) {
		return $args;
	}

	if ( get_environment_type() === 'local' ) {
		return $args;
	}

	return sign_wp_request( $args, $url );
}

/**
 * Sign requests made to Elasticsearch
 *
 * @param array $args
 * @param string $url
 * @return array
 */
function sign_wp_request( array $args, string $url ) : array {
	if ( isset( $args['headers']['Host'] ) ) {
		unset( $args['headers']['Host'] );
	}
	if ( is_array( $args['body'] ) ) {
		$args['body'] = http_build_query( $args['body'], null, '&' );
	}
	$request = new Request( $args['method'], $url, $args['headers'], $args['body'] );
	$signed_request = sign_psr7_request( $request );
	$args['headers']['Authorization'] = $signed_request->getHeader( 'Authorization' )[0];
	$args['headers']['X-Amz-Date'] = $signed_request->getHeader( 'X-Amz-Date' )[0];
	if ( $signed_request->getHeader( 'X-Amz-Security-Token' ) ) {
		$args['headers']['X-Amz-Security-Token'] = $signed_request->getHeader( 'X-Amz-Security-Token' )[0];
	}
	return $args;
}

/**
 * Sign a request object with authentication headers for sending to Elasticsearch.
 *
 * @param RequestInterface $request
 * @return RequestInterface
 */
function sign_psr7_request( RequestInterface $request ) : RequestInterface {
	if ( get_environment_type() === 'local' ) {
		return $request;
	}

	$signer = new SignatureV4( 'es', HM_ENV_REGION );
	if ( defined( 'ELASTICSEARCH_AWS_KEY' ) ) {
		$credentials = new Credentials\Credentials( ELASTICSEARCH_AWS_KEY, ELASTICSEARCH_AWS_SECRET );
	} else {
		$provider = CredentialProvider::defaultProvider();
		$credentials = call_user_func( $provider )->wait();
	}
	$signed_request = $signer->signRequest( $request, $credentials );

	return $signed_request;
}


function log_remote_request_errors( array $request ) {
	if ( is_wp_error( $request['request'] ) ) {
		trigger_error( sprintf( 'Error in ElasticPress request: %s (%s)', $request['request']->get_error_message(), $request['request']->get_error_code() ), E_USER_WARNING );
	}
}

/**
 * Default ElasticPress functionality is to fall-back to MySQL search when queries fail. We want to instead
 * no-op the query when this happens, as we don't want to put lots of load on to MySQL.
 *
 * @param string $request
 * @param WP_Query $query
 * @return string
 */
function noop_wp_query_on_failed_ep_request( string $request, WP_Query $query ) : string {
	if ( ! isset( $query->elasticsearch_success ) || $query->elasticsearch_success === true ) {
		return $request;
	}

	global $wpdb;
	return "SELECT * FROM $wpdb->posts WHERE 1=0";
}

function noop_wp_query_found_rows_on_failed_ep_request( string $sql, WP_Query $query ) : string {
	if ( ! isset( $query->elasticsearch_success ) || $query->elasticsearch_success === true ) {
		return $sql;
	}
	return '';
}

/**
 * Add the elasticsearch check to the Platform healthchecks.
 *
 * @param array $checks
 * @return array
 */
function add_elasticsearch_healthcheck( array $checks ) : array {
	$checks['elasticsearch'] = run_elasticsearch_healthcheck();
	$checks['elasticpress-index'] = run_elasticpress_indexed_healthcheck();

	return $checks;
}

/**
 * Run ElasticSearch health check.
 */
function run_elasticsearch_healthcheck() {
	$host = get_elasticsearch_url();
	$response = wp_remote_get( $host . '/_cluster/health' );
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'elasticsearch-unhealthy', $response->get_error_message() );
	}

	$body = wp_remote_retrieve_body( $response );
	if ( is_wp_error( $body ) ) {
		return new WP_Error( 'elasticsearch-unhealthy', $body->get_error_message() );
	}

	return true;
}

/**
 * Check if ElasticPress has been indexed.
 */
function run_elasticpress_indexed_healthcheck() {
	$last_sync = get_site_option( 'ep_last_sync', false );
	if ( ! $last_sync ) {
		return new WP_Error( 'elasticsearch-index-not-populated', 'ElasticPress last sync is not set.' );
	}

	return true;
}

/**
 * Override the indexed post statuses from ElasticPress.
 *
 * By default, ElasticPress only indexes public content, but
 * we want to index all content as we are using ElasticPress
 * in the WordPress admin too.
 *
 * @param array $statuses
 * @return array
 */
function get_elasticpress_indexable_post_statuses( array $statuses ) : array {
	return [ 'any' ];
}

/**
 * Override the indexed post types from ElasticPress.
 *
 * By default, ElasticPress only indexes public content, but
 * we want to index all content as we are using ElasticPress
 * in the WordPress admin too.
 *
 * @param array $statuses
 * @return array
 */
function get_elasticpress_indexable_post_types( array $types ) : array {
	return [ 'any' ];
}

/**
 * Override the elasticpress features should be enabled.
 *
 * @param boolean $is_active
 * @param array $settings
 * @param EP_Feature $feature
 * @return void
 */
function override_elasticpress_feature_activation( bool $is_active, array $settings, EP_Feature $feature ) {
	$config = get_config()['modules']['search'];
	$features_activated = [
		'search'        => true,
		'related_posts' => false,
		'documents'     => $config['index-documents'],
		'facets'        => false,
	];

	if ( ! isset( $features_activated[ $feature->slug ] ) ) {
		return $is_active;
	}

	return $features_activated[ $feature->slug ];
}

/**
 * Get the URL to the elasticsearch cluster.
 *
 * The URL will have no trailing slash.
 *
 * @return string
 */
function get_elasticsearch_url() : string {
	$host = sprintf( '%s://%s:%d', ELASTICSEARCH_PORT === 443 ? 'https' : 'http', ELASTICSEARCH_HOST, ELASTICSEARCH_PORT );
	return $host;
}
