<?php
/**
 * Altis Search Packages.
 *
 * @package altis/search
 */

namespace Altis\Enhanced_Search\Packages;

use Altis\Enhanced_Search;

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

	// Background tasks.
	add_action( 'altis.search.update_index_settings', __NAMESPACE__ . '\\update_index_settings', 10, 2 );
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

	$for_network = is_network_admin();

	foreach ( $types as $type ) {
		$uploaded_file_var = "{$type}_uploaded_file";
		$manual_file_var = "{$type}_manual_file";
		$text_var = "{$type}_text";
		$file_date_var = "{$type}_file_date";

		$file_type_string = str_replace( '_', '-', $type );

		$$uploaded_file_var = get_package_path( "uploaded-{$file_type_string}", $for_network );
		$$manual_file_var = get_package_path( "manual-{$file_type_string}", $for_network );

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

	if ( $for_network ) {
		$error = get_site_option( 'altis_search_config_error', false );
	} else {
		$error = get_option( 'altis_search_config_error', false );
	}

	include __DIR__ . '/templates/config.php';

	if ( $for_network ) {
		delete_site_option( 'altis_search_config_error' );
	} else {
		delete_option( 'altis_search_config_error' );
	}
}

/**
 * Returns the base directory for storing Elasticsearch packages.
 *
 * @return string
 */
function get_packages_dir() : string {
	$path = WP_CONTENT_DIR . '/uploads/es-packages';

	/**
	 * Filter the path at which Elasticsearch package files are stored.
	 *
	 * @param string $path Absolute directory path, defaults to wp-content/es-packages.
	 */
	$path = apply_filters( 'altis.search.packages_dir', $path );

	return $path;
}

/**
 * Get a package file path. Returns the path if the file exists
 * or null on failure.
 *
 * @param string $slug A slug ID for the package file.
 * @param bool $for_network If true returns path for network level package.
 * @return string
 */
function get_package_path( string $slug, bool $for_network = false ) : string {
	$path = sprintf(
		'%s/%s-%s.txt',
		get_packages_dir(),
		$for_network ? 'global' : 'site-' . get_current_blog_id(),
		$slug
	);

	return $path;
}

/**
 * Get the package ID for a given file path.
 *
 * @param string $slug The package slug to get the package ID for.
 * @param bool $for_network If true get the network level package ID.
 * @return string|null
 */
function get_package_id( string $slug, bool $for_network = false ) : ?string {
	if ( $for_network ) {
		$package_id = get_site_option( "altis_search_package_{$slug}", null );
	} else {
		$package_id = get_option( "altis_search_package_{$slug}", null );
	}

	/**
	 * Filter the returned package ID for a given slug.
	 *
	 * @param string|null $package_id The package ID to return.
	 * @param string $slug The package slug.
	 * @param bool $for_network True for network level package.
	 */
	$package_id = apply_filters( 'altis.search.get_package_id', $package_id, $slug, $for_network );

	return $package_id;
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

	// Ensure upload directory exists.
	$base_dir = get_packages_dir();
	if ( ! file_exists( $base_dir ) ) {
		wp_mkdir_p( $base_dir );
	}

	// Set a flag for whether this is site level or network level.
	$for_network = is_network_admin();

	// Track whether the index needs to be updated after any package updates.
	$should_update_indexes = false;

	// The different types of package.
	$types = [ 'synonyms', 'stopwords', 'user-dictionary' ];

	foreach ( $types as $type ) {
		$text_field = "{$type}-text";
		$file_field = "{$type}-file";
		$remove_field = "{$type}-remove";

		// Handle manual entry.
		if ( isset( $_POST[ $text_field ] ) && ! empty( $_POST[ $text_field ] ) ) {
			$text = sanitize_textarea_field( $_POST[ $text_field ] );
			$file = get_package_path( "manual-{$type}", $for_network );
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
				create_package( "manual-{$type}", $file, $for_network );
			}
		}

		// Handle file upload.
		if ( ! empty( $_FILES ) && isset( $_FILES[ $file_field ] ) && ! empty( $_FILES[ $file_field ]['tmp_name'] ) ) {
			$file = get_package_path( "uploaded-{$type}", $for_network );
			move_uploaded_file( $_FILES[ $file_field ]['tmp_name'], $file );
			create_package( "uploaded-{$type}", $file, $for_network );
			// User dictionary update means we should update the indexed data.
			if ( $type === 'user-dictionary' ) {
				$should_update_indexes = true;
			}
		}

		// Delete file if remove submit clicked.
		if ( isset( $_POST[ $remove_field ] ) ) {
			delete_package( "uploaded-{$type}", $for_network );
			// User dictionary update means we should update the indexed data.
			if ( $type === 'user-dictionary' ) {
				$should_update_indexes = true;
			}
		}
	}

	// Schedule mapping and index updates.
	wp_schedule_single_event( time(), 'altis.search.update_index_settings', [
		$for_network,
		$should_update_indexes,
	] );

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
 * @param bool $for_network If true creates the package at the network level.
 * @return string|null The package ID for referencing in analysers.
 */
function create_package( string $slug, string $file, bool $for_network = false ) : ?string {

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
	 * Override the package ID on creation for a given package file name.
	 *
	 * @param string|null The package ID for the given file.
	 * @param string $slug The package slug.
	 * @param string $file The full package file path.
	 * @param bool $for_network True if the package is a network level package.
	 */
	$package_id = apply_filters( 'altis.search.create_package_id', $package_id, $slug, $file, $for_network );

	// Store package ID.
	if ( $for_network ) {
		update_site_option( "altis_search_package_{$slug}", $package_id );
	} else {
		update_option( "altis_search_package_{$slug}", $package_id );
	}

	/**
	 * Action triggered when a new package has been created.
	 *
	 * Default usage is to trigger an update to the ES mapping.
	 *
	 * @param string $package_id The package ID.
	 * @param string $slug The package slug.
	 * @param bool $for_network True if the package was created at the network level.
	 */
	do_action( 'altis.search.created_package', $package_id, $slug, $for_network );

	// Return the reference used to get the package in an analyzer.
	return $package_id;
}

/**
 * Removes the stored package ID and file for a given package.
 *
 * @param string $slug The package file path.
 * @param bool $for_network If true deletes the network level package.
 * @return bool
 */
function delete_package( string $slug, bool $for_network = false ) : bool {
	$file = get_package_path( $slug );

	// Ensure file exists.
	if ( ! file_exists( $file ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( sprintf( 'The package file %s does not exist.', $file ), E_USER_WARNING );
	} else {
		// Delete the file.
		unlink( $file );
	}

	// Get the package ID to pass to action hook.
	$package_id = get_package_id( $slug, $for_network );

	// Remove the stored package ID.
	if ( $for_network ) {
		$deleted = delete_site_option( "altis_search_package_{$slug}" );
	} else {
		$deleted = delete_option( "altis_search_package_{$slug}" );
	}

	/**
	 * Action triggered when a package is deleted.
	 *
	 * @param string $package_id The package ID.
	 * @param string $slug The package slug.
	 * @param bool $for_network Whether this is for the network level or site level.
	 * @param bool $deleted Whether the option was successfully deleted or not.
	 */
	do_action( 'altis.search.deleted_package', $package_id, $slug, $for_network, $deleted );

	return $deleted;
}

/**
 * Update the index settings after upload. For stopwords and synonyms
 * no reindexing of data is required as they are applied at search time.
 *
 * If the data needs to be updated such as if the Japanese user dictionary
 * is updated then $update_index allows for updating using `_update_by_query`.
 *
 * Note this function should be run as a background task only.
 *
 * @param boolean $for_network Whether this is a network wide mapping update.
 * @param boolean $update_data Whether the index data should be updated as well.
 * @return void
 */
function update_index_settings( bool $for_network = false, bool $update_data = false ) {
	// Get latest settings.
	$mapping = Enhanced_Search\elasticpress_mapping( [] );
	$settings = $mapping['settings'];

	if ( $for_network ) {
		$sites = ep_get_sites( 0 );
		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );
			do_settings_update( $settings, $update_data );
			restore_current_blog();
		}
	} else {
		do_settings_update( $settings, $update_data );
	}
}

/**
 * Makes the Elasticsearch requests to update the index settings.
 *
 * @param array $settings The updated settings.
 * @param boolean $update_data Whether to update data in the index.
 * @return void
 */
function do_settings_update( array $settings, bool $update_data = false ) {
	// Close the index.
	ep_remote_request( ep_get_index_name() . '/_close', [
		'method' => 'POST',
	], [], 'close_index' );

	// Update the settings.
	ep_remote_request( ep_get_index_name() . '/_settings', [
		'method' => 'PUT',
		'body' => wp_json_encode( $settings ),
	], [], 'put_settings' );

	// Open the index.
	ep_remote_request( ep_get_index_name() . '/_open', [
		'method' => 'POST',
	], [], 'open_index' );

	// Update all data async if required.
	if ( $update_data ) {
		ep_remote_request( ep_get_index_name() . '/_update_by_query?conflicts=proceed', [
			'method' => 'POST',
		] );
	}
}
