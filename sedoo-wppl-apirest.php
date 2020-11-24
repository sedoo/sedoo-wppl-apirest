<?php
/**
 * Plugin Name:     Sedoo Wppl RESTAPI for wp cli
 * Plugin URI:      https://github.com/sedoo/sedoo-wppl-apirest
 * Description:     Endpoints for wp cli management 
 * Author:          Pierre VERT & Nicolas Gruwe - SEDOO DATA CENTER
 * Author URI:      https://www.sedoo.fr 
 * Text Domain:     sedoo-wppl-apirest
 * Domain Path:     /languages
 * Version:         0.1.5
 * GitHub Plugin URI: sedoo/sedoo-wppl-apirest
 * GitHub Branch:     master
 * @package         sedoo-wppl-apirest
 */

 
/**
 * Docs : https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/#creating-endpoints 
 */



/////// *** FUNCTION USED TO GENERATE LINKS *****//////

// Generate activation link here
function sedoo_apirest_one_site_generate_plugin_activate_link($plugin)
{
    // the plugin might be located in the plugin folder directly
    if (strpos($plugin, '/')) {
        $plugin = str_replace('/', '%2F', $plugin);
    }
    $activateUrl = sprintf(admin_url('plugins.php?action=activate&plugin=%s&plugin_status=all&paged=1&s'), $plugin);
    // change the plugin request to the plugin to pass the nonce check
    $_REQUEST['plugin'] = $plugin;
    $activateUrl = wp_nonce_url($activateUrl, 'activate-plugin_' . $plugin);
    return $activateUrl;
}




/**
 * Get the list of public sites
 * @return array The list of public sites
 */
function sedoo_wppl_restapi_get_sites() {
    $args = array(
        'public'    => 1,   // I only want the sites marked Public
        'archived'  => 0,
        'mature'    => 0,
        'spam'    => 0,
        'deleted'   => 0,
    );
    $sites = get_sites( $args );    
    return $sites;
}

/**
 * Get blog sites
 * @return array The list of public sites
 */

/////
// GET ALL SITES LIST  
// network/sites
/////
function sedoo_wppl_restapi_get_all_sites() {
    $sites_list = get_sites();
    foreach($sites_list as $site) {
        switch_to_blog( $site->blog_id );
            $theme_data = wp_get_theme();
            $theme_info = ['theme_name' => $theme_data->get( 'Name' ), 'theme_version' => $theme_data->get( 'Version' )];  
            $site->current_theme = $theme_info;
            $site->site_name = get_bloginfo('name');

        restore_current_blog();
    }
    // rest_ensure_response() wraps the data we want to return into a WP_REST_Response, and ensures it will be properly returned.
    return rest_ensure_response($sites_list);
}
 
/////
// GET ONE SITE DETAILS  
// network/site/ID
/////
function sedoo_wppl_restapi_get_one_site($data) {
    $one_site = get_blog_details($data['id']);

    switch_to_blog( $data['id'] );

        // Add users array
        //////
        $test;
        $one_site->users = get_users(array( 'fields' => array( 'display_name', 'ID') ));
        foreach($one_site->users as $user) {
            $user_meta = get_userdata($user->ID);
            $user->role = $user_meta->roles;
        }

        // Add current theme
        $theme_data = wp_get_theme();
        $theme_info = ['theme_name' => $theme_data->get( 'Name' ), 'theme_version' => $theme_data->get( 'Version' ),'theme_description' => $theme_data->get( 'Description' ), 'theme_textdomain' => $theme_data->get( 'TextDomain' )];  
        $one_site->current_theme = $theme_info;

        $active_plugins_list = get_option('active_plugins');
        $one_site->active_plugins = $active_plugins_list;
        $all_plugins = get_plugins();
        $all_plugins_array;
        foreach($all_plugins as $path => $plugin) {
            $plugin['path'] = $path;
            $plugin['activate_url'] = sedoo_apirest_one_site_generate_plugin_activate_link($path);
            $plugin['is_active'] = is_plugin_active( $path );
            $all_plugins_array[] =  $plugin;
        }
        $one_site->all_plugins = $all_plugins_array;
    restore_current_blog();

    return rest_ensure_response($one_site);
} 


/////
// GET FEED SUMMARY
// network/site/ID
/////
function sedoo_wppl_restapi_get_feed_summary() {
    $one_feed_summary->numbers_of_websites = count(get_sites());
    $one_feed_summary->numbers_of_users = count(get_users());
    $active_plugins_list = get_option('active_plugins');
    $one_feed_summary->active_plugins = count($active_plugins_list);

    return rest_ensure_response($one_feed_summary);
}


function sedoo_wppl_restapi_register_routes() {
    // register_rest_route() handles more arguments but we are going to stick to the basics for now.
    //http://localhost/wordpress_directory/wp-json/network/sites/list

    register_rest_route( 'network/','/summary', array(
        // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
        'methods'  => WP_REST_Server::READABLE,
        // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
        'callback' => 'sedoo_wppl_restapi_get_feed_summary',
    ) );


    register_rest_route( 'network/sites','/all', array(
        // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
        'methods'  => WP_REST_Server::READABLE,
        // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
        'callback' => 'sedoo_wppl_restapi_get_all_sites',
    ) );

    register_rest_route( 'network/site', '/(?P<id>\d+)', array(
        // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
        'methods'  => WP_REST_Server::READABLE,
        // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
        'callback' => 'sedoo_wppl_restapi_get_one_site',
        'args' => [
            'id'
        ],
    ) );
}
 
add_action( 'rest_api_init', 'sedoo_wppl_restapi_register_routes' );