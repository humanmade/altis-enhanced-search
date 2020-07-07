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
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts' );
	add_action( 'admin_init', __NAMESPACE__ . '\\handle_form' );
	add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu' );
	add_action( 'network_admin_menu', __NAMESPACE__ . '\\admin_menu' );
}

/**
 * Get Elasticsearch Service Client.
 *
 * @return ElasticsearchServiceClient
 */
function get_aes_client() : ElasticsearchServiceClient {
	return Altis\get_aws_sdk()->createElasticsearchService( [
		'version' => '2015-01-01',
		'region' => Altis\get_environment_region(),
	] );
}

/**
 * Add network admin page.
 *
 * @return void
 */
function admin_menu() {
	add_submenu_page(
		is_network_admin() ? 'settings.php' : 'options-general.php',
		__( 'Search Configuration', 'altis' ),
		__( 'Search Config', 'altis' ),
		is_network_admin() ? 'manage_network_options' : 'manage_options',
		'search-config',
		__NAMESPACE__ . '\\admin_page'
	);
}

/**
 * Enqueue scripts and styles for the search config form.
 *
 * @param string $hook_suffix The admin page hook.
 * @return void
 */
function enqueue_scripts( string $hook_suffix ) {
	if ( $hook_suffix !== 'settings_page_search-config' ) {
		return;
	}

	wp_enqueue_style( 'wp-components' );
}

/**
 * Includes the search config page template.
 *
 * @return void
 */
function admin_page() {
	$types = [ 'synonyms', 'stopwords', 'user_dictionary' ];

	$prefix = is_network_admin() ? '' : 'sites/' . get_current_blog_id() . '/';

	foreach ( $types as $type ) {
		$uploaded_file_var = "{$type}_uploaded_file";
		$manual_file_var = "{$type}_manual_file";
		$text_var = "{$type}_text";
		$file_date_var = "{$type}_file_date";

		$file_type_string = str_replace( '_', '-', $type );

		$$uploaded_file_var = get_package_path( "{$prefix}uploaded-{$file_type_string}" );
		$$manual_file_var = get_package_path( "{$prefix}manual-{$file_type_string}" );

		$$text_var = '';
		if ( file_exists( $$manual_file_var ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$$text_var = file_get_contents( $$manual_file_var );
		}

		$$file_date_var = false;
		if ( file_exists( $$uploaded_file_var ) ) {
			$$file_date_var = filemtime( $$uploaded_file_var );
		}
	}

	include __DIR__ . '/templates/config.php';
}

/**
 * Returns the base directory for storing Elasticsearch packages.
 *
 * @return string
 */
function get_packages_path() : string {
	$path = WP_CONTENT_DIR . '/es-packages';

	/**
	 * Filter the path at which Elasticsearch package files are stored.
	 *
	 * @param string $path Absolute directory path, defaults to wp-content/es-packages.
	 */
	$path = apply_filters( 'altis.search.packages_path', $path );

	return $path;
}

/**
 * Get a package file path. Returns the path if the file exists
 * or null on failure.
 *
 * @param string $slug A slug ID for the package file.
 * @return string
 */
function get_package_path( string $slug ) : string {
	$path = sprintf(
		'%s/%s.txt',
		get_packages_path(),
		$slug
	);

	return $path;
}

/**
 * Get the package ID for a given file path.
 *
 * @param string $slug The package slug to get the package ID for.
 * @return string|null
 */
function get_package_id( string $slug ) : ?string {
	$package_id = get_site_option( "altis_search_package_{$slug}" );
	if ( ! empty( $package_id ) ) {
		return $package_id;
	}

	return null;
}

/**
 * Process the packages form.
 *
 * @return void
 */
function handle_form() {
	if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'altis-search-config' ) {
		return;
	}

	if ( ! check_admin_referer( 'altis-search-config', '_altisnonce' ) ) {
		return;
	}

	// Set a global prefix in network admin.
	$prefix = is_network_admin() ? '' : 'sites/' . get_current_blog_id() . '/';

	// The different types of package.
	$types = [ 'synonyms', 'stopwords', 'user-dictionary' ];

	foreach ( $types as $type ) {
		$text_field = "{$type}-text";
		$file_field = "{$type}-file";
		$remove_field = "{$type}-remove";

		// Handle manual entry.
		if ( isset( $_POST[ $text_field ] ) && ! empty( $_POST[ $text_field ] ) ) {
			$text = sanitize_textarea_field( $_POST[ $text_field ] );
			$file = get_package_path( "{$prefix}manual-{$type}" );
			$has_changed = true;

			// Check for updates.
			if ( file_exists( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$has_changed = $text !== file_get_contents( $file );
			}

			// Write to uploads.
			if ( $has_changed ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
				file_put_contents( $file, $text );
				create_package( "{$prefix}manual-{$type}", $file );
			}
		}

		// Handle file upload.
		if ( ! empty( $_FILES ) && isset( $_FILES[ $file_field ] ) && ! empty( $_FILES[ $file_field ]['tmp_name'] ) ) {
			$file = get_package_path( "{$prefix}uploaded-{$type}" );
			move_uploaded_file( $_FILES[ $file_field ]['tmp_name'], $file );
			create_package( "{$prefix}uploaded-{$type}", $file );
		}

		// Delete file if remove submit clicked.
		if ( isset( $_POST[ $remove_field ] ) ) {
			delete_package( "{$prefix}uploaded-{$type}" );
		}
	}

	// Redirect back to form.
	$redirect_url = is_network_admin() ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
	wp_safe_redirect( $redirect_url . '?page=search-config' );
	exit;
}

/**
 * Create and associate a package file with AES, removing any existing
 * files with a matching name.
 *
 * @param string $slug The package slug.
 * @param string $file The package file path.
 * @return string|null The package ID for referencing in analysers.
 */
function create_package( string $slug, string $file ) : ?string {

	// Ensure file exists.
	if ( ! file_exists( $file ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( sprintf( 'The given package filepath %s does not exist.', $file ), E_USER_WARNING );
		return null;
	}

	// Default package ID is the full path to the package.
	// Note this will only work if Elasticsearch is running on the same server as WordPress.
	$package_id = $file;

	/**
	 * Override the package ID for a given package file name.
	 *
	 * @param string|null The package ID for the given file.
	 * @param string $file The full package file path.
	 */
	$package_id = apply_filters( 'altis.search.package_id', null, $file );

	// Store package ID for file.
	update_site_option( "altis_search_package_{$slug}", $package_id );

	// Return the reference used to get the package in an analyzer.
	return $package_id;
}

/**
 * Removes the stored package ID and file for a given package.
 *
 * @param string $slug The package file path.
 * @return bool
 */
function delete_package( string $slug ) : bool {
	$file = get_package_path( $slug );

	// Ensure file exists.
	if ( ! file_exists( $file ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( sprintf( 'The package file %s does not exist.', $file ), E_USER_WARNING );
	} else {
		// Delete the file.
		unlink( $file );
	}

	// Remove the stored package ID.
	return delete_site_option( "altis_search_package_{$slug}" );
}
