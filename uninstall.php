<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Media_Trim
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'media_trim_settings' );
delete_option( 'media_trim_scan_results' );
delete_option( 'media_trim_whitelist' );
delete_site_option( 'media_trim_settings' );
delete_site_option( 'media_trim_scan_results' );
delete_site_option( 'media_trim_whitelist' );

// Clean transients.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mtrim_%' OR option_name LIKE '_transient_timeout_mtrim_%'" );
