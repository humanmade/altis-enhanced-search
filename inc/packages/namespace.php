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

	$prefix = is_network_admin() ? 'global-' : '';

	foreach ( $types as $type ) {
		$uploaded_file_var = "{$type}_uploaded_file";
		$manual_file_var = "{$type}_manual_file";
		$text_var = "{$type}_text";
		$file_date_var = "{$type}_file_date";

		$$uploaded_file_var = get_package_file_path( "{$prefix}uploaded-{$type}" );
		$$manual_file_var = get_package_file_path( "{$prefix}manual-{$type}" );

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
	return wp_upload_dir()['basedir'] . '/search';
}

/**
 * Get a package file path. Returns the path if the file exists
 * or null on failure.
 *
 * @param string $type A file prefix for the package file.
 * @param int|null $blog_id The site ID to get the file path for.
 * @param int|null $network_id The network ID to get the file path for.
 * @return string
 */
function get_package_file_path( string $type, ?int $blog_id = null, ?int $network_id = null ) : string {
	return sprintf(
		'%s/%s-%d-%d.txt',
		get_packages_path(),
		$type,
		$network_id ?? get_current_network_id(),
		$blog_id ?? get_current_blog_id()
	);
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
	$prefix = is_network_admin() ? 'global-' : '';

	// Collect packages to create and associate.
	$packages = [];

	// The different types of package.
	$types = [ 'synonyms', 'stopwords', 'user-dictionary' ];

	foreach ( $types as $type ) {
		$text_field = "{$type}-text";
		$file_field = "{$type}-file";
		$remove_field = "{$type}-remove";

		// Handle manual entry.
		if ( isset( $_POST[ $text_field ] ) && ! empty( $_POST[ $text_field ] ) ) {
			$text = sanitize_textarea_field( $_POST[ $text_field ] );
			$file = get_package_file_path( "{$prefix}manual-{$type}" );
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
				$packages[] = $file;
			}
		}

		// Handle file upload.
		if ( ! empty( $_FILES ) && isset( $_FILES[ $file_field ] ) && ! empty( $_FILES[ $file_field ]['tmp_name'] ) ) {
			$file = get_package_file_path( "${$prefix}uploaded-{$type}" );
			move_uploaded_file( $_FILES[ $file_field ]['tmp_name'], $file );
			$packages[] = $file;
		}

		// Delete file if remove submit clicked.
		if ( isset( $_POST[ $remove_field ] ) ) {
			delete_package( get_package_file_path( "${$prefix}uploaded-{$type}" ) );
		}
	}

	foreach ( $packages as $package ) {
		// Create a new package and associate it.
		$package_id = create_package( $package );
		if ( $package_id ) {
			// Store package ID for file.
			$name = basename( $package );
			update_site_option( "altis_search_package_{$name}", $package_id );
		}
	}
}

/**
 * Get the package ID for a given file path.
 *
 * @param string $file The file to get the package ID for.
 * @return string|null
 */
function get_package_id( string $file ) : ?string {
	$name = basename( $file );

	$package_id = get_site_option( "altis_search_package_{$name}" );
	if ( $package_id ) {
		return $package_id;
	}

	return null;
}

/**
 * Create and associate a package file with AES, removing any existing
 * files with a matching name.
 *
 * @param string $file The full S3 package file path.
 * @return string|null The package ID for referencing in analysers.
 */
function create_package( string $file ) : ?string {

	// Ensure file exists.
	if ( ! file_exists( $file ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( sprintf( 'The given package filepath %s does not exist.', $file ), E_USER_WARNING );
		return null;
	}

	/**
	 * Override the package ID for a given package file name.
	 *
	 * @param string|null The package ID for the given file.
	 * @param string $file The full package file path.
	 */
	$package_id = apply_filters( 'altis.search.package_id', null, $file );
	if ( $package_id !== null ) {
		return $package_id;
	}

	// Check file is an S3 file path.
	if ( strpos( $file, 's3://' ) !== 0 ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( sprintf( 'The given package filepath %s is not a valid S3 file path.', $file ), E_USER_WARNING );
		return null;
	}

	// Get AES client.
	$client = get_aes_client();

	// Use the filename for the package name and for matching.
	$name = basename( $file );

	// Derive S3 bucket and path.
	preg_match( '#^s3://([^/]+)/(.*?)$#', $file, $s3_file_parts );
	$s3_bucket = defined( 'S3_UPLOADS_BUCKET' ) ? S3_UPLOADS_BUCKET : $s3_file_parts[1];
	$s3_key = $s3_file_parts[2];

	// Get domain.
	$domains = $client->listDomainNames();
	$domain = $domains['DomainNames'][0]['DomainName'] ?? false;

	if ( empty( $domain ) ) {
		trigger_error( 'Could not find an AWS ElasticSearch Service Domain to associate the package with.', E_USER_WARNING );
		return null;
	}

	// Get old package if one exists with same name.
	$existing = $client->describePackages( [
		'Filter' => [
			'Name' => 'PackageName',
			'Value' => $name,
		],
	] );

	// Create a new package.
	$new_package = $client->createPackage( [
		'PackageName' => $name, // required.
		'PackageSource' => [ // required.
			'S3BucketName' => $s3_bucket,
			'S3Key' => $s3_key,
		],
		'PackageType' => 'TXT-DICTIONARY',
	] );

	// Associate the package with the ES domain.
	$client->associatePackage( [
		'DomainName' => $domain, // required.
		'PackageID' => $new_package['PackageDetails']['PackageID'], // required.
	] );

	// Dissociate and remove the old version of the package.
	if ( ! empty( $existing['PackageDetailsList'] ) ) {
		$existing_package_id = $existing['PackageDetailsList'][0]['PackageID'];
		$client->dissociatePackage( [
			'DomainName' => $domain, // required.
			'PackageID' => $existing_package_id, // required.
		] );
		$client->deletePackage( [
			'PackageID' => $existing_package_id,
		] );
	}

	// Return the reference used to get the package in an analyzer.
	return 'analyzers/' . $new_package['PackageDetails']['PackageID'];
}

/**
 * Removes the stored package ID and file for a given package.
 *
 * @param string $file The package file path.
 * @return bool
 */
function delete_package( string $file ) : bool {

	// Ensure file exists.
	if ( ! file_exists( $file ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		trigger_error( sprintf( 'The given package filepath %s does not exist.', $file ), E_USER_WARNING );
		return false;
	}

	// Delete the file.
	unlink( $file );

	$name = basename( $file );

	// Remove the stored package ID.
	delete_site_option( "altis_search_package_{$name}" );

	return true;
}
