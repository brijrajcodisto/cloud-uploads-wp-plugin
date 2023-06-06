<?php
/*
 * Plugin Name: Cloud Uploads
 * Description: Migrate and store your wordpress upload files remotely on cloud storage along with cdn for fast delivery.
 * Version: 2.0
 * Author: Brij Raj Singh
 * Author URI: https://s3uploads.com
 * Text Domain: cloud-uploads
 * Requires at least: 5.3
 * Requires PHP: 7.0
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: true
 *
 *
 * Copyright 2023 s3uploads.com
*/
if( ! defined( 'ABSPATH' ) ) {
	return;
}

define( 'CLOUD_UPLOADS_VERSION', '2.0' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/inc/class-cloud-uploads-wp-cli-command.php';
}

register_activation_hook( __FILE__, 'cloud_uploads_install' );

add_action( 'plugins_loaded', 'cloud_uploads_init' );

function cloud_uploads_init() {
  if ( ! cloud_uploads_check_requirements() ) {
		return;
	}
	if ( ! defined( 'CLOUD_UPLOADS_SYNC_PER_LOOP' ) ) {
		define( 'CLOUD_UPLOADS_SYNC_PER_LOOP', 1000 );
	}
	//add_filter( 'wp_image_editors', 'filter_editors', 9 );	
	//include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-image-editor-imagick.php';
	include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-rewriter.php';
	include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-api-handler.php';
	include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-filelist.php';
	include_once  dirname( __FILE__ ) . '/inc/class-cloud-uploads-admin.php';
	
	$admin = new Cloud_Uploads_Admin();

	cloud_uploads_upgrade();
}

function filter_editors( $editors ) {

	if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
		unset( $editors[ $position ] );
	}

	array_unshift( $editors, 'Cloud_Uploads_Image_Editor_Imagick' );

	return $editors;
}

function cloud_uploads_upgrade() {
	// Install the needed DB table if not already.
	$installed = get_site_option( 'cup_installed' );
	if ( CLOUD_UPLOADS_VERSION != $installed ) {
		cloud_uploads_install();
	}
}

function cloud_uploads_install() {
  global $wpdb;
  //prevent race condition during upgrade by setting option before running potentially long query
	if ( is_multisite() ) {
		update_site_option( 'cup_installed', CLOUD_UPLOADS_VERSION );
	} else {
		update_option( 'cup_installed', CLOUD_UPLOADS_VERSION, true );
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
			<?php printf( esc_html__( 'The Cloud Uploads plugin requires PHP version 5.5.0 or higher. Your server is running PHP version %s.', 'cloud-uploads' ), PHP_VERSION ); ?>
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
			<?php printf( esc_html__( 'The Cloud Uploads plugin requires WordPress version 5.3 or higher. Your server is running WordPress version %s.', 'cloud-uploads' ), $wp_version ); ?>
		</p></div>
	<?php
}

/**
 * Check if URL rewriting is enabled.
 *
 * @return bool
 */
function cloud_uploads_enabled() {
	return get_site_option( 'cup_enabled' );
}