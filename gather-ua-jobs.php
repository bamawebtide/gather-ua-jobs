<?php

/**
 * Plugin Name:       Gather University of Alabama Jobs
 * Plugin URI:        https://webtide.ua.edu
 * Description:       This plugin "gathers" University of Alabama faculty jobs and allows you to be notified, via email, when jobs containing certain keywords are posted.
 * Version:           1.0
 * Author:            Rachel Carden, WebTide
 * Author URI:        https://webtide.ua.edu
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gather-ua-jobs
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define constants
define( 'GATHER_UA_JOBS_TEXT_DOMAIN', 'gather-ua-jobs' );

// Define globals
global $gather_ua_jobs_recurrence_intervals;
$gather_ua_jobs_recurrence_intervals = array( 'minutes', 'hours', 'days', 'weeks', 'months' );

// The code that runs during plugin activation.
register_activation_hook( __FILE__, 'activate_gather_ua_jobs' );
function activate_gather_ua_jobs() {}

// The code that runs during plugin deactivation.
register_deactivation_hook( __FILE__, 'deactivate_gather_ua_jobs' );
function deactivate_gather_ua_jobs() {}

// Load our updater class
require plugin_dir_path( __FILE__ ) . 'includes/webtide-repo-updater.php';

// Initiate the updater
// Parameters: the plugin ID/slug, the path to the main plugin file, and the version
$webtide_repo_updater = new WebTide_Repo_Updater( 'gather-ua-jobs', 'gather-ua-jobs/gather-ua-jobs.php', 1.0 );

//! Takes care of refreshing the job feeds via URL query arguments
add_action( 'plugins_loaded', 'gather_ua_jobs_url_refresh_feeds' );
function gather_ua_jobs_url_refresh_feeds() {

	// This means to update all job feeds
	if ( isset( $_GET[ 'refresh-job-feeds' ] ) && $_GET[ 'refresh-job-feeds' ] ) {

		// This will refresh the faculty jobs feed
		fetch_ua_faculty_jobs_feed( true );

		// Go back to the main page
		wp_redirect( add_query_arg( array( 'page' => 'gather-ua-jobs', 'faculty-jobs-refreshed' => 1 ), admin_url( 'tools.php' ) ) );
		exit;

	// Only update the faculty jobs feed
	} else if ( isset( $_GET[ 'refresh-faculty-jobs-feed' ] ) && $_GET[ 'refresh-faculty-jobs-feed' ] ) {

		// This will refresh the faculty jobs feed
		fetch_ua_faculty_jobs_feed( true );

		// Go back to the main page
        wp_redirect( add_query_arg( array( 'page' => 'gather-ua-jobs', 'faculty-jobs-refreshed' => 1 ), admin_url( 'tools.php' ) ) );
		exit;

	}

}

//! Add custom cron schedule intervals
add_filter( 'cron_schedules', 'gather_ua_jobs_add_custom_cron_schedules' );
function gather_ua_jobs_add_custom_cron_schedules( $schedules ) {
	global $gather_ua_jobs_recurrence_intervals;

	// Get the saved settings
	if ( ! ( $saved_settings = get_option( 'gather_ua_jobs_settings', array() ) ) )
		return $schedules;

	// Define the email settings
	$email_notif_setting = isset( $saved_settings ) && isset( $saved_settings['email_notification'] ) ? $saved_settings['email_notification'] : null;

	// I need to add the interval from the settings
	$recurrence_digit = isset( $email_notif_setting ) && isset( $email_notif_setting['recurrence_digit'] ) && $email_notif_setting['recurrence_digit'] > 1 ? $email_notif_setting['recurrence_digit'] : 1;
	$recurrence_interval = isset( $email_notif_setting ) && isset( $email_notif_setting['recurrence_interval'] ) && in_array( $email_notif_setting['recurrence_interval'], $gather_ua_jobs_recurrence_intervals ) ? $email_notif_setting['recurrence_interval'] : 'hours';

	// Setup interval in seconds
	$interval_in_seconds = 0;

	switch( $recurrence_interval ) {

		case 'days':
			$interval_in_seconds = $recurrence_digit * DAY_IN_SECONDS;
			break;

		case 'weeks':
			$interval_in_seconds = $recurrence_digit * WEEK_IN_SECONDS;
			break;

		case 'months':
			$interval_in_seconds = strtotime( "+{$recurrence_digit} month" ) - strtotime( 'now' );
			break;

		case 'minutes':
			$interval_in_seconds = $recurrence_digit * MINUTE_IN_SECONDS;
			break;

		case 'hours':
		default:
			$interval_in_seconds = $recurrence_digit * HOUR_IN_SECONDS;
			break;

	}

	// Add to the schedule
	$schedules[ 'gather_ua_jobs_send_email_notifications' ] = array(
		'interval'  => $interval_in_seconds,
		'display'   => __( 'Gather UA Jobs', GATHER_UA_JOBS_TEXT_DOMAIN ),
	);

	return $schedules;

}

//! Register the plugin's settings
add_action( 'admin_init', 'gather_ua_jobs_register_settings' );
function gather_ua_jobs_register_settings() {

	// Holds the plugin's settings
	register_setting( 'gather_ua_jobs_settings', 'gather_ua_jobs_settings', 'gather_ua_jobs_sanitize_settings' );

}

//! Sanitize the settings whenever they are updated
function gather_ua_jobs_sanitize_settings( $settings ) {

	// No point if it's empty
	if ( empty( $settings ) )
		return $settings;

	// Clean up the keywords
	if ( isset( $settings[ 'keywords' ] ) ) {

		// Convert to array
		if ( ! is_array( $settings[ 'keywords' ] ) )
			$settings[ 'keywords' ] = explode( ',', $settings[ 'keywords' ] );

		// And trim space on left and right end
		$settings[ 'keywords' ] = array_map( 'trim', $settings[ 'keywords' ] );

	}

	// Set the email settings
	$email_notif_settings = isset( $settings[ 'email_notification' ] ) ? $settings[ 'email_notification' ] : NULL;

	// Clean up the emails
	if ( isset( $email_notif_settings[ 'emails' ] ) ) {

		// Convert to array
		if ( ! is_array( $email_notif_settings[ 'emails' ] ) )
			$email_notif_settings[ 'emails' ] = explode( ',', $email_notif_settings[ 'emails' ] );

		// And trim space on left and right end
		$email_notif_settings[ 'emails' ] = array_map( 'trim', $email_notif_settings[ 'emails' ] );

	}

	// What is the name of the hook?
	$email_notif_hook = 'gather_ua_jobs_send_email_notifications';

	// If notifications are disabled...
	if ( ! ( isset( $email_notif_settings[ 'enabled' ] ) && strcasecmp( 'yes', $email_notif_settings[ 'enabled' ] ) == 0 ) ) {

		// Clear out any scheduled hooks
		wp_clear_scheduled_hook( $email_notif_hook );

	// If notifications are enabled...
	} else {

		// Make sure we actually have emails
		if ( ! ( $emails = isset( $email_notif_settings ) && isset( $email_notif_settings[ 'emails' ] ) ? ( ! is_array( $email_notif_settings[ 'emails' ] ) ? array_map( 'trim', explode( ',', $email_notif_settings[ 'emails' ] ) ) : $email_notif_settings[ 'emails' ] ) : NULL ) ) {

			// If we don't, clear out any scheduled hooks
			wp_clear_scheduled_hook( $email_notif_hook );

		} else {

			// Check to see when the next one is scheduled with these arguments
			if ( ! ( $email_notif_timestamp = wp_next_scheduled( $email_notif_hook, $email_notif_settings ) ) ) {

				// Clear out any scheduled hooks
				wp_clear_scheduled_hook( $email_notif_hook );

				// This means it didn't exist or the arguments changed so schedule a new one
				wp_schedule_event( time(), $email_notif_hook, $email_notif_hook );

			}

		}

	}

	return $settings;

}

//! Send email notification
add_action( 'gather_ua_jobs_send_email_notifications', 'gather_ua_jobs_send_email_notifications' );
function gather_ua_jobs_send_email_notifications() {

	// Get the saved settings
    $saved_settings = get_option( 'gather_ua_jobs_settings', array() );

    // Define the settings
    $keywords_setting = isset( $saved_settings ) && isset( $saved_settings[ 'keywords' ] ) ? $saved_settings[ 'keywords' ] : NULL;
    $email_notif_setting = isset( $saved_settings ) && isset( $saved_settings[ 'email_notification' ] ) ? $saved_settings[ 'email_notification' ] : NULL;

    // Check to make sure notifications are enabled
    if ( ! ( isset( $email_notif_setting[ 'enabled' ] ) && strcasecmp( 'yes', $email_notif_setting[ 'enabled' ] ) == 0 ) )
        return false;

    // Make sure we actually have emails
    if ( ! ( $emails = isset( $email_notif_setting ) && isset( $email_notif_setting[ 'emails' ] ) ? ( ! is_array( $email_notif_setting[ 'emails' ] ) ? array_map( 'trim', explode( ',', $email_notif_setting[ 'emails' ] ) ) : $email_notif_setting[ 'emails' ] ) : NULL ) )
        return false;

    // Get the jobs
    if ( ! ( $faculty_jobs = gather_ua_faculty_jobs( array( 'keywords' => $keywords_setting ) ) ) )
        return false;

    // Get our notification options
    $email_notification_option_name = 'gather_ua_jobs_email_notifications';
    $email_notification_options = get_option( $email_notification_option_name, array() );

    // Store in new options that will merge upon successful email
    $new_email_notification_options = array();

    // Build the email message
    $email_message = NULL;

    // Set the font family
    $font_family = "font-family: Arial, Helvetica, sans-serif;";

    // Go through each job and see if it needs to be added to the message
    $job_count = 0;
    foreach( $faculty_jobs as $job ) {

        // See if a notification has been set
        if ( ! empty( $email_notification_options ) && isset( $email_notification_options[ $job->ID ] ) )
            continue;

        // Make sure we have a title and a permalink
        if ( ! ( isset( $job->permalink ) && isset( $job->title ) ) )
            continue;

        // Store in new options
        $new_email_notification_options[ $job->ID ] = array(
            'time' => strtotime( 'now' ),
            'to' => $emails
        );

        // Add a divider
        $email_message .= '<hr />';

        // Add title and permalink to email message
        $email_message .= '<h4 style="margin-bottom:2px; color:#990000; ' . $font_family . ' font-size:17px; line-height:22px;"><a href="' . esc_url( $job->permalink ) . '" target="_blank" style="color:#990000;">' . esc_html__( $job->title, GATHER_UA_JOBS_TEXT_DOMAIN ) . '</a></h4>' . "\n\n";

        // Add published date to email message
        if ( isset( $job->published ) )
            $email_message .= '<p style="margin:0;' . $font_family . 'font-size:14px;line-height:18px;"><strong>Published:</strong> ' . $job->published->format( 'l, M j, Y' ) . '</p>' . "\n\n";

        // Add authors to email message
        if ( isset( $job->authors ) ) {

            // Build the author string
            $author_array = array();
            foreach( $job->authors as $author ) {
                if ( isset( $author->name ) )
                    $author_array[] = $author->name;
            }

            // If authors, add to email
            if ( ! empty( $author_array ) )
                $email_message .= '<p style="margin:0;' . $font_family . 'font-size:14px;line-height:18px;"><strong>Author(s):</strong> ' . implode( ', ', $author_array ) . '</p>' . "\n\n";

        }

        // Add content to email message
        if ( isset( $job->content ) )
            $email_message .= '<p style="margin:15px 0 20px 0;' . $font_family . 'font-size:15px;line-height:19px;">' . wp_trim_words( esc_html__( $job->content, GATHER_UA_JOBS_TEXT_DOMAIN ), $num_words = 55, '...' ) . '</p>' . "\n\n";

        $job_count++;

    }

    // Make sure we have an email message
    // If we don't it's because there are no new jobs to be notified
    if ( ! $email_message )
        return false;

    // Add instructions before the postings
    $tools_page_url = add_query_arg( array( 'page' => 'gather-ua-jobs' ), admin_url( 'tools.php' ) );
    $email_message = '<p><em>' . sprintf( __( 'The following UA faculty jobs postings match the keywords set for the %1$sGather UA Jobs plugin%2$s on the %3$s%4$s%5$s site. Please %6$svisit the plugin\'s tools page%7$s if you would like to modify the keywords or email notification settings.', GATHER_UA_JOBS_TEXT_DOMAIN ), '<a href="' . $tools_page_url . '" target="_blank" style="color:#990000;">', '</a>', '<a href="' . get_bloginfo( 'url' ) . '" target="_blank" style="color:#990000;">', get_bloginfo( 'name' ), '</a>', '<a href="' . $tools_page_url . '" target="_blank" style="color:#990000;">', '</a>' ) . '</em></p>' . $email_message;

    // Set the email as HTML
    add_filter( 'wp_mail_content_type', 'gather_ua_jobs_set_html_content_type' );

    // Try to send the email
    $send_the_email = wp_mail( $emails, 'New UA Faculty Job Postings', $email_message );

    // Reset content-type to avoid conflicts
    remove_filter( 'wp_mail_content_type', 'gather_ua_jobs_set_html_content_type' );

    // The email didn't send so get out of here
    if ( ! $send_the_email )
        return false;

    // Make sure both options are arrays
    if ( ! is_array( $email_notification_options ) )
        $email_notification_options = array();

    if ( ! is_array( $new_email_notification_options ) )
        $new_email_notification_options = array();

    // Merge the old options with the new options
    $email_notification_options = $email_notification_options + $new_email_notification_options;

    // Update the notification options
    update_option( $email_notification_option_name, $email_notification_options );

    return true;

}

//! Set emails as HTML emails
function gather_ua_jobs_set_html_content_type() {
    return 'text/html';
}

//! Add Tools page
add_action( 'admin_menu', 'register_gather_ua_jobs_tools_page' );
function register_gather_ua_jobs_tools_page() {

	// Add the tools page
	add_management_page( __( 'Gather UA Jobs', GATHER_UA_JOBS_TEXT_DOMAIN ), __( 'Gather UA Jobs', GATHER_UA_JOBS_TEXT_DOMAIN ), 'edit_posts', 'gather-ua-jobs', 'print_gather_ua_jobs_tools_page' );

}

//! Print Tools page
function print_gather_ua_jobs_tools_page() {

	// Include the file
	require plugin_dir_path( __FILE__ ) . 'includes/display-gather-ua-jobs-tools-page.php';

}

//! Add meta boxes to the Tools page
add_action( 'admin_head-tools_page_gather-ua-jobs', 'add_gather_ua_jobs_tools_meta_boxes' );
function add_gather_ua_jobs_tools_meta_boxes() {

    // Need this script for the meta boxes to work correctly
    wp_enqueue_script( 'post' );
    wp_enqueue_script( 'postbox' );

    // About this Plugin
    add_meta_box( 'gather-ua-jobs-tools-about-mb', __( 'About this Plugin', GATHER_UA_JOBS_TEXT_DOMAIN ), 'print_gather_ua_jobs_tools_meta_boxes', 'gather-ua-jobs-tools', 'side', 'core' );

    // Settings
    add_meta_box( 'gather-ua-jobs-tools-settings-mb', __( 'Settings', GATHER_UA_JOBS_TEXT_DOMAIN ), 'print_gather_ua_jobs_tools_meta_boxes', 'gather-ua-jobs-tools', 'normal', 'core' );

    // Faculty Jobs
    add_meta_box( 'gather-ua-jobs-tools-faculty-jobs-mb', __( 'Faculty Jobs', GATHER_UA_JOBS_TEXT_DOMAIN ), 'print_gather_ua_jobs_tools_meta_boxes', 'gather-ua-jobs-tools', 'normal', 'core' );

}

//! Print meta boxes on the Tools page
function print_gather_ua_jobs_tools_meta_boxes( $post, $metabox ) {
    global $gather_ua_jobs_recurrence_intervals;

    switch( $metabox[ 'id' ] ) {

        // About this Plugin
        case 'gather-ua-jobs-tools-about-mb':

            ?><h3 style="margin:0;padding:0;"><?php _e( 'Gathering UA Faculty Jobs', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></h3>
            <p style="margin-top: 5px;"><?php _e( 'This plugin "gathers" UA faculty jobs and allows you to be notified via email when jobs containing certain keywords are posted.', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></p>
            <p><?php _e( 'You can also use the gather_ua_faculty_jobs() function to gather the job information and use at will.', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></p>

            <h3 style="margin:20px 0 0 0;padding:0;"><?php _e( 'Gathering UA Staff Jobs', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></h3>
            <p style="margin-top: 5px;"><?php printf( __( 'At this point in time, %1$sthis plugin only gathers information for faculty jobs%2$s. The %3$sstaff jobs website%4$s does, however, have the capability to send you job alerts (filtered by keyword or category) via email. You can %5$ssetup UA staff job alerts%6$s on their website.', GATHER_UA_JOBS_TEXT_DOMAIN ), '<strong>', '</strong>', '<a href="http://staffjobs.ua.edu/jobAlert.html" target="_blank">', '</a>', '<a href="http://staffjobs.ua.edu/jobAlert.html" target="_blank">', '</a>' ); ?></p><?php

            break;

        // Settings
        case 'gather-ua-jobs-tools-settings-mb':

            // Get the saved settings
            $saved_settings = get_option( 'gather_ua_jobs_settings', array() );

            // Define the settings
            $keywords_setting = isset( $saved_settings ) && isset( $saved_settings[ 'keywords' ] ) ? $saved_settings[ 'keywords' ] : NULL;
            $email_notif_setting = isset( $saved_settings ) && isset( $saved_settings[ 'email_notification' ] ) ? $saved_settings[ 'email_notification' ] : NULL;

            // Define the email notification settings
            $email_notif_enabled_setting = isset( $email_notif_setting ) && isset( $email_notif_setting[ 'enabled' ] ) && 0 == strcasecmp( 'yes', $email_notif_setting[ 'enabled' ] ) ? true : false;
            $email_notif_emails_setting = isset( $email_notif_setting ) && isset( $email_notif_setting[ 'emails' ] ) ? $email_notif_setting[ 'emails' ] : NULL;
            $email_notif_recurrence_digit_setting = isset( $email_notif_setting ) && isset( $email_notif_setting[ 'recurrence_digit' ] ) ? $email_notif_setting[ 'recurrence_digit' ] : 1;
            $email_notif_recurrence_interval_setting = isset( $email_notif_setting ) && isset( $email_notif_setting[ 'recurrence_interval' ] ) ? $email_notif_setting[ 'recurrence_interval' ] : 'hours';

            ?><form method="post" action="options.php"><?php

                // Handle the settings
                settings_fields( 'gather_ua_jobs_settings' );

                ?><table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label for="gather-ua-jobs-settings-keywords"><?php _e( 'Job Keywords', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <input name="gather_ua_jobs_settings[keywords]" type="text" id="gather-ua-jobs-settings-keywords" value="<?php echo is_array( $keywords_setting ) ? implode( ', ', $keywords_setting ) : $keywords_setting; ?>" class="regular-text" />
                            <p class="description"><strong><?php _e( 'Separate your keywords with commas.', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></strong> <?php _e( 'These will decide which jobs you\'d like to view and are highlighted below.', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gather-ua-jobs-settings-email-notification-0"><?php _e( 'Would you like to be notified by email when a job posting matches one of your keywords?', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></label></th>
                        <td>
                            <fieldset>
                                <label><input id="gather-ua-jobs-settings-email-notification-0" name="gather_ua_jobs_settings[email_notification][enabled]" type="radio" value="yes"<?php checked( $email_notif_enabled_setting ); ?> /> <?php _e( 'Yes', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></label> &nbsp;&nbsp;
                                <label><input id="gather-ua-jobs-settings-email-notification-1" name="gather_ua_jobs_settings[email_notification][enabled]" type="radio" value=""<?php checked( ! $email_notif_enabled_setting ); ?> /> <?php _e( 'No', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></label>
                            </fieldset>

                            <h4 class="subsection-header"><?php _e( 'To Which Email Address(es)?', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></h4>
                            <input name="gather_ua_jobs_settings[email_notification][emails]" type="text" id="gather-ua-jobs-settings-email-notification-emails" value="<?php echo is_array( $email_notif_emails_setting ) ? implode( ', ', $email_notif_emails_setting ) : $email_notif_emails_setting; ?>" class="regular-text" />
                            <p class="description"><strong><?php _e( 'Separate your email addresses with commas.', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></strong></p>

                            <h4 class="subsection-header"><?php _e( 'How Often Would You Like To Check The Jobs?', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></h4>
                            <p>Every <input name="gather_ua_jobs_settings[email_notification][recurrence_digit]" type="number" id="gather-ua-jobs-settings-email-notification-recurrence-digit" style="width:60px;" value="<?php echo $email_notif_recurrence_digit_setting > 1 ? $email_notif_recurrence_digit_setting : 1; ?>" class="regular-text" />
                                <select name="gather_ua_jobs_settings[email_notification][recurrence_interval]" id="gather-ua-jobs-settings-email-notification-recurrence"><?php

                                    foreach ( $gather_ua_jobs_recurrence_intervals as $interval ) {
                                        ?><option value="<?php echo $interval; ?>"<?php selected( $interval, $email_notif_recurrence_interval_setting ); ?>><?php _e( ucwords( $interval ), GATHER_UA_JOBS_TEXT_DOMAIN ); ?></option><?php
                                    }

                                    ?></select></p>

                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="padding-top: 0;"><?php echo submit_button( __( 'Save Your Settings', GATHER_UA_JOBS_TEXT_DOMAIN ), 'primary', 'save_gather_ua_jobs_settings', false, array( 'id' => 'save-gather-ua-jobs-settings-button' ) ); ?></td>
                    </tr>
                    </tbody>
                </table>

            </form><?php

            break;

        // Faculty Jobs
        case 'gather-ua-jobs-tools-faculty-jobs-mb':

            // Get the saved settings
            $saved_settings = get_option( 'gather_ua_jobs_settings', array() );

            // Define the settings
            $keywords_setting = isset( $saved_settings ) && isset( $saved_settings[ 'keywords' ] ) ? $saved_settings[ 'keywords' ] : NULL;

            // Have the faculty jobs been refreshed?
            $faculty_jobs_refreshed = isset( $_GET[ 'faculty-jobs-refreshed' ] ) && $_GET[ 'faculty-jobs-refreshed' ] ? true : false;

            // Print the "Refresh List" button
            ?><a class="refresh-button button button-secondary" href="<?php echo add_query_arg( array( 'page' => 'gather-ua-jobs', 'refresh-faculty-jobs-feed' => 1 ), admin_url( 'tools.php' ) ); ?>"><?php _e( 'Refresh List', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></a><?php

            // If we just refreshed the list...
            if ( $faculty_jobs_refreshed ) {

                // If refreshed, this will highlight the area
                ?><style type="text/css">
                    #gather-ua-jobs-tools-faculty-jobs-mb {
                        background-color: #009f3f;
                        -webkit-animation: fadeToWhite 1s forwards;
                        -moz-animation: fadeToWhite 1s forwards;
                        -ms-animation: fadeToWhite 1s forwards;
                        -o-animation: fadeToWhite 1s forwards;
                        animation: fadeToWhite 1s forwards;
                    }
                    @keyframes fadeToWhite {
                        to { background-color: #fff; }
                    }
                    @-webkit-keyframes fadeToWhite {
                        to { background-color: #fff; }
                    }
                    @-moz-keyframes fadeToWhite {
                        to { background-color: #fff; }
                    }

                    @-o-keyframes fadeToWhite {
                        to { background-color: #fff; }
                    }
                    @-ms-keyframes fadeToWhite {
                        to { background-color: #fff; }
                    }
                </style>

                <p class="refreshed-message"><strong><?php _e( 'The faculty jobs list has been refreshed.', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></strong></p><?php

            }

            ?><p><?php _e( 'If you have provided keywords, jobs that include those keywords will be listed, and highlighted, at the top.', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></p><?php

            // Print the faculty jobs from the feed
            print_ua_faculty_jobs( array( 'keywords' => $keywords_setting ) );

            break;

    }

}

//! Print UA faculty jobs
function print_ua_faculty_jobs( $args = array() ) {

	// Set up the arguments
	$defaults = array(
		'keywords' => NULL,
        'highlight_keywords' => true,
        'show_all' => true,
	);

	// Parse the args
	$args = wp_parse_args( $args, $defaults );
	extract( $args, EXTR_OVERWRITE );

	// Clean up the keywords
	if ( ! empty( $keywords ) ) {

		// Make sure $keywords is an array
		if ( ! is_array( $keywords ) )
			$keywords = explode( ',', $keywords );

		// Clean up the array
		$keywords = array_map( 'trim', $keywords );

	}

    // Setup gather args
    $gather_args = array(
        'keywords' => $show_all ? NULL : $keywords,
    );

	// Get the jobs
	if ( ! ( $jobs = gather_ua_faculty_jobs( $gather_args ) ) ) {

		?><p><em><?php _e( 'There are no faculty jobs at this time.', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></em></p><?php
		return;

	}

	// Sort jobs by keyword first
	$keyword_jobs = array();
	$nonkeyword_jobs = array();
	foreach ( $jobs as $job ) :

		// Search title and content for keywords
		if ( ! empty( $keywords ) && is_array( $keywords ) ) :

			// Create the search regex
			$search_regex = '/(' . implode( '|', $keywords ) . ')/i';

			// Create the regex
			$replace_regex = '/(' . implode( '|', $keywords ) . ')/i';

			// Keyword exist?
			$keyword_exist = false;

			// Keywords in the title?
			if ( preg_match( $search_regex, $job->title ) ) {

				$keyword_exist = true;

				// Add the highlight span
                if ( $highlight_keywords )
                    $job->title = preg_replace( $replace_regex, "<span class=\"highlight-keyword\">$1</span>", $job->title );

			}

			// Keywords in the author?
			foreach( $job->authors as $author ) {

				// Search the name
				if ( isset( $author->name ) && preg_match( $search_regex, $author->name ) ) {

					$keyword_exist = true;

					// Add the highlight span
                    if ( $highlight_keywords )
                        $author->name = preg_replace( $replace_regex, "<span class=\"highlight-keyword\">$1</span>", $author->name );

				}

			}

			// Keywords in the content?
			if ( preg_match( $search_regex, $job->content ) ) {

				$keyword_exist = true;

				// Add the highlight span
                if ( $highlight_keywords )
                    $job->content = preg_replace( $replace_regex, "<span class=\"highlight-keyword\">$1</span>", $job->content );

			}

			// Add to the top of the list
			if ( $keyword_exist ) {

				$keyword_jobs[] = $job;
				continue;

			}

		endif;

		// Add everything else to the end fo the list
		$nonkeyword_jobs[] = $job;

	endforeach;

	?><ul class="ua-faculty-jobs"><?php

		foreach( array( 'keyword-jobs' => $keyword_jobs, 'nonkeyword-jobs' => $nonkeyword_jobs ) as $job_list => $job_list_jobs ) :
			foreach( $job_list_jobs as $job ) :

            // Assign classes
            $job_item_classes = array( $job_list );

            // Highlight job items if 'highlight_keywords' is true
            if ( $highlight_keywords && 'keyword-jobs' == $job_list )
                $job_item_classes[] = 'highlight';

			?><li class="<?php echo implode( ' ', $job_item_classes ); ?>">
				<span class="title"><a href="<?php echo esc_url( $job->permalink ); ?>" target="_blank"><?php _e( $job->title, GATHER_UA_JOBS_TEXT_DOMAIN ); ?></a></span>
				<ul>
					<li class="published"><strong><?php _e( 'Published:', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></strong> <?php echo $job->published->format( 'F j, Y \a\t g:i a' ); ?></li><?php

						if ( $job->published != $job->updated ) {
							?><li class="updated"><strong><?php _e( 'Updated:', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></strong> <?php echo $job->updated->format( 'F j, Y \a\t g:i a' ); ?></li><?php
						}

					?><li class="authors"><strong><?php _e( 'Authors:', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></strong> <?php

						// Build author name array
						$author_names = array();
						foreach( $job->authors as $author ) {
							if ( isset( $author->name ) )
								$author_names[] = $author->name;
						}

						// Print author names
						echo ! empty( $author_names ) ? implode( ', ', $author_names ) : NULL;

					?></li>
					<li class="content"><strong><?php _e( 'Description:', GATHER_UA_JOBS_TEXT_DOMAIN ); ?></strong> <?php _e( $job->content, GATHER_UA_JOBS_TEXT_DOMAIN ); ?></li>
				</ul>
			</li><?php

			endforeach;
		endforeach;

	?></ul><?php

}

//! Gather UA faculty jobs
function gather_ua_faculty_jobs( $args = array() ) {

	// Set up the arguments
	$defaults = array(
		'keywords' => NULL,
	);
	extract( wp_parse_args( $args, $defaults ), EXTR_OVERWRITE );

    // Clean up the keywords
    if ( ! empty( $keywords ) ) {

        // Make sure $keywords is an array
        if ( ! is_array( $keywords ) )
            $keywords = explode( ',', $keywords );

        // Clean up the array
        $keywords = array_map( 'trim', $keywords );

    }

    // Get jobs from the UA faculty jobs feed - caches for 12 hours
	$the_jobs_feed = fetch_ua_faculty_jobs_feed();

	// Make sure there isn't an error
	if ( is_wp_error( $the_jobs_feed ) )
		return false;

	// Figure out how many feed items there are
	$feed_count = $the_jobs_feed->get_item_quantity();

	// Sort/gather the feed items
	if ( $orig_feed_items = $the_jobs_feed->get_items( 0, $feed_count ) ) :

		// Create array of feed items to return
		$feed_items = array();

		// Loop through the items
		foreach ( $orig_feed_items as $job ) :

            // Set the job ID
            $job_id = $job->get_id();

            // Set the job title
            $job_title = $job->get_title();

            // Set the job content
            $job_content = $job->get_content();

            // Set the job authors
            $job_authors = $job->get_authors();

            // Search title and content for keywords
            if ( ! empty( $keywords ) && is_array( $keywords ) ) :

               // Create the search regex
               $search_regex = '/(' . implode( '|', $keywords ) . ')/i';

               // Keyword exist?
               $keyword_exist = false;

               // Keywords in the title?
               if ( preg_match( $search_regex, $job_title ) ) {

                    $keyword_exist = true;

               // Keywords in the content?
               } else if ( preg_match( $search_regex, $job_content ) ) {

                   $keyword_exist = true;

               } else {

                   // Keywords in the authors?
                   foreach( $job_authors as $author ) {

                       // Search the name
                       if ( isset( $author->name ) && preg_match( $search_regex, $author->name ) ) {

                           $keyword_exist = true;
                           break;

                       }

                   }

               }

               // If keyword doesn't exist, don't include
               if ( ! $keyword_exist ) {

                   continue;

               }

            endif;

			// Get published date (is in UTC)
			$published_date = ( $published_date_str = $job->get_gmdate( 'Y-m-d H:i:s O' ) ) ? new DateTime( $published_date_str ) : false;

			// Get updated date (is in UTC)
			$updated_date = ( $updated_date_str = $job->get_updated_gmdate( 'Y-m-d H:i:s O' ) ) ? new DateTime( $updated_date_str ) : false;

			// Get site's timezone
			if ( $timezone = get_option( 'timezone_string' ) ) {

				// Convert to timezone
				$published_date->setTimezone( new DateTimeZone($timezone ) );
				$updated_date->setTimezone( new DateTimeZone($timezone ) );

			}

			// Add job object
			$feed_items[] = (object) array (
                'ID' => $job_id,
				'permalink' => esc_url( $job->get_permalink() ),
				'published' => $published_date,
				'updated' => $updated_date,
				'title' => esc_html( $job_title ),
				'authors' => $job_authors,
				'content' => esc_html( $job_content )
			);

		endforeach;

		return $feed_items;

	endif;

	return false;

}

//! Get the faculty jobs feed
function fetch_ua_faculty_jobs_feed( $refresh = false ) {

	// Will refresh the cache lifetime to update the feed
	if ( $refresh ) {
		add_filter( 'wp_feed_cache_transient_lifetime', function ( $lifetime ) { return 0; } );
	}

	return fetch_feed( 'https://facultyjobs.ua.edu/all_jobs.atom' );

}