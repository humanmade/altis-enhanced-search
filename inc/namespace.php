<?php
/**
 * Altis Search.
 *
 * @package altis/search
 */

namespace Altis\Enhanced_Search;

use Altis;
use Aws\Credentials;
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use ElasticPress\Elasticsearch;
use ElasticPress\Feature;
use ElasticPress\Features;
use ElasticPress\Indexables;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use WP_CLI;
use WP_Error;
use WP_Query;
use WP_REST_Server;
use WP_Term_Query;
use WP_User_Query;

const SORTABLE_MAX_LENGTH = 10922;

/**
 * Bootstrap search module.
 *
 * @return void
 */
function bootstrap() {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_elasticpress', 4 );
	add_action( 'plugins_loaded', function () {
		// Run healthchecks on indexes after ElasticPress is loaded.
		add_filter( 'altis_healthchecks', __NAMESPACE__ . '\\add_elasticsearch_healthcheck' );
	} );

	// Load debug bar for ElasticPress if Query Monitor is enabled in the config and not a CLI request.
	if (
		( Altis\get_config()['modules']['dev-tools']['enabled'] ?? false ) &&
		( Altis\get_config()['modules']['dev-tools']['query-monitor'] ?? false ) &&
		( ! defined( 'WP_CLI' ) || ! WP_CLI )
	) {
		// Enable debugging for Elastic Press Debug Bar to display query logs.
		if ( ! defined( 'WP_EP_DEBUG' ) ) {
			define( 'WP_EP_DEBUG', true );
		}
		add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_debug_bar_elasticpress', 0 );
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
		define( 'EP_HOST', get_elasticsearch_url() );
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

	add_filter( 'http_request_args', __NAMESPACE__ . '\\remove_ep_search_term_header', 1 );
	add_filter( 'http_request_args', __NAMESPACE__ . '\\on_http_request_args', 10, 2 );
	add_filter( 'ep_pre_request_url', function ( $url ) {
		return set_url_scheme( $url, ELASTICSEARCH_PORT === 443 ? 'https' : 'http' );
	});
	add_action( 'ep_remote_request', __NAMESPACE__ . '\\log_remote_request_errors', 10, 2 );

	// Noop queries that tried to use ES, but failed. Priority 11 to be after ElasticPress.
	add_filter( 'posts_pre_query', __NAMESPACE__ . '\\noop_wp_query_on_failed_ep_request', 11, 2 );

	add_filter( 'ep_admin_wp_query_integration', '__return_true' );
	add_filter( 'ep_ajax_wp_query_integration', '__return_true' );
	add_filter( 'ep_indexable_post_status', __NAMESPACE__ . '\\get_elasticpress_indexable_post_statuses' );
	add_filter( 'ep_indexable_post_types', __NAMESPACE__ . '\\get_elasticpress_indexable_post_types' );
	add_filter( 'ep_indexable_taxonomies', __NAMESPACE__ . '\\get_elasticpress_indexable_taxonomies' );
	add_filter( 'ep_feature_active', __NAMESPACE__ . '\\override_elasticpress_feature_activation', 10, 3 );
	add_filter( 'ep_config_mapping', __NAMESPACE__ . '\\enable_slowlog_thresholds' );
	add_filter( 'ep_admin_notices', __NAMESPACE__ . '\\remove_ep_dashboard_notices' );

	// Modify the default search query to use preset modes.
	add_filter( 'ep_formatted_args_query', __NAMESPACE__ . '\\enhance_search_query', 10, 2 );
	add_filter( 'ep_term_formatted_args_query', __NAMESPACE__ . '\\enhance_term_search_query', 10, 2 );
	add_filter( 'ep_user_formatted_args_query', __NAMESPACE__ . '\\enhance_user_search_query', 10, 2 );

	// Default enable autosuggest matching for user & term searches.
	add_action( 'pre_get_users', __NAMESPACE__ . '\\enable_user_query_autosuggest' );
	add_action( 'pre_get_terms', __NAMESPACE__ . '\\enable_term_query_autosuggest' );

	// Add custom field boosting.
	add_filter( 'ep_weighting_default_post_type_weights', __NAMESPACE__ . '\\add_field_boost_defaults', 10, 2 );

	// Ensure search fields are properly mapped to values.
	add_filter( 'ep_search_fields', __NAMESPACE__ . '\\filter_search_fields', 10, 2 );

	// Modify the decay function paramters to use values from the Altis module config.
	add_filter( 'epwr_scale', __NAMESPACE__ . '\\apply_date_decay_config_values' );
	add_filter( 'epwr_decay', __NAMESPACE__ . '\\apply_date_decay_config_values' );
	add_filter( 'epwr_offset', __NAMESPACE__ . '\\apply_date_decay_config_values' );
	add_filter( 'epwr_boost_mode', __NAMESPACE__ . '\\apply_date_decay_config_values' );

	// Search against better default fields.
	add_filter( 'ep_weighting_default_post_type_weights', __NAMESPACE__ . '\\search_filtered_post_content', 100 );

	// Fix the mime type search query.
	add_filter( 'ep_formatted_args', __NAMESPACE__ . '\\fix_mime_type_query', 11, 2 );

	// Combine matching post type weighting queries. Weighting added on priority 20 by EP.
	add_filter( 'ep_formatted_args', __NAMESPACE__ . '\\combine_weighting_queries', 21 );

	// Fix term query sorting.
	add_filter( 'ep_term_formatted_args', __NAMESPACE__ . '\\fix_term_query_sorting', 20 );

	// Back compat for ElasticPress v2 - change post index name to old version.
	add_filter( 'ep_index_name', __NAMESPACE__ . '\\filter_index_name' );

	// Ensure the same attachments ingest pipeline ID is used for the whole network.
	add_filter( 'ep_documents_pipeline_id', __NAMESPACE__ . '\\filter_documents_pipeline_id' );

	// Ensure non ElasticPress indexes are not affected by global edits using *.
	add_filter( 'ep_pre_request_url', __NAMESPACE__ . '\\protect_non_ep_indexes', 10, 5 );

	// Sanitize query arguments.
	add_action( 'pre_get_posts', __NAMESPACE__ . '\\sanitize_query_args', 1000 );

	// Ensure upgrades aren't attempted during install due to db access.
	if ( defined( 'WP_INITIAL_INSTALL' ) && WP_INITIAL_INSTALL ) {
		add_filter( 'pre_site_option_ep_version', '__return_false' );
		add_filter( 'pre_site_option_ep_last_sync', '__return_false' );
	}

	require_once Altis\ROOT_DIR . '/vendor/10up/elasticpress/elasticpress.php';

	// Now ElasticPress has been included, we can remove some of it's filters.

	// Remove Admin UI for ElasticPress.
	remove_action( 'network_admin_menu', 'ElasticPress\\Dashboard\\action_admin_menu' );
	remove_action( 'admin_bar_menu', 'ElasticPress\\Dashboard\\action_network_admin_bar_menu', 50 );

	// Don't set up features during install.
	if ( defined( 'WP_INITIAL_INSTALL' ) && WP_INITIAL_INSTALL ) {
		remove_action( 'init', [ Features::factory(), 'handle_feature_activation' ], 0 );
		remove_action( 'init', [ Features::factory(), 'setup_features' ], 0 );
	}

	// Add default options on install.
	add_action( 'wp_install', __NAMESPACE__ . '\\on_wp_install' );
	add_action( 'ep_remote_request', __NAMESPACE__ . '\\on_delete_index', 11, 2 );

	// Ensure indexes are created after install.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		// Index after install.
		WP_CLI::add_hook( 'after_invoke:core multisite-install', __NAMESPACE__ . '\\setup_elasticpress_on_install' );
	}

	// Improve default analyzer with multilingual support. After EP filters run.
	add_filter( 'ep_config_mapping', __NAMESPACE__ . '\\elasticpress_mapping', 30, 2 );

	// Filter Options for Facet component settings.
	add_filter( 'site_option_ep_feature_settings', __NAMESPACE__ . '\\filter_facet_settings' );
	add_filter( 'option_ep_feature_settings', __NAMESPACE__ . '\\filter_facet_settings' );

	// Change custom search results icon.
	add_filter( 'register_post_type_args', __NAMESPACE__ . '\\custom_search_results_post_type_args', 10, 2 );

	// Configure features.
	add_action( 'init', __NAMESPACE__ . '\\configure_documents_feature', 1 );

	// Use ElasticPress request intercepting to chunk large requests.
	add_filter( 'ep_intercept_remote_request', '__return_true' );
	add_filter( 'ep_do_intercept_request', __NAMESPACE__ . '\\split_large_ep_request', 10, 4 );

	// Handle autosuggest requests.
	add_action( 'template_redirect', __NAMESPACE__ . '\\handle_autosuggest_endpoint' );

	// Set up packages feature.
	Packages\bootstrap();
}

/**
 * Load Debug Bar for ElasticPress.
 */
function load_debug_bar_elasticpress() {
	require_once Altis\ROOT_DIR . '/vendor/humanmade/debug-bar-elasticpress/debug-bar-elasticpress.php';
}

/**
 * Modify the default behaviour of the documents feature.
 *
 * @return void
 */
function configure_documents_feature() {
	$documents_feature = Features::factory()->get_registered_feature( 'documents' );

	if ( ! $documents_feature ) {
		return;
	}

	// Remove default document search integration.
	remove_filter( 'pre_get_posts', [ $documents_feature, 'setup_document_search' ] );
}

/**
 * Remove the EP-Search-Term header.
 *
 * This header is only used for the elasticpress.io hosted service and as
 * it stores the search query unencoded causes the request signing to fail
 * when searching with unicode characters.
 *
 * @param array $args EP request args.
 * @return array
 */
function remove_ep_search_term_header( array $args ) : array {
	if ( isset( $args['headers']['EP-Search-Term'] ) ) {
		unset( $args['headers']['EP-Search-Term'] );
	}

	return $args;
}

/**
 * Process HTTP request arguments.
 *
 * @param array $args Request arguments.
 * @param string $url Request URL.
 * @return array
 */
function on_http_request_args( array $args, string $url ) : array {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	$host = parse_url( $url, PHP_URL_HOST );

	if ( ELASTICSEARCH_HOST !== $host ) {
		return $args;
	}

	if ( Altis\get_environment_type() === 'local' || ! in_array( Altis\get_environment_architecture(), [ 'ec2', 'ecs' ], true ) ) {
		return $args;
	}

	return sign_wp_request( $args, $url );
}

/**
 * Sign requests made to Elasticsearch.
 *
 * @param array $args Request arguments.
 * @param string $url Request URL.
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
	if ( Altis\get_environment_type() === 'local' ) {
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

/**
 * Log ElasticPress request errors.
 *
 * @param array $request Request data.
 * @param string|null $type The type of request.
 * @return void
 */
function log_remote_request_errors( array $request, ?string $type = null ) {
	$request_response_body = wp_remote_retrieve_body( $request['request'] );
	$request_response_code = (int) wp_remote_retrieve_response_code( $request['request'] );
	$is_valid_res = ( $request_response_code >= 200 && $request_response_code <= 299 );
	$type = $type ?: 'unknown_request_type';

	// Backup check for errors, sometimes the response is ok but the query
	// response JSON contains errors.
	$has_errors = strpos( $request_response_body, '"errors":true' ) !== false;

	if ( is_wp_error( $request['request'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( sprintf( 'Error in ElasticPress request: %s %s (%s)', $type, $request['request']->get_error_message(), $request['request']->get_error_code() ), E_USER_WARNING );
	} elseif ( ! $is_valid_res || $has_errors ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( sprintf( 'Error in ElasticPress request: %s %s (%s)', $type, $request_response_body, $request_response_code ), E_USER_WARNING );
	}
}

/**
 * Default ElasticPress functionality is to fall-back to MySQL search when queries fail. We want to instead
 * no-op the query when this happens, as we don't want to put lots of load on to MySQL.
 *
 * @param ?array $posts List of posts or null if request failed.
 * @param WP_Query $query The current query object.
 * @return array
 */
function noop_wp_query_on_failed_ep_request( $posts, WP_Query $query ) : ?array {
	if ( $posts === null && $query->elasticsearch_success === false ) {
		return [];
	}

	return $posts;
}

/**
 * Modify the mime type query to support truncated forms.
 *
 * @param array $query The Elasticsearch query.
 * @param array $args The WP_Query query vars.
 * @return array
 */
function fix_mime_type_query( array $query, array $args ) : array {
	if ( empty( $args['post_mime_type'] ) ) {
		return $query;
	}

	if ( ! isset( $query['post_filter'] ) ) {
		return $query;
	}

	$filter = $query['post_filter']['bool']['must'] ?? [];

	// Collect mime types in post filter.
	$mime_types = [];

	foreach ( $filter as $index => $sub_query ) {
		// Extract list of mime types if present.
		if ( ! empty( $sub_query['terms']['post_mime_type'] ) ) {
			$mime_types = $sub_query['terms']['post_mime_type'];
		}
		// Extract base regex mime type if present.
		if ( ! empty( $sub_query['regexp']['post_mime_type'] ) ) {
			$mime_types = [ $sub_query['regexp']['post_mime_type'] ];
		}

		if ( empty( $mime_types ) ) {
			continue;
		}

		// Remove the existing mime type filter when we encounter it.
		unset( $filter[ $index ] );
		break;
	}

	if ( empty( $mime_types ) ) {
		return $query;
	}

	// Collect fully qualified mime types here e.g. image/jpeg.
	$terms = [];
	// Collect prefix types here e.g. image, image/*.
	$prefixes = [];

	// Process mime types into prefixes and terms.
	foreach ( $mime_types as $type ) {
		// Remove trailing slashes and wildcards.
		$type = rtrim( $type, './*' );
		if ( strpos( $type, '/' ) !== false ) {
			$terms[] = $type;
		} else {
			$prefixes[] = $type;
		}
	}

	// Add the new prefix and terms queries together.
	$mime_type_filter = [];
	if ( ! empty( $terms ) ) {
		$mime_type_filter[] = [ 'terms' => [ 'post_mime_type' => $terms ] ];
	}
	if ( ! empty( $prefixes ) ) {
		foreach ( $prefixes as $prefix ) {
			$mime_type_filter[] = [ 'prefix' => [ 'post_mime_type' => $prefix ] ];
		}
	}

	// Add a compound query for our terms and prefixes.
	if ( ! empty( $mime_type_filter ) ) {
		$filter[] = [ 'bool' => [ 'should' => $mime_type_filter ] ];
		$query['post_filter']['bool']['must'] = array_values( $filter );
	}

	return $query;
}

/**
 * Add default initial options and settings on install.
 *
 * @return void
 */
function on_wp_install() {
	// This option is used to determine the index name for backwards compat.
	set_index_version( 3 );
}

/**
 * Set the index version to match ElasticPress version when
 * indexes are deleted.
 *
 * @param array $query The Elasticsearch query.
 * @param string|null $type The remote request type.
 * @return void
 */
function on_delete_index( $query, ?string $type ) {
	if ( $type !== 'delete_index' ) {
		return;
	}
	// Set the version to 3.
	if ( get_index_version() === 2 ) {
		set_index_version( 3 );
	}
}

/**
 * Set the index version for the current site.
 *
 * @param integer $version The version number.
 * @return void
 */
function set_index_version( int $version ) {
	update_option( 'altis_search_index_version', $version );
}

/**
 * Get the index version for the current site.
 *
 * Defaults to 2 for ElasticPress version 2.
 *
 * @return int
 */
function get_index_version() : int {
	return get_option( 'altis_search_index_version', null ) ?? 2;
}

/**
 * Modify default index names.
 *
 * ElasticPress adds the indexable object type to index names. We can maintain backwards
 * compatibility by filtering the posts indexable index name to remove this type.
 *
 * @param string $index The index name.
 * @return string
 */
function filter_index_name( string $index ) : string {
	// Back compat for Altis v3 & ElasticPress 2.x
	// Version 3 of ElasticPress introduces Indexables allowing for user
	// and term search integration. The new index names follow the pattern
	// <site>-<indexable>-<blog-id> instead of <site>-<blog-id>.
	if ( get_index_version() === 2 && strpos( $index, '-post' ) !== false ) {
		$old_index = str_replace( '-post', '', $index );
		if ( Elasticsearch::factory()->index_exists( $old_index ) ) {
			return $old_index;
		} else {
			set_index_version( 3 );
		}
	}

	// Add ep- prefix to easily determine ElasticPress managed indexes.
	return "ep-{$index}";
}

/**
 * Modify the documents ingest pipeline ID.
 *
 * The documents ingest pipeline does not need to be site specific
 * as it is always the same.
 *
 * @param string $id Current pipeline ID.
 * @return string
 */
function filter_documents_pipeline_id( string $id ) : string {
	if ( get_index_version() === 2 ) {
		return $id;
	}
	return 'attachments';
}

/**
 * Ensure ElasticPress requests do not impact on indexes the plugin does not manage.
 *
 * @param string $url The full Elasticsearch request URL.
 * @param integer $failures Number of failures.
 * @param string $host Elasticsearch host name.
 * @param string $path Request path.
 * @param array $args Remote request arguments.
 * @return string
 */
function protect_non_ep_indexes( string $url, int $failures, string $host, string $path, array $args ) : string {
	// ElasticPress requests that work on all indexes may begin with * so we protect
	// indexes by enforcing the `ep-` prefix added by our filter.
	if ( strpos( trim( $path, '/' ), '*' ) === 0 ) {
		$url = str_replace( "{$host}/*", "{$host}/ep-*", $url );
	}

	return $url;
}

/**
 * Add the elasticsearch check to the Altis healthchecks.
 *
 * @param array $checks Healthchecks array.
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
	$sites = get_sites();
	$not_exists = [];
	foreach ( $sites as $site ) {
		if ( Indexables::factory()->get( 'post' ) && ! Indexables::factory()->get( 'post' )->index_exists( $site->blog_id ) ) {
			$not_exists[] = $site->domain . $site->path;
		}
	}

	if ( $not_exists ) {
		return new WP_Error(
			'elasticsearch-index-not-found',
			sprintf( 'ElasticPress Index does not exist for site(s) %s', implode( ', ', $not_exists ) )
		);
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
 * @param array $statuses List of psot status strings to index.
 * @return array
 */
function get_elasticpress_indexable_post_statuses( array $statuses ) : array {
	return get_post_stati();
}

/**
 * Override the indexed post types from ElasticPress.
 *
 * By default, ElasticPress only indexes public content, but
 * we want to index all content as we are using ElasticPress
 * in the WordPress admin too.
 *
 * @param array $types List of post types to index.
 * @return array
 */
function get_elasticpress_indexable_post_types( array $types ) : array {
	$post_types = get_post_types();
	$post_types = array_filter( $post_types, function ( $type ) {
		return ! in_array( $type, [
			'ep-synonym',
		], true );
	} );
	return $post_types;
}

/**
 * Override indexable taxonomies from ElasticPress.
 *
 * By default, ElasticPress only indexes public content, but
 * we want to index all content as we are using ElasticPress
 * in the WordPress admin too.
 *
 * @param array $taxonomies List of registered taxnonomy names.
 * @return array
 */
function get_elasticpress_indexable_taxonomies( array $taxonomies ) : array {
	return get_taxonomies();
}

/**
 * Override the elasticpress features should be enabled.
 *
 * @param boolean $is_active True if the feature is active.
 * @param array $settings Feature settings array.
 * @param Feature $feature The feature object.
 * @return bool
 */
function override_elasticpress_feature_activation( bool $is_active, array $settings, Feature $feature ) : bool {
	$config = Altis\get_config()['modules']['search'];

	$features_activated = [
		'search' => true,
		'related_posts' => (bool) ( $config['related-posts'] ?? false ),
		'documents' => (bool) ( $config['index-documents'] ?? true ),
		'facets' => (bool) ( $config['facets'] ?? false ),
		'woocommerce' => (bool) ( $config['woocommerce'] ?? false ),
		'autosuggest' => (bool) ( $config['autosuggest'] ?? false ),
		// Force protected content feature off as we're overriding indexable types & statuses anyway.
		// Enabling this feature causes all WP_Query calls for protected content post types to use
		// Elasticsearch, even if not performing a search.
		'protected_content' => false,
		'terms' => (bool) ( $config['terms'] ?? true ),
		'users' => (bool) ( $config['users'] ?? true ),
	];

	if ( ! isset( $features_activated[ $feature->slug ] ) ) {
		return $is_active;
	}

	if ( $feature->slug === 'autosuggest' && $features_activated[ $feature->slug ] === true && ! defined( 'EP_AUTOSUGGEST_ENDPOINT' ) ) {
		define( 'EP_AUTOSUGGEST_ENDPOINT', get_home_url( null, '/autosuggest/' ) );
	}

	return $features_activated[ $feature->slug ];
}

/**
 * Helper function to retrieve an option from the search config.
 *
 * @param string $option_key The option name.
 * @param mixed|null $default_value The default option value.
 * @return mixed|null
 */
function get_search_config_option( string $option_key, $default_value = null ) {
	$config = Altis\get_config()['modules']['search'];

	return $config[ $option_key ] ?? $default_value;
}

/**
 * Enables the required settings for slowlog indexing / queries to be captured.
 *
 * @param array $mapping ElasticSearch index mapping.
 * @return array
 */
function enable_slowlog_thresholds( array $mapping ) : array {
	$config = Altis\get_config()['modules']['search'];
	if ( isset( $config['slowlog_thresholds'] ) && (bool) $config['slowlog_thresholds'] ) {
		$mapping['settings']['index.search.slowlog.threshold.query.info'] = '2s';
		$mapping['settings']['index.search.slowlog.threshold.query.warn'] = '5s';
		$mapping['settings']['index.search.slowlog.threshold.fetch.info'] = '2s';
		$mapping['settings']['index.search.slowlog.threshold.fetch.warn'] = '5s';
		$mapping['settings']['index.indexing.slowlog.threshold.index.info'] = '5s';
		$mapping['settings']['index.indexing.slowlog.threshold.index.warn'] = '10s';
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
	$host = sprintf(
		'%s://%s%s',
		ELASTICSEARCH_PORT === 443 ? 'https' : 'http',
		ELASTICSEARCH_HOST,
		! in_array( ELASTICSEARCH_PORT, [ 80, 443 ], true ) ? ( ':' . ELASTICSEARCH_PORT ) : ''
	);
	return $host;
}

/**
 * When WordPress is installed via WP-CLI, run the ElasticPress setup.
 */
function setup_elasticpress_on_install() {
	WP_CLI::line( 'Setting up ElasticPress...' );
	$response = WP_CLI::runcommand( 'elasticpress index --setup --network-wide', [
		'return' => true,
	] );
	WP_CLI::line( $response );
	WP_CLI::line( WP_CLI::colorize( '%GElasticPress configured.%n' ) );
}

/**
 * Return the correct analyzer language based on the sites configured language code.
 *
 * @return string The language name to use.
 */
function elasticpress_analyzer_language() : string {

	// All the languages supported by v5.3 of elastic search.
	$supported_languages = [
		'ar'             => 'ar', // arabic.
		'hy'             => 'hy', // armenian.
		'eu'             => 'eu', // basque.
		'pt_br'          => 'br', // brazilian portuguese.
		'bg_bg'          => 'bg', // bulgarian.
		'bn_bd'          => 'bn', // bengali.
		'ca'             => 'ca', // catalan.
		'cs_cz'          => 'cs', // czech.
		'da_dk'          => 'da', // danish.
		'nl_be'          => 'nl', // dutch.
		'nl_nl'          => 'nl',
		'nl_nl_formal'   => 'nl',
		'en_au'          => 'en', // english.
		'en_ca'          => 'en',
		'en_gb'          => 'en',
		'en_nz'          => 'en',
		'en_us'          => 'en',
		'en_za'          => 'en',
		'fi'             => 'fi', // finnish.
		'fr_be'          => 'fr', // french.
		'fr_ca'          => 'fr',
		'fr_fr'          => 'fr',
		'ga'             => 'ga', // irish.
		'gl_es'          => 'gl', // galician.
		'de_at'          => 'de', // german.
		'de_ch'          => 'de',
		'de_ch_informal' => 'de',
		'de_de'          => 'de',
		'de_de_formal'   => 'de',
		'el'             => 'el', // greek.
		'hi_in'          => 'hi', // hindi.
		'hu_hu'          => 'hu', // hungarian.
		'id_id'          => 'id', // indonesian.
		'it_it'          => 'it', // italian.
		'lv'             => 'lv', // latvian.
		'lt_lt'          => 'lt', // lithuanian.
		'nb_no'          => 'nb', // norwegian bokmål.
		'nn_no'          => 'nn', // norwegian nynorsk.
		'fa_ir'          => 'fa', // persian.
		'pl_pl'          => 'pl', // polish.
		'pt_pt'          => 'pt', // portuguese.
		'pt_pt_ao90'     => 'pt',
		'ro_ro'          => 'ro', // romanian.
		'ru_ru'          => 'ru', // russian.
		'ru_ua'          => 'ua', // ukrainian.
		'ckb'            => 'ckb', // sorani / kurdish.
		'es_ar'          => 'es', // spanish.
		'es_cl'          => 'es',
		'es_co'          => 'es',
		'es_cr'          => 'es',
		'es_es'          => 'es',
		'es_gt'          => 'es',
		'es_mx'          => 'es',
		'es_pe'          => 'es',
		'es_ve'          => 'es',
		'sv_se'          => 'sv', // swedish.
		'tr_tr'          => 'tr', // turkish.
		'th'             => 'th', // thai.
		'zh_cn'          => 'zh', // chinese (china).
		'zh_hk'          => 'zh', // chinese (hong kong).
		'zh_tw'          => 'zh', // chinese (taiwan).
		'ja'             => 'ja', // japanese.
		'ko_kr'          => 'ko', // korean.
	];

	/**
	 * Get value from db as get_locale() doesn't always return the current
	 * value when using switch_to_blog().
	 */
	$locale = get_option( 'WPLANG', get_site_option( 'WPLANG' ) ) ?: 'en_US';
	$locale = strtolower( $locale );
	if ( isset( $supported_languages[ $locale ] ) ) {
		return $supported_languages[ $locale ];
	}

	return 'default';
}

/**
 * Add multilingual analyzers to the ElasticPress index settings
 * and override the default analyzer.
 *
 * @param array $mapping Mapping array.
 * @param string|null $index The current index this mapping is for.
 * @return array
 */
function elasticpress_mapping( array $mapping, ?string $index = null ) : array {
	// Get site level indexables like post, term etc...
	$indexables = Indexables::factory()->get_all( null, true );
	// Add the global indexables like user.
	$indexables = array_merge( $indexables, Indexables::factory()->get_all( true, true ) );
	$escaped_indexables = array_map( function ( $item ) {
		return preg_quote( $item, '/' );
	}, $indexables );
	// Derive current mapping type.
	preg_match( '/.+-(' . implode( '|', $escaped_indexables ) . ')(-\d+)?$/', $index, $matches );
	if ( isset( $matches[1] ) ) {
		$mapping_type = $matches[1];
	} else {
		trigger_error( sprintf( 'No matching indexable type for the index named "%s" found', $index ) );
		return $mapping;
	}

	$es_version = Elasticsearch::factory()->get_elasticsearch_version();

	// Merge filters, tokenizers and analyzers from JSON config.
	$settings = Analysis\get_analyzers();

	// Ensure a sensible max shingle diff.
	if ( ! isset( $mapping['settings']['index.max_shingle_diff'] ) ) {
		$mapping['settings']['index.max_shingle_diff'] = 8;
	}

	$mapping['settings']['analysis']['filter'] = array_merge(
		$mapping['settings']['analysis']['filter'] ?? [],
		$settings['filter'] ?? []
	);

	$mapping['settings']['analysis']['char_filter'] = array_merge(
		$mapping['settings']['analysis']['char_filter'] ?? [],
		$settings['char_filter'] ?? []
	);

	$mapping['settings']['analysis']['analyzer'] = array_merge(
		$mapping['settings']['analysis']['analyzer'] ?? [],
		$settings['analyzer'] ?? []
	);

	$mapping['settings']['analysis']['tokenizer'] = array_merge(
		$mapping['settings']['analysis']['tokenizer'] ?? [],
		$settings['tokenizer'] ?? []
	);

	$mapping['settings']['analysis']['normalizer'] = array_merge(
		$mapping['settings']['analysis']['normalizer'] ?? [],
		$settings['normalizer'] ?? []
	);

	// Set the shingle analyzer to use icu tokenizer.
	$mapping['settings']['analysis']['analyzer']['shingle_analyzer'] = [
		'type' => 'custom',
		'tokenizer' => 'icu_tokenizer',
		'filter' => [ 'icu_normalizer', 'icu_folding', 'shingle_filter' ],
	];

	// Get analyzer language.
	$language = elasticpress_analyzer_language();

	// Replace default analyzer.
	if ( isset( $mapping['settings']['analysis']['analyzer'][ $language . '_analyzer' ] ) ) {
		$mapping['settings']['analysis']['analyzer']['default'] = $mapping['settings']['analysis']['analyzer'][ $language . '_analyzer' ];
		$mapping['settings']['analysis']['analyzer']['default']['char_filter'] = array_merge(
			$mapping['settings']['analysis']['analyzer']['default']['char_filter'] ?? [],
			[ 'html_strip' ]
		);
	}

	// Remove deprecated _all fields mapping parameter.
	if ( $mapping['mappings']['post']['_all'] ?? false ) {
		unset( $mapping['mappings']['post']['_all'] );
	}
	if ( $mapping['mappings']['term']['_all'] ?? false ) {
		unset( $mapping['mappings']['term']['_all'] );
	}
	if ( $mapping['mappings']['user']['_all'] ?? false ) {
		unset( $mapping['mappings']['user']['_all'] );
	}

	// Unset the post title analyzer override to make it use the default.
	if ( $mapping['mappings']['post']['properties']['post_title']['fields']['post_title']['analyzer'] ?? false ) {
		unset( $mapping['mappings']['post']['properties']['post_title']['fields']['post_title']['analyzer'] );
	}

	// Handle user dictionary for Japanese sites.
	if ( $language === 'ja' ) {
		$is_network_japanese = get_site_option( 'WPLANG', 'en_US' ) === 'ja';
		$user_dictionary_package_id = Packages\get_package_id( 'uploaded-user-dictionary' );
		if ( ! $user_dictionary_package_id && $is_network_japanese ) {
			$user_dictionary_package_id = Packages\get_package_id( 'uploaded-user-dictionary', true );
		}

		// Check for a package ID and add it to the kuromoji tokenizer.
		if ( $user_dictionary_package_id ) {
			$mapping['settings']['analysis']['tokenizer']['kuromoji']['user_dictionary'] = $user_dictionary_package_id;
		}
	}

	// Add a default search analyzer if any custom stopwords or synonyms are provided.
	//
	// Synonyms and stopwords are quick enough to be applied at search time and avoid
	// increasing the index size unnecessarily.
	$is_network_language = get_site_option( 'WPLANG', 'en_US' ) === get_option( 'WPLANG', 'en_US' );
	$synonyms = [];
	$stopwords = [];

	foreach ( [ 'synonyms', 'stopwords' ] as $type ) {
		foreach ( [ 'uploaded', 'manual' ] as $sub_type ) {
			// Get package file path.
			$package_id = Packages\get_package_id( "{$sub_type}-{$type}" );
			// Check for network default.
			if ( ! $package_id && $is_network_language ) {
				$package_id = Packages\get_package_id( "{$sub_type}-{$type}", true );
			}

			// Check for a package ID.
			if ( ! $package_id ) {
				continue;
			}

			switch ( $type ) {
				case 'synonyms':
					$synonyms[ "{$sub_type}_{$type}_filter" ] = [
						'type' => 'synonym_graph',
						'synonyms_path' => $package_id,
					];
					break;
				case 'stopwords':
					$stopwords[ "{$sub_type}_{$type}_filter" ] = [
						'type' => 'stop',
						'ignore_case' => true,
						'stopwords_path' => $package_id,
					];
					break;
			}
		}
	}

	if ( ! empty( $synonyms ) || ! empty( $stopwords ) ) {
		$mapping['settings']['analysis']['filter'] = array_merge(
			$mapping['settings']['analysis']['filter'],
			$synonyms,
			$stopwords
		);

		// Copy default analyzer to default search.
		$mapping['settings']['analysis']['analyzer']['default_search'] = $mapping['settings']['analysis']['analyzer']['default'];

		// Add stopwords after `icu_normalizer` if present, otherwise prepend.
		// This ensures text is lowercased and full-width characters converted to
		// half-width while still retaining accents. This is should also ensure
		// stopwords and stemming have not yet been applied.
		if ( in_array( 'icu_normalizer', $mapping['settings']['analysis']['analyzer']['default_search']['filter'], true ) ) {
			array_splice(
				$mapping['settings']['analysis']['analyzer']['default_search']['filter'],
				array_search( 'icu_normalizer', $mapping['settings']['analysis']['analyzer']['default_search']['filter'], true ) + 1,
				0,
				array_keys( $stopwords )
			);
		} else {
			$mapping['settings']['analysis']['analyzer']['default_search']['filter'] = array_merge(
				array_keys( $stopwords ),
				$mapping['settings']['analysis']['analyzer']['default_search']['filter']
			);
		}

		// Add synonyms before `icu_folding` if present, otherwise append.
		// This ensures text is lowercased and full-width characters converted to
		// half-width while still retaining accents. This is should also ensure
		// default stopwords and minimal or light stemming have been applied.
		if ( in_array( 'icu_folding', $mapping['settings']['analysis']['analyzer']['default_search']['filter'], true ) ) {
			array_splice(
				$mapping['settings']['analysis']['analyzer']['default_search']['filter'],
				array_search( 'icu_folding', $mapping['settings']['analysis']['analyzer']['default_search']['filter'], true ),
				0,
				array_keys( $synonyms )
			);
		} else {
			$mapping['settings']['analysis']['analyzer']['default_search']['filter'] = array_merge(
				$mapping['settings']['analysis']['analyzer']['default_search']['filter'],
				array_keys( $synonyms )
			);
		}
	}

	if ( version_compare( $es_version, '7', '<' ) ) {
		$new_mapping = $mapping['mappings'][ $mapping_type ];
	} else {
		$new_mapping = $mapping['mappings'];
	}

	// Add autosuggest ngram analyzer by default, used for attachment, user and term search.
	$autosuggest_fields = get_autosuggest_fields();
	$search_analyzer = ! empty( $mapping['settings']['analysis']['analyzer']['default_search'] ) ? 'default_search' : 'default';
	foreach ( $autosuggest_fields[ $mapping_type ] as $field ) {

		// Ensure each field is represented in the mapping to avoid generating the
		// default mapping.
		$field_mapping = [
			'type' => 'text',
			'fields' => [
				'suggest' => [
					'type' => 'text',
					'analyzer' => 'edge_ngram_analyzer',
					'search_analyzer' => $search_analyzer,
				],
			],
		];

		// Field mapping already exists so just merge our suggest field.
		if ( isset( $new_mapping['properties'][ $field ] ) ) {
			if ( $new_mapping['properties'][ $field ]['type'] === 'text' ) {
				$new_mapping['properties'][ $field ] = array_merge_recursive_distinct(
					$new_mapping['properties'][ $field ],
					$field_mapping
				);
			}
			continue;
		}

		// Split field on dots in case this is a nested property.
		$sub_fields = explode( '.', $field );

		// Check dynamic templates to get the intended mapping.
		// https://www.elastic.co/guide/en/elasticsearch/reference/6.8/dynamic-templates.html for ref.
		$start_field = $sub_fields[0];
		$end_field = $sub_fields[ count( $sub_fields ) - 1 ];
		foreach ( ( $new_mapping['dynamic_templates'] ?? [] ) as $template ) {
			$template = wp_parse_args( $template[ array_keys( $template )[0] ], [
				'match_pattern' => null,
				'match' => '',
				'unmatch' => '',
				'path_match' => '',
				'path_unmatch' => '',
			] );

			// If `match_pattern` is `regex` then check `match` against the end field as a regular expression.
			if ( $template['match_pattern'] === 'regex' && ! preg_match( "/{$template['match']}/", $end_field ) ) {
				continue;
			}
			// Check end field against `match` only allowing for wildcards.
			if ( $template['match'] && $template['match_pattern'] !== 'regex' && ! fnmatch( $template['match'], $end_field ) ) {
				continue;
			}
			// Check end field against `unmatch` to exclude patterns after matching.
			if ( $template['unmatch'] && fnmatch( $template['unmatch'], $end_field ) ) {
				continue;
			}
			// Check full field path for wildcard matches against `path_match`.
			if ( $template['path_match'] && ! fnmatch( $template['path_match'], $field ) ) {
				continue;
			}
			// Check full field path against `path_unmatch` to exclude patterns after matching.
			if ( $template['path_unmatch'] && fnmatch( $template['path_unmatch'], $field ) ) {
				continue;
			}

			// We have a matching template so update the field mapping for a text field or object property.
			if ( ( $template['mapping']['type'] ?? false ) === 'text' ) {
				$field_mapping = array_merge_recursive_distinct( $template['mapping'], $field_mapping );
			}
			if ( $template['mapping']['properties'][ $end_field ] ?? false ) {
				$template['mapping']['properties'][ $end_field ] = array_merge_recursive_distinct(
					$template['mapping']['properties'][ $end_field ],
					$field_mapping
				);
				$field_mapping = [ 'properties' => $template['mapping']['properties'] ];
				// Remove end field from $sub_fields if matched.
				array_pop( $sub_fields );
			}
		}

		// This is a path mapping.
		if ( count( $sub_fields ) > 1 ) {
			// Remove the first part of the path as it is handled below.
			array_shift( $sub_fields );
			$sub_fields = array_reverse( $sub_fields );
			$field_mapping = array_reduce( $sub_fields, function ( $carry, $field ) {
				return [ 'properties' => [ $field => $carry ] ];
			}, $field_mapping );
		}

		// Merge mapping.
		$new_mapping['properties'] = array_merge_recursive_distinct(
			$new_mapping['properties'],
			[ $start_field => $field_mapping ]
		);
	}

	// Ensure text fields with a field name have the `.sortable` field.
	// Some ES version mappings are missing this.
	foreach ( $new_mapping['properties'] as $name => $property ) {
		if ( $property['type'] === 'text' && isset( $property['fields'] ) ) {
			$new_mapping['properties'][ $name ]['fields'] = array_merge_recursive_distinct( $property['fields'], [
				'sortable' => [
					'type'         => 'keyword',
					'ignore_above' => SORTABLE_MAX_LENGTH,
					'normalizer'   => 'lowerasciinormalizer',
				],
			] );
		}
	}

	if ( version_compare( $es_version, '7', '<' ) ) {
		$mapping['mappings'][ $mapping_type ] = $new_mapping;
	} else {
		$mapping['mappings'] = $new_mapping;
	}

	return $mapping;
}

/**
 * Recursive array merge that does not convert non array types to array.
 *
 * @param array $array1 The first array to merge data into.
 * @param array $array2 The array to override the first array.
 * @return array
 */
function array_merge_recursive_distinct( array $array1, array $array2 ) : array {
	$merged = $array1;

	foreach ( $array2 as $key => $value ) {
		if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
			$merged[ $key ] = array_merge_recursive_distinct( $merged[ $key ], $value );
		} else {
			$merged[ $key ] = $value;
		}
	}

	return $merged;
}

/**
 * Filter the ElasticPress dashboard notices.
 *
 * @param array $notices The notice keys array.
 * @return array
 */
function remove_ep_dashboard_notices( array $notices ) : array {
	$hidden = [
		'auto_activate_sync',
		'no_sync',
		'upgrade_sync',
		'using_autosuggest_defaults',
		'es_above_compat',
	];

	return array_diff_key( $notices, array_flip( $hidden ) );
}

/**
 * Filter to inject the config setting in to the site options or options.
 *
 * @param mixed $value The option value.
 * @return mixed
 */
function filter_facet_settings( $value ) {
	$facet_settings = get_search_config_option( 'facets' );

	// Setting is not specified or set to false.
	if ( empty( $facet_settings ) ) {
		return $value;
	}

	// Facet settings do not exist. Facets are disabled.
	if ( empty( $value['facets'] ) ) {
		return $value;
	}

	// Override match-type property.
	$value['facets']['match_type'] = $facet_settings['match-type'] ?? 'all';

	return $value;
}

/**
 * Get the search fuzziness configuration.
 *
 * Fetches the "fuzziness" config value, parses and validates it returning
 * a value that can be used in Elasticsearch queries.
 *
 * @return array
 */
function get_fuzziness() : array {
	$fuzziness = Altis\get_config()['modules']['search']['fuzziness'] ?? 'auto:4,7';

	// Handle array format.
	if ( ! is_array( $fuzziness ) ) {
		$fuzziness = [
			'distance' => $fuzziness,
		];
	}

	// Set default values.
	$fuzziness = wp_parse_args( $fuzziness, [
		'distance' => 'auto:4,7',
		'prefix-length' => 1,
		'max-expansions' => 50,
		'transpositions' => true,
	] );

	// Validate distance value.
	if ( ! preg_match( '/^([0-2]|auto:\d+,\d+|auto)$/', $fuzziness['distance'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( sprintf( 'The provided fuzziness distance config option %s is invalid. This should be an integer from 0-2 or a string in the format "auto:[min],[max]". Defaulting to "auto:4,7"', $fuzziness ), E_USER_WARNING );
		$fuzziness['distance'] = 'auto:4,7';
	}

	// Ensure correct type for distance is returned.
	if ( is_numeric( $fuzziness['distance'] ) ) {
		$fuzziness['distance'] = (int) $fuzziness;
	}

	// Sanitize value types.
	$fuzziness['prefix-length'] = absint( $fuzziness['prefix-length'] );
	$fuzziness['max-expansions'] = absint( $fuzziness['max-expansions'] );
	$fuzziness['transpositions'] = (bool) $fuzziness['max-expansions'];

	return $fuzziness;
}

/**
 * Modify the default search query based on the configured mode.
 *
 * 'strict' = full phrase matching with automatic fuzziness based
 *            on query length.
 * 'loose' = boosted full phrase matching with fuzzy individual term
 *           matching.
 * 'advanced' = loose term matching with support for quoted terms,
 *              parentheses, and, or and negation operators and
 *              prefixed wildcard queries.
 *
 * @param array $query The ElasticSearch query.
 * @param array $args The WP_Query args for the current query.
 * @param string $type The type of object being searched, one of post, term or user.
 * @return array The modified ElasticSearch query.
 */
function enhance_search_query( array $query, array $args, string $type = 'post' ) : array {
	// In some cases like user queries this field is called search rather than 's'.
	if ( isset( $args['search'] ) ) {
		$args['s'] = $args['search'];
	}

	// Check we have a search query.
	if ( ! isset( $args['s'] ) || empty( $args['s'] ) ) {
		return $query;
	}

	// Get algorithm version.
	$algorithm_version = get_site_option( 'ep_search_algorithm_version', '3.5' );
	$algorithm_version = apply_filters( 'ep_search_algorithm_version', $algorithm_version );

	// Get config settings.
	$strict = Altis\get_config()['modules']['search']['strict'] ?? true;
	$mode = Altis\get_config()['modules']['search']['mode'] ?? 'simple';
	$fuzziness = get_fuzziness();

	// Get search fields.
	$search_fields = $query['bool']['should'][0]['multi_match']['fields'];

	if ( $mode === 'simple' && $strict && ( $type !== 'post' || version_compare( $algorithm_version, '3.5', 'lt' ) ) ) {
		// Remove the fuzzy matching of any word in the phrase.
		// Deprecated with ElasticPress 3.5 but leaving in in case the search algorithm filter is used.
		unset( $query['bool']['should'][2] );

		// Fill in new algorithm settings for non post indexables.
		if ( $type !== 'post' ) {
			$query['bool']['should'][1]['multi_match'] = [
				'query'  => $args['s'],
				'fields' => $search_fields,
				'type'   => 'phrase',
				'slop'   => 5,
			];
		} else {
			// Set the full phrase match fuzziness to auto, this will auto adjust
			// the allowed Levenshtein distance depending on the query length.
			// - 0-3 chars = 0 edits.
			// - 4-6 chars = 1 edit.
			// - 7+ chars = 2 edits.
			$query['bool']['should'][1]['multi_match']['fuzziness'] = $fuzziness['distance'];
			$query['bool']['should'][1]['multi_match']['prefix_length'] = $fuzziness['prefix-length'];
			$query['bool']['should'][1]['multi_match']['max_expansions'] = $fuzziness['max-expansions'];
			$query['bool']['should'][1]['multi_match']['fuzzy_transpositions'] = $fuzziness['transpositions'];
		}
	}

	if ( $mode === 'advanced' ) {
		$query['bool']['should'] = [
			get_advanced_query( $args, $search_fields, $strict ),
		];
	}

	// Add ngram search if 'autosuggest' query arg is true.
	$autosuggest_fields = get_autosuggest_fields( $type );
	$use_autosuggest = ( isset( $args['autosuggest'] ) && $args['autosuggest'] ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	if ( ! empty( $autosuggest_fields ) && $use_autosuggest ) {
		// Append the suggest sub field suffix.
		$autosuggest_fields = array_map( function ( $field ) {
			return "{$field}.suggest";
		}, $autosuggest_fields );

		$query['bool']['should'][] = [
			'multi_match' => [
				'query' => $args['s'],
				'fields' => $autosuggest_fields,
				'fuzziness' => $fuzziness['distance'],
				'prefix_length' => $fuzziness['prefix-length'],
				'max_expansions' => $fuzziness['max-expansions'],
				'fuzzy_transpositions' => $fuzziness['transpositions'],
			],
		];
	}

	// Ensure this is not a keyed array.
	$query['bool']['should'] = array_values( $query['bool']['should'] );

	return $query;
}

/**
 * Combine equivalent post type search queries to improve search performance.
 *
 * @param array $query The Elasticsearch query array.
 * @return array
 */
function combine_weighting_queries( array $query ) : array {
	// No need to do anything if weighting was switched off or not run yet for some reason.
	if ( ! did_action( 'ep_weighting_added' ) ) {
		return $query;
	}

	$has_function_score = isset( $query['query']['function_score'] );
	$queries = $has_function_score ? $query['query']['function_score']['query'] : $query['query'];

	// Check weighted query is present.
	if ( empty( $query['bool']['should'][0]['bool']['filter'][0]['match']['post_type.raw'] ?? false ) ) {
		return $query;
	}

	$combined = [];

	foreach ( $queries['bool']['should'] as $type_query ) {
		$post_type = $type_query['bool']['filter'][0]['match']['post_type.raw'];
		$query_json = wp_json_encode( $type_query['bool']['must'] );
		if ( ! isset( $combined[ $query_json ] ) ) {
			$combined[ $query_json ] = [];
		}
		$combined[ $query_json ][] = $post_type;
	}

	$queries['bool']['should'] = [];

	foreach ( $combined as $query_json => $post_types ) {
		$queries['bool']['should'][] = [
			'bool' => [
				'must' => json_decode( $query_json, ARRAY_A ),
				'filter' => [
					[
						'terms' => [
							'post_type.raw' => $post_types,
						],
					],
				],
			],
		];
	}

	if ( $has_function_score ) {
		$query['query']['function_score']['query'] = $queries;
	} else {
		$query['query'] = $queries;
	}

	return $query;
}

/**
 * Swaps the default search against post content to filtered content.
 *
 * This means that reusable blocks will be parsed along with short codes and
 * other functionalilty that can modify the end result of the post content.
 *
 * @param array $weight_config The default field weighting config.
 * @return array
 */
function search_filtered_post_content( array $weight_config ) : array {
	if ( ! isset( $weight_config['post_content'] ) ) {
		return $weight_config;
	}

	$weight_config['post_content_filtered'] = $weight_config['post_content'];
	unset( $weight_config['post_content'] );

	return $weight_config;
}

/**
 * Add our configured default boost to search fields.
 *
 * @param array $fields The default field weightings.
 * @return array
 */
function add_field_boost_defaults( array $fields ) : array {
	$field_boost = Altis\get_config()['modules']['search']['field-boost'] ?? [];
	$boosted_fields = array_keys( $field_boost );
	$existing_fields = array_keys( $fields );

	// Update existing defaults.
	foreach ( $existing_fields as $field ) {
		if ( in_array( $field, $boosted_fields, true ) ) {
			$fields[ $field ]['weight'] = floatval( $field_boost[ $field ] );
		}
	}

	// Add additional fields.
	foreach ( $boosted_fields as $field ) {
		if ( ! in_array( $field, $existing_fields, true ) ) {
			$fields[ $field ] = [
				'enabled' => true,
				'weight' => floatval( $field_boost[ $field ] ),
			];
		}
	}

	return $fields;
}

/**
 * Modify the default term search query based on the configured mode.
 *
 * @param array $query The ElasticSearch query.
 * @param array $args The WP_Term_Query args for the current query.
 * @return array The modified ElasticSearch query.
 */
function enhance_term_search_query( array $query, array $args ) : array {
	return enhance_search_query( $query, $args, 'term' );
}

/**
 * Modify the default term search query based on the configured mode.
 *
 * @param array $query The ElasticSearch query.
 * @param array $args The WP_User_Query args for the current query.
 * @return array The modified ElasticSearch query.
 */
function enhance_user_search_query( array $query, array $args ) : array {
	return enhance_search_query( $query, $args, 'user' );
}

/**
 * Enable autosuggest for user queries.
 *
 * @param WP_User_Query $user_query The user query object.
 * @return void
 */
function enable_user_query_autosuggest( WP_User_Query $user_query ) {
	if ( empty( $user_query->get( 'search' ) ) ) {
		return;
	}
	$user_query->set( 'autosuggest', true );
}

/**
 * Enable autosuggest for term queries.
 *
 * @param WP_Term_Query $term_query The term query object.
 * @return void
 */
function enable_term_query_autosuggest( WP_Term_Query $term_query ) {
	if ( empty( $term_query->query_vars['search'] ) ) {
		return;
	}
	$term_query->query_vars['autosuggest'] = true;
}

/**
 * Correct term query sorting for ES 7.
 *
 * @param array $query The full ES query in array format.
 * @return array
 */
function fix_term_query_sorting( array $query ) : array {
	if ( ! isset( $query['sort'] ) || ! is_array( $query['sort'] ) ) {
		return $query;
	}

	foreach ( $query['sort'] as $maybe_field => $sort_group ) {
		if ( is_numeric( $maybe_field ) ) {
			foreach ( $sort_group as $field => $sort ) {
				$field_name = strstr( $field, '.long', true );
				unset( $query['sort'][ $maybe_field ][ $field ] );
				$query['sort'][ $maybe_field ][ $field_name ?: $field ] = $sort;
			}
		} else {
			$field_name = strstr( $maybe_field, '.long', true );
			unset( $query['sort'][ $maybe_field ] );
			$query['sort'][ $field_name ?: $maybe_field ] = $sort_group;
		}
	}

	return $query;
}

/**
 * Ensure meta search fields use the correct value property.
 *
 * @param array $search_fields Fields to search against.
 * @return array
 */
function filter_search_fields( array $search_fields ) : array {
	return array_map( function ( $field ) {
		if ( fnmatch( 'meta.*', $field ) && ! fnmatch( 'meta.*.value', $field ) ) {
			$field .= '.value';
		}
		return $field;
	}, $search_fields );
}

/**
 * Use date decay settings from Altis config, if specified.
 *
 * This function retrieves each of the decay function's parameters.
 *
 * @var string "offset"     How far in the past a post must be before it starts to decay.
 * @var string "scale"      The reference interval of the date distribution
 * @var float  "decay"      Factor by which relevance decays at each interval from the offset.
 * @var sting  "boost_mode" How this score of this function affects overall score.
 *
 * @uses $wp_filter global, for detecting the current filter.
 * @param string|float $default_value Current value for the value being filtered.
 * @return string|float Updated value.
 */
function apply_date_decay_config_values( $default_value ) {

	// Ensure this is running on a filter for one of the allowed list of fields.
	$matched_filter = preg_match(
		'~epwr_(scale|decay|offset|boost_mode)~',
		current_filter(),
		$field
	);

	if ( ! $matched_filter ) {
		return $default_value;
	}

	// If the field exists in the module config, return the value from config rather than the default.
	$config = Altis\get_config();

	return $config['modules']['search']['date-decay'][ $field[1] ] ?? $default_value;
}

/**
 * A list of fields to enable autosuggestions for when searching.
 *
 * @param string|null $type Optional indexable type to get fields for.
 * @return array
 */
function get_autosuggest_fields( ?string $type = null ) : array {
	$fields = [];

	foreach ( Indexables::factory()->get_all( null, true ) as $indexable ) {
		$fields[ $indexable ] = [];
	}

	// Add default autosuggest fields.
	if ( isset( $fields['post'] ) ) {
		$fields['post'] = [ 'post_title' ];
	}
	if ( isset( $fields['term'] ) ) {
		$fields['term'] = [ 'name' ];
	}
	if ( isset( $fields['user'] ) ) {
		$fields['user'] = [
			'user_nicename',
			'display_name',
			'user_login',
			'meta.first_name.value',
			'meta.last_name.value',
			'meta.nickname.value',
		];
	}

	/**
	 * Filter the fields to use for autosuggest search behaviour.
	 *
	 * @param array $fields The field names to include in autosuggestions.
	 */
	$fields = apply_filters( 'altis.search.autosuggest_fields', $fields );

	foreach ( $fields as $object_type => $type_fields ) {
		/**
		 * Filter the fields to use for autosuggest search behaviour.
		 *
		 * @param array $type_fields The field names for a specific type to include in autosuggestions.
		 */
		$fields[ $object_type ] = apply_filters( "altis.search.autosuggest_{$object_type}_fields", $type_fields );
	}

	// Return specified type if present.
	if ( $type ) {
		return $fields[ $type ] ?? [];
	}

	return $fields;
}

/**
 * Build an Elasticsearch simple query string array.
 *
 * @param array $args The WP_Query args.
 * @param array $search_fields The fields being searched against.
 * @param bool $strict Whether to use stricter matching 'and' operator by default.
 * @return array
 */
function get_advanced_query( array $args, array $search_fields, bool $strict = false ) : array {

	// Deconstruct the quoted parts of the query.
	$query_pieces = preg_split( '/(?:\s*"([^"]+)"\s*|\s+)/', $args['s'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

	// Get fuzzy search config values.
	$fuzziness = get_fuzziness();

	// Rebuild the query string with default fuzziness and operator keyword conversion.
	$query_string = array_reduce( $query_pieces, function ( $query_string, $piece ) use ( $fuzziness ) {
		$piece_tokens = explode( ' ', trim( $piece ) );
		if ( count( $piece_tokens ) > 1 ) {
			// Reconstruct quoted phrases for exact matching.
			$query_piece = '"' . implode( ' ', $piece_tokens ) . '"';
		} else {
			if ( $piece === 'OR' ) {
				// Convert uppercase OR to operator.
				$query_piece = '|';
			} elseif ( $piece === 'AND' ) {
				// Convert uppercase AND to operator.
				$query_piece = '+';
			} elseif ( in_array( $piece, [ '|', '+', '-', '*', '(', ')', '~' ], true ) ) {
				// Preserve known operators.
				$query_piece = $piece;
			} elseif ( strpos( $piece, '~' ) === false ) {
				// Add fuzzy search values.
				if ( is_int( $fuzziness['distance'] ) ) {
					// Add fixed fuzziness value on single words over 3 characters.
					if ( mb_strlen( $piece ) > 3 && $fuzziness['distance'] > 0 ) {
						$query_piece = "({$piece}~{$fuzziness['distance']}|{$piece})";
					} else {
						$query_piece = $piece;
					}
				} else {
					// Add equivalent of automatic fuzziness on single words.
					if ( preg_match( '/(?:auto:(\d+),(\d+)|auto)/', $fuzziness['distance'], $matches ) ) {
						if ( mb_strlen( $piece ) < ( (int) $matches[1] ?? 3 ) ) {
							$query_piece = $piece;
						} elseif ( mb_strlen( $piece ) < ( (int) $matches[2] ?? 6 ) ) {
							$query_piece = "({$piece}~1|{$piece})";
						} else {
							$query_piece = "({$piece}~2|{$piece})";
						}
					} else {
						$query_piece = $piece;
					}
				}
			} else {
				$query_piece = $piece;
			}
		}
		return trim( "{$query_string} {$query_piece}" );
	}, '' );

	// Set default operator based on strict setting.
	$default_operator = $strict ? 'and' : 'or';

	$query = [
		'query' => $query_string,
		'fields' => $search_fields,
		'default_operator' => $default_operator,
		// Requires at least the first character to match when applying
		// fuzzy matching. This significantly speeds up queries and it is rare
		// for someone to get the first letter of their search term wrong e.g.
		// breif / brief are the more common types of misspelling.
		'fuzzy_prefix_length' => $fuzziness['prefix-length'],
		// The number of fuzzy terms to generate, defaults to 50 in Elasticsearch.
		// Lower numbers mean faster searches but reduced numbers of matches.
		'fuzzy_max_expansions' => $fuzziness['max-expansions'],
		// Whether to allow transposing letters while generating fuzzy terms
		// e.g. ab -> ba.
		'fuzzy_transpositions' => $fuzziness['transpositions'],
	];

	/**
	 * Filter the advanced search query options.
	 *
	 * This can be used to tweak the behaviopur of fuzzy matching and other
	 * options documented here:
	 * https://www.elastic.co/guide/en/elasticsearch/reference/6.3/query-dsl-simple-query-string-query.html
	 *
	 * @param array $query The simple_query_string options array.
	 * @param array $args The WP_Query args.
	 */
	$query = apply_filters( 'altis.search.advanced_query', $query, $args );

	return [
		'simple_query_string' => $query,
	];
}

/**
 * Modify the custom search results post type arguments.
 *
 * @param array $args The post type args.
 * @param string $post_type The post type name.
 * @return array
 */
function custom_search_results_post_type_args( array $args, string $post_type ) : array {
	if ( $post_type !== 'ep-pointer' ) {
		return $args;
	}

	// Hide in admin menu, we'll add it as a subitem of main search config page.
	$args['show_in_menu'] = 'search-config';

	// Change the menu name to something shorter.
	$args['labels']['all_items'] = _x( 'Custom Search Results', 'post type menu name', 'altis' );

	return $args;
}

/**
 * Split elasticsearch requests that will be too large for the request limit.
 *
 * ElasticPress doesn't have support for limiting request / posts indexed based off
 * a request size limit. We hook into ElasticPress every time it will make a request
 * to check the payload size, and split it into multiple requests if needed.
 *
 * @param WP_Error|array $request The request to replace / override.
 * @param array $query The query to ElasticSearch.
 * @param array $args The request arguments.
 * @param integer $failures The current number of failures for this request.
 * @return WP_Error|array The overriden request.
 */
function split_large_ep_request( $request, array $query, array $args, int $failures ) {

	if ( empty( $args['body'] ) || $args['method'] !== 'POST' || ! strpos( $query['url'], '/_bulk' ) ) {
		return wp_remote_request( $query['url'], $args );
	}

	// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	$body_size_limit = apply_filters( 'altis.search.request-size-limit', 1024 * 1024 * 9 ); // 9 MB
	$combined_response = null;
	$body = $args['body'];
	$args['body'] = '';
	$requests = [];
	while ( $body ) {
		$next_chunk_position = strpos( $body, "\n\n" ) + 2;
		if ( $next_chunk_position === false ) {
			$next_chunk_position = strlen( $body );
		}

		if ( $next_chunk_position > $body_size_limit ) {
			$error_message = sprintf( 'Single chunk in ElasticPress bulk request (%d bytes) is larger than the request size limit of %d', $next_chunk_position, $body_size_limit );
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::warning( $error_message );
			}
			trigger_error( $error_message, E_USER_WARNING );
			$body = substr( $body, $next_chunk_position );
			continue;
		}

		// If adding the next chunk would take the current chunk over the limit, send the request now.
		if ( strlen( $args['body'] ) + $next_chunk_position > $body_size_limit ) {
			$requests[] = wp_remote_request( $query['url'], $args );
			$args['body'] = '';
		}

		$args['body'] .= substr( $body, 0, $next_chunk_position );
		$body = substr( $body, $next_chunk_position );

		// Final request.
		if ( ! $body ) {
			$requests[] = wp_remote_request( $query['url'], $args );
		}
	}

	$request = $requests[0];
	if ( is_wp_error( $request ) ) {
		return $request;
	}

	$response_data = [
		'errors' => false,
		'took' => 0,
		'items' => [],
	];

	// Combine all request stats in to one.
	foreach ( $requests as $r ) {
		if ( is_wp_error( $r ) ) {
			continue;
		}

		$body = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( ! is_array( $body ) ) {
			continue;
		}

		if ( ! empty( $body['errors'] ) ) {
			$response_data['errors'] = $body['errors'];
		}

		if ( isset( $body['items'] ) ) {
			$response_data['items'] = array_merge( $response_data['items'], $body['items'] );
		}

		if ( isset( $body['took'] ) ) {
			$response_data['took'] += $body['took'];
		}
	}

	$request['body'] = json_encode( $response_data );

	return $request;
}

/**
 * Handle request forwarding to ES.
 */
function handle_autosuggest_endpoint() {
	// Check autosuggest is enabled.
	$config = Altis\get_config()['modules']['search'];
	if ( ! ( $config['autosuggest'] ?? false ) ) {
		return;
	}

	// Ensure this is a POST request.
	if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
		return;
	}

	// Do we have an endpoint defined?
	if ( ! defined( 'EP_AUTOSUGGEST_ENDPOINT' ) ) {
		return;
	}

	// Ensure we include site path for subdirectory installs or sites.
	$endpoint = wp_parse_url( EP_AUTOSUGGEST_ENDPOINT, PHP_URL_PATH );
	if ( rtrim( $endpoint, '/' ) !== $_SERVER['REQUEST_URI'] ) {
		return;
	}

	// Check request is from same origin.
	$origin = get_http_origin();
	if ( parse_url( $origin, PHP_URL_HOST ) !== parse_url( get_home_url(), PHP_URL_HOST ) ) {
		wp_send_json( [], 200 );
	}

	// Validate data.
	$json = json_decode( WP_REST_Server::get_raw_data(), true );
	if ( ! $json ) {
		wp_send_json( [], 200 );
	}

	/**
	 * Features instance.
	 *
	 * @var Features $features
	 */
	$features = Features::factory();

	/**
	 * Search feature instance.
	 *
	 * @var Feature\Search\Search $search
	 */
	$search = $features->get_registered_feature( 'search' );

	// Force post filter value.
	$json['post_filter'] = [
		'bool' => [
			'must' => [
				[
					'term' => [
						'post_status' => 'publish',
					],
				],
				[
					'terms' => [
						'post_type.raw' => array_values( $search->get_searchable_post_types() ),
					],
				],
			],
		],
	];

	/**
	 * Elasticsearch client object.
	 *
	 * @var Elasticsearch $client
	 */
	$client = Elasticsearch::factory();

	// Pass to EP.
	$response = $client->remote_request( Indexables::factory()->get( 'post' )->get_index_name() . '/_search', [
		'body'   => json_encode( $json ),
		'method' => 'POST',
	] );

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	// Return JSON response.
	wp_send_json( $data, 200 );
}

/**
 * Sanitizes array type query args to ensure there are no empty values.
 *
 * Empty values in `terms` queries will cause an ES query to error.
 *
 * @param \WP_Query $query The WP_Query instance (passed by reference).
 */
function sanitize_query_args( WP_Query $query ) : void {
	$keys_to_sanitize = [
		'author__in',
		'author__not_in',
		'category__and',
		'category__in',
		'category__not_in',
		'tag__and',
		'tag__in',
		'tag__not_in',
		'tag_slug__and',
		'tag_slug__in',
		'post_parent__in',
		'post_parent__not_in',
		'post__in',
		'post__not_in',
		'post_name__in',
	];
	foreach ( $keys_to_sanitize as $key ) {
		if ( empty( $query->get( $key ) ) ) {
			continue;
		}
		$query->set( $key, array_values( array_filter( (array) $query->get( $key ) ) ) );
	}
}
