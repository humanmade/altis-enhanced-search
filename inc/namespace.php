<?php
/**
 * Altis Search.
 *
 * @package altis/search
 */

namespace Altis\Enhanced_Search;

use Altis;
use Altis\Enhanced_Search\Packages;
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

/**
 * Bootstrap search module.
 *
 * @return void
 */
function bootstrap() {
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_elasticpress', 9 );
	add_filter( 'altis_healthchecks', __NAMESPACE__ . '\\add_elasticsearch_healthcheck' );

	// Load debug bar for ElasticPress if Query Monitor is enabled in the config.
	if ( Altis\get_config()['modules']['dev-tools']['query-monitor'] ?? false ) {

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
	add_action( 'ep_remote_request', __NAMESPACE__ . '\\log_remote_request_errors', 10, 2 );
	add_filter( 'posts_request', __NAMESPACE__ . '\\noop_wp_query_on_failed_ep_request', 11, 2 );
	add_filter( 'found_posts_query', __NAMESPACE__ . '\\noop_wp_query_on_failed_ep_request', 6, 2 );
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

	// Back compat for ElasticPress v2 - change post index name to old version.
	add_filter( 'ep_index_name', __NAMESPACE__ . '\\filter_index_name' );

	// Ensure the same attachments ingest pipeline ID is used for the whole network.
	add_filter( 'ep_documents_pipeline_id', __NAMESPACE__ . '\\filter_documents_pipeline_id' );

	// Ensure non ElasticPress indexes are not affected by global edits using *.
	add_filter( 'ep_pre_request_url', __NAMESPACE__ . '\\protect_non_ep_indexes', 10, 5 );

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

	// Improve default analyzer with multilingual support.
	add_filter( 'ep_config_mapping', __NAMESPACE__ . '\\elasticpress_mapping' );

	// Filter Options for Facet component settings.
	add_filter( 'site_option_ep_feature_settings', __NAMESPACE__ . '\\filter_facet_settings' );
	add_filter( 'option_ep_feature_settings', __NAMESPACE__ . '\\filter_facet_settings' );

	// Change custom search results icon.
	add_filter( 'register_post_type_args', __NAMESPACE__ . '\\custom_search_results_post_type_args', 10, 2 );

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
 * @param string $request SQL query string.
 * @param WP_Query $query The current query object.
 * @return string
 */
function noop_wp_query_on_failed_ep_request( string $request, WP_Query $query ) : string {
	if ( ! isset( $query->elasticsearch_success ) || $query->elasticsearch_success === true ) {
		return $request;
	}

	global $wpdb;
	return "SELECT * FROM $wpdb->posts WHERE 1=0";
}

/**
 * No-op found rows query if ElasticSearch request fails.
 *
 * @param string $sql SQL query string.
 * @param WP_Query $query The current query object.
 * @return string
 */
function noop_wp_query_found_rows_on_failed_ep_request( string $sql, WP_Query $query ) : string {
	if ( ! isset( $query->elasticsearch_success ) || $query->elasticsearch_success === true ) {
		return $sql;
	}
	return '';
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
		if ( ! Indexables::factory()->get( 'post' )->index_exists( $site->blog_id ) ) {
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
	return get_post_types();
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
		'terms' => true,
		'users' => true,
	];

	if ( ! isset( $features_activated[ $feature->slug ] ) ) {
		return $is_active;
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
 * Enables the required settings for slowlog queries to be captured.
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
	$locale = get_option( 'WPLANG', get_site_option( 'WPLANG', 'en_US' ) );
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
 * @return array
 */
function elasticpress_mapping( array $mapping ) : array {

	// Merge filters, tokenizers and analyzers from JSON config.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$settings_json = file_get_contents( __DIR__ . '/analyzers.json' );
	$settings = json_decode( $settings_json, true );

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

			if ( $type === 'synonyms' ) {
				$synonyms[ "{$sub_type}_{$type}_filter" ] = [
					'type' => 'synonym_graph',
					'synonyms_path' => $package_id,
				];
			}
			if ( $type === 'stopwords' ) {
				$stopwords[ "{$sub_type}_{$type}_filter" ] = [
					'type' => 'stop',
					'ignore_case' => true,
					'stopwords_path' => $package_id,
				];
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
		// Add our custom filters.
		$mapping['settings']['analysis']['analyzer']['default_search']['filter'] = array_merge(
			array_keys( $synonyms ),
			array_keys( $stopwords ),
			$mapping['settings']['analysis']['analyzer']['default_search']['filter']
		);
	}

	return $mapping;
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
	];

	return array_diff( $notices, $hidden );
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
 * @return array The modified ElasticSearch query.
 */
function enhance_search_query( array $query, array $args ) : array {
	if ( ! isset( $args['s'] ) || empty( $args['s'] ) ) {
		return $query;
	}

	$strict = Altis\get_config()['modules']['search']['strict'] ?? true;
	$mode = Altis\get_config()['modules']['search']['mode'] ?? 'simple';
	$field_boost = Altis\get_config()['modules']['search']['field-boost'] ?? [];

	// Get search fields.
	$search_fields = $query['bool']['should'][0]['multi_match']['fields'];

	// Boost specific fields.
	if ( ! empty( $field_boost ) ) {
		foreach ( $field_boost as $field => $boost ) {
			if ( ! is_string( $field ) ) {
				trigger_error( 'Search module field boost value must be an object.', E_USER_WARNING );
				continue;
			}
			$existing_index = array_search( $field, $search_fields, true );
			$boosted_field = sprintf( '%s^%F', $field, floatval( $boost ) );
			if ( $existing_index !== false ) {
				$search_fields[ $existing_index ] = $boosted_field;
			} else {
				$search_fields[] = $boosted_field;
			}
		}
	}

	if ( $mode === 'simple' && $strict ) {
		// Remove the fuzzy matching of any word in the phrase.
		unset( $query['bool']['should'][2] );

		// Set the full phrase match fuzziness to auto, this will auto adjust
		// the allowed Levenshtein distance depending on the query length.
		$query['bool']['should'][1]['multi_match']['fuzziness'] = 'AUTO';
	}

	if ( $mode === 'advanced' ) {
		$query['bool']['should'] = [
			get_advanced_query( $args['s'], $search_fields, $strict ),
		];
	}

	return $query;
}

/**
 * Build an Elasticsearch simple query string array.
 *
 * @param string $query_string The search terms.
 * @param array $search_fields The fields being searched against.
 * @param bool $strict Whether to use stricter matching 'and' operator by default.
 * @return array
 */
function get_advanced_query( string $query_string, array $search_fields, bool $strict = false ) : array {

	// Deconstruct the quoted parts of the query.
	$query_pieces = preg_split( '/(?:\s*"([^"]+)"\s*|\s+)/', $query_string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

	// Rebuild the query string with default fuzziness and operator keyword conversion.
	$query_string = array_reduce( $query_pieces, function ( $query_string, $piece ) {
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
				// Add automatic fuzziness on single words without the fuzzy operator.
				$query_piece = "{$piece}~";
			} else {
				$query_piece = $piece;
			}
		}
		return trim( "{$query_string} {$query_piece}" );
	}, '' );

	// Set default operator based on strict setting.
	$default_operator = $strict ? 'and' : 'or';

	return [
		'simple_query_string' => [
			'query' => $query_string,
			'fields' => $search_fields,
			'default_operator' => $default_operator,
		],
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
