<?php
/**
 * Main plugin file
 *
 * @package svbk-rcp-countdown
 */

/*
Plugin Name: Restrict Content Pro - Countdown Offer
Description: Enable Pay Buttons with offer Countdown
Author: Silverback Studio
Version: 1.1
Author URI: http://www.silverbackstudio.it/
Text Domain: svbk-rcp-countdown
*/

use Svbk\WP\Plugins\RCP\Countdown;

define( 'SVBK_RCP_COUNTDOWN_PLUGIN_FILE', __FILE__ );

/**
 * Loads textdomain and main initializes main class
 *
 * @return void
 */
function svbk_rcp_countdown_init() {
	load_plugin_textdomain( 'svbk-rcp-countdown', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'Svbk\WP\Plugins\RCP\Countdown\Discount' ) ) {
		require_once 'src/class-discount.php';
	}

	$svbk_rcp_countdown = new Countdown\Discount();

	if ( ! class_exists( 'Svbk\WP\Plugins\RCP\Countdown\PayButton' ) ) {
		require_once 'src/class-paybutton.php';
	}

	$svbk_rcp_paybutton = Countdown\PayButton::register();
}

add_action( 'plugins_loaded', 'svbk_rcp_countdown_init' );
