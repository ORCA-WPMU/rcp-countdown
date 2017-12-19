<?php
/**
 * Main plugin file
 *
 * @package svbk-rcp-countdown
 */

namespace Svbk\WP\Plugins\RCP\Countdown;

/*
Plugin Name: Restrict Content Pro - Countdown Offer
Description: Enable Pay Buttons with offer Countdown
Author: Silverback Studio
Version: 1.1
Author URI: http://www.silverbackstudio.it/
Text Domain: svbk-rcp-countdown
*/

use RCP_Discounts;
use DateTime;

define( 'SVBK_RCP_COUNTDOWN_PLUGIN_FILE', __FILE__ );

/**
 * Loads textdomain and main initializes main class
 *
 * @return void
 */
function svbk_rcp_countdown_init() {
	load_plugin_textdomain( 'svbk-rcp-countdown', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	add_action( 'rcp_add_subscription_form', __NAMESPACE__ . '\level_discount_duration_form' );
	add_action( 'rcp_edit_subscription_form', __NAMESPACE__ . '\level_discount_duration_form' );

	add_action( 'rcp_add_subscription', 			__NAMESPACE__ . '\level_discount_duration_save' , 10, 2 );
	add_action( 'rcp_pre_edit_subscription_level',	__NAMESPACE__ . '\level_discount_duration_save' , 10, 2 );

	add_action( 'rcp_registration_init', __NAMESPACE__ . '\apply_discount' , 9 );

	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts'  );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\svbk_rcp_countdown_init' );


add_action( 'admin_init', __NAMESPACE__ . '\svbk_rcp_countdown_check_parent' );

/**
 * Checks if required dependency plugins are installed
 *
 * @return void
 */
function svbk_rcp_countdown_check_parent() {

	if ( is_admin() && ! is_plugin_active( 'wp-session-manager/wp-session-manager.php' ) && current_user_can( 'activate_plugins' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\svbk_rcp_countdown_plugin_notice' );

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


/**
 * Prints the HTML fields in subscrioption's admin panel
 *
 * @param object $level Optional. The subscription level object.
 *
 * @return void
 */
function level_discount_duration_form( $level = null ) {
	global $rcp_levels_db;

	$defaults = array(
		'main_discount' => 0,
	);

	if ( ! empty( $level ) ) {
		$defaults['main_discount'] = $rcp_levels_db->get_meta( $level->id, 'main_discount', true );
	}
	?>

	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="main_discount"><?php esc_html_e( 'Main Discount', 'svbk-rcp-countdown' ); ?></label>
		</th>
		<td>
			<select name="main_discount" id="main_discount">
				<option value="" <?php selected( $defaults['main_discount'], '' )?> ><?php esc_html_e('- Select -', 'svbk-rcp-countdown') ?></option>
			<?php
				$discounts = rcp_get_discounts();

				foreach ( $discounts as $discount ) :
				// limit discounts to only those applicable to this level.
				if ( $level && ! empty( $discount->subscription_id ) && ( $level->id !== $discount->subscription_id ) ) {
					continue;
				}
				?>
				<option value="<?php echo esc_attr( $discount->id ); ?>" <?php selected( $defaults['main_discount'], $discount->id )?> ><?php echo esc_html( $discount->name ); ?></option>
				<?php endforeach;

			?>
			</select>
			<p class="description"><?php esc_html_e( 'The discount applied during the countdown.', 'svbk-rcp-countdown' ); ?></p>
		</td>
	</tr>
<?php }


/**
 * Saves countdown values from the subscription admin pane.
 *
 * @param int   $level_id The subscription level ID.
 * @param array $args The submitted form filed values.
 *
 * @return void
 */
function level_discount_duration_save( $level_id, $args ) {

	global $rcp_levels_db;

	$defaults = array(
		'main_discount' => 0,
	);

	$args = wp_parse_args( $args, $defaults );

	if ( current_filter() === 'rcp_add_subscription' ) {
		$rcp_levels_db->add_meta( $level_id, 'main_discount', intval( $args['main_discount'] ) );
	} elseif ( current_filter() === 'rcp_pre_edit_subscription_level' ) {
		$rcp_levels_db->update_meta( $level_id, 'main_discount', intval( $args['main_discount'] ) );
	}
}

/**
 * Enqueue scripts and JS config variables
 *
 * @return void
 */
function scripts() {

	$discounts = new RCP_Discounts();
	
	wp_register_script( 'jquery.countdown', 'https://cdn.jsdelivr.net/npm/jquery-countdown@2.2.0/dist/jquery.countdown.min.js' );

	$subscription = rcp_get_subscription_levels( 'active' );

	wp_enqueue_script( 'rcp-countdown', plugins_url( '/js/rcp-countdown.js', SVBK_RCP_COUNTDOWN_PLUGIN_FILE ), array( 'jquery', 'jquery.countdown' ), '20170530', true );

	$user_id = apply_filters( 'svbk_rcp_countdown_current_user', is_user_logged_in() ? get_current_user_id() : null );

	$output = array();

	foreach ( $subscription as &$level ) {
		
		$main_discount = main_discount( $level->id );
		
		if( ! $main_discount ) {
			continue;
		}
		
		$expiration = $discounts->get_expiration( $main_discount->id );

		 if ( ! $expiration ) {
			continue;
		 }

		$expiration = new DateTime( $expiration );
		
		$output[] = array(
			'id' => $level->id,
			'discount_expires' => $expiration->format( 'U000' ),
		);
		
	}

	wp_localize_script( 'rcp-countdown', 'svbkRcpCountdown', $output );
}

/**
 * Apply a discount to a RCP registration
 *
 * @param RCP_Registration $registration The subscription level object.
 *
 * @return void
 */
function apply_discount( RCP_Registration $registration ) {

	$discounts = new RCP_Discounts();

	$subscription_id = $registration->get_subscription();
	
	$main_discount = main_discount( $subscription_id );

	if ( ! $registration->get_discounts() && $main_discount && ! $discounts->is_expired( $main_discount->id ) ) {
		$registration->add_discount( $main_discount->code );
	}

}

/**
 * Get the main discount for a subscription
 *
 * @param int $subscription_id The subscription level object.
 *
 * @return RCP_Discount
 */
function main_discount( $subscription_id ) {
	global $rcp_levels_db;

	$discount_id = $rcp_levels_db->get_meta( $subscription_id, 'main_discount', true );

	if ( $discount_id ) {
		return rcp_get_discount_details( $discount_id );
	}

	return false;
}