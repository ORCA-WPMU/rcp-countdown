<?php
/**
 * Main RCP Countdown class
 *
 * @package svbk-rcp-countdown
 * @author Brando Meniconi <b.meniconi@silverbackstudio.it>
 */

namespace Svbk\WP\Plugins\RCP\Countdown;

use WP_Session;
use DateTimeImmutable;
use DateInterval;
use RCP_Registration;

/**
 * Main RCP Countdown class
 */
class Discount {

	/**
	 * Prints the HTML fields in subscrioption's admin panel
	 *
	 * @param object $level Optional. The subscription level object.
	 *
	 * @return void
	 */
	public function level_discount_duration_form( $level = null ) {
		global $rcp_levels_db;

		$defaults = array(
			'main_discount' => 0,
			'discount_duration' => 0,
			'discount_duration_unit' => 'minute',
		);

		if ( ! empty( $level ) ) {
			$defaults['main_discount'] = $rcp_levels_db->get_meta( $level->id, 'main_discount', true );
			$defaults['discount_duration'] = $rcp_levels_db->get_meta( $level->id, 'discount_duration', true );
			$defaults['discount_duration_unit'] = $rcp_levels_db->get_meta( $level->id, 'discount_duration_unit', true );
		}
		?>

		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="main_discount"><?php esc_html_e( 'Main Discount', 'svbk-rcp-countdown' ); ?></label>
			</th>
			<td>
				<select name="main_discount" id="main_discount">
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
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="discount_duration"><?php esc_html_e( 'Discount Duration', 'svbk-rcp-countdown' ); ?></label>
			</th>
			<td>
				<input type="text" id="discount_duration" style="width: 40px;" name="discount_duration" value="<?php echo esc_attr( $defaults['discount_duration'] ); ?>"/>
				<select name="discount_duration_unit" id="discount_duration_unit">
					<option value="minute" <?php selected( $defaults['discount_duration_unit'], 'minute' )?> ><?php esc_html_e( 'Minute(s)', 'svbk-rcp-countdown' ); ?></option>
					<option value="hour" <?php selected( $defaults['discount_duration_unit'], 'hour' )?> ><?php esc_html_e( 'Hours(s)', 'svbk-rcp-countdown' ); ?></option>
					<option value="day" <?php selected( $defaults['discount_duration_unit'], 'day' )?> ><?php esc_html_e( 'Days(s)', 'svbk-rcp-countdown' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Length of time the countdown discount should last. Enter 0 for no discount.', 'svbk-rcp-countdown' ); ?>
				</p>
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
	public function level_discount_duration_save( $level_id, $args ) {

		global $rcp_levels_db;

		$defaults = array(
		'main_discount'       => 0,
		'discount_duration'      => 0,
		'discount_duration_unit' => 'minutes',
		);

		$args = wp_parse_args( $args, $defaults );

		$unit = sanitize_text_field( $args['discount_duration_unit'] );
		$unit = in_array( $unit, array( 'minute', 'hour', 'day' ), true ) ? $unit : 'minute';

		if ( current_filter() === 'rcp_add_subscription' ) {
			$rcp_levels_db->add_meta( $level_id, 'main_discount', intval( $args['main_discount'] ) );
			$rcp_levels_db->add_meta( $level_id, 'discount_duration', intval( $args['discount_duration'] ) );
			$rcp_levels_db->add_meta( $level_id, 'discount_duration_unit', $unit );
		} elseif ( current_filter() === 'rcp_pre_edit_subscription_level' ) {
			$rcp_levels_db->update_meta( $level_id, 'main_discount', intval( $args['main_discount'] ) );
			$rcp_levels_db->update_meta( $level_id, 'discount_duration', intval( $args['discount_duration'] ) );
			$rcp_levels_db->update_meta( $level_id, 'discount_duration_unit', $unit );
		}
	}

	/**
	 * Enqueue scripts and JS config variables
	 *
	 * @return void
	 */
	public function scripts() {

		wp_register_script( 'jquery.countdown', 'https://cdn.jsdelivr.net/jquery.countdown/2.2/jquery.countdown.min.js' );

		$session = WP_Session::get_instance();
		$subscription = rcp_get_subscription_levels( 'active' );

		wp_enqueue_script( 'rcp-countdown', plugins_url( '/js/rcp-countdown.js', SVBK_RCP_COUNTDOWN_PLUGIN_FILE ), array( 'jquery', 'jquery.countdown' ), '20170530', true );

		$user_id = apply_filters( 'svbk_rcp_countdown_current_user', is_user_logged_in() ? get_current_user_id() : null );

		foreach ( $subscription as &$level ) {
			$expiration = self::get_user_expiration( $level, $user_id );

			if ( ! $expiration ) {
				$discount_duration = self::get_discount_duration( $level );
				$now = new DateTimeImmutable( 'NOW' );
				$expiration = apply_filters( 'svbk_rcp_countdown_set_user_expiration', $now->add( new DateInterval( 'PT' . $discount_duration . 'S' ) ), $level, $user_id );
			}

				$level->discount_expires = $expiration->format( 'U000' );
		}

		wp_localize_script( 'rcp-countdown', 'svbkRcpCountdown', $subscription );
	}

	/**
	 * Triggers the countdown for a specific subsciption level.
	 *
	 * @param object $level The subscription level object.
	 *
	 * @return DateTimeImmutable The expiration time
	 */
	public static function trigger_expiration( $level ) {

		$user_id = apply_filters( 'svbk_rcp_countdown_current_user', is_user_logged_in() ? get_current_user_id() : null );
		$expiration = self::get_user_expiration( $level, $user_id );

		if ( ! $expiration ) {
			$expiration = self::set_user_expiration( $level, $user_id );
		}

		do_action( 'svbk_rcp_countdown_trigger_expiration', $level, $user_id );

		return $expiration;
	}

	/**
	 * Get expiration time for a specific Level and User
	 *
	 * @param object $level The subscription level object.
	 * @param int    $user_id Optional. The registered user id, if not set uses current session only.
	 *
	 * @return DateTimeImmutable The expiration time
	 */
	public static function get_user_expiration( $level, $user_id = null ) {

		$key = 'svbk_rcp_ctd_' . $level->role . '_discount_expires';
		$expire = null;

		if ( $user_id && ( $user_expiration = get_user_meta( $user_id, $key, true ) ) ) {
			$expire = $user_expiration;
		 } else { 
			$session = WP_Session::get_instance();
			if ( isset( $session[ $key ] ) ) {
				$expire = $session[ $key ];
			}
		}
		return apply_filters( 'svbk_rcp_countdown_get_user_expiration', $expire, $level, $user_id );
	}

	/**
	 * Set expiration time for a specific Level and User
	 *
	 * @param object $level The subscription level object.
	 * @param int    $user_id Optional. The registered user id, if not set uses current session only.
	 *
	 * @return DateTimeImmutable The expiration time set
	 */
	public static function set_user_expiration( $level, $user_id = null ) {

		$discount_duration = self::get_discount_duration( $level );
		$now = new DateTimeImmutable( 'NOW' );
		$key = 'svbk_rcp_ctd_' . $level->role . '_discount_expires';

		$expiration = apply_filters( 'svbk_rcp_countdown_set_user_expiration', $now->add( new DateInterval( 'PT' . $discount_duration . 'S' ) ), $level, $user_id );

		if ( $user_id ) {
			add_user_meta( $user_id, $key, $expiration, true );
		}

		$session = WP_Session::get_instance();
		$session[ $key ] = $expiration;

		return $expiration;
	}

	/**
	 * Get the main discount duration for a specific subscription level
	 *
	 * @param object $level The subscription level object.
	 *
	 * @return int The discount duration in seconds
	 */
	public static function get_discount_duration( $level ) {

		global $rcp_levels_db;

		$discount_duration_base = $rcp_levels_db->get_meta( $level->id, 'discount_duration', true );
		$discount_duration_unit = $rcp_levels_db->get_meta( $level->id, 'discount_duration_unit', true );

		$multipliers = array(
			'minute' => MINUTE_IN_SECONDS,
			'hour' => HOUR_IN_SECONDS,
			'day' => DAY_IN_SECONDS,
		);

		if ( isset( $multipliers[ $discount_duration_unit ] ) ) {
			$discount_duration = intval( $discount_duration_base ) * $multipliers[ $discount_duration_unit ];
		} else {
			$discount_duration = intval( $discount_duration_base ) * MINUTE_IN_SECONDS;
		}

		return apply_filters( 'svbk_rcp_countdown_level_exipire', $discount_duration, $level, $discount_duration_base, $discount_duration_unit );
	}

	/**
	 * Check if the countdown is elapsed for the current user
	 *
	 * @param int $level_id The subscription level ID.
	 *
	 * @return bool
	 */
	public static function has_expired( $level_id ) {

		global $rcp_levels_db;

		$user_id = apply_filters( 'svbk_rcp_countdown_current_user', is_user_logged_in() ? get_current_user_id() : null );
		$level = $rcp_levels_db->get_level( $level_id );

		$expiration = self::get_user_expiration( $level, $user_id );
		$now = new DateTimeImmutable( 'NOW' );

		return $expiration && ( $now < $expiration );
	}

	/**
	 * Get the main discount for a subscription
	 *
	 * @param int $subscription_id The subscription level object.
	 *
	 * @return RCP_Discount
	 */
	public static function main_discount( $subscription_id ) {
		global $rcp_levels_db;

		$discount_id = $rcp_levels_db->get_meta( $subscription_id, 'main_discount', true );

		if ( $discount_id ) {
			return rcp_get_discount_details( $discount_id );
		}

		return false;
	}

	/**
	 * Apply a discount to a RCP registration
	 *
	 * @param RCP_Registration $registration The subscription level object.
	 *
	 * @return void
	 */
	public function apply_discount( RCP_Registration $registration ) {

		$subscription_id = $registration->get_subscription();
		$main_discount = self::main_discount( $subscription_id );

		if ( $main_discount && self::has_expired( $subscription_id ) ) {
			$registration->add_discount( $main_discount->code );
		}

	}
}
