<?php
/*
 * Plugin Name: do_action
 * Version: 1.0
 * Plugin URI: http://doaction.org/
 * Description: Custom functionality for doaction.org
 * Author: Hugh Lashbrooke
 * Author URI: http://hughlashbrooke.com/
 * Requires at least: 4.5
 * Tested up to: 4.5.2
 *
 * Text Domain: do-action
 * Domain Path: /languages/
 *
 * @package WordPress
 * @author Hugh Lashbrooke
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Load plugin localisation
load_plugin_textdomain('do-action', false, dirname(plugin_basename(__FILE__)) . '/languages/');

// Load plugin class files
require_once( 'includes/class-do-action.php' );
require_once( 'includes/class-do-action-tools.php' );

// Load plugin libraries
require_once( 'includes/lib/class-do-action-admin-api.php' );
require_once( 'includes/lib/class-do-action-post-type.php' );
require_once( 'includes/lib/class-do-action-taxonomy.php' );

/**
 * Returns the main instance of do_action to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object do_action
 */
function do_action_functions () {
	$instance = do_action::instance( __FILE__, '1.0.0' );
	$instance->tools = do_action_tools::instance( $instance );
	return $instance;
}

do_action_functions();

add_filter('widget_text', 'do_shortcode');