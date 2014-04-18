<?php
/*
Plugin Name: WordPress CAS Server
Version: 0.1-alpha
Description: Provides authentication services based on Jasig CAS protocols.
Author: Luís Rodrigues
Author URI: http://goblindegook.net/
Plugin URI: https://github.com/goblindegook/wordpress-cas-server
Text Domain: wordpress-cas-server
Domain Path: /languages
*/

/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin
 */

require_once( dirname( __FILE__ ) . '/includes/WPCASServer.php' );

if (!class_exists( 'WPCASServerPlugin' )):

class WPCASServerPlugin {

    /**
     * Plugin version.
     */
    const VERSION = '0.1-alpha';

    /**
     * Plugin slug.
     */
    const SLUG = 'wordpress-cas-server';

    /**
     * Plugin options key.
     */
    const OPTIONS_KEY = 'wordpress_cas_server';

    /**
     * Plugin file.
     */
    const FILE = 'wordpress-cas-server/wordpress-cas-server.php';

    /**
     * Query variable used to pass the requested CAS route.
     */
    const QUERY_VAR_ROUTE = 'cas_route';

    /**
     * CAS server instance.
     * @var WPCASServer
     */
    protected $server;

    /**
     * Default plugin options.
     * @var array
     */
    private $default_options = array(
        'path' => 'wp-cas',
        );

    /**
     * WordPress CAS Server plugin constructor.
     */
    public function __construct ( $server ) {
        register_activation_hook( __FILE__, array( $this, 'activation' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );

        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

        $this->server = $server;
    }

    /**
     * Plugin activation callback.
     */
    public function activation ( $network_wide ) {
        if (function_exists( 'is_multisite' ) && is_multisite() && $network_wide) {
            $sites = wp_get_sites();
            foreach ( $sites as $site ) {
                switch_to_blog( $site['blog_id'] );
                $this->add_rewrite_rules();
                flush_rewrite_rules();
            }
            restore_current_blog();
        }
        else
        {
            $this->add_rewrite_rules();
            flush_rewrite_rules();
        }
    }

    /**
     * Plugin deactivation callback to flush rewrite rules.
     */
    public function deactivation ( $network_wide ) {
        if (function_exists( 'is_multisite' ) && is_multisite() && $network_wide) {
            $sites = wp_get_sites();
            foreach ( $sites as $site ) {
                switch_to_blog( $site['blog_id'] );
                flush_rewrite_rules();
            }
            restore_current_blog();
        }
        else
        {
            flush_rewrite_rules();
        }
    }

    /**
     * Plugin loading callback.
     */
    public function plugins_loaded () {
        add_action( 'init'                  , array( $this, 'init' ) );
        add_action( 'template_redirect'     , array( $this, 'template_redirect' ), -100 );
        add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
    }

    /**
     * Plugin initialization callback.
     * 
     * @uses $wp
     */
    public function init () {
        global $wp;
        $wp->add_query_var( self::QUERY_VAR_ROUTE );
        $this->_update_options();
        $this->add_rewrite_rules();
    }

    /**
     * Serve the CAS request and stop.
     */
    public function template_redirect () {
        global $wp;

        // Abort unless processing a CAS request:
        if (empty( $wp->query_vars[self::QUERY_VAR_ROUTE] )) {
            return;
        }

        $this->server->handleRequest( $wp->query_vars[self::QUERY_VAR_ROUTE] );

        exit;
    }

    /**
     * Callback to filter the hosts WordPress allows redirections to.
     * 
     * @param  array $allowed   List of valid redirection target hosts.
     * @return array            Filtered list of valid redirection target hosts.
     * 
     * @todo
     */
    public function allowed_redirect_hosts ( $allowed ) {
        // TODO
        return $allowed;
    }

    /**
     * Get plugin options.
     * @return array Plugin options.
     */
    private function _get_options () {
        return get_option( self::OPTIONS_KEY, $this->default_options );
    }

    /**
     * Get plugin option by key.
     * @param  string $key  Plugin option key to return.
     * @return mixed        Plugin option value.
     */
    private function _get_option ( $key = null ) {
        $options = $this->_get_options();
        return $options[$key];
    }

    /**
     * Update the plugin options in the database.
     * @param  array  $updated_options  Updated options to set (will be merged with existing options).
     */
    private function _update_options ( $updated_options = array() ) {
        $options = $this->_get_options();
        $options = array_merge( (array) $options, (array) $updated_options );
        update_option( self::OPTIONS_KEY, $options );
    }

    /**
     * Register new rewrite rules for the CAS server URIs.
     * @return void
     */
    protected function add_rewrite_rules () {
        $path = $this->_get_option( 'path' );
        add_rewrite_rule( '^' . $path . '(.*)?', 'index.php?' . self::QUERY_VAR_ROUTE . '=$matches[1]', 'top' );
    }

}

$GLOBALS[WPCASServerPlugin::SLUG] = new WPCASServerPlugin( new WPCASServer );

endif;