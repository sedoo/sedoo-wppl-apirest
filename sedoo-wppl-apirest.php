<?php
/**
 * Plugin Name:     Sedoo Wppl RESTAPI for wp cli
 * Plugin URI:      https://github.com/sedoo/sedoo-wppl-apirest
 * Description:     Endpoints for wp cli management 
 * Author:          Pierre VERT & Nicolas Gruwe - SEDOO DATA CENTER
 * Author URI:      https://www.sedoo.fr 
 * Text Domain:     sedoo-wppl-apirest
 * Domain Path:     /languages
 * Version:         0.1.7.3
 * GitHub Plugin URI: sedoo/sedoo-wppl-apirest
 * GitHub Branch:     master
 * @package         sedoo-wppl-apirest
 */

 
/**
 * Docs : https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/#creating-endpoints 
 */

function get_domain_mapped_url( $custom_blog_id ) {

    // Enable WordPress DB connection
    global $wpdb;

    // To reduce the number of database queries, save the results the first time we encounter each blog ID.
    static $return_url = array();

    $wpdb->dmtable = $wpdb->base_prefix . 'domain_mapping';

    if ( !isset( $return_url[ $custom_blog_id ] ) ) {
            $s = $wpdb->suppress_errors();

            if ( get_site_option( 'dm_no_primary_domain' ) == 1 ) {
                    $domain = $wpdb->get_var( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = '{$custom_blog_id}' AND domain = '" . $wpdb->escape( $_SERVER[ 'HTTP_HOST' ] ) . "' LIMIT 1" );
                    if ( null == $domain ) {
                            $return_url[ $custom_blog_id ] = untrailingslashit( get_site_url( $custom_blog_id ) );
                            return $return_url[ $custom_blog_id ];
                    }
            } else {
                    // get primary domain, if we don't have one then return original url.
                    $domain = $wpdb->get_var( "SELECT domain FROM {$wpdb->dmtable} WHERE blog_id = '{$custom_blog_id}' AND active = 1 LIMIT 1" );
                    if ( null == $domain ) {
                            $return_url[ $wpdb->blogid ] = untrailingslashit( get_site_url( $custom_blog_id ) );
                            return $return_url[ $custom_blog_id ];
                    }
            }

            $wpdb->suppress_errors( $s );
            if ( false == isset( $_SERVER[ 'HTTPS' ] ) )
                    $_SERVER[ 'HTTPS' ] = 'Off';
            $protocol = ( 'on' == strtolower( $_SERVER[ 'HTTPS' ] ) ) ? 'https://' : 'http://';
            if ( $domain ) {
                    $return_url[ $custom_blog_id ] = untrailingslashit( $protocol . $domain  );
                    $setting = $return_url[ $custom_blog_id ];
            } else {
                    $return_url[ $custom_blog_id ] = false;
            }
    } elseif ( $return_url[ $custom_blog_id ] !== FALSE) {
            $setting = $return_url[ $custom_blog_id ];
    }

    return $setting;
}





/**
 * Get blog sites
 * @return array The list of public sites
 */

/////
// GET FEED SUMMARY
// network/summary
/////
function sedoo_wppl_restapi_get_feed_summary() {
    foreach(get_sites() as $site) {
        switch_to_blog( $site->blog_id );
        $one_feed_summary->site_name[] = get_bloginfo('name');
        restore_current_blog();
    }
    $one_feed_summary->numbers_of_websites = count(get_sites());
    $one_feed_summary->numbers_of_users = count(get_users());
    $active_plugins_list = get_option('active_plugins');
    $one_feed_summary->active_plugins = count($active_plugins_list);

    $one_feed_summary->config->phpversion = phpversion();
    $one_feed_summary->config->apacheversion = apache_get_version();
    $one_feed_summary->config->freespace = round(disk_free_space('/') /1024/1024/1024); // retourne en octet donc je onvertis en Gb

    return rest_ensure_response($one_feed_summary);
}

/////
// GET ALL SITES LIST  OF A FEED
// network/sites/all
/////
function sedoo_wppl_restapi_get_all_sites() {
    $sites_list['sites'] = get_sites();
    foreach($sites_list['sites'] as $site) {
        switch_to_blog( $site->blog_id );
            $theme_data = wp_get_theme();
            $theme_info = ['theme_name' => $theme_data->get( 'Name' ), 'theme_version' => $theme_data->get( 'Version' )];  
            $site->current_theme = $theme_info;
            $site->site_name = get_bloginfo('name');
        restore_current_blog();
    }
    $sites_list['config']->phpversion = phpversion();
    $sites_list['config']->apacheversion = apache_get_version();
    $sites_list['config']->networkurl = network_admin_url();
    $sites_list['config']->freespace = round(disk_free_space('/') /1024/1024/1024); // retourne en octet donc je onvertis en Gb


    // rest_ensure_response() wraps the data we want to return into a WP_REST_Response, and ensures it will be properly returned.
    return rest_ensure_response($sites_list);
}


/////
// GET ALL SITES LIST  OF A FEED
// network/sites/all
/////
function sedoo_wppl_restapi_get_all_sites_url() {
    $sites_list['sites'] = get_sites();
    foreach($sites_list['sites'] as $site) {
        switch_to_blog( $site->blog_id );
            $site->site_url_2 = get_site_url();
            $site->site_url_3 = get_site_url();
            $site->site_url = get_domain_mapped_url($site->blog_id);
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
        // Add current theme
        $theme_data = wp_get_theme();
        $theme_info = ['screenshot' => get_stylesheet_directory_uri() . '/screenshot.png' . $screenshot,'theme_name' => $theme_data->get( 'Name' ), 'theme_version' => $theme_data->get( 'Version' ),'theme_description' => $theme_data->get( 'Description' ), 'theme_textdomain' => $theme_data->get( 'TextDomain' )];  
        $one_site->current_theme = $theme_info;

        $active_plugins_list = get_option('active_plugins');
        $one_site->active_plugins = $active_plugins_list;
        $all_plugins = get_plugins();
        $all_plugins_array;
        foreach($all_plugins as $path => $plugin) {
            $plugin['path'] = $path;
            $plugin['is_active'] = is_plugin_active( $path );
            $all_plugins_array[] =  $plugin;
        }
        $one_site->all_plugins = $all_plugins_array;
    restore_current_blog();

    return rest_ensure_response($one_site);
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

    register_rest_route( 'network/','/urllist', array(
        // By using this constant we ensure that when the WP_REST_Server changes our readable endpoints will work as intended.
        'methods'  => WP_REST_Server::READABLE,
        // Here we register our callback. The callback is fired when this endpoint is matched by the WP_REST_Server class.
        'callback' => 'sedoo_wppl_restapi_get_all_sites_url',
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