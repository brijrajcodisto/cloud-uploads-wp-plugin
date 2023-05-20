<?php
/*
 * Plugin Name: S3 Uploads
 * Description: Infinitely scalable cloud storage and delivery for your videos and uploads made easy! Upload directly to cloud storage and manage your files right from the WordPress Media Library.
 * Version: 2.0
 * Author: S3 Uploads
 * Author URI: https://s3uploads.com
 * Text Domain: s3uploads
 * Requires at least: 5.3
 * Requires PHP: 7.0
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: true
 *
 * Inspired by AWS PHP SDK stream wrapper code from the S3 Uploads plugin by Human Made https://github.com/humanmade/S3-Uploads.
 *
 * Copyright 2023 s3uploads.com
*/
if( ! defined( 'ABSPATH' ) ) {
	return;
}

define( 'S3_UPLOADS_VERSION', '2.0' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once dirname( __FILE__ ) . '/inc/class-s3-uploads-wp-cli-command.php';
}

register_activation_hook( __FILE__, 's3_uploads_install' );

add_action( 'plugins_loaded', 's3_uploads_init' );

function s3_uploads_init() {
  if ( ! s3_uploads_check_requirements() ) {
		return;
	}
	include_once  dirname( __FILE__ ) . '/inc/class-s3-uploads-api-handler.php';
	include_once  dirname( __FILE__ ) . '/inc/class-s3-uploads-filelist.php';
	include_once  dirname( __FILE__ ) . '/inc/class-s3-uploads-admin.php';
	$admin = new S3_Uploads_Admin();
	
				
	s3_uploads_upgrade();
}

function s3_uploads_upgrade() {
	// Install the needed DB table if not already.
	$installed = get_site_option( 's3up_installed' );
	if ( S3_UPLOADS_VERSION != $installed ) {
		s3_uploads_install();
	}
}

function s3_uploads_install() {
  global $wpdb;
  //prevent race condition during upgrade by setting option before running potentially long query
	if ( is_multisite() ) {
		update_site_option( 's3up_installed', S3_UPLOADS_VERSION );
	} else {
		update_option( 's3up_installed', S3_UPLOADS_VERSION, true );
	}
  $charset_collate = $wpdb->get_charset_collate();

	//191 is the maximum innodb default key length on utf8mb4
	$sql = "CREATE TABLE {$wpdb->base_prefix}s3_uploads_files (
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
function s3_uploads_check_requirements() {
	global $wp_version;
	$hook = is_multisite() ? 'network_admin_notices' : 'admin_notices';

	if ( version_compare( PHP_VERSION, '5.5.0', '<' ) ) {
		add_action( $hook, 's3_uploads_outdated_php_version_notice' );

		return false;
	}

	if ( version_compare( $wp_version, '5.3.0', '<' ) ) {
		add_action( $hook, 's3_uploads_outdated_wp_version_notice' );

		return false;
	}

	return true;
}

/**
 * Print an admin notice when the PHP version is not high enough.
 *
 * This has to be a named function for compatibility with PHP 5.2.
 */
function s3_uploads_outdated_php_version_notice() {
	?>
	<div class="notice notice-warning is-dismissible"><p>
			<?php printf( esc_html__( 'The S3 Uploads plugin requires PHP version 5.5.0 or higher. Your server is running PHP version %s.', 's3-uploads' ), PHP_VERSION ); ?>
		</p></div>
	<?php
}

/**
 * Print an admin notice when the WP version is not high enough.
 *
 * This has to be a named function for compatibility with PHP 5.2.
 */
function s3_uploads_outdated_wp_version_notice() {
	global $wp_version;
	?>
	<div class="notice notice-warning is-dismissible"><p>
			<?php printf( esc_html__( 'The S3 Uploads plugin requires WordPress version 5.3 or higher. Your server is running WordPress version %s.', 's3-uploads' ), $wp_version ); ?>
		</p></div>
	<?php
}

/**
 * Check if URL rewriting is enabled.
 *
 * @return bool
 */
function s3_uploads_enabled() {
	return get_site_option( 's3up_enabled' );
}
