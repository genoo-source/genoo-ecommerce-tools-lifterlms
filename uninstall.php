<?php
/**
 * Uninstall handler
 *
 * Fired when the plugin is uninstalled via the WordPress admin.
 * This file must be named uninstall.php and placed in the plugin root directory.
 *
 * @package GenooLLMS
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall
 *
 * Note: Post meta (connected_memberships, links-in-use, forum-for-comments,
 * courses-in-order) is intentionally NOT removed as it's associated with
 * content that users may want to preserve.
 */

// Check if user wants to preserve data (optional setting).
$preserve_data = get_option( 'genoo_llms_preserve_data_on_uninstall', false );

if ( ! $preserve_data ) {
	// Remove plugin options.
	delete_option( 'satellite_site_settings' );
	delete_option( 'genoo_llms_version' );
	delete_option( 'genoo_llms_preserve_data_on_uninstall' );

	// Clean up any transients.
	delete_transient( 'genoo_llms_satellite_connection_test' );

	// Note: We do NOT delete the following post meta as they contain user content:
	// - connected_memberships (on products)
	// - links-in-use (on lessons)
	// - forum-for-comments (on lessons)
	// - courses-in-order (on memberships)
	//
	// If a full cleanup is desired, uncomment the following:
	/*
	global $wpdb;

	// Delete post meta.
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => 'connected_memberships' ) );
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => 'links-in-use' ) );
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => 'forum-for-comments' ) );
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => 'courses-in-order' ) );

	// Delete user meta.
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'store_users' ) );
	*/
}

