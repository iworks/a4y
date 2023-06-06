<?php
/**
 * a4y — simpel analitycs for you
 *
 * @package           PLUGIN_NAME
 * @author            AUTHOR_NAME
 * @copyright         2023-PLUGIN_TILL_YEAR Marcin Pietrzak
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       a4y
 * Plugin URI:        PLUGIN_URI
 * Description:       PLUGIN_DESCRIPTION
 * Version:           PLUGIN_VERSION
 * Requires at least: PLUGIN_REQUIRES_WORDPRESS
 * Requires PHP:      PLUGIN_REQUIRES_PHP
 * Author:            AUTHOR_NAME
 * Author URI:        AUTHOR_URI
 * Text Domain:       mutatio
 * License:           GPL v3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt

Copyright 2023-PLUGIN_TILL_YEAR Marcin Pietrzak (marcin@iworks.pl)

this program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * static options
 */
define( 'IWORKS_A4WP_VERSION', 'PLUGIN_VERSION' );
define( 'IWORKS_A4WP_PREFIX', 'iworks_a4y_' );
$base   = dirname( __FILE__ );
$vendor = $base . '/includes';

/**
 * require: Iworksa4y Class
 */
if ( ! class_exists( 'iworks_a4y' ) ) {
	require_once $vendor . '/iworks/a4y.php';
}
/**
 * configuration
 */
require_once $base . '/etc/options.php';
/**
 * require: IworksOptions Class
 */
if ( ! class_exists( 'iworks_options' ) ) {
	require_once $vendor . '/iworks/options/options.php';
}

/**
 * i18n
 */
load_plugin_textdomain( 'a4y', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

/**
 * load options
 */
$iworks_a4y_options = new iworks_options();
$iworks_a4y_options->set_option_function_name( 'iworks_a4y_options' );
$iworks_a4y_options->set_option_prefix( IWORKS_A4WP_PREFIX );

function iworks_a4y_get_options() {
	global $iworks_a4y_options;
	return $iworks_a4y_options;
}

function iworks_a4y_options_init() {
	global $iworks_a4y_options;
	$iworks_a4y_options->options_init();
}

function iworks_a4y_activate() {
	$iworks_a4y_options = new iworks_options();
	$iworks_a4y_options->set_option_function_name( 'iworks_a4y_options' );
	$iworks_a4y_options->set_option_prefix( IWORKS_A4WP_PREFIX );
	$iworks_a4y_options->activate();
}

function iworks_a4y_deactivate() {
	global $iworks_a4y_options;
	$iworks_a4y_options->deactivate();
}

$iworks_a4y = new iworks_a4y();

/**
 * install & uninstall
 */
register_activation_hook( __FILE__, 'iworks_a4y_activate' );
register_deactivation_hook( __FILE__, 'iworks_a4y_deactivate' );
