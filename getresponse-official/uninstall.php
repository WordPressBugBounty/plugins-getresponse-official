<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://getresponse.com
 * @since      1.0.0
 *
 * @package    Getresponse_For_Wp
 */

// If uninstall not called from WordPress, then exit.
use GR\Wordpress\Core\Gr_Rest_Api_Service;
use GR\Wordpress\Core\logger\Gr_Logger_Configuration;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

(new Gr_Rest_Api_Service())->delete_configuration();
(new Gr_Logger_Configuration())->delete_configuration();