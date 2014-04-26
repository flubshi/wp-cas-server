<?php
/**
 * @package WPCASServerPlugin
 * @subpackage WPCASServerPlugin_Tests
 */

class WP_TestWPCASServerPluginFilters extends WP_UnitTestCase {
    
    private $plugin;
    private $server;
    private $redirect_location;

    /**
     * Setup test suite for the CASServerPlugin class.
     */
    function setUp () {
        parent::setUp();
        $this->plugin = $GLOBALS[WPCASServerPlugin::SLUG];
        $this->server = new WPCASServer;
        add_filter( 'wp_redirect', array( $this, 'wp_redirect_handler' ) );
    }

    /**
     * Finish the test suite for the CASServerPlugin class.
     */
    function tearDown () {
        parent::tearDown();
        unset( $this->server );
        unset( $this->redirect_location );
        remove_filter( 'wp_redirect', array( $this, 'wp_redirect_handler' ) );
    }

    function wp_redirect_handler ( $location ) {
        $this->redirect_location = $location;
        throw new WPDieException( "Redirecting to $location" );
    }

    /**
     * Run an XPath query on an XML string.
     * 
     * @param  string $query XPath query to run.
     * @param  string $xml   Stringified XML.
     * 
     * @return array         Query results in array form.
     */
    private function _xpath_query_xml( $query, $xml ) {
        $doc = new DOMDocument;
        $doc->loadXML( $xml );

        $xpath = new DOMXPath( $doc );
        $results = $xpath->query( $query );

        $output = array();

        foreach ($results as $element) {
            $output[] = $element;
        }

        return $output;
    }

    /**
     * Test whether a WordPress filter is called for a given function and set of arguments.
     * 
     * @param  string  $label    Action label to test.
     * @param  mixed   $function String or array with the function to execute.
     * @param  array   $args     Ordered list of arguments to pass the function.
     */
    private function _assertFilterIsCalled ( $label, $function, $args ) {
        $mock = $this->getMock( 'stdClass', array( 'filter' ) );
        $mock->expects( $this->once() )->method( 'filter' )->will( $this->returnArgument( 0 ) );

        add_filter( $label, array( $mock, 'filter' ) );
        ob_start();

        try {
            call_user_func_array( $function, $args );
        }
        catch (WPDieException $message) {
            ob_end_clean();
            remove_filter( $label, array( $mock, 'filter' ) );
            return;
        }

        // finally not supported in PHP 5.3 and 5.4
        ob_end_clean();
        remove_filter( $label, array( $mock, 'filter' ) );
    }

    /**
     * @group filter
     */
    function test_cas_enabled () {
        $filter   = 'cas_enabled';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( 'invalid-uri' );
        
        $this->_assertFilterIsCalled( $filter, $function, $args );
    }

    /**
     * @group filter
     */
    function test_cas_server_routes () {
        $filter   = 'cas_server_routes';
        $function = array( $this->server, 'routes' );
        $args     = array();
        
        $this->_assertFilterIsCalled( $filter, $function, $args );
    }

    /**
     * @group filter
     */
    function test_cas_server_response () {
        $filter   = 'cas_server_response';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( 'invalid-uri' );
        
        $this->_assertFilterIsCalled( $filter, $function, $args );
    }

    /**
     * @group filter
     */
    function test_cas_server_dispatch_args () {
        $filter   = 'cas_server_dispatch_args';
        $function = array( $this->server, 'handleRequest' );
        $args     = array( 'login' );

        $handler = function () {
            return new WP_Error();
        };

        $mock = $this->getMock( 'stdClass', array( 'filter' ) );
        $mock->expects( $this->once() )->method( 'filter' )->will( $this->returnValue( new WP_Error() ) );

        add_filter( $filter, array( $mock, 'filter' ) );
        $xml = $this->server->handleRequest( 'login' );
        remove_filter( $filter, array( $mock, 'filter' ) );

        $this->assertCount( 1, $this->_xpath_query_xml( '//cas:serviceResponse/cas:authenticationFailure', $xml ),
            "'cas_server_dispatch_args' may return WP_Error to abort request.");

        $this->markTestIncomplete();

        $this->_assertFilterIsCalled( $filter, $function, $args );
    }

    /**
     * @group filter
     */
    function test_cas_server_login_args () {
        $filter   = 'cas_server_login_args';
        $function = array( $this->server, 'login' );
        $args     = array( array() );

        $_POST = array(
            'username' => 'username',
            'password' => 'password',
            'lt'       => 'lt',
            );

        $this->_assertFilterIsCalled( $filter, $function, $args );
    }

    /**
     * @group filter
     */
    function test_cas_server_redirect_service () {
        $filter   = 'cas_server_redirect_service';
        $function = array( $this->server, 'login' );
        $args     = array(
            'service' => 'http://test/',
            );

        wp_set_current_user( $this->factory->user->create() );

        $this->_assertFilterIsCalled( $filter, $function, array( $args ) );
    }

    /**
     * @group filter
     */
    function test_cas_server_custom_auth_uri () {
        $filter   = 'cas_server_custom_auth_uri';
        $function = array( $this->server, 'login' );
        
        wp_set_current_user( false );

        $this->_assertFilterIsCalled( $filter, $function, array() );
    }

    /**
     * @group filter
     */
    function test_cas_server_ticket_expiration () {
        $filter   = 'cas_server_ticket_expiration';
        $function = array( $this->server, 'login' );

        $service = 'http://test/';

        wp_set_current_user( $this->factory->user->create() );
        
        $args = array(
            'service' => $service,
            );

        $this->_assertFilterIsCalled( $filter, $function, array( $args ) );
    }

    /**
     * @group filter
     */
    function test_cas_server_validation_extra_attributes () {
        $filter   = 'cas_server_validation_extra_attributes';
        $function = array( $this->server, 'serviceValidate' );
        
        $service = 'http://test/';

        wp_set_current_user( $this->factory->user->create() );

        try {
            $this->server->login( array( 'service' => $service ) );
        }
        catch (WPDieException $message) {
            parse_str( parse_url( $this->redirect_location, PHP_URL_QUERY ), $query );
        }

        update_option( WPCASServerPlugin::OPTIONS_KEY, array(
            'expiration' => 60,
            'attributes' => array( 'user_email' ),
            ) );

        $args = array(
            'service' => $service,
            'ticket'  => $query['ticket'],
            );

        $this->_assertFilterIsCalled( $filter, $function, array( $args ) );
    }

}
