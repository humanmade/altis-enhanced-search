<?php
/**
 * Altis Search Packages.
 *
 * @package altis/search
 */

namespace Altis\Enhanced_Search\Packages;

use Altis\Enhanced_Search;
use ElasticPress\Elasticsearch;
use ElasticPress\Indexables;
use ElasticPress\Utils;
use WP_Error;

// Maximum package file size recommended for inline settings.
const MAX_INLINE_SETTINGS_SIZE_RECOMMENDED = 100 * 1024;

/**
 * Bind hooks for Elasticsearch Packages.
 *
 * @return void
 */
function bootstrap() {
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts' );
	add_action( 'admin_init', __NAMESPACE__ . '\\handle_form' );
	add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu', 5 );
	add_action( 'network_admin_menu', __NAMESPACE__ . '\\admin_menu' );
	add_filter( 'removable_query_args', __NAMESPACE__ . '\\add_removable_query_args' );

	// Background tasks.
	add_action( 'altis.search.updated_packages', __NAMESPACE__ . '\\on_updated_packages', 10, 2 );
	add_action( 'altis.search.update_index_settings', __NAMESPACE__ . '\\do_settings_update', 10, 2 );
}

/**
 * Add network admin page.
 *
 * @return void
 */
function admin_menu() : void {
	add_menu_page(
		__( 'Search Configuration', 'altis' ),
		__( 'Search Config', 'altis' ),
		is_network_admin() ? 'manage_network_options' : 'manage_options',
		'search-config',
		__NAMESPACE__ . '\\admin_page',
		'dashicons-search',
		171
	);
}

/**
 * Add our custom success/error messaging query args to the removable list.
 *
 * @param array $removable_query_args Array of query parameter names to remove from the URL in the admin.
 * @return array
 */
function add_removable_query_args( array $removable_query_args ) : array {
	$removable_query_args[] = 'did_update';
	return $removable_query_args;
}

/**
 * Enqueue scripts and styles for the search config form.
 *
 * @param string $hook_suffix The admin page hook.
 * @return void
 */
function enqueue_scripts( string $hook_suffix ) : void {
	if ( $hook_suffix !== 'toplevel_page_search-config' ) {
		return;
	}

	wp_enqueue_style(
		'altis-search-packages',
		plugin_dir_url( dirname( __FILE__, 2 ) ) . 'assets/packages.css',
		[ 'wp-components' ],
		'2020-08-26-01'
	);
}

/**
 * Include the search config page template.
 *
 * @return void
 */
function admin_page() : void {
	$types = [
		'synonyms' => [],
		'stopwords' => [],
		'user_dictionary' => [],
	];

	$for_network = is_network_admin();

	foreach ( $types as $type => $data ) {
		$file_type_string = str_replace( '_', '-', $type );

		if ( $for_network ) {
			$data['uploaded_status'] = get_site_option( "altis_search_package_status_uploaded-{$file_type_string}" );
			$data['manual_status'] = get_site_option( "altis_search_package_status_manual-{$file_type_string}" );
			$data['uploaded_error'] = get_site_option( "altis_search_package_error_uploaded-{$file_type_string}", null );
			$data['manual_error'] = get_site_option( "altis_search_package_error_manual-{$file_type_string}", null );
		} else {
			$data['uploaded_status'] = get_option( "altis_search_package_status_uploaded-{$file_type_string}" );
			$data['manual_status'] = get_option( "altis_search_package_status_manual-{$file_type_string}" );
			$data['uploaded_error'] = get_option( "altis_search_package_error_uploaded-{$file_type_string}", null );
			$data['manual_error'] = get_option( "altis_search_package_error_manual-{$file_type_string}", null );
		}

		$data['uploaded_file'] = get_package_path( "uploaded-{$file_type_string}", $for_network );
		$data['manual_file'] = get_package_path( "manual-{$file_type_string}", $for_network );

		$data['text'] = '';
		if ( file_exists( $data['manual_file'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data['text'] = file_get_contents( $data['manual_file'] );
		}

		$data['file_date'] = false;
		if ( file_exists( $data['uploaded_file'] ) ) {
			$data['file_date'] = filemtime( $data['uploaded_file'] );
		}

		$types[ $type ] = $data;
	}

	$did_update = isset( $_GET['did_update'] );

	if ( $for_network ) {
		$errors = get_site_transient( 'altis_search_config_error' );
	} else {
		$errors = get_transient( 'altis_search_config_error' );
	}

	if ( ! empty( $errors ) && is_array( $errors ) ) {
		$errors = array_map( function ( $error ) {
			return new WP_Error( $error['code'], $error['message'] );
		}, $errors );
	}

	// Make ES version available.
	$elasticsearch_version = Elasticsearch::factory()->get_elasticsearch_version();

	include __DIR__ . '/templates/config.php';

	if ( ! empty( $errors ) ) {
		if ( $for_network ) {
			delete_site_transient( 'altis_search_config_error' );
		} else {
			delete_transient( 'altis_search_config_error' );
		}
	}
}

/**
 * Set the search config error message.
 *
 * @param WP_Error $error The error object to add.
 * @param bool $for_network Whether this should be added as a network level error.
 * @return void
 */
function add_error_message( WP_Error $error, bool $for_network = false ) : void {
	static $errors = [];
	$errors[] = $error;
	$errors = array_map( function ( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return $error;
		}
		return [
			'code' => $error->get_error_code(),
			'message' => $error->get_error_message(),
		];
	}, $errors );

	if ( is_network_admin() || $for_network ) {
		set_site_transient( 'altis_search_config_error', $errors );
	} else {
		set_transient( 'altis_search_config_error', $errors );
	}
}

/**
 * Returns the base directory for storing Elasticsearch packages.
 *
 * @return string
 */
function get_packages_dir() : string {
	$upload_dir = wp_get_upload_dir();
	$path = $upload_dir['basedir'] . '/es-packages';

	/**
	 * Filter the path at which Elasticsearch package files are stored.
	 *
	 * @param string $path Absolute directory path, defaults to wp-content/es-packages.
	 */
	$path = apply_filters( 'altis.search.packages_dir', $path );

	return $path;
}

/**
 * Get a package file path.
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

	// Check package file exists.
	// In cases where a database has been imported the package file may not exist
	// for the current stack so we apply a fail safe here.
	$package_path = get_package_path( $slug, $for_network );
	if ( ! empty( $package_id ) && ! file_exists( $package_path ) ) {
		trigger_error( sprintf( 'Referenced package file "%s" does not exist.', $package_path ), E_USER_WARNING );
		return null;
	}

	return $package_id;
}

/**
 * Returns the contents of a package using its slug and network-wide flag.
 *
 * @param string $slug The package slug to get the package ID for.
 * @param bool $for_network If true get the network level package ID.
 *
 * @return string|null
 */
function get_package_contents( string $slug, bool $for_network = false ) : ?string {
	$path = get_package_path( $slug, $for_network );
	if ( empty( $path ) ) {
		return null;
	}

	if ( ! file_exists( $path ) ) {
		trigger_error( sprintf( 'Package file "%s" does not exist at "%s".', $slug, $path ) );
		return null;
	}

	return file_get_contents( $path ) ?: null;
}

/**
 * Process the packages form.
 *
 * @return void
 */
function handle_form() : void {
	if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'altis-search-config' ) {
		return;
	}

	if ( ! check_admin_referer( 'altis-search-config', '_altisnonce' ) ) {
		return;
	}

	// Track errors.
	$errors = [];

	// Ensure upload directory exists.
	$base_dir = get_packages_dir();
	if ( ! file_exists( $base_dir ) ) {
		$package_dir_created = wp_mkdir_p( $base_dir );
		if ( ! $package_dir_created ) {
			$errors[] = new WP_Error(
				'create_package_dir_failed',
				// translators: %s replaced by package base directory.
				sprintf( __( 'Failed to create packages base directory at %s', 'altis' ), $base_dir )
			);
		}
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
		$package_id = null;

		// Handle manual entry.
		if ( isset( $_POST[ $text_field ] ) ) {
			$text = sanitize_textarea_field( wp_unslash( $_POST[ $text_field ] ) );
			$file = get_package_path( "manual-{$type}", $for_network );
			$has_changed = true;

			// Check for updates.
			// phpcs:ignore -- Allow error suppression for existing ID check when file exists.
			if ( file_exists( $file ) && ! empty( @get_package_id( "manual-{$type}", $for_network ) ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$has_changed = $text !== file_get_contents( $file );
			}

			// Write to uploads.
			if ( $has_changed ) {
				if ( empty( $text ) ) {
					if ( file_exists( $file ) ) {
						$deleted = delete_package( "manual-{$type}", $for_network );
						if ( is_wp_error( $deleted ) ) {
							$errors[] = $deleted;
						}
					}
				} else {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
					$result = file_put_contents( $file, $text );
					if ( ! $result ) {
						$errors[] = new WP_Error(
							'write_package_error',
							// translators: %s replaced by search file package path.
							sprintf( __( 'Could not write search package file to %s', 'altis' ), $file )
						);
					} else {
						$package_id = create_package( "manual-{$type}", $file, $for_network );
						if ( is_wp_error( $package_id ) ) {
							$errors[] = $package_id;
						}
					}
				}
			}
		}

		// Handle file upload.
		if ( ! empty( $_FILES ) && isset( $_FILES[ $file_field ] ) && ! empty( $_FILES[ $file_field ]['tmp_name'] ) ) {
			// phpcs:ignore HM.Security.ValidatedSanitizedInput
			$mime_type = mime_content_type( $_FILES[ $file_field ]['tmp_name'] );
			if ( $mime_type !== 'text/plain' ) {
				$errors[] = new WP_Error(
					'file_type_incorrect',
					// translators: %s replaced by search package file path.
					sprintf( __( 'Detected unsupported file type %s, only text files are supported.', 'altis' ), $mime_type )
				);

			} else {
				$file = get_package_path( "uploaded-{$type}", $for_network );
				// phpcs:ignore HM.Security.ValidatedSanitizedInput.InputNotSanitized
				$result = move_uploaded_file( wp_unslash( $_FILES[ $file_field ]['tmp_name'] ), $file );
				if ( ! $result ) {
					$errors[] = new WP_Error(
						'write_package_error',
						// translators: %s replaced by search package file path.
						sprintf( __( 'Could not write search package file to %s', 'altis' ), $file )
					);
				} else {
					$package_id = create_package( "uploaded-{$type}", $file, $for_network );
					if ( is_wp_error( $package_id ) ) {
						$errors[] = $package_id;
					} else {
						// User dictionary update means we should update the indexed data.
						if ( $type === 'user-dictionary' ) {
							$should_update_indexes = true;
						}
					}
				}
			}
		}

		// Delete file if remove submit clicked.
		if ( isset( $_POST[ $remove_field ] ) ) {
			$deleted = delete_package( "uploaded-{$type}", $for_network );
			if ( is_wp_error( $deleted ) ) {
				$errors[] = $deleted;
			} else {
				// User dictionary update means we should update the indexed data.
				if ( $type === 'user-dictionary' ) {
					$should_update_indexes = true;
				}
			}
		}
	}

	// Store the errors.
	foreach ( $errors as $error ) {
		add_error_message( $error );
	}

	// Schedule mapping and index updates.
	if ( empty( $errors ) ) {
		do_action( 'altis.search.updated_packages', $for_network, $should_update_indexes );
	}

	// Redirect back to form.
	$redirect_url = is_network_admin() ? network_admin_url( 'settings.php' ) : admin_url( 'admin.php' );
	$redirect_url = add_query_arg( [
		'page' => 'search-config',
		'did_update' => 1,
	], $redirect_url );
	wp_safe_redirect( $redirect_url );
	exit;
}

/**
 * Default callback for when packages have been updated.
 *
 * @param boolean $for_network Whether to update all sites.
 * @param boolean $should_update_indexes Whether to reindex data.
 */
function on_updated_packages( bool $for_network, bool $should_update_indexes ) {
	wp_schedule_single_event( time(), 'altis.search.update_index_settings', [
		$for_network,
		$should_update_indexes,
	] );
}

/**
 * Create and associate a package file with AES, removing any existing
 * files with a matching name.
 *
 * @param string $slug The package slug.
 * @param string $file The package file path.
 * @param bool $for_network If true creates the package at the network level.
 * @return string|WP_Error The package ID for referencing in analysers or a WP_Error.
 */
function create_package( string $slug, string $file, bool $for_network = false ) {
	// Ensure file exists.
	if ( ! file_exists( $file ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$error = sprintf( 'The given package filepath %s does not exist.', $file );
		return new WP_Error( 'altis_search_file_not_found', $error );
	}

	// Default package ID is the full path to the package.
	// Note this will only work if Elasticsearch is running on the same server as WordPress.
	$package_id = $file;

	/**
	 * Override the package ID on creation for a given package file name.
	 *
	 * @param string|WP_Error The package ID for the given file.
	 * @param string $slug The package slug.
	 * @param string $file The full package file path.
	 * @param bool $for_network True if the package is a network level package.
	 */
	$package_id = apply_filters( 'altis.search.create_package_id', $package_id, $slug, $file, $for_network );
	if ( is_wp_error( $package_id ) ) {
		if ( $for_network ) {
			update_site_option( "altis_search_package_status_{$slug}", 'ERROR' );
		} else {
			update_option( "altis_search_package_status_{$slug}", 'ERROR' );
		}
		return $package_id;
	}

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
 * @return bool|WP_Error
 */
function delete_package( string $slug, bool $for_network = false ) {
	// Get the package ID to pass to action hook.
	$package_id = get_package_id( $slug, $for_network );
	if ( empty( $package_id ) ) {
		return new WP_Error(
			'delete_package_error',
			// translators: %s replaced by package slug.
			sprintf( __( 'Package for slug %s does not exist', 'altis' ), $slug )
		);
	}

	$file = get_package_path( $slug, $for_network );

	// Delete the file.
	unlink( $file );

	// Remove the stored package ID.
	if ( $for_network ) {
		$deleted = delete_site_option( "altis_search_package_{$slug}" );
		delete_site_option( "altis_search_package_status_{$slug}" );
		delete_site_option( "altis_search_package_error_{$slug}" );
	} else {
		$deleted = delete_option( "altis_search_package_{$slug}" );
		delete_option( "altis_search_package_status_{$slug}" );
		delete_option( "altis_search_package_error_{$slug}" );
	}

	if ( ! $deleted ) {
		return new WP_Error(
			'delete_package_error',
			// translators: %s replaced by package slug.
			sprintf( __( 'Failed to delete package with slug %s', 'altis' ), $slug )
		);
	}

	/**
	 * Action triggered when a package is deleted.
	 *
	 * @param string $package_id The package ID.
	 * @param string $slug The package slug.
	 * @param bool $for_network Whether this is for the network level or site level.
	 */
	do_action( 'altis.search.deleted_package', $package_id, $slug, $for_network );

	return true;
}

/**
 * Return a comma separated list of all default indexables for the given site.
 *
 * @param integer|null $blog_id The site to get index names for. Defaults to current site.
 * @return string
 */
function get_site_indices( ?int $blog_id = null ) : string {
	$indexes = [];

	// Check for default indexes and their existence.
	foreach ( Indexables::factory()->get_all() as $indexable ) {
		if ( ! $indexable->index_exists( $blog_id ) ) {
			continue;
		}
		$indexes[] = $indexable->get_index_name( $blog_id );
	}

	return implode( ',', $indexes );
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
function do_settings_update( bool $for_network = false, bool $update_data = false ) : void {
	// Get latest settings.
	// We use 'x-post-1' one here to mimic an index name so that our generic customised settings
	// can be returned without needing to replicate the code in EP's put_mapping methods.
	$mapping = Enhanced_Search\elasticpress_mapping( [], 'x-post-1' );
	$settings = $mapping['settings'];

	if ( $for_network ) {
		$sites = Utils\get_sites( 0 );
		foreach ( $sites as $site ) {
			if ( ! Utils\is_site_indexable( $site['blog_id'] ) ) {
				continue;
			}
			update_index_settings( get_site_indices( $site['blog_id'] ), $settings, $update_data );
		}
	} else {
		update_index_settings( get_site_indices(), $settings, $update_data );
	}

	/**
	 * Action triggered once all indexes have been updated.
	 */
	do_action( 'altis.search.updated_all_index_settings' );
}

/**
 * Update index settings.
 *
 * @param string $index An optional index name or pattern to operate on.
 * @param array $settings The updated settings.
 * @param boolean $update_data Whether to update data in the index.
 * @return void
 */
function update_index_settings( string $index, array $settings, bool $update_data = false ) : void {
	$client = Elasticsearch::factory();

	// Direct settings updates are only supported on Opendistro Elasticsearch 7.8+.
	if ( version_compare( $client->get_elasticsearch_version(), '7.8', '<' ) ) {
		if ( defined( 'WP_EP_DEBUG' ) && WP_EP_DEBUG ) {
			trigger_error( 'Elasticsearch version 7.8 or higher is required to update index settings. Please reindex data manually.', E_USER_WARNING );
		}
		return;
	}

	// Close the index.
	$client->remote_request( $index . '/_close', [
		'method' => 'POST',
	], [], 'close_index' );

	// Update the settings.
	$client->remote_request( $index . '/_settings', [
		'method' => 'PUT',
		'body' => wp_json_encode( $settings ),
		'timeout' => 15,
	], [], 'put_settings' );

	// Open the index.
	$client->remote_request( $index . '/_open', [
		'method' => 'POST',
	], [], 'open_index' );

	// Update all data async if required.
	if ( $update_data ) {
		$client->remote_request( $index . '/_update_by_query?conflicts=proceed&wait_for_completion=false', [
			'method' => 'POST',
		] );
	}

	/**
	 * Action triggered when settings for a given index pattern have been updated.
	 *
	 * @param string $index The index that has just had its settings updated.
	 */
	do_action( 'altis.search.updated_settings_for_index', $index );
}
