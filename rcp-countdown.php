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
	
	add_action( 'rcp_add_subscription_form', array( $svbk_rcp_countdown, 'level_discount_duration_form' ) );
	add_action( 'rcp_edit_subscription_form', array( $svbk_rcp_countdown, 'level_discount_duration_form' ) );

	add_action( 'rcp_add_subscription', array( $svbk_rcp_countdown, 'level_discount_duration_save' ), 10, 2 );
	add_action( 'rcp_pre_edit_subscription_level', array( $svbk_rcp_countdown, 'level_discount_duration_save' ), 10, 2 );

	add_action( 'rcp_registration_init', array( $svbk_rcp_countdown, 'apply_discount' ), 9 );

	add_action( 'wp_enqueue_scripts', array( $svbk_rcp_countdown, 'scripts' ) );

	if ( ! class_exists( 'Svbk\WP\Plugins\RCP\Countdown\PayButton' ) ) {
		require_once 'src/class-paybutton.php';
	}

	$svbk_rcp_paybutton = Countdown\PayButton::register();
}

add_action( 'plugins_loaded', 'svbk_rcp_countdown_init' );


add_action( 'admin_init', 'svbk_rcp_countdown_check_parent' );

/**
 * Checks if required dependency plugins are installed
 *
 * @return void
 */
function svbk_rcp_countdown_check_parent() {

	if ( is_admin() && ! is_plugin_active( 'wp-session-manager/wp-session-manager.php' ) && current_user_can( 'activate_plugins' ) ) {
		add_action( 'admin_notices', 'svbk_rcp_countdown_plugin_notice' );

		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}
}

/**
 * Displays notices for not active plugins
 *
 * @return void
 */
function svbk_rcp_countdown_plugin_notice() {
	?><div class="error"><p><?php esc_html_e( 'Sorry, but Restrict Content Pro  - Countdown requires the WP Session Manager plugin to be installed and active.', 'svbk-rcp-countdown' ); ?></p></div><?php
}
