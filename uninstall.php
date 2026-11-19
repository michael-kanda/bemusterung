<?php
/**
 * Aufräumen bei der Deinstallation.
 *
 * Inhalte und hochgeladene Dateien bleiben bewusst erhalten. Entfernt werden
 * die Einstellungen und alle Zugangstoken, damit keine gültigen Upload-Links
 * zurückbleiben.
 *
 * @package MXO_Bemusterung
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'mxo_bem_settings' );
delete_option( 'mxo_bem_access' );
delete_transient( 'mxo_bem_upload_pages' );

foreach ( array( 'mxo_bem_access_id' ) as $meta_key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
}

wp_cache_flush();
