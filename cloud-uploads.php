<?php
/*
 * Plugin Name: Cloud Uploads Pro
 * Description: Migrate and store your wordpress upload files remotely on cloud storage along with cdn for fast delivery.
 * Version: 1.0.0
 * Author: Brij Raj Singh
 * Text Domain: cloud-uploads-pro
 * Requires at least: 5.3
 * Requires PHP: 7.0
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: true
 *
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly      


define( 'CLOUD_UPLOADS_VERSION', '1.0' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/inc/class-cloud-uploads-wp-cli-command.php';
}

register_activation_hook( __FILE__, 'cloud_uploads_install' );

add_action( 'plugins_loaded', 'cloud_uploads_init' );

function cloud_uploads_init() {
  if ( ! cloud_uploads_check_requirements() ) {
		return;
	}
	include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-api-handler.php';
	include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-filelist.php';
	include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-admin.php';
	include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-rewriter.php';
	// include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-image-editor-imagick.php';
	
	$admin = new Cloud_Uploads_Admin();
	
	$original_root_dirs = get_original_upload_dir_root();
	$replacements = [ $original_root_dirs['baseurl'] ];
	$cdn_url = get_s3_url();
	new Cloud_Uploads_Rewriter( $original_root_dirs['baseurl'], $replacements, $cdn_url );

	// add_filter( 'wp_image_editors', 'filter_editors', 9 );
	// add_action( 'delete_attachment', 'delete_attachment_files' );
	add_filter( 'wp_read_image_metadata', 'wp_filter_read_image_metadata', 10, 2 );
	add_filter( 'wp_update_attachment_metadata', 'update_attachment_metadata', 10, 2 );
	add_filter( 'wp_get_attachment_metadata', 'get_attachment_metadata' );
	add_filter( 'wp_resource_hints', 'wp_filter_resource_hints', 10, 2 );
				
	cloud_uploads_upgrade();
}

	/**
	 * Delete all attachment files from S3 when an attachment is deleted.
	 *
	 * WordPress Core's handling of deleting files for attachments via
	 * wp_delete_attachment_files is not compatible with remote streams, as
	 * it makes many assumptions about local file paths. The hooks also do
	 * not exist to be able to modify their behavior. As such, we just clean
	 * up the s3 files when an attachment is removed, and leave WordPress to try
	 * a failed attempt at mangling the iu:// urls.
	 *
	 * UPDATE deletes seem to get issued properly now, only use this for purging from CDN.
	 *
	 * @param $post_id
	 */
	function delete_attachment_files( $post_id ) {
		$meta = wp_get_attachment_metadata( $post_id );
		$file = get_attached_file( $post_id );

		$to_purge = [];
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $sizeinfo ) {
				$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
				//wp_delete_file( $intermediate_file );
				$to_purge[] = $intermediate_file;
			}
		}

		wp_delete_file( $file );
		$to_purge[] = $file;

		$dirs = wp_get_upload_dir();
		foreach ( $to_purge as $key => $file ) {
			$to_purge[ $key ] = str_replace( $dirs['basedir'], $dirs['baseurl'], $file );
		}

		//purge these from CDN cache
		$this->api->purge( $to_purge );
	}

/**
	 * Return an error to display before trying to save newly uploaded media.
	 *
	 * @param $file
	 *
	 * @return array
	 */
	function block_uploads( $file ) {
		$file['error'] = esc_html__( "Files can't be uploaded due to a billing issue with your Infinite Uploads account.", 'cloud-uploads' );

		return $file;
	}

	/**
	 * Block editing media in Gutenberg WP 5.5+ block.
	 *
	 * @param                 $result null
	 * @param WP_REST_Server  $server
	 * @param WP_REST_Request $request
	 *
	 * @return mixed|WP_Error
	 */
	function block_rest_upload( $result, $server, $request ) {
		//if route matches media edit return error
		if ( preg_match( '%/wp/v2/media/\d+/edit%', $request->get_route() ) ) {
			$result = new WP_Error(
				'rest_cant_upload',
				__( "Files can't be uploaded due to a billing issue with your Cloud Uploads account.", 'cloud-uploads' ),
				[ 'status' => 403 ]
			);
		}

		return $result;
	}

	function filter_editors( $editors ) {

		if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
			unset( $editors[ $position ] );
		}

		array_unshift( $editors, 'Cloud_Uploads_Image_Editor_Imagick' );

		return $editors;
	}

	/**
	 * Filters wp_read_image_metadata. exif_read_data() doesn't work on
	 * file streams so we need to make a temporary local copy to extract
	 * exif data from.
	 *
	 * @param array  $meta
	 * @param string $file
	 *
	 * @return array|bool
	 */
	function wp_filter_read_image_metadata( $meta, $file ) {
		remove_filter( 'wp_read_image_metadata', 'wp_filter_read_image_metadata', 10 );
		$temp_file = copy_image_from_s3( $file );
		$meta      = wp_read_image_metadata( $temp_file );
		add_filter( 'wp_read_image_metadata', 'wp_filter_read_image_metadata', 10, 2 );
		unlink( $temp_file );

		return $meta;
	}

	/**
	 * Get a local copy of the file.
	 *
	 * @param string $file
	 *
	 * @return string
	 */
	function copy_image_from_s3( $file ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}
		$temp_filename = wp_tempnam( $file );
		copy( $file, $temp_filename );

		return $temp_filename;
	}

	/**
	 * Filters the attachment meta data. wp_prepare_attachment_for_js triggers a HeadObject to get filesize, usually uncached
	 * on media grid and sometimes on frontend with some things, increasing TTFB a lot. Instead cache it when attachment is updated or created.
	 *
	 * @param array $data          Array of updated attachment meta data.
	 * @param int   $attachment_id Attachment post ID.
	 *
	 * @return array
	 */
	function update_attachment_metadata( $data, $attachment_id ) {
		$attached_file = get_attached_file( $attachment_id );
		if ( file_exists( $attached_file ) ) {
			$data['filesize'] = filesize( $attached_file );
		}

		return $data;
	}

	/**
	 * Filters the attachment meta data. wp_prepare_attachment_for_js triggers a HeadObject to get filesize, usually uncached
	 * on media grid and sometimes on frontend with some things, increasing TTFB a lot.
	 *
	 * @param array $data Array of meta data for the given attachment.
	 *
	 * @return array
	 */
	function get_attachment_metadata( $data ) {
		if ( ! isset( $data['filesize'] ) ) {
			$data['filesize'] = '';
		}

		return $data;
	}

	/**
	 * Add the DNS address for the S3 Bucket to list for DNS prefetch.
	 *
	 * @param $hints
	 * @param $relation_type
	 *
	 * @return array
	 */
	function wp_filter_resource_hints( $hints, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$hints[] = get_s3_url();
		}

		return $hints;
	}

function get_s3_url() {
	return 'https://test-s3.mackshost.com/';
	// if ( $this->bucket_url ) {
	// 	return 'https://' . $this->bucket_url;
	// }

	// $bucket = strtok( $this->bucket, '/' );
	// $path   = substr( $this->bucket, strlen( $bucket ) );

	// return apply_filters( 'infinite_uploads_bucket_url', 'https://' . $bucket . '.s3.amazonaws.com' . $path );
}

function cloud_uploads_upgrade() {
	// Install the needed DB table if not already.
	$installed = get_site_option( 'cloud_uploads_installed' );
	if ( CLOUD_UPLOADS_VERSION != $installed ) {
		cloud_uploads_install();
	}
}

function cloud_uploads_install() {
  global $wpdb;
  //prevent race condition during upgrade by setting option before running potentially long query
	if ( is_multisite() ) {
		update_site_option( 'cloud_uploads_installed', CLOUD_UPLOADS_VERSION );
	} else {
		update_option( 'cloud_uploads_installed', CLOUD_UPLOADS_VERSION, true );
	}
  $charset_collate = $wpdb->get_charset_collate();

	//191 is the maximum innodb default key length on utf8mb4
	$sql = "CREATE TABLE {$wpdb->base_prefix}cloud_uploads_files (
            `file` VARCHAR(255) NOT NULL,
            `size` BIGINT UNSIGNED NOT NULL DEFAULT '0',
            `modified` INT UNSIGNED NOT NULL,
            `type` VARCHAR(20) NOT NULL,
            `transferred` BIGINT UNSIGNED NOT NULL DEFAULT '0',
            `synced` BOOLEAN NOT NULL DEFAULT '0',
            `deleted` BOOLEAN NOT NULL DEFAULT '0',
            `errors` INT UNSIGNED NOT NULL DEFAULT '0',
            `transfer_status` TEXT NULL DEFAULT NULL,
            PRIMARY KEY  (`file`(191)),
            KEY `type` (`type`),
            KEY `synced` (`synced`),
            KEY `deleted` (`deleted`)
        ) {$charset_collate};";

	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}



	return dbDelta( $sql );
}

	/**
	 * Get root upload dir for multisite. Based on _wp_upload_dir().
	 *
	 * @return array See wp_upload_dir()
	 */
	function get_original_upload_dir_root() {
		$siteurl     = get_option( 'siteurl' );
		$upload_path = trim( get_option( 'upload_path' ) );

		if ( empty( $upload_path ) || 'wp-content/uploads' === $upload_path ) {
			$dir = WP_CONTENT_DIR . '/uploads';
		} elseif ( 0 !== strpos( $upload_path, ABSPATH ) ) {
			// $dir is absolute, $upload_path is (maybe) relative to ABSPATH.
			$dir = path_join( ABSPATH, $upload_path );
		} else {
			$dir = $upload_path;
		}

		$url = get_option( 'upload_url_path' );
		if ( ! $url ) {
			if ( empty( $upload_path ) || ( 'wp-content/uploads' === $upload_path ) || ( $upload_path == $dir ) ) {
				$url = WP_CONTENT_URL . '/uploads';
			} else {
				$url = trailingslashit( $siteurl ) . $upload_path;
			}
		}

		/*
		 * Honor the value of UPLOADS. This happens as long as ms-files rewriting is disabled.
		 * We also sometimes obey UPLOADS when rewriting is enabled -- see the next block.
		 */
		if ( defined( 'UPLOADS' ) && ! ( is_multisite() && get_site_option( 'ms_files_rewriting' ) ) ) {
			$dir = ABSPATH . UPLOADS;
			$url = trailingslashit( $siteurl ) . UPLOADS;
		}

		// If multisite (and if not the main site in a post-MU network).
		if ( is_multisite() && ! ( is_main_network() && is_main_site() && defined( 'MULTISITE' ) ) ) {

			if ( get_site_option( 'ms_files_rewriting' ) && defined( 'UPLOADS' ) && ! ms_is_switched() ) {
				/*
				 * Handle the old-form ms-files.php rewriting if the network still has that enabled.
				 * When ms-files rewriting is enabled, then we only listen to UPLOADS when:
				 * 1) We are not on the main site in a post-MU network, as wp-content/uploads is used
				 *    there, and
				 * 2) We are not switched, as ms_upload_constants() hardcodes these constants to reflect
				 *    the original blog ID.
				 *
				 * Rather than UPLOADS, we actually use BLOGUPLOADDIR if it is set, as it is absolute.
				 * (And it will be set, see ms_upload_constants().) Otherwise, UPLOADS can be used, as
				 * as it is relative to ABSPATH. For the final piece: when UPLOADS is used with ms-files
				 * rewriting in multisite, the resulting URL is /files. (#WP22702 for background.)
				 */

				$dir = ABSPATH . untrailingslashit( UPLOADBLOGSDIR );
				$url = trailingslashit( $siteurl ) . 'files';
			}
		}

		$basedir = $dir;
		$baseurl = $url;

		return array(
			'basedir' => $basedir,
			'baseurl' => $baseurl,
		);
	}

/**
 * Check whether the environment meets the plugin's requirements, like the minimum PHP version.
 *
 * @return bool True if the requirements are met, else false.
 */
function cloud_uploads_check_requirements() {
	global $wp_version;
	$hook = is_multisite() ? 'network_admin_notices' : 'admin_notices';

	if ( version_compare( PHP_VERSION, '5.5.0', '<' ) ) {
		add_action( $hook, 'cloud_uploads_outdated_php_version_notice' );

		return false;
	}

	if ( version_compare( $wp_version, '5.3.0', '<' ) ) {
		add_action( $hook, 'cloud_uploads_outdated_wp_version_notice' );

		return false;
	}

	return true;
}

/**
 * Print an admin notice when the PHP version is not high enough.
 *
 * This has to be a named function for compatibility with PHP 5.2.
 */
function cloud_uploads_outdated_php_version_notice() {
	?>
	<div class="notice notice-warning is-dismissible"><p>
			<?php printf( esc_html__( 'The Cloud Uploads plugin requires PHP version 5.5.0 or higher. Your server is running PHP version %s.', 'cloud-uploads' ), esc_html(PHP_VERSION) ); ?>
		</p></div>
	<?php
}

/**
 * Print an admin notice when the WP version is not high enough.
 *
 * This has to be a named function for compatibility with PHP 5.2.
 */
function cloud_uploads_outdated_wp_version_notice() {
	global $wp_version;
	?>
	<div class="notice notice-warning is-dismissible"><p>
			<?php printf( esc_html__( 'The Cloud Uploads plugin requires WordPress version 5.3 or higher. Your server is running WordPress version %s.', 'cloud-uploads' ), esc_html($wp_version) ); ?>
		</p></div>
	<?php
}

/**
 * Check if URL rewriting is enabled.
 *
 * @return bool
 */
function cloud_uploads_enabled() {
	return get_site_option( 'cloud_uploads_enabled' );
}


add_filter('wp_handle_upload', 'wpse_256351_upload', 10, 2 );
function wpse_256351_upload( $file ) {
  //* Do something interesting
	//print_r( $file, true );
	//error_log( print_r( $file, true ) );
	//$admin = new Cloud_Uploads_Admin();
	$api = new Cloud_Uploads_Api_Handler();
	$data = array("url"=>$file['url']);
	$result = $api->call('file', $data, 'POST');
	$wp_upload_url = wp_upload_dir();
	//error_log( print_r( $wp_upload_url['baseurl'], true ) );
	//wp_upload_dir
	$file['url'] = "https://test-s3.mackshost.com/".$wp_upload_url['subdir'].'/'.basename($file['url']);
	//error_log( print_r( $file, true ) );
	
  return $file;
}

add_action('wp_generate_attachment_metadata', 'process_images_on_raw_upload', 10, 2);
function process_images_on_raw_upload($data, $attachment_id) {
  //do magic here
	//error_log( print_r( $attachment_id, true ) );
	error_log( print_r( $data, true ) );
	error_log( print_r( count($data['sizes']), true ) );
	$sizes = array_values($data['sizes']);
	$wp_upload_url = wp_upload_dir();
	$api = new Cloud_Uploads_Api_Handler();

	for($i = 0; $i < count($sizes); $i++) {
		$file = $sizes[$i]['file'];
		$data = array("url"=>$wp_upload_url['url'].'/'.$file);
		$result = $api->call('file', $data, 'POST');
		error_log( print_r( $file, true ) );
	}
	// $wp_upload_url = wp_upload_dir();
	// error_log( print_r( $wp_upload_url, true ) );
	// $api = new Cloud_Uploads_Api_Handler();
	// $data = array("url"=>$wp_upload_url[url].'/'.file['url']);
	// $result = $api->call('file', $data, 'POST');
	// $wp_upload_url = wp_upload_dir();
}

// add_filter('wp_handle_upload_prefilter', 'custom_upload_filter' );

// function custom_upload_filter( $file ) {
// 	$wp_upload_url = wp_upload_dir();
// 	error_log( print_r( $file, true ) );
// 	$file['url'] = "https://test-s3.mackshost.com/".$wp_upload_url['subdir'].'/'.basename($file['url']);
// 	return $file;
// }

// add_filter( 'pre_option_upload_url_path', function( $upload_url_path ) {
// 	error_log( print_r( 'testing krishna', true ) );
// 	return 'https://test-s3.mackshost.com';
// });