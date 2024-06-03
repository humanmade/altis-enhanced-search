<?php
/**
 * This file sanitizes and sends search requests to the Elasticsearch server.
 *
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
 * phpcs:disable WordPress.Security.NonceVerification
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable WordPress.PHP.IniSet
 *
 * @package altis/enhanced-search
 */

namespace Altis\Enhanced_Search\Instant_Results;

use Aws\Credentials;
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

// Make required libraries available.
require_once '/usr/src/app/vendor/autoload.php';

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
		$args['body'] = http_build_query( $args['body'], '', '&' );
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
	if ( getenv( 'HM_ENV_TYPE' ) === 'local' ) {
		return $request;
	}

	$signer = new SignatureV4( 'es', getenv( 'HM_ENV_REGION' ) );
	if ( defined( 'ELASTICSEARCH_AWS_KEY' ) ) {
		$credentials = new Credentials\Credentials( getenv( 'ELASTICSEARCH_AWS_KEY' ), getenv( 'ELASTICSEARCH_AWS_SECRET' ) );
	} else {
		$provider = CredentialProvider::defaultProvider();
		$credentials = call_user_func( $provider )->wait();
	}

	$signed_request = $signer->signRequest( $request, $credentials );
	return $signed_request;
}

/**
 * Parses the DB_HOST setting to interpret it for mysqli_real_connect().
 *
 * mysqli_real_connect() doesn't support the host param including a port or socket
 * like mysql_connect() does. This duplicates how mysql_connect() detects a port
 * and/or socket file.
 *
 * @since 4.9.0
 *
 * @param string $host The DB_HOST setting to parse.
 * @return array|false Array containing the host, the port, the socket and
 *                     whether it is an IPv6 address, in that order.
 *                     False if $host couldn't be parsed.
 */
function parse_db_host( $host ) {
	$port    = null;
	$socket  = null;
	$is_ipv6 = false;

	// First peel off the socket parameter from the right, if it exists.
	$socket_pos = strpos( $host, ':/' );
	if ( false !== $socket_pos ) {
		$socket = substr( $host, $socket_pos + 1 );
		$host   = substr( $host, 0, $socket_pos );
	}

	// We need to check for an IPv6 address first.
	// An IPv6 address will always contain at least two colons.
	if ( substr_count( $host, ':' ) > 1 ) {
		$pattern = '#^(?:\[)?(?P<host>[0-9a-fA-F:]+)(?:\]:(?P<port>[\d]+))?#';
		$is_ipv6 = true;
	} else {
		// We seem to be dealing with an IPv4 address.
		$pattern = '#^(?P<host>[^:/]*)(?::(?P<port>[\d]+))?#';
	}

	$matches = array();
	$result  = preg_match( $pattern, $host, $matches );

	if ( 1 !== $result ) {
		// Couldn't parse the address, bail.
		return false;
	}

	$host = '';
	foreach ( array( 'host', 'port' ) as $component ) {
		if ( ! empty( $matches[ $component ] ) ) {
			$$component = $matches[ $component ];
		}
	}

	return array( $host, $port, $socket, $is_ipv6 );
}

/**
 * Class to hold all the proxy functionality.
 */
class EP_PHP_Proxy {

	/**
	 * The query to be sent to Elasticsearch.
	 *
	 * @var string|array
	 */
	protected $query;

	/**
	 * The additional filters the request may need.
	 *
	 * @var array
	 */
	protected $filters = [];

	/**
	 * The relation between filters.
	 *
	 * @var array
	 */
	protected $filter_relations = [];

	/**
	 * Global relation between filters
	 *
	 * @var string
	 */
	protected $relation = '';

	/**
	 * The request object.
	 *
	 * @var object
	 */
	protected $request;

	/**
	 * The request response.
	 *
	 * @var string
	 */
	protected $response;

	/**
	 * The URL of the posts index.
	 *
	 * @var string
	 */
	protected $post_index_url = '';

	/**
	 * Entry point of the class.
	 */
	public function proxy() {

		$blog_id = (int) ( $_GET['blog_id'] ?? null );

		if ( ! $blog_id ) {
			http_response_code( 400 );
			exit( 'missing or bad blog_id query parameter' );
		}

		mysqli_report( MYSQLI_REPORT_OFF );
		$dbh = mysqli_init();

		$host = getenv( 'DB_HOST' );
		$port    = null;
		$socket  = null;
		$is_ipv6 = false;
		$host_data = parse_db_host( getenv( 'DB_HOST' ) );
		if ( $host_data ) {
			list( $host, $port, $socket, $is_ipv6 ) = $host_data;
		}
		if ( $is_ipv6 && extension_loaded( 'mysqlnd' ) ) {
			$host = "[$host]";
		}

		mysqli_real_connect(
			$dbh,
			$host,
			getenv( 'DB_USER' ),
			getenv( 'DB_PASSWORD' ),
			null,
			$port,
			$socket,
			defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0
		);

		mysqli_select_db( $dbh, getenv( 'DB_NAME' ) );

		$result = mysqli_query(
			$dbh,
			"SELECT meta_value
				FROM wp_sitemeta
				WHERE meta_key
				IN( 'ep_{$blog_id}_post_index_url', 'ep_{$blog_id}_instant_results_template' );"
		);

		if ( ! $result ) {
			mysqli_close( $dbh );
			http_response_code( 400 );
			exit( 'no index URL or search results template found' );
		}

		$data = mysqli_fetch_all( $result );

		if ( empty( $data ) ) {
			mysqli_close( $dbh );
			http_response_code( 400 );
			exit( 'no index URL or search results template found' );
		}

		$search_template = '';
		$post_index_url = '';

		foreach ( $data as $row ) {
			if ( strpos( $row[0], '{' ) === 0 ) {
				$search_template = $row[0];
			}
			if ( strpos( $row[0], 'http' ) === 0 ) {
				$post_index_url = $row[0];
			}
		}

		$this->query = $search_template;
		$this->post_index_url = $post_index_url;

		$this->build_query();
		$this->make_request();
		$this->return_response();
	}

	/**
	 * Build the query to be sent, i.e., get the template and make all necessary replaces/changes.
	 */
	protected function build_query() {
		// For the next replacements, we'll need to work with an object
		$this->query = json_decode( $this->query, true );

		$this->set_search_term();
		$this->set_pagination();
		$this->set_order();
		$this->set_highlighting();

		$this->relation = ( ! empty( $_REQUEST['relation'] ) ) ? $this->sanitize_string( $_REQUEST['relation'] ) : 'or';
		$this->relation = ( 'or' === $this->relation ) ? $this->relation : 'and';

		$this->handle_post_type_filter();
		$this->handle_taxonomies_filters();
		$this->handle_price_filter();

		$this->apply_filters();

		$this->query = json_encode( $this->query );
	}

	/**
	 * Set the search term in the query.
	 */
	protected function set_search_term() {
		$search_term = $this->sanitize_string( $_REQUEST['search'] ?? '' );

		// Stringify the JSON object again just to make the str_replace easier.
		if ( ! empty( $search_term ) ) {
			$query_string = json_encode( $this->query );
			$query_string = str_replace( '{{ep_placeholder}}', $search_term, $query_string );
			$this->query  = json_decode( $query_string, true );
			return;
		}

		// If there is no search term, get everything.
		$this->query['query'] = [ 'match_all' => [ 'boost' => 1 ] ];
	}

	/**
	 * Set the pagination.
	 */
	protected function set_pagination() {
		// Pagination
		$per_page = $this->sanitize_number( $_REQUEST['per_page'] ?? 10 );
		$offset   = $this->sanitize_number( $_REQUEST['offset'] ?? 0 );
		if ( $per_page && $per_page > 1 ) {
			$this->query['size'] = $per_page;
		}
		if ( $offset && $offset > 1 ) {
			$this->query['from'] = $offset;
		}
	}

	/**
	 * Set the order.
	 */
	protected function set_order() {
		$orderby = $this->sanitize_string( $_REQUEST['orderby'] ?? 'date' );
		$order   = $this->sanitize_string( $_REQUEST['order'] ?? 'desc' );

		$order = ( 'desc' === $order ) ? $order : 'asc';

		$sort_clause = [];

		switch ( $orderby ) {
			case 'date':
				$sort_clause['post_date'] = [ 'order' => $order ];
				break;

			case 'price':
				$sort_clause['meta._price.double'] = [
					'order' => $order,
					'mode'  => ( 'asc' === $order ) ? 'min' : 'max',
				];
				break;

			case 'rating':
				$sort_clause['meta._wc_average_rating.double'] = [ 'order' => $order ];
				break;
		}

		if ( ! empty( $sort_clause ) ) {
			$this->query['sort'] = [ $sort_clause ];
		}
	}

	/**
	 * Set the highlighting clause.
	 */
	protected function set_highlighting() {
		$this->query['highlight'] = [
			'type'      => 'plain',
			'encoder'   => 'html',
			'pre_tags'  => [ '' ],
			'post_tags' => [ '' ],
			'fields'    => [
				'post_title'         => [
					'number_of_fragments' => 0,
					'no_match_size'       => 9999,
				],
				'post_content_plain' => [

					'number_of_fragments' => 2,
					'fragment_size'       => 200,
					'no_match_size'       => 200,
				],
			],
		];

		$tag = $this->sanitize_string( $_REQUEST['highlight'] ?? '' );

		if ( $tag ) {
			$this->query['highlight']['pre_tags']  = [ "<${tag}>" ];
			$this->query['highlight']['post_tags'] = [ "</${tag}>" ];
		}
	}

	/**
	 * Add post types to the filters.
	 */
	protected function handle_post_type_filter() {
		$post_types = ( ! empty( $_REQUEST['post_type'] ) ) ? explode( ',', $_REQUEST['post_type'] ) : [];
		$post_types = array_filter( array_map( [ $this, 'sanitize_string' ], $post_types ) );
		if ( empty( $post_types ) ) {
			return;
		}

		if ( 'or' === $this->relation ) {
			$this->filters['post_type'] = [
				'terms' => [
					'post_type.raw' => $post_types,
				],
			];
			return;
		}

		$terms = [];
		foreach ( $post_types as $post_type ) {
			$terms[] = [
				'term' => [
					'post_type.raw' => $post_type,
				],
			];
		}

		$this->filters['post_type'] = [
			'bool' => [
				'must' => $terms,
			],
		];
	}

	/**
	 * Add taxonomies to the filters.
	 */
	protected function handle_taxonomies_filters() {
		$taxonomies    = [];
		$tax_relations = ( ! empty( $_REQUEST['term_relations'] ) ) ? (array) $_REQUEST['term_relations'] : [];
		foreach ( (array) $_REQUEST as $key => $value ) {
			if ( ! preg_match( '/^tax-(\S+)$/', $key, $matches ) ) {
				continue;
			}

			$taxonomy = $matches[1];

			$relation = ( ! empty( $tax_relations[ $taxonomy ] ) ) ?
				$this->sanitize_string( $tax_relations[ $taxonomy ] ) :
				$this->relation;

			$taxonomies[ $matches[1] ] = [
				'relation' => $relation,
				'terms'    => array_map( [ $this, 'sanitize_number' ], explode( ',', $value ) ),
			];
		}
		if ( empty( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy_slug => $taxonomy ) {
			if ( 'or' === $this->relation ) {
				$this->filters[ $taxonomy_slug ] = [
					'terms' => [
						"terms.{$taxonomy_slug}.term_id" => $taxonomy['terms'],
					],
				];
				return;
			}

			$terms = [];
			foreach ( $taxonomy['terms'] as $term ) {
				$terms[] = [
					'term' => [
						"terms.{$taxonomy_slug}.term_id" => $term,
					],
				];
			}

			$this->filters[ $taxonomy_slug ] = [
				'bool' => [
					'must' => $terms,
				],
			];
		}
	}

	/**
	 * Add price ranges to the filters.
	 */
	protected function handle_price_filter() {
		$min_price = ( ! empty( $_REQUEST['min_price'] ) ) ? $this->sanitize_string( $_REQUEST['min_price'] ) : '';
		$max_price = ( ! empty( $_REQUEST['max_price'] ) ) ? $this->sanitize_string( $_REQUEST['max_price'] ) : '';

		if ( $min_price ) {
			$this->filters['min_price'] = [
				'range' => [
					'meta._price.double' => [
						'gte' => $min_price,
					],
				],
			];
		}

		if ( $max_price ) {
			$this->filters['max_price'] = [
				'range' => [
					'meta._price.double' => [
						'lte' => $max_price,
					],
				],
			];
		}
	}

	/**
	 * Add filters to the query.
	 */
	protected function apply_filters() {
		$occurrence = ( 'and' === $this->relation ) ? 'must' : 'should';

		$existing_filter = ( ! empty( $this->query['post_filter'] ) ) ? $this->query['post_filter'] : [ 'match_all' => [ 'boost' => 1 ] ];

		if ( ! empty( $this->filters ) ) {
			$this->query['post_filter'] = [
				'bool' => [
					'must' => [
						$existing_filter,
						[
							'bool' => [
								$occurrence => array_values( $this->filters ),
							],
						],
					],
				],
			];
		}

		/**
		 * If there's no aggregations in the template or if the relation isn't 'and', we are done.
		 */
		if ( empty( $this->query['aggs'] ) || 'and' !== $this->relation ) {
			return;
		}

		/**
		 * Apply filters to aggregations.
		 *
		 * Note the usage of `&agg` (passing by reference.)
		 */
		foreach ( $this->query['aggs'] as $agg_name => &$agg ) {
			$new_filters = [];

			/**
			 * Only filter an aggregation if there's sub-aggregations.
			 */
			if ( empty( $agg['aggs'] ) ) {
				continue;
			}

			/**
			 * Get any existing filter, or a placeholder.
			 */
			$existing_filter = $agg['filter'] ?? [ 'match_all' => [ 'boost' => 1 ] ];

			/**
			 * Get new filters for this aggregation.
			 *
			 * Don't apply a filter to a matching aggregation if the relation is 'or'.
			 */
			foreach ( $this->filters as $filter_name => $filter ) {
				// @todo: this relation should not be the global one but the relation between aggs.
				if ( $filter_name === $agg_name && 'or' === $this->relation ) {
					continue;
				}

				$new_filters[] = $filter;
			}

			/**
			 * Add filters to the aggregation.
			 */
			if ( ! empty( $new_filters ) ) {
				$agg['filter'] = [
					'bool' => [
						'must' => [
							$existing_filter,
							[
								'bool' => [
									$occurrence => $new_filters,
								],
							],
						],
					],
				];
			}
		}
	}

	/**
	 * Make the cURL request.
	 */
	protected function make_request() {
		$http_headers = [ 'Content-Type: application/json' ];
		$endpoint     = $this->post_index_url . '/_search';

		// Create the cURL request.
		$this->request = curl_init( $endpoint );

		curl_setopt( $this->request, CURLOPT_POSTFIELDS, $this->query );

		curl_setopt_array(
			$this->request,
			[
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HEADER         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLINFO_HEADER_OUT    => true,
				CURLOPT_HTTPHEADER     => $http_headers,
			]
		);

		$this->response = curl_exec( $this->request );
	}

	/**
	 * Format and output the response from Elasticsearch.
	 */
	protected function return_response() {
		// Fetch all info from the request.
		$header_size      = curl_getinfo( $this->request, CURLINFO_HEADER_SIZE );
		$response_header  = substr( $this->response, 0, $header_size );
		$response_body    = substr( $this->response, $header_size );
		$response_info    = curl_getinfo( $this->request );
		$response_code    = $response_info['http_code'] ?? 500;
		$response_headers = preg_split( '/[\r\n]+/', $response_info['request_header'] ?? '' );
		if ( 0 === $response_code ) {
			$response_code = 404;
		}

		curl_close( $this->request );

		// Respond with the same headers, content and status code.

		// Split header text into an array.
		$response_headers = preg_split( '/[\r\n]+/', $response_header );
		// Pass headers to output
		foreach ( $response_headers as $header ) {
			// Pass following headers to response.
			if ( preg_match( '/^(?:Content-Type|Content-Language|Content-Security|X)/i', $header ) ) {
				header( $header );
			} elseif ( strpos( $header, 'Set-Cookie' ) !== false ) {
				// Replace cookie domain and path.
				$header = preg_replace( '/((?>domain)\s*=\s*)[^;\s]+/', '\1.' . $_SERVER['HTTP_HOST'], $header );
				$header = preg_replace( '/\s*;?\s*path\s*=\s*[^;\s]+/', '', $header );
				header( $header, false );
			} elseif ( 'Content-Encoding: gzip' === $header ) {
				// Decode response body if gzip encoding is used.
				$response_body = gzdecode( $response_body );
			}
		}

		http_response_code( $response_code );
		exit( $response_body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Utilitary function to sanitize string.
	 *
	 * @param string $string String to be sanitized
	 * @return string
	 */
	protected function sanitize_string( $string ) {
		return filter_var( $string, FILTER_SANITIZE_SPECIAL_CHARS );
	}

	/**
	 * Utilitary function to sanitize numbers.
	 *
	 * @param string $string Number to be sanitized
	 * @return string
	 */
	protected function sanitize_number( $string ) {
		return filter_var( $string, FILTER_SANITIZE_NUMBER_INT );
	}
}

$ep_php_proxy = new EP_PHP_Proxy();
$ep_php_proxy->proxy();
