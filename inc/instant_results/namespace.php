<?php
/**
 * Altis Search Proxy for Instant Results.
 *
 * Based on the proxy plugin at https://github.com/10up/elasticpress-proxy
 *
 * @package altis/enhanced-search
 */

namespace Altis\Enhanced_Search\Instant_Results;

use ElasticPress\Indexables;
use ElasticPress\Utils;

/**
 * Bootstrap Instant Results Proxy.
 *
 * @return void
 */
function boostrap() {
	add_filter( 'ep_instant_results_available', '__return_true' );
	add_action( 'ep_instant_results_template_saved', __NAMESPACE__ . '\\save_template' );
	add_filter( 'ep_instant_results_search_endpoint', __NAMESPACE__ . '\\set_proxy' );
}

/**
 * Save the a PHP file with the search query template and the post index URL into the uploads folder.
 *
 * @param string $search_template The search template.
 * @return void
 */
function save_template( string $search_template ) : void {
	$post_index_base = trailingslashit( Utils\get_host( true ) );
	foreach( Utils\get_sites() as $site ) {
		$post_index = Indexables::factory()->get( 'post' )->get_index_name( $site['blog_id'] );
		update_site_option( "ep_{$site['blog_id']}_post_index_url", $post_index_base . $post_index, false );
		update_site_option( "ep_{$site['blog_id']}_instant_results_template", $search_template, false );
	}
}

/**
 * Set the custom proxy as the search endpoint.
 *
 * @return string
 */
function set_proxy() : string {
	return sprintf( '%sproxy.php?blog_id=%d',
		plugin_dir_url( __FILE__ ),
		get_current_blog_id()
	);
}
