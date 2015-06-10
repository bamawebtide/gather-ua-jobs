<?php

/**
 * This class defines all the code necessary to update the plugin from the WebTide repository.
 *
 * @since      1.0
 * @author     Rachel Carden <rmcarden@ua.edu>
 */
class WebTide_Repo_Updater {

    /**
     * The ID/slug of this plugin.
     *
     * @since    1.0
     * @access   private
     * @var      string    $plugin_slug    The ID/slug of this plugin.
     */
    private $plugin_slug;

    /**
     * The path of the plugin's main file.
     *
     * @since    1.0
     * @access   private
     * @var      string    $plugin_file     The path of the plugin's main file.
     */
    private $plugin_file;

    /**
     * The version of this plugin.
     *
     * @since    1.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Will hold the update response so we don't have to request it multiple times.
     *
     * @since    1.0
     * @access   private
     * @var      object    $update_response    Holds the update response
     */
    private $update_response;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0
     * @var      string    $plugin_slug		The ID of this plugin.
     * @var      string    $plugin_file		The path of the plugin's main file.
     * @var      string    $version         The version of this plugin.
     */
    public function __construct( $plugin_slug, $plugin_file, $version ) {

        // Set some data
        $this->plugin_slug = $plugin_slug;
        $this->plugin_file = $plugin_file;
        $this->version = $version;

        // Check for the plugin update
        add_filter( 'site_transient_update_plugins', array( $this, 'check_for_plugin_update' ), 10 );

        // Display the update changelog
        add_action( 'install_plugins_pre_plugin-information', array( $this, 'display_changelog' ), 0 );

    }

    /**
     * Retrieves the update response information
     * from the WebTide website.
     *
     * @since 	1.0
     */
    private function get_plugin_update_response() {

        // See if we already have the update response
        if ( isset( $this->update_response ) && ! empty( $this->update_response ) ) {

            // Return the response
            return $this->update_response;

        }

        // Create the remote URL
        if ( $remote_url = add_query_arg( array( 'get_wp_plugin_update_response' => $this->plugin_slug, 'current_version' => $this->version ), 'https://webtide.ua.edu/' ) ) {

            // Get the response body
            if ( ( $response = wp_remote_get( $remote_url ) ) && ! is_wp_error( $response )
                && ( $response_body = json_decode( wp_remote_retrieve_body( $response ) ) ) ) {

                // Store the response
                $this->update_response = $response_body;

                // Return the response
                return $this->update_response;

            }

        }

    }

    /**
     * Because this plugin doesn't live in the WordPress.org repo,
     * we have to manually check to see if the plugin has an update
     * from the WebTide website.
     *
     * @since 	1.0
     * @param	$plugins_info - an array of plugin info
     * @return 	array - the plugin info info after it has been filtered
     */
    public function check_for_plugin_update( $plugins_info ) {

        // Make sure we have checked info which contains the current version
        if ( ! ( $current_version = isset( $plugins_info->checked ) && isset( $plugins_info->checked ) && isset( $plugins_info->checked[ $this->plugin_file ] ) ? $plugins_info->checked[ $this->plugin_file ] : false ) )
            return $plugins_info;

        // Get the update response
        if ( $update_response = $this->get_plugin_update_response() ) {

            // If we have a response, add to the info
            if ( ( $new_version = isset( $update_response->new_version ) ? $update_response->new_version : false )
                && floatval( $new_version ) > floatval( $this->version ) ) {

                // Add the response to the plugins info
                $plugins_info->response[ $this->plugin_file ] = $update_response;

            }

        }

        return $plugins_info;

    }

    /**
     * If the plugin has an update, this displays the changelog
     * when the user is wanting to view the update details.
     *
     * @since 	1.0
     */
    public function display_changelog() {

        // Confirm we're viewing our plugin
        if ( ! ( $this->plugin_slug == $_REQUEST[ 'plugin' ] && 'plugin-information' == $_REQUEST[ 'tab' ] && 'changelog' == $_REQUEST[ 'section' ] ) )
            return;

        // Get the update response
        if ( $update_response = $this->get_plugin_update_response() ) {

            // Get new version
            $new_version = isset( $update_response->new_version ) ? $update_response->new_version : false;

            // Add styles
            ?><style type="text/css">
                h1 {font-size:20px; font-family:"Lucida Grande",Lucida,"Lucida Sans",Arial,sans-serif;}
                h2 {font-size:16px; font-family:"Lucida Grande",Lucida,"Lucida Sans",Arial,sans-serif;}
                h3 {font-size:16px; font-family:"Lucida Grande",Lucida,"Lucida Sans",Arial,sans-serif;}
                p {font-size:13px; font-family:"Lucida Grande",Lucida,"Lucida Sans",Arial,sans-serif;}
                a {color:#900;}
                li {font-size:13px; font-family:"Lucida Grande",Lucida,"Lucida Sans",Arial,sans-serif;line-height:150%; margin-bottom:6px;}
            </style><?php

            // Display wrapper
            ?><div style="height:97%;margin:20px;padding:20px;border:1px solid #aaa;-moz-box-sizing: border-box;-webkit-box-sizing: border-box;box-sizing: border-box;">
            <h2>What's New in UA MyBama CAS Authentication<?php echo $new_version ? " v{$new_version}" : NULL; ?></h2><?php

            // Get changelog data
            if ( $changelog = isset( $update_response->changelog ) ? $update_response->changelog : false ) {

                // If the changelog is markdown, we need the parser
                if ( isset( $update_response->changelog_is_markdown ) && $update_response->changelog_is_markdown ) {

                    // Load the ParseDown library
                    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ParseDown.php';

                    // Setup ParseDown
                    $parsedown = new Parsedown();

                    // Print Markdown text
                    echo $parsedown->text( $changelog );

                    // Otherwise, just print
                } else {

                    echo wpautop( $changelog );

                }

            } else {

                ?><p>We seem to be missing the changelog data. If you continue to have problems, please <a href="https://webtide.ua.edu/contact/">let WebTide know</a>.</p><?php

            }

            ?></div><?php

            exit;

        }

    }

}