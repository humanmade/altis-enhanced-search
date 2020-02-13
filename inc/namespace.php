<?php

namespace Altis\Enhanced_Search;

use Aws\Credentials;
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use const Altis\ROOT_DIR;
use ElasticPress_CLI_Command;
use EP_Dashboard;
use EP_Feature;
use EP_Features;

use function Altis\get_config;
use function Altis\get_environment_type;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use WP_CLI;
use WP_Error;
use WP_Query;

function bootstrap() {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_elasticpress' );
	add_filter( 'altis_healthchecks', __NAMESPACE__ . '\\add_elasticsearch_healthcheck' );

	// Load debug bar for ElasticPress if enabled in the config.
	if ( get_config()['modules']['search']['enabled'] ?? false ) {
		add_action( 'muplugins_loaded', __NAMESPACE__ . '\\load_debug_bar_elasticpress', 0 );
	}
}

/**
 * Load and configure Elasticpress.
 */
function load_elasticpress() {
	if ( ! defined( 'ELASTICSEARCH_HOST' ) || ! ELASTICSEARCH_HOST ) {
		return;
	}
	if ( ! defined( 'EP_HOST' ) ) {
		define( 'EP_HOST', sprintf( '%s://%s:%d', ELASTICSEARCH_PORT === 443 ? 'https' : 'http', ELASTICSEARCH_HOST, ELASTICSEARCH_PORT ) );
	}

	if ( ! defined( 'EP_IS_NETWORK' ) ) {
		define( 'EP_IS_NETWORK', true );
	}

	// Set index prefix from env if found. Used for separating test indexes.
	if ( getenv( 'EP_INDEX_PREFIX' ) && ! defined( 'EP_INDEX_PREFIX' ) ) {
		define( 'EP_INDEX_PREFIX', getenv( 'EP_INDEX_PREFIX' ) );
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
	add_filter( 'ep_config_mapping', __NAMESPACE__ . '\\enable_slowlog_thresholds' );
	add_filter( 'ep_admin_notice_type', __NAMESPACE__ . '\\remove_ep_dashboard_notices', 20 );

	require_once ROOT_DIR . '/vendor/10up/elasticpress/elasticpress.php';

	// Now ElasticPress has been included, we can remove some of it's filters.

	// Remove Admin UI for ElasticPress
	remove_action( 'network_admin_menu', [ EP_Dashboard::factory(), 'action_admin_menu' ] );
	remove_action( 'admin_bar_menu', [ EP_Dashboard::factory(), 'action_network_admin_bar_menu' ], 50 );

	// Don't set up features during install.
	if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
		remove_action( 'init', [ EP_Features::factory(), 'handle_feature_activation' ], 0 );
		remove_action( 'init', [ EP_Features::factory(), 'setup_features' ], 0 );
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		// Raise error reporting threshold for the index command as it will generate
		// a benign warning when the index doesn't already exist.
		WP_CLI::add_hook( 'before_invoke:elasticpress index', function () {
			error_reporting( E_ERROR );
		} );
		// Index after install.
		WP_CLI::add_hook( 'after_invoke:core multisite-install', __NAMESPACE__ . '\\setup_elasticpress_on_install' );
	}

	// Map site language to Elasticsearch analyzer.
	add_filter( 'ep_analyzer_language', __NAMESPACE__ . '\\elasticpress_analyzer_language', 10, 2 );
}

/**
 * Load Debug Bar for ElasticPress.
 */
function load_debug_bar_elasticpress() {
	require_once ROOT_DIR . '/vendor/10up/debug-bar-elasticpress/debug-bar-elasticpress.php';
}

function on_http_request_args( $args, $url ) {
	// @codingStandardsIgnoreLine
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
 * @param RequestInterface $request The request object to sign.
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
	$request_response_code = (int) wp_remote_retrieve_response_code( $request['request'] );
	$is_valid_res = ( $request_response_code >= 200 && $request_response_code <= 299 );

	if ( is_wp_error( $request['request'] ) ) {
		trigger_error( sprintf( 'Error in ElasticPress request: %s (%s)', $request['request']->get_error_message(), $request['request']->get_error_code() ), E_USER_WARNING );
	} elseif ( ! $is_valid_res ) {
		trigger_error( sprintf( 'Error in ElasticPress request: %s (%s)', wp_remote_retrieve_body( $request['request'] ), $request_response_code ), E_USER_WARNING );
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
 * Add the elasticsearch check to the Altis healthchecks.
 *
 * @param array $checks
 * @return array
 */
function add_elasticsearch_healthcheck( array $checks ) : array {
	$checks['elasticsearch'] = run_elasticsearch_healthcheck();
	$checks['elasticpress-index'] = run_elasticpress_indexed_healthcheck();
	$checks['elasticpress-synced'] = run_elasticpress_synced_healthcheck();

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
 * Check if ElasticPress index exists.
 */
function run_elasticpress_indexed_healthcheck() {
	$status = ep_index_exists();
	if ( ! $status ) {
		return new WP_Error( 'elasticsearch-index-not-found', 'ElasticPress Index does not exist.' );
	}

	return true;
}

/**
 * Check if ElasticPress is synced with the index.
 */
function run_elasticpress_synced_healthcheck() {
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
 * Enables the required settings for slowlog queries to be captured.
 *
 * @param array $mapping
 * @return array
 */
function enable_slowlog_thresholds( array $mapping ) : array {
	$config = get_config()['modules']['search'];
	if ( isset( $config['slowlog_thresholds'] ) && (bool) $config['slowlog_thresholds'] ) {
		$mapping['settings']['index.search.slowlog.threshold.query.info'] = '2s';
		$mapping['settings']['index.search.slowlog.threshold.query.warn'] = '5s';
		$mapping['settings']['index.search.slowlog.threshold.fetch.info'] = '2s';
		$mapping['settings']['index.search.slowlog.threshold.fetch.warn'] = '5s';
	}
	return $mapping;
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

/**
 * When WordPress is installed via WP-CLI, run the ElasticPress setup.
 */
function setup_elasticpress_on_install() {
	$ep = new ElasticPress_CLI_Command();
	WP_CLI::line( 'Setting up ElasticPress...' );

	// Elevate error reporting level as there's a benign warning thrown on first index.
	$error_reporting_level = error_reporting();
	error_reporting( E_ERROR );

	// Create the index.
	$ep->index( [], [
		'setup' => true,
		'network-wide' => true,
	] );

	// Reset error reporting level.
	error_reporting( $error_reporting_level );
}

/**
 * Return the correct analyzer language based on the sites configured language code.
 *
 * @param string $language THe current language.
 * @param string $filter The specific filter.
 *
 * @return string The language name to use.
 */
function elasticpress_analyzer_language( string $language, string $filter ) : string {

	// All the languages supported by v5.3 of elastic search.
	$supported_languages = [
		'ar'             => 'arabic',
		'hy'             => 'armenian',
		'eu'             => 'basque',
		'pt_br'          => 'brazilian',
		'bg_bg'          => 'bulgarian',
		'ca'             => 'catalan',
		'cs_cz'          => 'czech',
		'da_dk'          => 'danish',
		'nl_be'          => 'dutch',
		'nl_nl'          => 'dutch',
		'nl_nl_formal'   => 'dutch',
		'en_au'          => 'english',
		'en_ca'          => 'english',
		'en_gb'          => 'english',
		'en_nz'          => 'english',
		'en_us'          => 'english',
		'en_za'          => 'english',
		'fi'             => 'finnish',
		'fr_be'          => 'french',
		'fr_ca'          => 'french',
		'fr_fr'          => 'french',
		'gl_es'          => 'galician',
		'de_ch'          => 'german',
		'de_ch_informal' => 'german',
		'de_de'          => 'german',
		'de_de_formal'   => 'german',
		'el'             => 'greek',
		'hi_in'          => 'hindi',
		'hu_hu'          => 'hungarian',
		'id_id'          => 'indonesian',
		'it_it'          => 'italian',
		'lv'             => 'latvian',
		'lt_lt'          => 'lithuanian',
		'nb_no'          => 'norwegian',
		'nn_no'          => 'norwegian',
		'fa_ir'          => 'persian',
		'pt_pt'          => 'portuguese',
		'pt_pt_ao90'     => 'portuguese',
		'ro_ro'          => 'romanian',
		'ru_ru'          => 'russian',
		'ckb'            => 'sorani',
		'es_ar'          => 'spanish',
		'es_cl'          => 'spanish',
		'es_co'          => 'spanish',
		'es_cr'          => 'spanish',
		'es_es'          => 'spanish',
		'es_gt'          => 'spanish',
		'es_mx'          => 'spanish',
		'es_pe'          => 'spanish',
		'es_ve'          => 'spanish',
		'sv_se'          => 'swedish',
		'tr_tr'          => 'turkish',
		'th'             => 'thai',
		'zh_cn'          => 'cjk', // chinese (china).
		'zh_hk'          => 'cjk', // chinese (hong kong).
		'zh_tw'          => 'cjk', // chinese (taiwan).
		'ja'             => 'cjk', // japanese.
		'ko_kr'          => 'cjk', // korean.
	];

	$lang_code = get_option( 'WPLANG' );
	$lang_code = strtolower( $lang_code );
	if ( isset( $supported_languages[ $lang_code ] ) ) {
		return $supported_languages[ $lang_code ];
	}

	return $language;
}

/**
 * Filter the ElasticPress dashboard notices.
 *
 * @param string $notice The notice ID.
 * @return string
 */
function remove_ep_dashboard_notices( string $notice ) : string {
	$hidden = [
		'sync-disabled-auto-activate',
		'sync-disabled-no-sync',
		'sync-disabled-upgrade',
	];

	if ( in_array( $notice, $hidden, true ) ) {
		return '';
	}

	return $notice;
}
