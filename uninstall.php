<?php

/**
 * Fired when the plugin is uninstalled.
 */

global $wpdb;

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete options
$wpdb->delete( $wpdb->options, array( 'option_name' => 'gather_ua_jobs_settings' ) );
$wpdb->delete( $wpdb->options, array( 'option_name' => 'gather_ua_jobs_email_notifications' ) );

// If multisite, delete individual site options
if ( $blogs = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} ORDER BY blog_id", NULL ) ) ) {

    foreach ( $blogs as $this_blog_id ) {

        // Set blog id so $wpdb will know which table to tweak
        $wpdb->set_blog_id( $this_blog_id );

        // Delete site options for each site
        $wpdb->delete( $wpdb->options, array( 'option_name' => 'gather_ua_jobs_settings' ) );
        $wpdb->delete( $wpdb->options, array( 'option_name' => 'gather_ua_jobs_email_notifications' ) );

    }

}