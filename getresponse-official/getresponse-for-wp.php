<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://getresponse.com
 * @since             1.0.0
 * @package           Getresponse_For_Wp
 *
 * @wordpress-plugin
 * Plugin Name:       GetResponseOfficial
 * Plugin URI:        https://www.getresponse.com/help/how-to-integrate-wordpress-with-getresponse.html
 * Description:       GetResponse for WordPress lets you add site visitors to your contact list, update contact information, track site visits, and pass ecommerce data to GetResponse. It helps you keep your list growing and ensures you have the contact information and ecommerce data to plan successful marketing campaigns.
 * Version:           1.3.9
 * Author:            GetResponse
 * Author URI:        https://getresponse.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       getresponse-for-wp
 */

use GR\Wordpress\Core\Getresponse_For_Wp;

require_once __DIR__ . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'GETRESPONSE_FOR_WP_VERSION', '1.3.9' );

( new Getresponse_For_Wp() )->run();
